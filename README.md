# Manden Baoubab

Site web + CRM pour **Manden Baoubab**, une entreprise numerique malienne (fictive) basee a Bamako.
Le projet regroupe en une seule application React :

- un **site vitrine** presentant les services (creation de sites, applications web, logiciels PC) ;
- trois **plateformes maison** : streaming (MandenStream), e-commerce (MandenShop) et IPTV (MandenTV) ;
- un **blog** (liste + article, alimente par l'espace admin) ;
- un **espace d'administration protege** accessible uniquement aux administrateurs ;
- un **CRM complet** pour tout gerer : prospects, clients, projets, produits/offres,
  commandes & abonnements, articles de blog, messages et parametres.

## Stack technique

- **React 18** + **React Router 6**
- **Vite** pour le dev et le build
- **CSS maison** (design system inspire du drapeau malien : vert / or / rouge) — aucune dependance UI externe
- **Persistance des donnees** cote navigateur via `localStorage` (aucun backend requis)

## Demarrage

```bash
npm install
npm run dev
```

Puis ouvrez l'URL affichee (par defaut http://localhost:5173).

Pour generer la version de production :

```bash
npm run build
npm run preview
```

## Acces a l'espace administration

L'espace admin n'est **pas** lie dans le menu public. On y accede :

- via l'URL directe **`/admin`** (redirige vers la connexion si non authentifie) ;
- ou par le petit lien discret « Espace administration » en bas de page (footer).

### Comptes de demonstration

| Role             | Email                     | Mot de passe |
|------------------|---------------------------|--------------|
| Administrateur   | `admin@mandenbaoubab.com`    | `admin123`   |
| Chef de projet   | `fatou@mandenbaoubab.com`    | `manden2025`   |

## Fonctionnalites du CRM

- **Tableau de bord** : KPIs (CA, prospects, clients, abonnes), graphique des revenus par
  plateforme, pipeline commercial et activite recente.
- **Prospects** : gestion complete (creation, edition, suppression), filtres par statut,
  recherche, pipeline (Nouveau → Contacte → Qualifie → Gagne / Perdu).
- **Clients** : fiche client, secteur, valeur du portefeuille.
- **Projets** : suivi d'avancement, budget, echeance, responsable.
- **Produits & offres** : offres streaming / IPTV et produits e-commerce.
- **Commandes & abonnements** : suivi et changement de statut en un clic.
- **Blog** : redaction, publication / depublication, categories.
- **Messages** : les demandes du formulaire de contact arrivent ici et peuvent etre
  converties en prospects.
- **Parametres** : informations de l'entreprise, gestion des utilisateurs admin,
  reinitialisation des donnees de demonstration.

## Structure du projet

```
src/
├── main.jsx                 # Point d'entree
├── App.jsx                  # Routes (public / auth / admin)
├── index.css                # Design system complet
├── context/AuthContext.jsx  # Authentification admin (localStorage)
├── data/
│   ├── store.js             # Mini "base de donnees" + hook useCollection
│   ├── seed.js              # Donnees de demonstration
│   └── format.js            # Formatage FCFA / dates
├── components/              # Navbar, Footer, layouts, Modal, Sidebar...
└── pages/
    ├── public/              # Accueil, Services, Streaming, E-commerce, IPTV, Blog, Contact
    ├── auth/                # Connexion admin
    └── admin/               # Dashboard + toutes les pages du CRM
```

## Notes

- Les donnees sont stockees dans le navigateur (`localStorage`). Pour repartir des donnees
  d'origine, utilisez le bouton **Reinitialiser** dans _Parametres_.
- Les montants sont en **FCFA (XOF)**.
- Ceci est une base front-end prete a etre branchee sur une vraie API / base de donnees
  (remplacer les fonctions de `src/data/store.js` par des appels reseau).


---

## Nouveautes

- **Barre d'onglets mobile** : sur telephone, la navigation principale s'affiche comme une
  barre d'onglets fixee en bas de l'ecran (facon application), en plus du menu du haut.
- **Animations au scroll** : les sections apparaissent en fondu/glissement lorsqu'elles
  entrent dans la zone visible (respecte `prefers-reduced-motion`).
- **Images de fond** : motifs decoratifs inspires du bogolan malien (`public/img/*.svg`)
  sur le hero, les sections claires et la banniere d'appel a l'action.
- **Panier e-commerce** : ajout au panier depuis la page E-commerce, tiroir coulissant
  (quantites, suppression, total) et tunnel de commande. La commande validee est creee
  dans le CRM (collection `orders`, ou `POST /api/orders` en mode API).
- **Gestion des administrateurs via l'API** : la page _Parametres_ cree / supprime les
  comptes admin via `GET|POST|PUT|DELETE /api/users` (en mode API).

## Deux modes de fonctionnement

Le front detecte automatiquement le mode selon la variable `VITE_API_URL` (fichier `.env`) :

| Mode | Condition | Donnees | Authentification |
|------|-----------|---------|------------------|
| **Demo** (defaut) | `VITE_API_URL` vide | `localStorage` (donnees de demo) | verifiees en local |
| **API** | `VITE_API_URL` renseigne | API Laravel (base de donnees) | Laravel **Sanctum** (SPA par cookie) |

Pour activer le mode API, copiez `.env.example` en `.env` et renseignez l'URL du back-end :

```bash
cp .env.example .env
# puis dans .env :
VITE_API_URL=http://localhost:8000
```

En mode API, le formulaire de contact envoie les messages a l'API, la connexion admin
utilise Sanctum, et toutes les pages du CRM (prospects, clients, projets, produits,
commandes, blog, messages, parametres) lisent/ecrivent via le back-end.

---

## Back-end : Laravel 12 + Sanctum (`backend/`)

Une API REST complete est fournie dans le dossier `backend/`.

> **Note version** : le back-end cible **Laravel 12** (pleinement supporte). Laravel 11 a
> atteint la fin de son support de securite le 12 mars 2026 : Composer bloque desormais son
> installation via les *security advisories*. Laravel 12 conserve la meme structure de projet,
> le code est donc inchange.

### Installation

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Base SQLite par defaut :
touch database/database.sqlite
php artisan migrate --seed

php artisan serve   # http://localhost:8000
```

> Pour MySQL, adaptez les variables `DB_*` dans `backend/.env` avant `php artisan migrate --seed`.

### Authentification (Sanctum SPA, par cookie)

L'authentification se fait **par cookie de session** (mode SPA de Sanctum), et non par
jeton. Le front :

1. appelle `GET /sanctum/csrf-cookie` pour recevoir le cookie `XSRF-TOKEN` ;
2. renvoie ce jeton dans l'en-tete `X-XSRF-TOKEN` sur les requetes mutantes ;
3. envoie toutes les requetes avec `credentials: 'include'` (cookie de session).

Routes :

- `POST /api/login` `{ email, password }` -> ouvre la session, renvoie `{ user }`
- `GET  /api/user` (session requise) -> utilisateur courant
- `POST /api/logout` (session requise) -> ferme la session

> **CSRF token mismatch ?** C'est le symptome d'un front qui n'a pas recupere le cookie
> CSRF avant de poster. Le client (`src/api/client.js`) le gere automatiquement. Cote
> back-end, verifiez que `SANCTUM_STATEFUL_DOMAINS` contient l'origine du front
> (`localhost:5173`), que `FRONTEND_URL` est correct et que `config/cors.php` a
> `supports_credentials => true`.

### Ressources CRM (session requise, sauf creation publique)

`GET|POST /api/{ressource}` et `GET|PUT|DELETE /api/{ressource}/{id}` pour :
`leads`, `clients`, `projects`, `products`, `posts`, `users`.

- `POST /api/messages` (formulaire de contact) et `POST /api/orders` (checkout) sont
  **publics** ; la lecture/modification/suppression de ces ressources reste protegee.
- `GET|PUT /api/settings` : parametres de l'entreprise (ligne unique).
- `GET /api/stats` : indicateurs du tableau de bord.
- `GET|POST|PUT|DELETE /api/users` : gestion des administrateurs (email unique, mot de
  passe hache ; impossible de supprimer le dernier admin ou son propre compte).

### Comptes seedes

- `admin@mandenbaoubab.com` / `admin123`
- `fatou@mandenbaoubab.com` / `manden2025`

### CORS

`backend/config/cors.php` autorise l'origine du front (`FRONTEND_URL`, par defaut
`http://localhost:5173`). Adaptez-la pour la production.

### Limitations connues

- Front et back doivent partager le meme domaine racine pour l'auth par cookie. En local,
  `localhost:5173` (front) et `localhost:8000` (back) fonctionnent car meme site. En
  production, utilisez des sous-domaines du meme domaine (ex : `app.exemple.ml` et
  `api.exemple.ml`) et adaptez `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`.
- Le back-end n'a pas pu etre execute dans l'environnement de developpement (pas d'acces
  reseau a Packagist) : la syntaxe PHP a ete verifiee avec `php -l` mais lancez
  `composer install` + `php artisan migrate --seed` en local pour confirmer.
