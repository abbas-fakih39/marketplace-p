<?php

namespace App\Controller;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/services', name: 'api_services_')]
class ServiceController extends AbstractController
{
    // Formate un service en tableau pour la réponse JSON
    private function serialiser(Service $s): array
    {
        return [
            'id'          => $s->getId(),
            'titre'       => $s->getTitre(),
            'description' => $s->getDescription(),
            'prix'        => $s->getPrix(),
            'statut'      => $s->getStatut(),
            'createdAt'   => $s->getCreatedAt()->format('Y-m-d H:i:s'),
            'prestataire' => [
                'id'    => $s->getPrestataire()->getId(),
                'email' => $s->getPrestataire()->getEmail(),
            ],
        ];
    }

    // Liste tous les services actifs
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ServiceRepository $repo): JsonResponse
    {
        $services = $repo->findBy(['statut' => Service::STATUT_ACTIF]);

        return $this->json(array_map([$this, 'serialiser'], $services));
    }

    // Détail d'un service
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Service $service): JsonResponse
    {
        return $this->json($this->serialiser($service));
    }

    // Crée un nouveau service - réservé aux prestataires
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['titre']) || empty($data['description']) || !isset($data['prix'])) {
            return $this->json(['message' => 'titre, description et prix sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        $service = new Service();
        $service->setTitre($data['titre']);
        $service->setDescription($data['description']);
        $service->setPrix((string) $data['prix']);
        $service->setPrestataire($this->getUser());

        if (isset($data['statut']) && in_array($data['statut'], [Service::STATUT_ACTIF, Service::STATUT_INACTIF], true)) {
            $service->setStatut($data['statut']);
        }

        $em->persist($service);
        $em->flush();

        return $this->json($this->serialiser($service), Response::HTTP_CREATED);
    }

    // Modifie un service - réservé au prestataire propriétaire
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function update(Service $service, Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Seul le prestataire qui a créé le service peut le modifier
        if ($service->getPrestataire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['titre'])) {
            $service->setTitre($data['titre']);
        }
        if (!empty($data['description'])) {
            $service->setDescription($data['description']);
        }
        if (isset($data['prix'])) {
            $service->setPrix((string) $data['prix']);
        }
        if (isset($data['statut']) && in_array($data['statut'], [Service::STATUT_ACTIF, Service::STATUT_INACTIF], true)) {
            $service->setStatut($data['statut']);
        }

        $em->flush();

        return $this->json($this->serialiser($service));
    }

    // Supprime un service - prestataire propriétaire ou admin
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function delete(Service $service, EntityManagerInterface $em): JsonResponse
    {
        $estProprietaire = $service->getPrestataire() === $this->getUser();
        $estAdmin        = $this->isGranted('ROLE_ADMIN');

        if (!$estProprietaire && !$estAdmin) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($service);
        $em->flush();

        return $this->json(['message' => 'Service supprimé.']);
    }
}
