# Faso Digital

Site web + CRM pour **Faso Digital**, une entreprise numerique malienne (fictive) basee a Bamako.
Le projet regroupe en une seule application React :

- un **site vitrine** presentant les services (creation de sites, applications web, logiciels PC) ;
- trois **plateformes maison** : streaming (FasoStream), e-commerce (FasoShop) et IPTV (FasoTV) ;
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
| Administrateur   | `admin@fasodigital.ml`    | `admin123`   |
| Chef de projet   | `fatou@fasodigital.ml`    | `faso2025`   |

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

## Deux modes de fonctionnement

Le front detecte automatiquement le mode selon la variable `VITE_API_URL` (fichier `.env`) :

| Mode | Condition | Donnees | Authentification |
|------|-----------|---------|------------------|
| **Demo** (defaut) | `VITE_API_URL` vide | `localStorage` (donnees de demo) | verifiees en local |
| **API** | `VITE_API_URL` renseigne | API Laravel (base de donnees) | Laravel **Sanctum** (jeton Bearer) |

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

### Authentification (Sanctum, par jeton)

- `POST /api/login` `{ email, password }` -> `{ token, user }`
- `GET  /api/user` (jeton requis) -> utilisateur courant
- `POST /api/logout` (jeton requis) -> revoque le jeton

Le front stocke le jeton et l'envoie dans l'en-tete `Authorization: Bearer <token>`.

### Ressources CRM (jeton requis, sauf creation de message)

`GET|POST /api/{ressource}` et `GET|PUT|DELETE /api/{ressource}/{id}` pour :
`leads`, `clients`, `projects`, `products`, `orders`, `posts`.

- `POST /api/messages` est **public** (formulaire de contact) ; le reste des routes
  `messages` est protege.
- `GET|PUT /api/settings` : parametres de l'entreprise (ligne unique).
- `GET /api/stats` : indicateurs du tableau de bord.

### Comptes seedes

- `admin@fasodigital.ml` / `admin123`
- `fatou@fasodigital.ml` / `faso2025`

### CORS

`backend/config/cors.php` autorise l'origine du front (`FRONTEND_URL`, par defaut
`http://localhost:5173`). Adaptez-la pour la production.

### Limitations connues

- La gestion des utilisateurs admin (page Parametres) reste locale : ajoutez un
  `UserController` + routes si vous souhaitez la gerer via l'API.
- Le back-end n'a pas pu etre execute dans l'environnement de developpement (pas d'acces
  reseau a Packagist) : la syntaxe PHP a ete verifiee avec `php -l` mais lancez
  `composer install` + `php artisan migrate --seed` en local pour confirmer.
