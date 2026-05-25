<?php

namespace App\Entity;

use App\Repository\MiseEnRelationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MiseEnRelationRepository::class)]
class MiseEnRelation
{
    // Statuts possibles d'une mise en relation
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_VALIDE   = 'valide';
    public const STATUT_REFUSE   = 'refuse';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_COURS;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    // Le service concerné par cette mise en relation
    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'misesEnRelation')]
    #[ORM\JoinColumn(nullable: false)]
    private Service $service;

    // La demande concernée par cette mise en relation
    #[ORM\ManyToOne(targetEntity: Demande::class, inversedBy: 'misesEnRelation')]
    #[ORM\JoinColumn(nullable: false)]
    private Demande $demande;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getService(): Service
    {
        return $this->service;
    }

    public function setService(Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function getDemande(): Demande
    {
        return $this->demande;
    }

    public function setDemande(Demande $demande): static
    {
        $this->demande = $demande;
        return $this;
    }
}
