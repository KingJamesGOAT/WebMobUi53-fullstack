# Système de Sondage Fullstack - TP

Application web fullstack permettant de créer, gérer et partager des sondages.
Développée dans le cadre du cours de développement web à la HEIG-VD.

---

## Prérequis et Installation

**Prérequis :** PHP >= 8.2, Composer, Node.js >= 18, npm, SQLite (ou MySQL/PostgreSQL)

```bash
# 1. Installer les dépendances PHP et JavaScript
composer install
npm install

# 2. Configurer l'environnement
cp .env.example .env
php artisan key:generate

# 3. Créer la base de données et exécuter les migrations avec les données de test
php artisan migrate:fresh --seed

# 4. Compiler les assets frontend
npm run build

# 5. Démarrer le serveur de développement
composer run dev
```

L'application est accessible à l'adresse : **http://localhost:8000**

---

## Choix Techniques et Architecture

### Backend — Laravel comme API JSON pure

Laravel est utilisé exclusivement pour exposer des **endpoints JSON versionnés** sous le préfixe `/api/v1/`.
Il ne génère aucun HTML pour les fonctionnalités de sondage. Toute la logique métier (validation, authentification, vérification d'expiration, protection des brouillons) est traitée côté serveur et retournée sous forme de réponses JSON avec des codes HTTP sémantiques.

L'authentification des utilisateurs (sessions Laravel) et celle de l'API (Sanctum) coexistent : les routes de gestion des sondages sont protégées par `auth:sanctum`, tandis que la route de vote et de lecture publique sont accessibles sans authentification.

### Frontend — Vue.js 3 avec la Composition API

Deux applications Vue.js distinctes sont intégrées dans des vues Blade :
- **`poll-dashboard`** : tableau de bord pour la gestion (CRUD) des sondages.
- **`poll-vote`** : page de vote et visualisation des résultats, accessible via le token de partage.

La logique est organisée selon le pattern **Composable/Store/Component** :
- `useFetchApi.js` : couche d'abstraction sur `fetch` avec gestion du timeout, des erreurs et des headers.
- `usePolling.js` : composable générique pour le rafraîchissement automatique des données.
- `usePollStore.js` : état global réactif partagé entre les composants du dashboard.

### Style — Tailwind CSS

Le design est entièrement géré par Tailwind CSS via des classes utilitaires, sans fichier CSS personnalisé. L'interface est responsive et supporte le mode sombre (`dark:`).

### Sécurité du partage — Token unique

Chaque sondage possède un `secret_token` de 10 caractères aléatoires (unique en base de données), utilisé pour construire l'URL de partage `/vote/{token}`. Ce mécanisme permet de partager un sondage sans exposer son identifiant numérique interne.

---

## Fonctionnalités implémentées

### Gestion des sondages (Dashboard)
- **CRUD complet** : création, modification et suppression d'un sondage depuis le frontend Vue.js.
- **Gestion des options** : ajout dynamique d'options de réponse dans le formulaire, modification et suppression lors de l'édition.
- **Mode brouillon** : un sondage peut être sauvegardé en brouillon (`is_draft = true`) et démarré plus tard via un bouton dédié.
- **Paramètres configurables** :
  - Choix unique ou choix multiples (`allow_multiple_choices`)
  - Résultats publics ou privés (`results_public`)
  - Durée de disponibilité en heures (`duration`) → calcul automatique de `ends_at`
- **Lien de partage** : affichage direct du lien `/vote/{token}` depuis le tableau de bord.

### Page de vote publique
- **Accès par token** : la page de vote est accessible à tout utilisateur via l'URL contenant le token.
- **Vote protégé** : seuls les utilisateurs authentifiés peuvent voter. L'unicité du vote est garantie côté frontend (`localStorage`) et côté API (vérification en base de données).
- **Expiration** : le vote est bloqué (frontend + API) une fois la date de fin dépassée, avec un message clair affiché.
- **Résultats en temps réel** : les résultats sont rafraîchis automatiquement toutes les 3 secondes via polling.
- **Visualisation graphique** : barres de progression animées affichant le pourcentage de votes par option.
- **Confidentialité** : les statistiques de vote ne sont visibles que si le sondage est public **ou** si l'utilisateur est le propriétaire. L'API ne retourne pas les `votes_count` dans le JSON pour les sondages privés.

---

## Structure des routes API

| Méthode | URL | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/polls` | ✅ Requis | Liste les sondages de l'utilisateur connecté |
| `POST` | `/api/v1/polls` | ✅ Requis | Crée un nouveau sondage |
| `PUT` | `/api/v1/polls/{id}` | ✅ Requis | Modifie un sondage existant |
| `DELETE` | `/api/v1/polls/{id}` | ✅ Requis | Supprime un sondage |
| `GET` | `/api/v1/polls/{token}` | ❌ Public | Affiche un sondage via son token |
| `POST` | `/api/v1/polls/{token}/vote` | ❌ Public* | Soumet un vote (*vote authentifié requis) |
