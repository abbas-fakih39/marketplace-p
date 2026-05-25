# Documentation technique - Marketplace de services

## Table des matières

1. [Présentation du projet](#1-présentation-du-projet)
2. [Conception](#2-conception)
3. [Architecture](#3-architecture)
4. [Sécurité](#4-sécurité)
5. [Installation et lancement](#5-installation-et-lancement)
6. [Référence des endpoints](#6-référence-des-endpoints)
7. [Guide de test rapide](#7-guide-de-test-rapide)

---

## 1. Présentation du projet

### Objectif

Cette API backend permet de mettre en relation des **utilisateurs** ayant des besoins et des **prestataires** proposant des services. Elle est conçue pour être consommée par n'importe quel client (web, mobile) via des requêtes HTTP JSON.

### Fonctionnalités principales

- Création de compte et authentification par token JWT
- Gestion de trois rôles : utilisateur, prestataire, administrateur
- Publication et consultation de services par les prestataires
- Création de demandes par les utilisateurs
- Mise en relation entre un service et une demande, avec gestion d'états

---

## 2. Conception

### Entités et champs

#### User
| Champ | Type | Description |
|---|---|---|
| `id` | int | Identifiant auto-incrémenté |
| `email` | string (unique) | Identifiant de connexion |
| `password` | string | Mot de passe hashé (bcrypt) |
| `roles` | JSON | Tableau de rôles Symfony |

#### Service
| Champ | Type | Description |
|---|---|---|
| `id` | int | Identifiant |
| `titre` | string | Intitulé du service |
| `description` | text | Description détaillée |
| `prix` | decimal(10,2) | Prix en euros |
| `statut` | string | `actif` ou `inactif` |
| `createdAt` | datetime | Date de création |
| `prestataire` | ManyToOne → User | Propriétaire du service |

#### Demande
| Champ | Type | Description |
|---|---|---|
| `id` | int | Identifiant |
| `message` | text | Description du besoin |
| `statut` | string | `en_attente`, `acceptee`, `refusee` |
| `createdAt` | datetime | Date de création |
| `utilisateur` | ManyToOne → User | Auteur de la demande |

#### MiseEnRelation
| Champ | Type | Description |
|---|---|---|
| `id` | int | Identifiant |
| `statut` | string | `en_cours`, `valide`, `refuse` |
| `createdAt` | datetime | Date de création |
| `service` | ManyToOne → Service | Service concerné |
| `demande` | ManyToOne → Demande | Demande concernée |

### Relations

```
User (1) ──────────────── (N) Service
User (1) ──────────────── (N) Demande
Service (1) ──────────── (N) MiseEnRelation
Demande (1) ──────────── (N) MiseEnRelation
```

### Choix de modélisation

- **Rôles en JSON** : Symfony Security impose un tableau de rôles. On stocke le rôle principal (`ROLE_PRESTATAIRE`) directement, `ROLE_USER` est toujours ajouté automatiquement par `getRoles()`.
- **Statuts en constantes** : chaque entité expose ses valeurs autorisées via des constantes de classe (`Service::STATUT_ACTIF`, etc.), ce qui évite les valeurs magiques en base.
- **Prix en `decimal`** : le type `decimal(10,2)` évite les erreurs d'arrondi des flottants pour les montants monétaires.
- **`DateTimeImmutable` au constructeur** : la date de création est initialisée dans `__construct()` et n'expose pas de setter, garantissant son immuabilité.

---

## 3. Architecture

### Organisation du code

```
src/
├── Controller/
│   ├── AuthController.php          # /api/register, /api/login
│   ├── ServiceController.php       # /api/services (CRUD)
│   ├── DemandeController.php       # /api/demandes (CRUD)
│   └── MiseEnRelationController.php # /api/mises-en-relation
├── Entity/
│   ├── User.php
│   ├── Service.php
│   ├── Demande.php
│   └── MiseEnRelation.php
├── Repository/
│   ├── UserRepository.php
│   ├── ServiceRepository.php
│   ├── DemandeRepository.php
│   └── MiseEnRelationRepository.php
└── DataFixtures/
    └── AppFixtures.php             # Données de test
```

### Séparation des responsabilités

- **Controllers** : reçoivent la requête HTTP, valident les droits et les données, délèguent à Doctrine, retournent du JSON.
- **Entities** : portent uniquement les données et les constantes métier. Pas de logique applicative.
- **Repositories** : encapsulent les requêtes Doctrine. Le repository `User` implémente `PasswordUpgraderInterface` pour la migration automatique des hashs.
- **Fixtures** : données de test injectées via `doctrine:fixtures:load`, indépendantes du code de production.

### Choix techniques

| Choix | Justification |
|---|---|
| Symfony 7 (LTS) | Framework robuste, maintenance jusqu'en 2028, écosystème mature |
| Doctrine ORM | Mapping objet-relationnel standard Symfony, migrations versionées |
| JWT (lexik bundle) | Mécanisme stateless adapté aux API, pas de session serveur |
| MySQL 8 | Base relationnelle fiable, clés étrangères, contraintes d'intégrité |
| Réponses JSON manuelles | Évite les références circulaires du Serializer sans configuration de groupes |

---

## 4. Sécurité

### Mécanisme d'authentification

L'authentification est basée sur **JSON Web Tokens (JWT)** via `lexik/jwt-authentication-bundle`.

**Flux de connexion :**

```
Client                          API
  │                              │
  │── POST /api/login ─────────► │
  │   {email, password}          │
  │                              │── Vérifie les credentials via Doctrine
  │                              │── Hash le mot de passe (bcrypt)
  │◄─ {token: "eyJ..."} ──────── │
  │                              │
  │── GET /api/services ───────► │
  │   Authorization: Bearer eyJ  │── Décode et valide le JWT
  │◄─ [{id:1, titre:...}] ────── │
```

Le token JWT est signé avec une clé RSA privée (`config/jwt/private.pem`) et vérifié avec la clé publique correspondante. Il expire après **1 heure** (configurable dans `config/packages/lexik_jwt_authentication.yaml`).

### Gestion des rôles

| Rôle | Hérite de | Capacités |
|---|---|---|
| `ROLE_USER` | - | Créer et consulter ses demandes |
| `ROLE_PRESTATAIRE` | `ROLE_USER` | Créer et gérer ses services, créer des mises en relation, changer le statut des demandes |
| `ROLE_ADMIN` | `ROLE_USER` | Accès complet à toutes les ressources |

Le rôle est attribué à l'inscription via le champ `role` du body (`ROLE_USER` par défaut, `ROLE_PRESTATAIRE` possible).

### Protection des routes

**Niveau firewall** (`config/packages/security.yaml`) :
```yaml
access_control:
    - { path: ^/api/login,    roles: PUBLIC_ACCESS }
    - { path: ^/api/register, roles: PUBLIC_ACCESS }
    - { path: ^/api,          roles: IS_AUTHENTICATED_FULLY }
```

Toute route `/api/*` autre que `login` et `register` exige un token JWT valide.

**Niveau controller** (`#[IsGranted]`) :
- `POST /api/services` - `ROLE_PRESTATAIRE` obligatoire
- `PATCH /api/demandes/{id}/statut` - `ROLE_PRESTATAIRE` obligatoire
- `POST/GET /api/mises-en-relation` - `ROLE_PRESTATAIRE` obligatoire

**Niveau propriété** (vérification manuelle dans le controller) :
- Un prestataire ne peut modifier/supprimer que **ses propres** services
- Un utilisateur ne peut voir/supprimer que **ses propres** demandes
- Un prestataire ne peut créer une MER que pour **ses propres** services

### Sécurité des mots de passe

Les mots de passe sont hashés avec l'algorithme **auto** de Symfony (bcrypt par défaut). Le repository `UserRepository` implémente `PasswordUpgraderInterface` pour la migration automatique des hashs si l'algorithme évolue.

---

## 5. Installation et lancement

### Prérequis

- PHP 8.2+
- Composer
- Symfony CLI
- MySQL 8 (ou Docker)

### Installation

```bash
# Cloner le projet
git clone <url-du-repo>
cd marketplacephp

# Installer les dépendances
composer install

# Configurer l'environnement
cp .env .env.local
# Éditer .env.local : renseigner DATABASE_URL avec vos credentials MySQL

# Générer les clés JWT (sur Windows, définir OPENSSL_CONF si nécessaire)
php bin/console lexik:jwt:generate-keypair

# Créer la base de données et appliquer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# Charger les données de test
php bin/console doctrine:fixtures:load --no-interaction
```

### Lancer le serveur de développement

```bash
symfony server:start --no-tls
# ou
php -S localhost:8000 -t public
```

### Démarrer MySQL avec Docker

```bash
docker run -d \
  --name marketplace-mysql \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
  -e MYSQL_DATABASE=marketplacephp \
  -p 3306:3306 \
  mysql:8.0

# À chaque session suivante :
docker start marketplace-mysql
```

---

## 6. Référence des endpoints

### Authentification

| Méthode | URL | Accès | Body |
|---|---|---|---|
| POST | `/api/register` | Public | `{"email","password","role?"}` |
| POST | `/api/login` | Public | `{"email","password"}` |

### Services

| Méthode | URL | Accès | Description |
|---|---|---|---|
| GET | `/api/services` | Authentifié | Liste les services actifs |
| POST | `/api/services` | ROLE_PRESTATAIRE | Crée un service |
| GET | `/api/services/{id}` | Authentifié | Détail d'un service |
| PUT | `/api/services/{id}` | ROLE_PRESTATAIRE (propriétaire) | Modifie un service |
| DELETE | `/api/services/{id}` | ROLE_PRESTATAIRE (propriétaire) ou ROLE_ADMIN | Supprime un service |

### Demandes

| Méthode | URL | Accès | Description |
|---|---|---|---|
| GET | `/api/demandes` | Authentifié | Ses demandes (ou toutes si ROLE_ADMIN) |
| POST | `/api/demandes` | Authentifié | Crée une demande |
| GET | `/api/demandes/{id}` | Propriétaire ou ROLE_ADMIN | Détail d'une demande |
| PATCH | `/api/demandes/{id}/statut` | ROLE_PRESTATAIRE | Change le statut |
| DELETE | `/api/demandes/{id}` | Propriétaire ou ROLE_ADMIN | Supprime une demande |

### Mises en relation

| Méthode | URL | Accès | Description |
|---|---|---|---|
| GET | `/api/mises-en-relation` | ROLE_PRESTATAIRE | Ses MER (ou toutes si ROLE_ADMIN) |
| POST | `/api/mises-en-relation` | ROLE_PRESTATAIRE | Crée une mise en relation |
| GET | `/api/mises-en-relation/{id}` | Parties concernées ou ROLE_ADMIN | Détail |
| PATCH | `/api/mises-en-relation/{id}/statut` | ROLE_PRESTATAIRE (propriétaire) | Change le statut |

---

## 7. Guide de test rapide

### Étape 1 - Obtenir un token

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alice.dupont@prestataire.fr","password":"password"}'
```

Réponse :
```json
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." }
```

### Étape 2 - Utiliser le token

```bash
curl http://localhost:8000/api/services \
  -H "Authorization: Bearer <token>"
```

### Étape 3 - Créer un service (prestataire)

```bash
curl -X POST http://localhost:8000/api/services \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"titre":"Mon service","description":"Description","prix":500}'
```

### Étape 4 - Créer une demande (utilisateur)

Se connecter avec `user1@marketplace.fr` / `password`, puis :

```bash
curl -X POST http://localhost:8000/api/demandes \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"message":"Je cherche un prestataire pour mon projet."}'
```

### Étape 5 - Créer une mise en relation

Se connecter en prestataire, puis :

```bash
curl -X POST http://localhost:8000/api/mises-en-relation \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"service_id":1,"demande_id":1}'
```

### Comptes de test disponibles (après fixtures)

| Email | Mot de passe | Rôle |
|---|---|---|
| `admin@marketplace.fr` | `password` | ROLE_ADMIN |
| `alice.dupont@prestataire.fr` | `password` | ROLE_PRESTATAIRE |
| `bob.martin@prestataire.fr` | `password` | ROLE_PRESTATAIRE |
| `claire.moreau@prestataire.fr` | `password` | ROLE_PRESTATAIRE |
| `user1@marketplace.fr` | `password` | ROLE_USER |
| `user2@marketplace.fr` | `password` | ROLE_USER |
| `user3@marketplace.fr` | `password` | ROLE_USER |
| `user4@marketplace.fr` | `password` | ROLE_USER |
| `user5@marketplace.fr` | `password` | ROLE_USER |

---

