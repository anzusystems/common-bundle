<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Factory;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\McpDateTimeHandler;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use BackedEnum;
use Doctrine\Common\Collections\Collection;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionEnumBackedCase;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Assert\Regex is left out on purpose: PCRE is not the ECMA-262 dialect of "pattern", and the validator words it better.
 * A Collection<string, X> is an object here; the serializer writes it as one only with strategy: Serialize::KEYS_VALUES.
 */
final class McpSchemaFactory
{
    public const string TYPE = 'type';
    public const string PROPERTIES = 'properties';
    public const string ITEMS = 'items';
    public const string DESCRIPTION = 'description';
    public const string TYPE_OBJECT = 'object';
    public const string TYPE_ARRAY = 'array';
    public const string ADDITIONAL_PROPERTIES = 'additionalProperties';
    public const string UNTYPED_COLLECTION_MESSAGE = 'The MCP schema cannot describe %s::%s, a Collection without its key and item types: declare Collection<int, X> (Collection<string, X> for a keyed map) in its @var, or in its @return for a getter.';
    public const string UNTYPED_ARRAY_MESSAGE = 'The MCP schema cannot describe %s::%s, an array without its item type: declare list<X> (array<string, X> for a keyed map) in its @var, or in its @return for a getter.';
    public const string UNDESCRIBED_CLASS_MESSAGE = 'The MCP schema cannot describe the class %s, it has no #[Serialize] member: use a scalar, a Uuid, a backed enum, a DateTimeImmutable with #[Serialize(handler: McpDateTimeHandler::class)] or a class with #[Serialize] members.';

    private const string REQUIRED = 'required';
    private const string ENUM = 'enum';
    private const string DEFAULT = 'default';
    private const string MIN_LENGTH = 'minLength';
    private const string MAX_LENGTH = 'maxLength';
    private const string MIN_ITEMS = 'minItems';
    private const string MAX_ITEMS = 'maxItems';
    private const string MINIMUM = 'minimum';
    private const string MAXIMUM = 'maximum';
    private const string EXCLUSIVE_MINIMUM = 'exclusiveMinimum';
    private const string EXCLUSIVE_MAXIMUM = 'exclusiveMaximum';
    private const string TYPE_STRING = 'string';
    private const string TYPE_NULL = 'null';
    private const string TYPE_INTEGER = 'integer';
    private const string FORMAT = 'format';
    private const string FORMAT_UUID = 'uuid';
    private const string VALUE_FORMAT = '"%s"%s = %s';
    private const string VALUE_SEPARATOR = ', ';
    private const string DEFAULT_MARK = ' (default)';
    private const string GETTER_PREFIX_PATTERN = '~^(get|is)(?=\p{Lu})~';
    private const array JSON_TYPES = [
        TypeIdentifier::INT->value => self::TYPE_INTEGER,
        TypeIdentifier::FLOAT->value => 'number',
        TypeIdentifier::BOOL->value => 'boolean',
        TypeIdentifier::STRING->value => self::TYPE_STRING,
        TypeIdentifier::ARRAY->value => self::TYPE_ARRAY,
    ];

    private readonly TypeResolver $typeResolver;

    /**
     * @var array<class-string, true>
     */
    private array $describedClasses = [];

