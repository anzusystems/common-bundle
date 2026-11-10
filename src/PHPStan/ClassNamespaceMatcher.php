<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\PHPStan;

final readonly class ClassNamespaceMatcher
{
    private const string NAMESPACE_SEPARATOR = '\\';
    private const string NAMESPACE_WILDCARD = '*';
    private const string PATTERN_DELIMITER = '/';

    /**
     * @var list<string>
     */
    private array $patterns;

    /**
     * @param list<string> $namespaces `*` stands for one segment, e.g. `App\Domain\*\Mcp`
     */
    public function __construct(array $namespaces)
    {
        $this->patterns = array_map($this->createPattern(...), $namespaces);
    }

    public function matches(string $className): bool
    {
        return array_any($this->patterns, static fn (string $pattern): bool => 1 === preg_match($pattern, $className));
    }

    private function createPattern(string $namespace): string
    {
        $quotedNamespace = preg_quote(trim($namespace, self::NAMESPACE_SEPARATOR), self::PATTERN_DELIMITER);
        $namespacePattern = str_replace(search: preg_quote(self::NAMESPACE_WILDCARD, self::PATTERN_DELIMITER), replace: '\w+', subject: $quotedNamespace);

        return sprintf('%1$s^%2$s\\\\%1$s', self::PATTERN_DELIMITER, $namespacePattern);
    }
}
