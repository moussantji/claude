# Torrents → Lecteurs — Guide de déploiement (100 % gratuit)

Ce guide explique comment déployer la fonctionnalité **Torrents** : l'admin
soumet un **magnet / lien `.torrent`**, le système le récupère puis met chaque
épisode en ligne sur un lecteur (Doodstream, Vidmoly, Streamtape, Mp4upload) et
crée automatiquement les liens d'épisodes.

## Comment ça marche

Deux fournisseurs interchangeables (`TORRENT_PROVIDER` dans `.env`) :

| Fournisseur | Coût | Qui télécharge le torrent ? |
|-------------|------|------------------------------|
| **`aria2`** (défaut) | 🆓 gratuit | **Ton serveur** (aria2c), puis upload du fichier au lecteur |
| **`realdebrid`** | 💰 ~3 €/mois | Real-Debrid ; le lecteur récupère le lien (rien sur ton serveur) |

```
Magnet/.torrent
  → [aria2]      ton serveur télécharge  → upload fichier au lecteur
  → [realdebrid] Real-Debrid résout      → le lecteur récupère le lien HTTP
  → episode_link créé pour chaque épisode
```

> **Important (aria2)** : un torrent n'est **pas** une URL HTTP. Quelqu'un doit
> le télécharger via BitTorrent. En mode gratuit, c'est ton serveur. Il faut
> donc une machine capable de faire tourner `aria2c` (bande passante + disque
> temporaire). Le fichier est **supprimé** dès qu'il est uploadé sur le lecteur.

---

## Option A — Serveur gratuit à vie : Oracle Cloud Free Tier

Si tu n'as pas déjà de serveur, l'**Oracle Cloud Free Tier** offre une VM
gratuite « à vie » avec un gros quota de bande passante mensuel — idéale pour ce
worker. Les étapes ci-dessous valent aussi pour n'importe quel VPS/PC Linux
(Debian/Ubuntu).

### 1. Créer la VM

1. Crée un compte sur [Oracle Cloud](https://www.oracle.com/cloud/free/).
2. Crée une instance **Always Free** — choisis impérativement une forme
   **ARM Ampere A1 (`VM.Standard.A1.Flex`)** avec une image **Ubuntu**.
3. Ouvre l'accès SSH, connecte-toi :
   ```bash
   ssh ubuntu@<IP_DE_TA_VM>
   ```

> **⚠ Choix de la forme — important**
> - **Prends l'ARM Ampere A1** (jusqu'à 2 OCPU / 12 Go dans l'offre gratuite
>   actuelle) : c'est amplement suffisant pour aria2c + le worker.
> - **Évite les micro-instances AMD** (`VM.Standard.E2.1.Micro`, 1/8 OCPU /
>   1 Go RAM) : trop faibles.
>
> **⚠ Disponibilité ARM**
> Dans les régions populaires, Oracle renvoie souvent *« Out of host capacity »*
> à la création de l'instance ARM. Solutions : réessaye régulièrement, ou choisis
> une **région/domaine de disponibilité** moins saturé au moment de créer ton
> compte (la région ne peut pas être changée ensuite).
>
> **Débit & quota réseau**
> La bande passante sortante offre un large quota gratuit mensuel (généreux pour
> cet usage). Le débit peut varier selon la région ; ce worker étant une tâche
> de fond, ce n'est pas bloquant. Le vrai facteur limitant reste le nombre de
> seeders du torrent et la vitesse d'upload acceptée par le lecteur.

### 2. Installer les dépendances

```bash
sudo apt update
sudo apt install -y aria2 php-cli php-curl php-mbstring php-xml unzip git
# (PHP 8.2+ requis pour Laravel 12 — utilise le PPA ondrej/php si besoin)
```

> Le worker n'a besoin **que** de `php artisan` + `aria2c`. Tu peux faire tourner
> la base de données (MySQL) sur cette même VM ou sur une autre machine : le
> worker et le site web doivent juste partager la **même base de données**.

---

## Option B — Ton propre VPS / PC Linux

Mêmes commandes que l'étape 2 ci-dessus. `aria2c` est dispo sur tous les gestionnaires :

```bash
# Debian/Ubuntu
sudo apt install -y aria2
# Arch
sudo pacman -S aria2
# macOS
brew install aria2
```

---

## Lancer aria2c en démon RPC (recommandé)

Le mode **RPC** est non bloquant : aria2c reste actif et l'app le pilote via une
API. C'est bien plus fiable que le mode CLI (qui bloque le process pendant tout
le téléchargement).

### 1. Créer le dossier de téléchargement

```bash
mkdir -p /home/ubuntu/torrents
```

### 2. Créer un service systemd

Crée `/etc/systemd/system/aria2.service` :

```ini
[Unit]
Description=aria2c RPC daemon
After=network.target

[Service]
User=ubuntu
ExecStart=/usr/bin/aria2c \
  --enable-rpc \
  --rpc-listen-all=false \
  --rpc-listen-port=6800 \
  --rpc-secret=CHANGE_MOI_TOKEN_SECRET \
  --dir=/home/ubuntu/torrents \
  --seed-time=0 \
  --bt-stop-timeout=0 \
  --file-allocation=none \
  --max-connection-per-server=8 \
  --split=8
Restart=always

[Install]
WantedBy=multi-user.target
```

