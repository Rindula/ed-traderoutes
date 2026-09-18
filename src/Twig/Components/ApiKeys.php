<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\SyncKey;
use App\Entity\User;
use App\Repository\SyncKeyRepository;
use App\Security\SyncKeyCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('ApiKeys')]
final class ApiKeys
{
    use DefaultActionTrait;

    private ?string $newKeyIdentifier = null;
    private ?string $newKeyToken = null;

    public function __construct(
        private readonly SyncKeyRepository $keys,
        private readonly SyncKeyCipher $cipher,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return list<SyncKey> */
    public function getKeys(): array
    {
        return $this->keys->findForUser($this->user(), true);
    }

    public function getNewKeyIdentifier(): ?string
    {
        return $this->newKeyIdentifier;
    }

    public function getNewKeyToken(): ?string
    {
        return $this->newKeyToken;
    }

    #[LiveAction]
    public function createKey(): void
    {
        $key = $this->keys->createForUser($this->user());
        $this->entityManager->flush();

        // The raw token is returned exactly once so it can be copied into EDMC.
        $this->newKeyIdentifier = $key->getKeyIdentifier();
        $this->newKeyToken = $key->revealToken($this->cipher);
    }

    private function user(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('An authenticated user is required for API key management.');
        }

        return $user;
    }
}
