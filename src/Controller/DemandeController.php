<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Repository\DemandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/demandes', name: 'api_demandes_')]
class DemandeController extends AbstractController
{
    private function serialiser(Demande $d): array
    {
        return [
            'id'          => $d->getId(),
            'message'     => $d->getMessage(),
            'statut'      => $d->getStatut(),
            'createdAt'   => $d->getCreatedAt()->format('Y-m-d H:i:s'),
            'utilisateur' => [
                'id'    => $d->getUtilisateur()->getId(),
                'email' => $d->getUtilisateur()->getEmail(),
            ],
        ];
    }

    // Liste les demandes : toutes pour l'admin, les siennes pour l'utilisateur
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(DemandeRepository $repo): JsonResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $demandes = $repo->findAll();
        } else {
            $demandes = $repo->findBy(['utilisateur' => $this->getUser()]);
        }

        return $this->json(array_map([$this, 'serialiser'], $demandes));
    }

    // Détail d'une demande - propriétaire ou admin uniquement
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Demande $demande): JsonResponse
    {
        if ($demande->getUtilisateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serialiser($demande));
    }

    // Crée une demande - tout utilisateur authentifié
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['message'])) {
            return $this->json(['message' => 'Le champ message est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $demande = new Demande();
        $demande->setMessage($data['message']);
        $demande->setUtilisateur($this->getUser());

        $em->persist($demande);
        $em->flush();

        return $this->json($this->serialiser($demande), Response::HTTP_CREATED);
    }

    // Change le statut d'une demande - prestataire (accepte/refuse) ou admin
    #[Route('/{id}/statut', name: 'statut', methods: ['PATCH'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function changerStatut(Demande $demande, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $statuts = [Demande::STATUT_EN_ATTENTE, Demande::STATUT_ACCEPTEE, Demande::STATUT_REFUSEE];

        if (empty($data['statut']) || !in_array($data['statut'], $statuts, true)) {
            return $this->json([
                'message' => 'Statut invalide. Valeurs acceptées : ' . implode(', ', $statuts),
            ], Response::HTTP_BAD_REQUEST);
        }

        $demande->setStatut($data['statut']);
        $em->flush();

        return $this->json($this->serialiser($demande));
    }

    // Supprime une demande - propriétaire ou admin uniquement
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Demande $demande, EntityManagerInterface $em): JsonResponse
    {
        if ($demande->getUtilisateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($demande);
        $em->flush();

        return $this->json(['message' => 'Demande supprimée.']);
    }
}
