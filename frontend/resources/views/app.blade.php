<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07070c">
    <!-- Strip the Referer so hotlink-protected anime-sama images load. -->
    <meta name="referrer" content="no-referrer">
    <title>Anime-Sama — Streaming</title>
    <meta name="description" content="Regardez vos animes préférés en streaming.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css?v=3">
</head>
<body>
    <!-- Animated cinematic backdrop -->
    <div class="bg-stage" id="bgStage" aria-hidden="true"></div>
    <div class="bg-trailer" id="bgTrailer" aria-hidden="true"></div>
    <div class="bg-scrim" aria-hidden="true"></div>

    <!-- Navigation -->
    <header class="nav" id="nav">
        <div class="nav__inner">
            <a class="brand" href="#" data-nav="home">
                <span class="brand__mark">A</span>
                <span class="brand__text">ANIME<b>SAMA</b></span>
            </a>

            <nav class="nav__links">
                <a href="#" class="nav__link is-active" data-nav="home">Accueil</a>
                <a href="#" class="nav__link" data-nav="new">Derniers épisodes</a>
                <a href="#" class="nav__link" data-nav="list">Ma liste</a>
            </nav>

            <div class="nav__right">
                <form class="search" id="searchForm" role="search">
                    <svg class="search__icon" viewBox="0 0 24 24"><path d="M21 21l-4.3-4.3M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                    <input id="searchInput" class="search__input" type="search" placeholder="Rechercher un anime…" autocomplete="off">
                </form>
                <button class="btn btn--auth" id="authButton">Se connecter</button>
            </div>
        </div>
    </header>

    <main id="main">
        <!-- Hero -->
        <section class="hero" id="hero">
            <div class="hero__content" id="heroContent"></div>
            <div class="hero__dots" id="heroDots"></div>
        </section>

        <!-- Dynamic rows -->
        <div class="rows" id="rows"></div>

        <footer class="footer">
            <p>Anime-Sama — projet personnel/éducatif. Les contenus proviennent de sources publiques tierces.</p>
        </footer>
    </main>

    <!-- Detail modal -->
    <div class="modal" id="detailModal" aria-hidden="true">
        <div class="modal__scrim" data-close="detail"></div>
        <div class="modal__panel detail" role="dialog" aria-modal="true">
            <button class="modal__close" data-close="detail" aria-label="Fermer">&times;</button>
            <div class="detail__body" id="detailBody"></div>
        </div>
    </div>

    <!-- Player modal -->
    <div class="modal" id="playerModal" aria-hidden="true">
        <div class="modal__scrim" data-close="player"></div>
        <div class="modal__panel player" role="dialog" aria-modal="true">
            <button class="modal__close" data-close="player" aria-label="Fermer">&times;</button>
            <div class="player__frame">
                <div class="player__loader" id="playerLoader"><span class="spinner"></span></div>
                <iframe id="playerIframe" allowfullscreen referrerpolicy="origin" title="Lecteur"></iframe>
            </div>
            <div class="player__bar" id="playerBar"></div>
        </div>
    </div>

    <!-- Auth modal -->
    <div class="modal" id="authModal" aria-hidden="true">
        <div class="modal__scrim" data-close="auth"></div>
        <div class="modal__panel auth" role="dialog" aria-modal="true">
            <button class="modal__close" data-close="auth" aria-label="Fermer">&times;</button>
            <div class="auth__brand"><span class="brand__mark">A</span> ANIME<b>SAMA</b></div>
            <div class="auth__tabs">
                <button class="auth__tab is-active" data-tab="login">Connexion</button>
                <button class="auth__tab" data-tab="register">Inscription</button>
            </div>
            <form class="auth__form" id="authForm">
                <div class="field" data-only="register">
                    <label>Nom</label>
                    <input name="name" type="text" autocomplete="name" placeholder="Votre nom">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input name="email" type="email" autocomplete="email" placeholder="vous@exemple.com" required>
                </div>
                <div class="field">
                    <label>Mot de passe</label>
                    <input name="password" type="password" autocomplete="current-password" placeholder="••••••••" required minlength="8">
                </div>
                <div class="field" data-only="register">
                    <label>Confirmer le mot de passe</label>
                    <input name="password_confirmation" type="password" autocomplete="new-password" placeholder="••••••••">
                </div>
                <p class="auth__error" id="authError"></p>
                <button class="btn btn--primary btn--block" type="submit" id="authSubmit">Se connecter</button>
            </form>
        </div>
    </div>

    <!-- Toasts -->
    <div class="toasts" id="toasts"></div>

    <script src="/js/app.js?v=3"></script>
</body>
</html>
