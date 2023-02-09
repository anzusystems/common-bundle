<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Entity;

use AnzuSystems\CommonBundle\Repository\JobUserDataDeleteRepository;
use AnzuSystems\CommonBundle\Validator\Constraints as AppAssert;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobUserDataDeleteRepository::class)]
class JobUserDataDelete extends Job
{
    /**
     * Target user for which to process data delete.
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[AppAssert\EntityExists(entity: AnzuUser::class)]
    #[Assert\NotBlank]
    #[Serialize]
    protected int $targetUserId;

    /**
     * If true, user's personal data like email or name will be anonymized.
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    protected bool $anonymizeUser;

    public function __construct()
    {
        parent::__construct();
        $this->setTargetUserId(0);
        $this->setAnonymizeUser(false);
    }

    public function getTargetUserId(): int
    {
        return $this->targetUserId;
    }

    public function setTargetUserId(int $targetUserId): self
    {
        $this->targetUserId = $targetUserId;

        return $this;
    }

    public function isAnonymizeUser(): bool
    {
        return $this->anonymizeUser;
    }

    public function setAnonymizeUser(bool $anonymizeUser): self
    {
        $this->anonymizeUser = $anonymizeUser;

        return $this;
    }
}
