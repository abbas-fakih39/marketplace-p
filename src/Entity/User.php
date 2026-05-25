<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    // Stocke le mot de passe hashé
    #[ORM\Column]
    private string $password;

    // Tableau de rôles : ROLE_USER, ROLE_PRESTATAIRE, ROLE_ADMIN
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    // Services publiés par ce prestataire
    #[ORM\OneToMany(targetEntity: Service::class, mappedBy: 'prestataire', cascade: ['persist', 'remove'])]
    private Collection $services;

    // Demandes créées par cet utilisateur
    #[ORM\OneToMany(targetEntity: Demande::class, mappedBy: 'utilisateur', cascade: ['persist', 'remove'])]
    private Collection $demandes;

    public function __construct()
    {
        $this->services = new ArrayCollection();
        $this->demandes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    // Identifiant unique de l'utilisateur pour Symfony Security
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // Garantit que tout utilisateur a au moins ROLE_USER
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Pas de données sensibles temporaires à effacer
    }

    public function getServices(): Collection
    {
        return $this->services;
    }

    public function getDemandes(): Collection
    {
        return $this->demandes;
    }
}
