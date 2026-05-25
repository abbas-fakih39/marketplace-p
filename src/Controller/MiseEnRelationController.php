<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\MiseEnRelation;
use App\Entity\Service;
use App\Repository\MiseEnRelationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mises-en-relation', name: 'api_mer_')]
class MiseEnRelationController extends AbstractController
{
    private function serialiser(MiseEnRelation $mer): array
    {
        return [
            'id'        => $mer->getId(),
            'statut'    => $mer->getStatut(),
            'createdAt' => $mer->getCreatedAt()->format('Y-m-d H:i:s'),
            'service'   => [
                'id'    => $mer->getService()->getId(),
                'titre' => $mer->getService()->getTitre(),
            ],
            'demande'   => [
                'id'      => $mer->getDemande()->getId(),
                'message' => $mer->getDemande()->getMessage(),
                'statut'  => $mer->getDemande()->getStatut(),
            ],
        ];
    }

    // Liste les mises en relation - admin voit tout, prestataire voit les siennes
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function list(MiseEnRelationRepository $repo): JsonResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $mers = $repo->findAll();
        } else {
            // Filtre les MER dont le service appartient à ce prestataire
            $mers = $repo->createQueryBuilder('m')
                ->join('m.service', 's')
                ->where('s.prestataire = :user')
                ->setParameter('user', $this->getUser())
                ->getQuery()
                ->getResult();
        }

        return $this->json(array_map([$this, 'serialiser'], $mers));
    }

    // Détail d'une mise en relation
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function show(MiseEnRelation $mer): JsonResponse
    {
        $estPrestataire = $mer->getService()->getPrestataire() === $this->getUser();
        $estDemandeur   = $mer->getDemande()->getUtilisateur() === $this->getUser();
        $estAdmin       = $this->isGranted('ROLE_ADMIN');

        if (!$estPrestataire && !$estDemandeur && !$estAdmin) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serialiser($mer));
    }

    // Crée une mise en relation entre un service et une demande - prestataire seulement
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['service_id']) || empty($data['demande_id'])) {
            return $this->json(['message' => 'service_id et demande_id sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        $service = $em->getRepository(Service::class)->find($data['service_id']);
        $demande = $em->getRepository(Demande::class)->find($data['demande_id']);

        if (!$service || !$demande) {
            return $this->json(['message' => 'Service ou demande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Seul le prestataire propriétaire du service peut créer une mise en relation
        if ($service->getPrestataire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé : vous n\'êtes pas le prestataire de ce service.'], Response::HTTP_FORBIDDEN);
        }

        // Évite les doublons
        $existante = $em->getRepository(MiseEnRelation::class)->findOneBy([
            'service' => $service,
            'demande' => $demande,
        ]);
        if ($existante) {
            return $this->json(['message' => 'Une mise en relation existe déjà pour ce service et cette demande.'], Response::HTTP_CONFLICT);
        }

        $mer = new MiseEnRelation();
        $mer->setService($service);
        $mer->setDemande($demande);

        $em->persist($mer);
        $em->flush();

        return $this->json($this->serialiser($mer), Response::HTTP_CREATED);
    }

    // Change le statut d'une mise en relation - prestataire concerné ou admin
    #[Route('/{id}/statut', name: 'statut', methods: ['PATCH'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function changerStatut(MiseEnRelation $mer, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if ($mer->getService()->getPrestataire() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $data    = json_decode($request->getContent(), true);
        $statuts = [MiseEnRelation::STATUT_EN_COURS, MiseEnRelation::STATUT_VALIDE, MiseEnRelation::STATUT_REFUSE];

        if (empty($data['statut']) || !in_array($data['statut'], $statuts, true)) {
            return $this->json([
                'message' => 'Statut invalide. Valeurs acceptées : ' . implode(', ', $statuts),
            ], Response::HTTP_BAD_REQUEST);
        }

        $mer->setStatut($data['statut']);
        $em->flush();

        return $this->json($this->serialiser($mer));
    }
}
