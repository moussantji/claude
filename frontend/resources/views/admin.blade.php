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
        .searchbar { display:flex; gap:10px; margin-bottom:14px; }
        .searchbar input { flex:1; }
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
            </div>
            <table>
                <thead><tr><th></th><th>Nom</th><th>Saisons</th><th>Trailer</th><th>Synopsis</th><th></th></tr></thead>
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
        <form class="card" id="editForm">
            <h2 style="margin:0 0 4px" id="editTitle">Éditer</h2>
            <p class="muted" style="margin:0 0 8px" id="editUrl"></p>
            <label>Nom</label>
            <input name="name">
            <label>Trailer (URL YouTube ou identifiant)</label>
            <input name="trailer_url" placeholder="https://www.youtube.com/watch?v=…">
            <label>Image (URL)</label>
            <input name="image_url">
            <label>Synopsis</label>
            <textarea name="synopsis"></textarea>
            <label style="display:flex;align-items:center;gap:8px;text-transform:none">
                <input type="checkbox" name="is_mature" style="width:auto"> Contenu 18+
            </label>
            <p class="err" id="editErr"></p>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px">
                <button type="button" class="btn" id="editCancel">Annuler</button>
                <button type="submit" class="btn btn--primary">Enregistrer</button>
            </div>
        </form>
    </div>

    <div class="toast" id="toast"></div>
    <script src="/js/admin.js?v=1"></script>
</body>
</html>
