<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Controller;

use AnzuSystems\CommonBundle\Controller\AbstractJobController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/job')]
final class JobController extends AbstractJobController
{
    protected function getCreateAcl(): string
    {
        return '';
    }

    protected function getDeleteAcl(): string
    {
        return '';
    }

    protected function getViewAcl(): string
    {
        return '';
    }
}
