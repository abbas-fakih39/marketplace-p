<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    // Cette méthode ne s'exécute jamais : json_login l'intercepte avant
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Géré automatiquement par le firewall json_login + lexik JWT
        throw new \LogicException('Ce endpoint est intercepté par le firewall de sécurité.');
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validation des champs obligatoires
        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['message' => 'Email et mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifie que l'email n'est pas déjà utilisé
        $existant = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existant) {
            return $this->json(['message' => 'Cet email est déjà utilisé.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));

        // Rôle autorisé à la création : ROLE_USER ou ROLE_PRESTATAIRE
        $roleAutorise = ['ROLE_USER', 'ROLE_PRESTATAIRE'];
        $role = $data['role'] ?? 'ROLE_USER';
        if (!in_array($role, $roleAutorise, true)) {
            $role = 'ROLE_USER';
        }
        $user->setRoles([$role]);

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'Compte créé avec succès.',
            'user' => [
                'id'    => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }
}