Puis active-le :

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now aria2
sudo systemctl status aria2      # doit être "active (running)"
```

> Remplace `CHANGE_MOI_TOKEN_SECRET` par un secret de ton choix — tu le
> reporteras dans `ARIA2_RPC_SECRET`.

---

## Configurer l'application (`.env`)

Dans le `.env` de la partie **frontend** (Laravel) :

```env
# Fournisseur gratuit par défaut
TORRENT_PROVIDER=aria2

# Combien de fichiers traités par passage du worker
TORRENT_FILES_PER_RUN=10

# aria2 RPC (doit correspondre au service systemd ci-dessus)
ARIA2_RPC_URL=http://localhost:6800/jsonrpc
ARIA2_RPC_SECRET=CHANGE_MOI_TOKEN_SECRET
ARIA2_BINARY=aria2c
TORRENT_DOWNLOAD_DIR=/home/ubuntu/torrents
TORRENT_DOWNLOAD_TIMEOUT=3600

# Au moins UN lecteur cible configuré (exemple Doodstream)
TRANSFER_DOODSTREAM_API_KEY=ta_cle_api_doodstream
```

> Les identifiants des lecteurs (`TRANSFER_*`) sont les mêmes que ceux utilisés
> par les commandes `episodes:*`. Vérifie-les avec
> `php artisan episodes:check-connections`.

---

## Quotas d'upload quotidiens par lecteur (automatique)

Certains lecteurs limitent le **nombre d'uploads par jour**. Le système respecte
ce quota **automatiquement** : quand le cap d'un lecteur est atteint, les
épisodes restants **restent en file et repartent tout seuls le lendemain**
(le compteur se remet à zéro à minuit).

```env
# Cap par défaut appliqué à tout lecteur sans limite propre
TORRENT_DEFAULT_DAILY_LIMIT=200

# Limite spécifique par lecteur (prioritaire sur le défaut)
TRANSFER_DOODSTREAM_DAILY_LIMIT=500
TRANSFER_VIDMOLY_DAILY_LIMIT=200
TRANSFER_STREAMTAPE_DAILY_LIMIT=200
TRANSFER_MP4UPLOAD_DAILY_LIMIT=200
```

- Le compteur est **partagé** avec `episodes:remote` : même lecteur = même
  budget quotidien (pas de double comptage entre les deux fonctionnalités).
- Aucune action manuelle : le worker (`torrents:process`, toutes les 2 min)
  reprend automatiquement les épisodes en attente dès que le budget se libère.
- L'usage du jour (`utilisés / limite` par lecteur) est visible dans
  l'interface admin → **🧲 Torrents** (rouge = plein).

---

## Lancer le worker

Le worker est la commande `torrents:process`, déjà programmée toutes les 2 min
dans le scheduler. Il suffit de faire tourner le scheduler Laravel :

```bash
# Sur la machine qui a aria2c + accès à la base de données
php artisan schedule:work
```

Pour un déploiement permanent, mets le scheduler en cron (une ligne) :

```bash
crontab -e
# ajoute :
* * * * * cd /chemin/vers/frontend && php artisan schedule:run >> /dev/null 2>&1
```

Ou lance le worker manuellement / en boucle :

```bash
php artisan torrents:process              # un passage
php artisan torrents:process --status     # état de la file
php artisan torrents:process --limit=10   # plus de batches par passage
php artisan torrents:process --retry-failed
```

---

## Utilisation (interface admin)

1. Connecte-toi sur **`/admin`** avec un compte administrateur
   (`php artisan user:make-admin ton@email.com`).
2. Clique sur **🧲 Torrents**.
3. Renseigne :
   - **Lecteur cible** (Doodstream, Vidmoly…)
   - **Saison ID** (pack de saison → mapping auto par n° d'épisode) **ou**
     **Épisode ID** (épisode unique)
   - Le **magnet / lien `.torrent`** (ou colle directement des liens HTTP)
4. **Envoyer**. Suis l'avancement : `Téléchargement` (aria2 %) → `Envoi lecteur`
   → `Terminé`. Le détail par épisode permet de **remapper** manuellement un
   fichier non détecté.

---

## Dépannage

| Symptôme | Cause probable | Solution |
|----------|----------------|----------|
| « aria2c indisponible » à la soumission | démon RPC non lancé / mauvais port | `systemctl status aria2`, vérifie `ARIA2_RPC_URL` et `ARIA2_RPC_SECRET` |
| Le batch reste en `Téléchargement` | peu de seeds, ou torrent mort | patiente ; sinon `--retry-failed` avec un autre torrent |
| Fichiers en `non mappé` (⊘) | n° d'épisode non détecté | remappe manuellement dans l'onglet **Épisodes** du batch |
| Échec à l'upload lecteur | clé API lecteur absente/invalide | renseigne `TRANSFER_<HOST>_*`, teste `episodes:check-connections` |
| Disque plein | téléchargements non nettoyés | les fichiers sont supprimés après upload ; vérifie l'espace de `TORRENT_DOWNLOAD_DIR` |

---

## Passer à Real-Debrid plus tard

Aucun changement de code : mets à jour `.env` puis relance le worker.

```env
TORRENT_PROVIDER=realdebrid
REALDEBRID_API_TOKEN=ton_token   # https://real-debrid.com/apitoken
```

Avec Real-Debrid, ton serveur ne télécharge plus rien (le lecteur récupère le
lien HTTP fourni par Real-Debrid) — utile si la bande passante de ta VM est
limitée.
