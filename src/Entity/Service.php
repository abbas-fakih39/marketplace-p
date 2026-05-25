<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service
{
    // Statuts possibles d'un service
    public const STATUT_ACTIF   = 'actif';
    public const STATUT_INACTIF = 'inactif';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(type: 'text')]
    private string $description;

    // Prix en euros, deux décimales
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $prix;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_ACTIF;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    // Prestataire propriétaire du service
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: false)]
    private User $prestataire;

    // Mises en relation associées à ce service
    #[ORM\OneToMany(targetEntity: MiseEnRelation::class, mappedBy: 'service', cascade: ['persist', 'remove'])]
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

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrix(): string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;
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

    public function getPrestataire(): User
    {
        return $this->prestataire;
    }

    public function setPrestataire(User $prestataire): static
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    public function getMisesEnRelation(): Collection
    {
        return $this->misesEnRelation;
    }
}
