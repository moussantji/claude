# Stream — app mobile (React Native / Expo)

Maquette fonctionnelle de [stream.mandenbaoubab.com](http://stream.mandenbaoubab.com) :
palette sombre + rouge (`#E50914`), Ionicons, onglets Accueil / Recherche / Téléchargements / Profil,
stack React Navigation (`Home → Detail → Detail reco → Player`), deep link `mandenstream://`.

## Lecture & téléchargement — choix produit

**Un seul téléchargement écrit le MP4 local ; le player ne lit QUE les octets déjà sur disque.**
Jamais de second flux HTTP distant sur mobile (le web est le seul fallback direct).

### Politique « quand lancer la lecture » (sonde moov)

Un MP4 classique a son index (`moov`) **à la fin** : lire un préfixe partiel échoue.
Avant d'ouvrir le player, `src/mp4Probe.js` parcourt les boîtes MP4 du fichier **partiel** :

- `moov` complet **avant** `mdat` (*faststart*) → lecture progressive immédiate via le
  serveur Range local ;
- `mdat` avant `moov` → la lecture attend le fichier **complet** (message honnête dans le
  player, pas de spinner mensonger) ;
- plancher `MIN_PLAY_BYTES` (256 Ko) dans tous les cas.

### Serveur Range local (`src/localRangeServer.js`, port 18765)

- chunks lus en base64 depuis expo-file-system et écrits via `socket.write(b64, "base64")`
  → aucun décodage Buffer/Uint8Array côté JS ;
- 1 requête = 1 connexion (`Connection: close`) ;
- attentes bornées : un seek non encore téléchargé renvoie `503 + Retry-After` après
  ~8 s max ; en lecture séquentielle la réponse est bornée aux octets disponibles
  (jamais de 503 tant que ça avance) ;
- taille annoncée (`Content-Range`) : **vraie totale si fichier complet**, sinon l'octet
  déjà écrit (fichier valide qui grandit). C'est auto-cohérent parce que la lecture
  n'est ouverte qu'après preuve de `moov` complet — la durée vient du moov.

### Fin de téléchargement

Quand `status === "done"`, le player bascule de l'URL Range vers `file://…`
**en conservant la position** (position capturée avant unload, réappliquée au load).

## Téléchargements

- persistance `documentDirectory/downloads/jobs.json` + re-scan du dossier au boot
  (fichiers orphelins récupérés, titre complété via `/detail`) ;
- pause / reprise (resumeData expo-file-system). Après kill de l'app : reprise via
  resumeData persisté ; si l'URL signée a expiré → redémarrage propre du fichier ;
- « Retirer » = stop + suppression disque + purge de la liste ;
- IDs canoniques `subjectId-S-E-quality` : accueil et fiche pointent le même job,
  pas de double téléchargement.

## Auth

JWT persisté sur disque (`.auth_token`), rechargé au démarrage, logout = purge.
`/history` sans session → section masquée proprement (l'API répond 500
« Route [login] » sans token : traité comme non connecté).

## Build natif requis

`react-native-tcp-socket` ne tourne pas dans Expo Go. Nécessite un dev client :

```bash
cd mobile
npm install
npx expo prebuild          # génère android/ ios/
npx expo run:android       # ou run:ios
```

`app.json` : `scheme: mandenstream`, `android.usesCleartextTraffic: true`
(le serveur local est en `http://127.0.0.1`, bloqué par défaut sur Android 9+).

## Choix techniques assumés

- **expo-av gardé** malgré la dépréciation : SDK 52 stable, migration expo-video =
  réécriture complète du player (source swapping, position sync, fullscreen) à risque
  élevé pour ce correctif. À planifier séparément avec la montée SDK 54.
- Pas d'inscription / reset password : l'API n'expose que `/auth/login`.
- Espace disque / Wi-Fi only : non implémentés (pas d'endpoint de quota côté API) ;
  la vérification de taille finale protège contre les fichiers tronqués.

## API branchée

`GET /home`, `/trending?page`, `/category`, `/detail?subjectId`, `/downloads?subjectId&season&episode`,
`/search?q`, `/auth/me`, `POST /auth/login`, `GET /history` (auth).
Réponses enveloppées `{ "data": … }` (vérifié sur l'API réelle).