    public function __construct()
    {
        $this->typeResolver = TypeResolver::create();
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    public function createForClass(string $class): array
    {
        $this->describedClasses[$class] = true;
        $properties = [];
        $required = [];
        $reflection = new ReflectionClass($class);
        foreach ([...$this->collectProperties($reflection), ...$reflection->getMethods(ReflectionMethod::IS_PUBLIC)] as $member) {
            $serialize = $this->resolveAttribute($member, Serialize::class);
            if (null === $serialize) {
                continue;
            }
            $name = $serialize->serializedName ?? $this->resolveSerializedName($member);
            $constraints = $this->resolveConstraints($member);
            $schema = $this->applyConstraints($this->createForMember($member), $constraints);
            $default = $member instanceof ReflectionProperty && $this->hasDefaultValue($member) ? $this->getDefaultValue($member) : null;
            $description = $this->createDescription($member, $default);
            if (is_string($description)) {
                $schema[self::DESCRIPTION] = $description;
            }
            $properties[$name] = $schema;
            if ($member instanceof ReflectionMethod || $this->isRequired($member, $constraints)) {
                $field = $this->resolveAttribute($member, McpField::class);
                if (null === $field || $field->requiredBySchema) {
                    $required[] = $name;
                }

                continue;
            }
            $properties[$name][self::DEFAULT] = $this->normalizeDefault($this->getDefaultValue($member));
        }

        $schema = [self::TYPE => self::TYPE_OBJECT, self::PROPERTIES => $properties];
        if (false === empty($required)) {
            $schema[self::REQUIRED] = $required;
        }
        $schema[self::ADDITIONAL_PROPERTIES] = false;

        return $schema;
    }

    /**
     * The classes every createForClass() of this instance went through, nested ones, enums and compound constraints
     * included: their files decide the schema, so a container that holds it has to depend on them.
     *
     * @return list<class-string>
     */
    public function getDescribedClasses(): array
    {
        return array_keys($this->describedClasses);
    }

    /**
     * ReflectionClass::getProperties() leaves out the private properties of parent classes, which the serializer reads.
     *
     * @param ReflectionClass<object> $reflection
     *
     * @return array<string, ReflectionProperty>
     */
    private function collectProperties(ReflectionClass $reflection): array
    {
        $properties = [];
        for ($class = $reflection; false !== $class; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                $properties[$property->getName()] ??= $property;
            }
        }

        return $properties;
    }

    private function createDescription(ReflectionProperty|ReflectionMethod $member, mixed $default): ?string
    {
        $parts = [];
        $field = $this->resolveAttribute($member, McpField::class);
        if ($field instanceof McpField) {
            $parts[] = $field->description;
        }
        if ($this->isMcpDateTime($member)) {
            $parts[] = McpDateTimeHandler::FORMAT_DESCRIPTION;
        }
        if ($this->isMcpEndOfDay($member)) {
            $parts[] = McpDateTimeHandler::END_OF_DAY_DESCRIPTION;
        }
        $enumClass = $this->resolveEnumClass($this->typeResolver->resolve($member));
        $values = [];
        foreach (is_string($enumClass) ? $enumClass::cases() : [] as $case) {
            $caseField = $this->resolveAttribute(new ReflectionEnumBackedCase($case::class, $case->name), McpField::class);
            if ($caseField instanceof McpField) {
                $values[] = sprintf(self::VALUE_FORMAT, $case->value, $case === $default ? self::DEFAULT_MARK : '', $caseField->description);
            }
        }
        if (false === empty($values)) {
            $parts[] = implode(self::VALUE_SEPARATOR, $values) . '.';
        }

        return empty($parts) ? null : implode(' ', $parts);
    }

