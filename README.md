# Système de Sondage Fullstack — TP

Application web fullstack permettant de créer, gérer et partager des sondages.  
Développée par Steve Benjamin dans le cadre du cours d'Ingénierie des Médias à la HEIG-VD.

---

## Prérequis et Installation

**Prérequis :** PHP >= 8.2, Composer, Node.js >= 18, npm, SQLite (ou MySQL / PostgreSQL)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh
php artisan db:seed
npm run build
composer run dev
```

L'application est accessible à l'adresse : `http://localhost:8000`

---

## Choix Techniques et Architecture

### Backend : Laravel comme API JSON pure

Laravel est utilisé exclusivement pour exposer des **endpoints JSON versionnés** sous le préfixe `/api/v1/`.
Il ne génère aucun HTML pour les fonctionnalités de sondage. Toute la logique métier (validation, authentification, vérification d'expiration, protection des brouillons) est traitée côté serveur et retournée sous forme de réponses JSON avec des codes HTTP sémantiques.

L'authentification des utilisateurs (sessions Laravel) et celle de l'API (Sanctum) coexistent : les routes de gestion sont protégées par `auth:sanctum`, tandis que la lecture et le vote publics sont accessibles sans authentification.

### Frontend : Vue.js 3 avec la Composition API

Deux applications Vue.js distinctes sont intégrées dans des vues Blade :

| Application | Rôle |
|---|---|
| `poll-dashboard` | Tableau de bord pour la gestion (CRUD) des sondages |
| `poll-vote` | Page de vote et visualisation des résultats via le token de partage |

La logique est organisée selon le pattern **Composable / Store / Component** :

| Fichier | Responsabilité |
|---|---|
| `useFetchApi.js` | Couche d'abstraction sur `fetch` avec gestion du timeout, des erreurs et des headers |
| `usePolling.js` | Composable générique pour le rafraîchissement automatique des données |
| `usePollStore.js` | État global réactif partagé entre les composants du dashboard |

### Style : Tailwind CSS

Le design est entièrement géré par Tailwind CSS via des classes utilitaires, sans fichier CSS personnalisé. L'interface est responsive et supporte le mode sombre.

### Sécurité du partage : Token unique

Chaque sondage possède un `secret_token` de 10 caractères aléatoires (unique en base de données), utilisé pour construire l'URL de partage `/vote/{token}`. Ce mécanisme permet de partager un sondage sans exposer son identifiant numérique interne.

---

## Fonctionnalités implémentées

### Gestion des sondages (Dashboard)

| Fonctionnalité | Description |
|---|---|
| CRUD complet | Création, modification et suppression d'un sondage depuis le frontend Vue.js |
| Gestion des options | Ajout dynamique, modification et suppression des options lors de l'édition |
| Mode brouillon | Un sondage peut être sauvegardé en brouillon et démarré plus tard via un bouton dédié |
| Paramètres | Choix unique ou multiple, résultats publics ou privés, durée de disponibilité en heures |
| Lien de partage | Affichage direct du lien `/vote/{token}` depuis le tableau de bord |

### Page de vote publique

| Fonctionnalité | Description |
|---|---|
| Accès par token | La page de vote est accessible à tout utilisateur via l'URL contenant le token |
| Vote protégé | Seuls les utilisateurs authentifiés peuvent voter ; l'unicité est garantie côté frontend et API |
| Expiration | Le vote est bloqué une fois la date de fin dépassée, avec un message clair affiché |
| Résultats en temps réel | Les résultats sont rafraîchis automatiquement toutes les 3 secondes via polling |
| Visualisation graphique | Barres de progression animées affichant le pourcentage de votes par option |
| Confidentialité | Les statistiques ne sont visibles que si le sondage est public ou si l'utilisateur est le propriétaire |

---

## Structure des routes API

Les routes de gestion nécessitent une authentification via Sanctum. Les routes de lecture et de vote sont publiques.

| Méthode | Route | Acces | Description |
|---|---|---|---|
| GET | `/api/v1/polls` | Prive | Liste les sondages de l'utilisateur connecté |
| POST | `/api/v1/polls` | Prive | Crée un nouveau sondage |
| PUT | `/api/v1/polls/{id}` | Prive | Modifie un sondage existant |
| DELETE | `/api/v1/polls/{id}` | Prive | Supprime un sondage |
| GET | `/api/v1/polls/{token}` | Public | Affiche un sondage via son token |
| POST | `/api/v1/polls/{token}/vote` | Public | Soumet un vote (utilisateur authentifié requis) |
