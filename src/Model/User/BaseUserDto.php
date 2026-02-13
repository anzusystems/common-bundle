<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\User;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints\UniqueEntityDto;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Entity\Embeds\Avatar;
use AnzuSystems\Contracts\Entity\Embeds\Person;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @psalm-consistent-constructor
 */
#[UniqueEntityDto(entity: AnzuUser::class, fields: ['id'])]
#[UniqueEntityDto(entity: AnzuUser::class, fields: ['email'])]
class BaseUserDto
{
    #[Serialize]
    protected ?int $id = null;

    #[Assert\Email(message: ValidationException::ERROR_FIELD_INVALID)]
    #[Assert\Length(max: 256, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Serialize]
    protected string $email = '';

    #[Assert\Valid]
    #[Serialize]
    protected Person $person;

    #[Assert\Valid]
    #[Serialize]
    protected Avatar $avatar;
    protected string $resourceName = '';

    public function __construct()
    {
        $this->setPerson(new Person());
        $this->setAvatar(new Avatar());
    }

    /**
     * @psalm-suppress LessSpecificReturnStatement
     * @psalm-suppress MoreSpecificReturnType
     */
    public static function createFromUser(AnzuUser $user): static
    {
        return (new static())
            ->setId($user->getId())
            ->setEmail($user->getEmail())
            ->setPerson($user->getPerson())
            ->setAvatar($user->getAvatar())
            ->setResourceName($user::getResourceName())
        ;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }

    public function setPerson(Person $person): static
    {
        $this->person = $person;

        return $this;
    }

    public function getAvatar(): Avatar
    {
        return $this->avatar;
    }

    public function setAvatar(Avatar $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    #[Serialize(serializedName: '_resourceName')]
    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    public function setResourceName(string $resourceName): static
    {
        $this->resourceName = $resourceName;

        return $this;
    }

    #[Serialize(serializedName: '_system')]
    public static function getSystem(): string
    {
        return AnzuApp::getAppSystem();
    }
}
