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
    <link rel="stylesheet" href="/css/app.css?v=4">
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

    <!-- Mobile bottom navigation -->
    <nav class="tabbar" id="tabbar" aria-label="Navigation mobile">
        <button class="tabbar__item is-active" data-tab="home">
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/></svg>
            <span>Accueil</span>
        </button>
        <button class="tabbar__item" data-tab="new">
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L6 13h5l-1 9 8-12h-5z"/></svg>
            <span>Nouveautés</span>
        </button>
        <button class="tabbar__item tabbar__item--fab" data-tab="search" aria-label="Rechercher">
            <span class="tabbar__fab">
                <svg viewBox="0 0 24 24" stroke-linecap="round"><path d="M21 21l-4.3-4.3M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
            </span>
        </button>
        <button class="tabbar__item" data-tab="list">
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.35-9.5-8.5C1 9 2.5 5.5 6 5.5c2 0 3.2 1.2 4 2.3.8-1.1 2-2.3 4-2.3 3.5 0 5 3.5 3.5 7C19 16.65 12 21 12 21z"/></svg>
            <span>Ma liste</span>
        </button>
        <button class="tabbar__item" data-tab="account">
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
            <span>Compte</span>
        </button>
    </nav>

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

    <script src="/js/app.js?v=6"></script>
</body>
</html>