    /**
     * @return class-string<BackedEnum>|null
     */
    private function resolveEnumClass(Type $type): ?string
    {
        if ($type instanceof NullableType) {
            return $this->resolveEnumClass($type->getWrappedType());
        }
        if ($type instanceof BackedEnumType) {
            return $type->getClassName();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function createForMember(ReflectionProperty|ReflectionMethod $member): array
    {
        $type = $this->typeResolver->resolve($member);
        if ($this->isUntypedCollection($type)) {
            throw new LogicException(sprintf(self::UNTYPED_COLLECTION_MESSAGE, $member->class, $member->getName()));
        }
        if ($this->isUntypedArray($type)) {
            throw new LogicException(sprintf(self::UNTYPED_ARRAY_MESSAGE, $member->class, $member->getName()));
        }
        if ($this->isMcpDateTime($member)) {
            $this->describedClasses[McpDateTimeHandler::class] = true;
            $schema = [self::TYPE => self::TYPE_STRING];

            return $type instanceof NullableType ? $this->allowNull($schema) : $schema;
        }
        $intKeyedRawArrayType = $this->findIntKeyedRawArrayType($member, $type);
        if ($intKeyedRawArrayType instanceof CollectionType) {
            $schema = $this->createForMap($intKeyedRawArrayType);

            return $type instanceof NullableType ? $this->allowNull($schema) : $schema;
        }

        return $this->createForType($type);
    }

    /**
     * RawArrayHandler keeps the keys, so an int-keyed array that is not a list reaches the client as a JSON object.
     */
    private function findIntKeyedRawArrayType(ReflectionProperty|ReflectionMethod $member, Type $type): ?CollectionType
    {
        $innerType = $type instanceof NullableType ? $type->getWrappedType() : $type;
        if (false === $innerType instanceof CollectionType || $innerType->isList()) {
            return null;
        }
        $keyType = $innerType->getCollectionKeyType();
        $isIntKeyed = $keyType instanceof BuiltinType && $keyType->isIdentifiedBy(TypeIdentifier::INT);
        if ($isIntKeyed && RawArrayHandler::class === $this->resolveAttribute($member, Serialize::class)?->handler) {
            return $innerType;
        }

        return null;
    }

    private function isMcpDateTime(ReflectionProperty|ReflectionMethod $member): bool
    {
        return McpDateTimeHandler::class === $this->resolveAttribute($member, Serialize::class)?->handler;
    }

    private function isMcpEndOfDay(ReflectionProperty|ReflectionMethod $member): bool
    {
        return $this->isMcpDateTime($member)
            && McpDateTimeHandler::END_OF_DAY === $this->resolveAttribute($member, Serialize::class)?->type;
    }

    private function isUntypedCollection(Type $type): bool
    {
        $innerType = $type instanceof NullableType ? $type->getWrappedType() : $type;
        if ($innerType instanceof CollectionType) {
            $innerType = $innerType->getWrappedType();
        }

        return $innerType instanceof ObjectType && is_a(object_or_class: $innerType->getClassName(), class: Collection::class, allow_string: true);
    }

    private function isUntypedArray(Type $type): bool
    {
        $innerType = $type instanceof NullableType ? $type->getWrappedType() : $type;

        return $innerType instanceof CollectionType && $innerType->getWrappedType() instanceof BuiltinType;
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    public static function isOfType(array $schema, string $type): bool
    {
        $schemaType = $schema[self::TYPE] ?? null;

        return $type === $schemaType || (is_array($schemaType) && in_array(needle: $type, haystack: $schemaType, strict: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function createForType(Type $type): array
    {
        if ($type instanceof NullableType) {
            return $this->allowNull($this->createForType($type->getWrappedType()));
        }
        if ($type instanceof BackedEnumType) {
            $enumClass = $type->getClassName();
            $this->describedClasses[$enumClass] = true;

            return [
                self::TYPE => $this->createForType($type->getBackingType())[self::TYPE],
                self::ENUM => array_map(static fn (BackedEnum $case): int|string => $case->value, $enumClass::cases()),
            ];
        }
        if ($type instanceof CollectionType && $this->isKeyedByString($type)) {
            return $this->createForMap($type);
        }
        if ($type instanceof CollectionType) {
            $valueSchema = $this->createForType($type->getCollectionValueType());

            return empty($valueSchema) ? [self::TYPE => self::TYPE_ARRAY] : [self::TYPE => self::TYPE_ARRAY, self::ITEMS => $valueSchema];
        }
        if ($type instanceof ObjectType && is_a(object_or_class: $type->getClassName(), class: Uuid::class, allow_string: true)) {
            return [self::TYPE => self::TYPE_STRING, self::FORMAT => self::FORMAT_UUID];
        }
        if ($type instanceof ObjectType) {
            $schema = $this->createForClass($type->getClassName());
            if (empty($schema[self::PROPERTIES])) {
                throw new LogicException(sprintf(self::UNDESCRIBED_CLASS_MESSAGE, $type->getClassName()));
            }

            return $schema;
        }
        if ($type instanceof BuiltinType && isset(self::JSON_TYPES[$type->getTypeIdentifier()->value])) {
            return [self::TYPE => self::JSON_TYPES[$type->getTypeIdentifier()->value]];
        }
        if ($type instanceof BuiltinType && $type->isIdentifiedBy(TypeIdentifier::MIXED)) {
            return [];
        }

        throw new LogicException(sprintf('The type "%s" cannot be described in an MCP tool schema, use a scalar, a backed enum, a list or a class.', $type));
    }

    /**
     * @return array<string, mixed>
     */
    private function createForMap(CollectionType $type): array
    {
        $valueSchema = $this->createForType($type->getCollectionValueType());

        return [self::TYPE => self::TYPE_OBJECT, self::ADDITIONAL_PROPERTIES => empty($valueSchema) ? true : $valueSchema];
    }

    /**
     * The key of array<X> is the union int|string, which isIdentifiedBy() takes for a string too, but such a value is a list.
     */
    private function isKeyedByString(CollectionType $type): bool
    {
        $keyType = $type->getCollectionKeyType();

        return $keyType instanceof BuiltinType && $keyType->isIdentifiedBy(TypeIdentifier::STRING);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<array-key, Constraint> $constraints
     *
     * @return array<string, mixed>
     */
    private function applyConstraints(array $schema, array $constraints): array
    {
        foreach ($constraints as $constraint) {
            $schema = match (true) {
                $constraint instanceof Assert\All => [
                    ...$schema,
                    self::ITEMS => $this->applyConstraints((array) ($schema[self::ITEMS] ?? []), (array) $constraint->constraints),
                ],
                $constraint instanceof Assert\NotBlank => $this->applyNotBlank($schema),
                $constraint instanceof Assert\Length => $this->applyBounds(schema: $schema, minKey: self::MIN_LENGTH, min: $constraint->min, maxKey: self::MAX_LENGTH, max: $constraint->max),
                $constraint instanceof Assert\Count => $this->applyBounds(schema: $schema, minKey: self::MIN_ITEMS, min: $constraint->min, maxKey: self::MAX_ITEMS, max: $constraint->max),
                $constraint instanceof Assert\Range => $this->applyBounds(schema: $schema, minKey: self::MINIMUM, min: $constraint->min, maxKey: self::MAXIMUM, max: $constraint->max),
                $constraint instanceof Assert\GreaterThan => $this->applyExclusiveBound(schema: $schema, inclusiveKey: self::MINIMUM, exclusiveKey: self::EXCLUSIVE_MINIMUM, value: $constraint->value, step: 1),
                $constraint instanceof Assert\GreaterThanOrEqual => $this->applyBounds(schema: $schema, minKey: self::MINIMUM, min: $constraint->value, maxKey: self::MAXIMUM, max: null),
                $constraint instanceof Assert\LessThan => $this->applyExclusiveBound(schema: $schema, inclusiveKey: self::MAXIMUM, exclusiveKey: self::EXCLUSIVE_MAXIMUM, value: $constraint->value, step: -1),
                $constraint instanceof Assert\LessThanOrEqual => $this->applyBounds(schema: $schema, minKey: self::MINIMUM, min: null, maxKey: self::MAXIMUM, max: $constraint->value),
                $constraint instanceof Assert\Choice => $this->applyChoice($schema, $constraint),
                default => $schema,
            };
        }

        return $schema;
    }

    /**
     * A Choice with match: false forbids its choices, which the enum cannot say, so the validator alone checks it.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyChoice(array $schema, Assert\Choice $choice): array
    {
        if (false === is_array($choice->choices) || false === $choice->match) {
            return $schema;
        }
        $choices = array_values($choice->choices);
        if ($choice->multiple) {
            return [...$schema, self::ITEMS => $this->applyChoices((array) ($schema[self::ITEMS] ?? []), $choices)];
        }

        return $this->applyChoices($schema, $choices);
    }

    /**
     * A nullable property keeps null among its choices, as the validator skips null.
     *
     * @param array<string, mixed> $schema
     * @param list<mixed> $choices
     *
     * @return array<string, mixed>
     */
    private function applyChoices(array $schema, array $choices): array
    {
        $types = (array) ($schema[self::TYPE] ?? []);
        if (in_array(needle: self::TYPE_NULL, haystack: $types, strict: true)) {
            $choices[] = null;
        }

        return [...$schema, self::ENUM => $choices];
    }

    /**
     * The same shape the SDK gives a nullable handler parameter: null joins the type and the enum.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function allowNull(array $schema): array
    {
        if (is_string($schema[self::TYPE] ?? null)) {
            $schema[self::TYPE] = [self::TYPE_NULL, $schema[self::TYPE]];
        }
        if (isset($schema[self::ENUM]) && is_array($schema[self::ENUM])) {
            $schema[self::ENUM][] = null;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyNotBlank(array $schema): array
    {
        $minKey = match (true) {
            self::isOfType($schema, self::TYPE_STRING) => self::MIN_LENGTH,
            self::isOfType($schema, self::TYPE_ARRAY) => self::MIN_ITEMS,
            default => null,
        };
        if (null === $minKey) {
            return $schema;
        }

        return [...$schema, $minKey => max((int) ($schema[$minKey] ?? 0), 1)];
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyBounds(array $schema, string $minKey, mixed $min, string $maxKey, mixed $max): array
    {
        if (is_int($min) || is_float($min)) {
            $schema[$minKey] = $min;
        }
        if (is_int($max) || is_float($max)) {
            $schema[$maxKey] = $max;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyExclusiveBound(array $schema, string $inclusiveKey, string $exclusiveKey, mixed $value, int $step): array
    {
        if (is_int($value) && self::isOfType($schema, self::TYPE_INTEGER)) {
            return [...$schema, $inclusiveKey => $value + $step];
        }
        if (is_int($value) || is_float($value)) {
            return [...$schema, $exclusiveKey => $value];
        }

        return $schema;
    }

    /**
     * @param array<array-key, Constraint> $constraints
     */
    private function isRequired(ReflectionProperty $property, array $constraints): bool
    {
        if (false === $this->hasDefaultValue($property)) {
            return true;
        }
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Assert\NotBlank && false === $constraint->allowNull) {
                return true;
            }
            if ($constraint instanceof Assert\NotNull) {
                return true;
            }
            if ($constraint instanceof Assert\Count && is_int($constraint->min) && $constraint->min > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * A promoted property has no default of its own: the default belongs to its constructor parameter.
     */
    private function hasDefaultValue(ReflectionProperty $property): bool
    {
        if ($property->isPromoted()) {
            return $this->getPromotedParameter($property)
                ->isDefaultValueAvailable();
        }

        return $property->hasDefaultValue();
    }

    private function getDefaultValue(ReflectionProperty $property): mixed
    {
        if ($property->isPromoted()) {
            return $this->getPromotedParameter($property)
                ->getDefaultValue();
        }

        return $property->getDefaultValue();
    }

    private function getPromotedParameter(ReflectionProperty $property): ReflectionParameter
    {
        return new ReflectionParameter([$property->class, '__construct'], $property->getName());
    }

    private function resolveSerializedName(ReflectionProperty|ReflectionMethod $member): string
    {
        if ($member instanceof ReflectionProperty) {
            return $member->getName();
        }

        return (string) preg_replace(pattern: self::GETTER_PREFIX_PATTERN, replacement: '', subject: $member->getName())
            |> lcfirst(...);
    }

    private function normalizeDefault(mixed $default): mixed
    {
        if ($default instanceof BackedEnum) {
            return $default->value;
        }

        return $default;
    }

    /**
     * @return array<array-key, Constraint>
     */
    private function resolveConstraints(ReflectionProperty|ReflectionMethod $member): array
    {
        $constraints = [];
        foreach ($member->getAttributes(Constraint::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $constraint = $attribute->newInstance();
            if ($constraint instanceof Assert\Compound) {
                $this->describedClasses[$constraint::class] = true;
                array_push($constraints, ...$constraint->constraints);

                continue;
            }
            $constraints[] = $constraint;
        }

        return $constraints;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function resolveAttribute(ReflectionProperty|ReflectionMethod|ReflectionEnumBackedCase $member, string $class): ?object
    {
        return array_first($member->getAttributes($class))?->newInstance();
    }
}
