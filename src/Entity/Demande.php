<?php

namespace App\Entity;

use App\Repository\DemandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeRepository::class)]
class Demande
{
    // Statuts du cycle de vie d'une demande
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACCEPTEE   = 'acceptee';
    public const STATUT_REFUSEE    = 'refusee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    // Utilisateur qui a créé la demande
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'demandes')]
    #[ORM\JoinColumn(nullable: false)]
    private User $utilisateur;

    // Mises en relation issues de cette demande
    #[ORM\OneToMany(targetEntity: MiseEnRelation::class, mappedBy: 'demande', cascade: ['persist', 'remove'])]
    private Collection $misesEnRelation;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->misesEnRelation = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUtilisateur(): User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getMisesEnRelation(): Collection
    {
        return $this->misesEnRelation;
    }
}
