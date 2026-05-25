<?php

namespace App\DataFixtures;

use App\Entity\Demande;
use App\Entity\MiseEnRelation;
use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // ---------------------------------------------------------------
        // 1. Utilisateurs
        // ---------------------------------------------------------------

        $admin = $this->creerUser($manager, 'admin@marketplace.fr', 'password', ['ROLE_ADMIN']);

        $prestataires = [];
        $donneesPrestataires = [
            ['alice.dupont@prestataire.fr', 'Alice Dupont'],
            ['bob.martin@prestataire.fr', 'Bob Martin'],
            ['claire.moreau@prestataire.fr', 'Claire Moreau'],
        ];
        foreach ($donneesPrestataires as [$email, $nom]) {
            $prestataires[] = $this->creerUser($manager, $email, 'password', ['ROLE_PRESTATAIRE']);
        }

        $utilisateurs = [];
        for ($i = 1; $i <= 5; $i++) {
            $utilisateurs[] = $this->creerUser(
                $manager,
                "user{$i}@marketplace.fr",
                'password',
                ['ROLE_USER']
            );
        }

        $manager->flush();

        // ---------------------------------------------------------------
        // 2. Services (publiés par les prestataires)
        // ---------------------------------------------------------------

        $catalogueServices = [
            // Alice - développement web
            [
                ['Création site vitrine',       'Site vitrine professionnel en HTML/CSS/JS avec design responsive.',          750.00],
                ['Boutique e-commerce Symfony', 'Développement complet d\'une boutique en ligne avec paiement intégré.',    2500.00],
                ['API REST Symfony',            'Conception et développement d\'une API REST sécurisée avec JWT.',           1800.00],
            ],
            // Bob - design & graphisme
            [
                ['Logo & charte graphique',  'Création d\'un logo original + guide de style complet.',                       450.00],
                ['Maquettes UI/UX Figma',    'Design de maquettes interactives pour application web ou mobile.',             900.00],
                ['Refonte visuelle de site', 'Analyse et refonte complète de l\'identité visuelle d\'un site existant.',    1200.00],
            ],
            // Claire - marketing digital
            [
                ['Stratégie réseaux sociaux', 'Audit et plan d\'action complet pour les réseaux sociaux.',                   600.00],
                ['Campagne Google Ads',        'Création et gestion de campagnes publicitaires Google Ads sur 1 mois.',      800.00],
                ['Rédaction SEO',              'Rédaction de 10 articles optimisés pour le référencement naturel.',          350.00],
            ],
        ];

        $services = [];
        foreach ($catalogueServices as $indexPresta => $listeServices) {
            foreach ($listeServices as [$titre, $description, $prix]) {
                $service = new Service();
                $service->setTitre($titre);
                $service->setDescription($description);
                $service->setPrix((string) $prix);
                $service->setStatut(Service::STATUT_ACTIF);
                $service->setPrestataire($prestataires[$indexPresta]);
                $manager->persist($service);
                $services[] = $service;
            }
        }

        $manager->flush();

        // ---------------------------------------------------------------
        // 3. Demandes (créées par les utilisateurs)
        // ---------------------------------------------------------------

        $messagesDemandes = [
            'Bonjour, je cherche un prestataire pour refaire mon site web. Mon budget est limité.',
            'Nous avons besoin d\'une API pour notre application mobile. Pouvez-vous nous contacter ?',
            'Je souhaite lancer une boutique en ligne pour vendre mes produits artisanaux.',
            'Recherche graphiste pour créer le logo de ma startup. Livraison rapide souhaitée.',
            'Je veux améliorer le référencement de mon site. Quelles sont vos disponibilités ?',
            'Projet de refonte complète de notre site B2B. Devis souhaité rapidement.',
            'Besoin d\'aide pour gérer nos réseaux sociaux, nous manquons de temps.',
            'Application de gestion interne à développer, stack Symfony de préférence.',
        ];

        $demandes = [];
        foreach ($utilisateurs as $index => $utilisateur) {
            // Chaque utilisateur crée 1 ou 2 demandes
            $nbDemandes = ($index % 2 === 0) ? 2 : 1;
            for ($i = 0; $i < $nbDemandes; $i++) {
                $demande = new Demande();
                $demande->setMessage($messagesDemandes[($index * 2 + $i) % count($messagesDemandes)]);
                $demande->setUtilisateur($utilisateur);
                $manager->persist($demande);
                $demandes[] = $demande;
            }
        }

        $manager->flush();

        // ---------------------------------------------------------------
        // 4. Mises en relation
        // ---------------------------------------------------------------

        // Associations manuelles pour avoir des cas variés
        $associations = [
            // [index service, index demande, statut]
            [0, 0, MiseEnRelation::STATUT_VALIDE],   // site vitrine → demande 1, validée
            [1, 1, MiseEnRelation::STATUT_EN_COURS],  // e-commerce → demande 2, en cours
            [2, 2, MiseEnRelation::STATUT_EN_COURS],  // API REST → demande 3, en cours
            [3, 3, MiseEnRelation::STATUT_REFUSE],    // logo → demande 4, refusée
            [4, 4, MiseEnRelation::STATUT_VALIDE],    // maquettes → demande 5, validée
            [6, 5, MiseEnRelation::STATUT_EN_COURS],  // réseaux sociaux → demande 6, en cours
            [7, 6, MiseEnRelation::STATUT_VALIDE],    // Google Ads → demande 7, validée
        ];

        foreach ($associations as [$si, $di, $statut]) {
            if (!isset($services[$si]) || !isset($demandes[$di])) {
                continue;
            }
            $mer = new MiseEnRelation();
            $mer->setService($services[$si]);
            $mer->setDemande($demandes[$di]);
            $mer->setStatut($statut);
            $manager->persist($mer);
        }

        $manager->flush();
    }

    private function creerUser(ObjectManager $manager, string $email, string $plainPassword, array $roles): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $manager->persist($user);
        return $user;
    }
}
