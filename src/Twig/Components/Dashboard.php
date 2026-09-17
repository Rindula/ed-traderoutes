<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Application\Dashboard\DashboardReadModel;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('Dashboard')]
final class Dashboard
{
    use DefaultActionTrait;

    public function __construct(
        private readonly DashboardReadModel $readModel,
        private readonly Security $security,
    ) {
    }

    /** @return array<string, mixed> */
    public function getDashboard(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('An authenticated user is required for the dashboard.');
        }

        return $this->readModel->forUser($user);
    }
}
