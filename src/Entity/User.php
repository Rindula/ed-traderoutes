<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $subject;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct(string $subject, ?string $email = null, ?string $displayName = null)
    {
        $this->id = (string) new Ulid();
        $this->subject = $subject;
        $this->email = $email;
        $this->displayName = $displayName;
    }

    public function getId(): string { return $this->id; }
    public function getSubject(): string { return $this->subject; }
    public function getEmail(): ?string { return $this->email; }
    public function getDisplayName(): ?string { return $this->displayName; }

    public function updateProfile(?string $email, ?string $displayName): void
    {
        $this->email = $email;
        $this->displayName = $displayName;
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    public function getUserIdentifier(): string { return $this->subject; }
    public function getRoles(): array { return array_values(array_unique([...$this->roles, 'ROLE_USER'])); }
    public function eraseCredentials(): void {}
}
