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
