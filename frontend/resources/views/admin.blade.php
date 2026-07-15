<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Admin — Anime-Sama</title>
    <style>
        :root { --bg:#0b0b12; --panel:#15151f; --border:#26263a; --text:#ececf5; --muted:#9a9ab0; --accent:#7c5cff; --danger:#ff4d6d; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--text); }
        a { color:var(--accent); }
        header { display:flex; align-items:center; gap:12px; padding:14px 20px; border-bottom:1px solid var(--border); position:sticky; top:0; background:rgba(11,11,18,.9); backdrop-filter:blur(8px); }
        header h1 { font-size:16px; margin:0; letter-spacing:.5px; }
        header .spacer { flex:1; }
        .btn { border:1px solid var(--border); background:var(--panel); color:var(--text); border-radius:8px; padding:8px 12px; cursor:pointer; font-size:14px; }
        .btn:hover { border-color:var(--accent); }
        .btn--primary { background:var(--accent); border-color:var(--accent); }
        .btn--danger { background:transparent; border-color:var(--danger); color:var(--danger); }
        .wrap { max-width:1100px; margin:0 auto; padding:20px; }
        .stats { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
        .stat { background:var(--panel); border:1px solid var(--border); border-radius:12px; padding:14px 18px; min-width:120px; }
        .stat b { display:block; font-size:22px; }
        .stat span { color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.4px; }
        input, textarea { width:100%; background:#0f0f18; border:1px solid var(--border); color:var(--text); border-radius:8px; padding:10px; font:inherit; }
        textarea { min-height:120px; resize:vertical; }
        label { display:block; font-size:12px; color:var(--muted); margin:12px 0 5px; text-transform:uppercase; letter-spacing:.4px; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px; border-bottom:1px solid var(--border); font-size:14px; }
        th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; }
        tr:hover td { background:rgba(124,92,255,.06); }
        .row-actions { display:flex; gap:8px; }
        .searchbar { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
        .searchbar input { flex:1; min-width:140px; }
        .pager { display:flex; gap:10px; align-items:center; margin-top:14px; }
        .center { min-height:70vh; display:grid; place-items:center; }
        .card { background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:24px; width:360px; max-width:92vw; }
        .modal { position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; place-items:center; padding:20px; z-index:50; }
        .modal.open { display:grid; }
        .modal .card { width:640px; max-height:88vh; overflow:auto; }
        .muted { color:var(--muted); }
        .err { color:var(--danger); font-size:13px; min-height:18px; }
        .thumb { width:40px; height:56px; object-fit:cover; border-radius:4px; background:#222; }
        .hide { display:none; }
        .toast { position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:var(--panel); border:1px solid var(--accent); padding:10px 16px; border-radius:10px; opacity:0; transition:opacity .3s; }
        .toast.show { opacity:1; }
        @media (max-width:600px) {
            header { flex-wrap:wrap; gap:8px; padding:10px 14px; }
            header h1 { font-size:13px; }
            .wrap { padding:12px; }
            .searchbar { gap:6px; }
            .searchbar input { font-size:13px; padding:8px; }
            .btn { padding:7px 10px; font-size:12px; }
            table { font-size:12px; }
            th, td { padding:6px 4px; }
            .thumb { width:30px; height:42px; }
            .stat { min-width:80px; padding:10px 12px; }
            .stat b { font-size:18px; }
            .modal .card { width:96vw; padding:16px; }
        }
        .genre-tag {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 5px 12px; border-radius: 999px;
            font-size: 12px; font-weight: 600;
            background: rgba(255,255,255,0.06); border: 1px solid var(--border);
            color: var(--muted); cursor: pointer; transition: all 0.15s;
        }
        .genre-tag:hover { border-color: var(--accent); color: var(--text); }
        .genre-tag.active {
            background: var(--accent); border-color: var(--accent); color: #fff;
            box-shadow: 0 2px 8px rgba(124,92,255,0.3);
        }
    </style>
</head>
<body>
    <!-- Login -->
    <div id="login" class="center hide">
        <form class="card" id="loginForm">
            <h1 style="margin:0 0 4px">Admin</h1>
            <p class="muted" style="margin:0 0 16px">Connectez-vous avec un compte administrateur.</p>
            <label>Email</label>
            <input name="email" type="email" required autocomplete="email">
            <label>Mot de passe</label>
            <input name="password" type="password" required autocomplete="current-password">
            <p class="err" id="loginErr"></p>
            <button class="btn btn--primary" style="width:100%;margin-top:8px" type="submit">Se connecter</button>
        </form>
    </div>

    <!-- Dashboard -->
    <div id="dash" class="hide">
        <header>
            <h1>ANIME<b style="color:var(--accent)">SAMA</b> · Admin</h1>
            <div class="spacer"></div>
            <a class="btn" href="/">← Retour au site</a>
            <button class="btn" id="logout">Déconnexion</button>
        </header>
        <div class="wrap">
            <div class="stats" id="stats"></div>
            <div class="searchbar">
                <input id="q" placeholder="Rechercher un anime par nom…">
                <button class="btn" id="searchBtn">Rechercher</button>
                <button class="btn btn--primary" id="createAnimeBtn">+ Nouvel anime</button>
                <button class="btn" id="planningBtn">Planning</button>
                <button class="btn" id="requestsBtn">Demandes</button>
                <button class="btn" id="transfersBtn">Transferts</button>
                <button class="btn" id="torrentsBtn">🧲 Torrents</button>
                <button class="btn btn--primary" id="teamBtn">👥 Équipe</button>
            </div>
            <table>
                <thead><tr><th>ID</th><th></th><th>Nom</th><th>Saisons</th><th>Trailer</th><th>Synopsis</th><th></th></tr></thead>
                <tbody id="rows"></tbody>
            </table>
            <div class="pager">
                <button class="btn" id="prev">← Précédent</button>
                <span class="muted" id="pageInfo"></span>
                <button class="btn" id="next">Suivant →</button>
            </div>
        </div>
    </div>

    <!-- Edit modal -->
    <div class="modal" id="editModal">
        <div class="card">
            <form id="editForm">
                <h2 style="margin:0 0 4px" id="editTitle">Éditer</h2>
                <p class="muted" style="margin:0 0 8px" id="editUrl"></p>
                <label>Nom</label>
                <input name="name">
                <label>URL catalogue (utilisée pour ouvrir l'anime)</label>
                <input name="url" placeholder="local://anime/slug ou https://...">
                <label>Trailer (URL YouTube ou identifiant)</label>
                <input name="trailer_url" placeholder="https://www.youtube.com/watch?v=…">
                <label>Image (URL)</label>
                <input name="image_url">
                <label>Synopsis</label>
                <textarea name="synopsis"></textarea>
                <label style="display:flex;align-items:center;gap:8px;text-transform:none">
                    <input type="checkbox" name="is_mature" style="width:auto"> Contenu 18+
                </label>
                <label>Genres <span class="muted" style="font-size:11px;text-transform:none">(cliquez pour sélectionner, plusieurs possibles)</span></label>
                <div id="genreSelector"></div>
                <p class="err" id="editErr"></p>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px">
                    <button type="button" class="btn" id="editCancel">Fermer</button>
                    <button type="submit" class="btn btn--primary">Enregistrer</button>
                </div>
            </form>
            <div style="margin-top:18px;border-top:1px solid var(--border);padding-top:14px">
                <h3 style="margin:0 0 8px;font-size:14px">Saisons</h3>
                <div id="editSeasons" class="muted">—</div>
            </div>
        </div>
    </div>

    <!-- Episodes & links modal -->
    <div class="modal" id="episodesModal">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <h2 style="margin:0" id="epTitle">Épisodes</h2>
                <button type="button" class="btn" id="epClose">Fermer</button>
            </div>
            <div id="epList" style="margin-top:12px"></div>
        </div>
    </div>

    <!-- Create anime modal -->
    <div class="modal" id="createAnimeModal">
        <div class="card">
            <form id="createAnimeForm">
                <h2 style="margin:0 0 4px">Nouvel anime</h2>
                <p class="muted" style="margin:0 0 16px">Créez un nouvel anime dans la bibliothèque locale.</p>
                <label>Nom *</label>
                <input name="name" required placeholder="Nom de l'anime">
                <label>Image (URL)</label>
                <input name="image_url" placeholder="https://…">
                <label>Trailer (URL YouTube)</label>
                <input name="trailer_url" placeholder="https://www.youtube.com/watch?v=…">
                <label>Synopsis</label>
                <textarea name="synopsis" placeholder="Description de l'anime…"></textarea>
                <label style="display:flex;align-items:center;gap:8px;text-transform:none">
                    <input type="checkbox" name="is_mature" style="width:auto"> Contenu 18+
                </label>
                <p class="err" id="createAnimeErr"></p>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px">
                    <button type="button" class="btn" id="createAnimeCancel">Annuler</button>
                    <button type="submit" class="btn btn--primary">Créer l'anime</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transfers monitoring modal -->
    <div class="modal" id="transfersModal">
        <div class="card" style="width:760px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <h2 style="margin:0">Transferts d'épisodes</h2>
                <button type="button" class="btn" id="transfersClose">Fermer</button>
            </div>
            <p class="muted" style="margin:8px 0 12px">Progression des transferts (episodes:auto / :quota) et erreurs.</p>
            <div class="stats" id="transferStats" style="margin-bottom:10px"></div>
            <div class="muted" style="font-size:12px;margin-bottom:4px">Envois par host aujourd'hui (remote upload)</div>
            <div class="stats" id="transferPerHost" style="margin-bottom:14px"></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
                <button class="btn btn--primary" id="retryAllBtn" title="Remet tous les échecs en file">↻ Réessayer les erreurs</button>
                <button class="btn" id="redoAllBtn" title="Remet les terminés en file (re-upload)">⟳ Refaire les terminés</button>
                <button class="btn" id="refreshTransfersBtn">Actualiser</button>
            </div>
            <h3 style="margin:6px 0 8px;font-size:14px">Erreurs</h3>
            <div id="transferErrors" style="max-height:48vh;overflow-y:auto"></div>
        </div>
    </div>

    <!-- Team manual tracking modal -->
    <div class="modal" id="teamModal">
        <div class="card" style="width:1000px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <h2 style="margin:0">👥 Suivi équipe — téléchargement / upload manuel</h2>
                <button type="button" class="btn" id="teamClose">Fermer</button>
            </div>
            <p class="muted" style="margin:8px 0 12px">Cochez les animes traités ou modifiés. Les animes de +25 épisodes sont marqués pour être faits plus tard.</p>

            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                <label style="margin:0;text-transform:none;color:var(--muted);font-size:13px">Votre nom&nbsp;:</label>
                <input id="teamMember" placeholder="Votre prénom (pour signer votre travail)" style="max-width:260px" autocomplete="off">
            </div>

            <div class="stats" id="teamStats" style="margin-bottom:12px"></div>

            <div class="searchbar" style="margin-bottom:10px">
                <input id="teamQ" placeholder="Rechercher un anime…">
                <button class="btn" id="teamSearchBtn">Rechercher</button>
            </div>
            <div id="teamFilters" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px"></div>

            <div style="max-height:52vh;overflow-y:auto">
                <table>
                    <thead><tr>
                        <th>ID</th><th>Anime</th><th>Épisodes</th>
                        <th>Fait ✅</th><th>Modifié ✏️</th><th>+ tard ⏳</th><th>Qui</th>
                    </tr></thead>
                    <tbody id="teamRows"></tbody>
                </table>
            </div>
            <div class="pager">
                <button class="btn" id="teamPrev">← Précédent</button>
                <span class="muted" id="teamPageInfo"></span>
                <button class="btn" id="teamNext">Suivant →</button>
            </div>
        </div>
    </div>

    <!-- Torrents (remote upload) modal -->
    <div class="modal" id="torrentsModal">
        <div class="card" style="width:900px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <h2 style="margin:0">🧲 Torrents — envoi des liens aux lecteurs</h2>
                <button type="button" class="btn" id="torrentsClose">Fermer</button>
            </div>
            <p class="muted" style="margin:8px 0 6px">
                Collez un <b>lien magnet / .torrent</b> (copié depuis uTorrent, qBittorrent, etc.) :
                le <b>VPS le télécharge directement (aria2)</b>, puis chaque épisode est mis en ligne
                sur le(s) lecteur(s) cible(s). Ou collez des liens HTTP directs (un par ligne).
            </p>
            <p class="muted" id="tProviderInfo" style="margin:0 0 12px;font-size:12px"></p>
            <p class="muted hide" id="tProviderWarn" style="margin:0 0 12px;color:var(--danger)"></p>

            <div class="hide" id="tMigrateWarn" style="margin:0 0 12px;padding:10px;border:1px solid var(--danger);border-radius:8px;background:rgba(255,77,109,.08)">
                <div style="color:var(--danger);font-size:13px;margin-bottom:8px">⚠ La table multi-lecteurs n'existe pas encore en base. Lance la migration pour activer l'envoi vers plusieurs lecteurs.</div>
                <button class="btn btn--primary" id="tMigrateBtn">🛠 Migrer la base maintenant</button>
                <pre id="tMigrateOut" class="hide" style="margin:10px 0 0;font-size:11px;white-space:pre-wrap;max-height:160px;overflow:auto"></pre>
            </div>

            <div class="stats" id="torrentStats" style="margin-bottom:12px"></div>
            <div class="muted" style="font-size:12px;margin-bottom:4px">Quota d'upload par lecteur aujourd'hui (repris demain si atteint)</div>
            <div class="stats" id="torrentDailyUsage" style="margin-bottom:14px"></div>

            <!-- Submission form -->
            <div style="border:1px solid var(--accent);border-radius:12px;padding:14px;margin-bottom:16px;background:rgba(124,92,255,0.05)">
                <b style="font-size:13px;display:block;margin-bottom:10px">Nouveau batch</b>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <div style="flex:2;min-width:220px">
                        <label>Lecteurs cibles <span class="muted" style="text-transform:none;font-size:11px">(coche = envoyé à CE lecteur ; chaque épisode part vers tous les cochés)</span></label>
                        <div id="tPlatforms" style="display:flex;flex-wrap:wrap;gap:6px"></div>
                    </div>
                    <div style="flex:1;min-width:120px">
                        <label>Langue</label>
                        <input id="tLang" value="VOSTFR" placeholder="VOSTFR">
                    </div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                    <div style="flex:1;min-width:120px">
                        <label>Anime ID <span class="muted" style="text-transform:none;font-size:11px">(pour lier à la base + organiser en dossiers)</span></label>
                        <input id="tAnimeId" type="number" placeholder="ex: 5">
                    </div>
                    <div style="flex:1;min-width:120px">
                        <label>Saison ID <span class="muted" style="text-transform:none;font-size:11px">(pack: mappe auto par n° d'épisode)</span></label>
                        <input id="tSeasonId" type="number" placeholder="ex: 12">
                    </div>
                    <div style="flex:1;min-width:120px">
                        <label>OU Épisode ID <span class="muted" style="text-transform:none;font-size:11px">(épisode unique)</span></label>
                        <input id="tEpisodeId" type="number" placeholder="ex: 42">
                    </div>
                </div>
                <label>Titre (optionnel)</label>
                <input id="tTitle" placeholder="ex: One Piece — Saison 1">
                <label>Lien magnet / .torrent <span class="muted" id="tProviderTag" style="text-transform:none;font-size:11px"></span></label>
                <input id="tTorrentUrl" placeholder="magnet:?xt=urn:btih:… (copié depuis uTorrent) ou https://…/pack.torrent">
                <label>OU liens HTTP directs (un par ligne)</label>
                <textarea id="tLinks" placeholder="https://seedbox.example/One.Piece.S01E01.1080p.mp4&#10;https://seedbox.example/One.Piece.S01E02.1080p.mp4&#10;…"></textarea>
                <p class="err" id="tErr"></p>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:10px">
                    <button class="btn btn--primary" id="tSubmitBtn">Envoyer aux lecteurs</button>
                </div>
            </div>

            <!-- Batch list -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;align-items:center">
                <b style="font-size:14px;flex:1">Batches</b>
                <button class="btn btn--primary" id="tProcessBtn" title="Traiter la file maintenant (résout les torrents + envoie aux lecteurs)">▶ Traiter maintenant</button>
                <button class="btn" id="tContentBtn" title="Animes ajoutés/modifiés récemment + liens de vérification">📚 Contenu ajouté</button>
                <button class="btn" id="tRetryAllBtn" title="Réarmer tous les échecs">↻ Réessayer erreurs</button>
                <button class="btn" id="tCleanupBtn" title="Garder un seul lien par lecteur et par langue pour chaque épisode">🧹 Dédupliquer les liens</button>
                <button class="btn btn--danger" id="tKeepManagedBtn" title="Ne garder QUE doodstream/vidmoly/streamtape/filemoon et supprimer tous les autres liens">🗑 Garder que mes 4 lecteurs</button>
                <button class="btn" id="tRefreshBtn">Actualiser</button>
            </div>
            <div id="torrentList" style="max-height:44vh;overflow-y:auto"></div>
        </div>
    </div>

    <!-- Torrent files (per-episode) modal -->
    <div class="modal" id="torrentFilesModal">
        <div class="card" style="width:760px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <h2 style="margin:0" id="tfTitle">Épisodes du batch</h2>
                <button type="button" class="btn" id="torrentFilesClose">Fermer</button>
            </div>
            <p class="muted" style="margin:8px 0 12px">Détail par lien. Un lien non mappé (⊘) peut être rattaché manuellement à un épisode.</p>
            <div id="torrentFilesList" style="max-height:60vh;overflow-y:auto"></div>
        </div>
    </div>

    <!-- Recently added / modified content modal -->
    <div class="modal" id="recentContentModal">
        <div class="card" style="width:820px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <h2 style="margin:0">Contenu ajouté / modifié</h2>
                <button type="button" class="btn" id="recentContentClose">Fermer</button>
            </div>
            <p class="muted" style="margin:8px 0 12px">Animes disposant de liens vidéo, triés du plus récemment modifié. Cliquez sur un anime pour voir les épisodes et vérifier chaque lecteur.</p>
            <div style="display:flex;gap:8px;margin-bottom:10px">
                <input id="rcSearch" placeholder="Rechercher un anime…" style="flex:1">
                <button class="btn" id="rcRefreshBtn">Actualiser</button>
            </div>
            <div id="recentContentList" style="max-height:56vh;overflow-y:auto"></div>
        </div>
    </div>

    <!-- One anime: episodes × player links to verify -->
    <div class="modal" id="recentLinksModal">
        <div class="card" style="width:820px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <h2 style="margin:0" id="rcLinksTitle">Liens de l'anime</h2>
                <button type="button" class="btn" id="recentLinksClose">Fermer</button>
            </div>
            <p class="muted" id="rcLinksHint" style="margin:8px 0 12px">Cliquez un lien pour l'ouvrir dans un nouvel onglet et vérifier la lecture.</p>
            <div id="recentLinksList" style="max-height:60vh;overflow-y:auto"></div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <!-- Planning modal -->
    <div class="modal" id="planningModal">
        <div class="card" style="width:700px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <h2 style="margin:0">Planning de diffusion</h2>
                <button type="button" class="btn" id="planningClose">Fermer</button>
            </div>
            <div id="planningList" style="margin-top:12px;max-height:60vh;overflow-y:auto"></div>
        </div>
    </div>

    <!-- Content requests modal -->
    <div class="modal" id="requestsModal">
        <div class="card" style="width:700px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <h2 style="margin:0">Demandes de contenu</h2>
                <button type="button" class="btn" id="requestsClose">Fermer</button>
            </div>
            <p class="muted" style="margin:8px 0 12px">Animes demandés par les utilisateurs</p>
            <div id="requestsList" style="max-height:60vh;overflow-y:auto"></div>
        </div>
    </div>

    <script src="/js/admin.js?v=26"></script>
</body>
</html>
