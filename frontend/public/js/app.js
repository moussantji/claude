/* =========================================================================
   Anime-Sama — front-end application (vanilla JS, no build step)
   Talks to the Laravel API at /api — serves from MySQL only.
   ========================================================================= */
(() => {
    'use strict';

    const API = '/api';
    const HERO_ROTATE_MS = 13000;

    const state = {
        token: localStorage.getItem('as_token') || null,
        user: JSON.parse(localStorage.getItem('as_user') || 'null'),
        favorites: new Map(),
        heroItems: [],
        heroIndex: 0,
        heroTimer: null,
        bgLayers: [],
        bgActive: 0,
        authMode: 'login',
        currentEpisode: null,
        currentContext: null,
        preferredLang: null,
        trailerCache: new Map(),
        trailerTimer: null,
        trailerActive: false,
        trailerMuted: true,
        currentTrailerKey: null,
    };

    const LANG_LABEL = { VF: 'VF', VOSTFR: 'VOSTFR', VASTFR: 'VASTFR', VCN: 'VCN', VJSTFR: 'VJSTFR', VKR: 'VKR', VQC: 'VQC' };
    const LANG_ORDER = ['VF', 'VOSTFR', 'VASTFR', 'VJSTFR', 'VCN', 'VKR', 'VQC'];

    /* ---- tiny DOM helpers ------------------------------------------------ */
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const el = (tag, cls, html) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (html != null) n.innerHTML = html;
        return n;
    };
    const initial = (s) => (s || '?').trim().charAt(0).toUpperCase();


    /* ---- API client ------------------------------------------------------ */
    async function api(path, { method = 'GET', params, body, auth = false } = {}) {
        const url = new URL(API + path, location.origin);
        if (params) {
            Object.entries(params).forEach(([k, v]) => {
                if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
            });
        }
        const headers = { Accept: 'application/json' };
        if (body) headers['Content-Type'] = 'application/json';
        if (auth && state.token) headers.Authorization = 'Bearer ' + state.token;
        const res = await fetch(url, { method, headers, body: body ? JSON.stringify(body) : undefined });
        let json = {};
        try { json = await res.json(); } catch (_) {}
        if (!res.ok) throw new Error(json.message || `Erreur (${res.status})`);
        return json;
    }

    /* ---- Toasts ---------------------------------------------------------- */
    function toast(message, kind = 'ok') {
        const t = el('div', `toast toast--${kind}`, `<span class="toast__dot"></span><span>${escapeHtml(message)}</span>`);
        $('#toasts').appendChild(t);
        setTimeout(() => { t.style.transition = 'opacity .3s, transform .3s'; t.style.opacity = '0'; t.style.transform = 'translateY(10px)'; setTimeout(() => t.remove(), 300); }, 3200);
    }
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }


    /* ---- Cinematic background ------------------------------------------- */
    function setupBackground() {
        const stage = $('#bgStage');
        stage.innerHTML = '';
        state.bgLayers = [el('div', 'bg-layer'), el('div', 'bg-layer')];
        state.bgLayers.forEach((l) => stage.appendChild(l));
    }
    function setBackground(imageUrl) {
        if (!imageUrl || !state.bgLayers.length) return;
        const next = (state.bgActive + 1) % 2;
        const layer = state.bgLayers[next];
        const img = new Image();
        img.referrerPolicy = 'no-referrer';
        img.onload = () => {
            layer.style.backgroundImage = `url("${imageUrl}")`;
            state.bgLayers.forEach((l, i) => l.classList.toggle('is-active', i === next));
            state.bgActive = next;
        };
        img.src = imageUrl;
    }

    /* ---- Cards ----------------------------------------------------------- */
    function posterHtml(item) {
        if (item.image_url) {
            return `<img class="card__poster" src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.name)}"
                    loading="lazy" referrerpolicy="no-referrer" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'card__poster card__ph',textContent:'${escapeHtml(initial(item.name))}'}))">`;
        }
        return `<div class="card__poster card__ph">${escapeHtml(initial(item.name))}</div>`;
    }

    function makeCard(item) {
        const url = item._catalogueUrl || item.url;
        const tags = [].concat(item.languages || []).concat(item.categories || []).slice(0, 3)
            .map((t) => `<span class="card__tag">${escapeHtml(t)}</span>`).join('');
        const card = el('div', 'card', `
            ${posterHtml(item)}
            <button class="card__play" aria-label="Ouvrir"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></button>
            <div class="card__info"><div class="card__name">${escapeHtml(item.name)}</div><div class="card__tags">${tags}</div></div>`);
        card.addEventListener('click', () => openDetail(url, item));
        return card;
    }

    function skeletonCard() { return el('div', 'card card--sk', '<div class="card__poster sk"></div>'); }


    /* ---- Rows ------------------------------------------------------------ */
    function makeRow(title, items) {
        const row = el('section', 'row');
        row.innerHTML = `
            <div class="row__head"><h2 class="row__title">${escapeHtml(title)}</h2><span class="row__count">${items.length}</span></div>
            <div class="row__viewport">
                <button class="row__arrow row__arrow--prev" aria-label="Précédent"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
                <button class="row__arrow row__arrow--next" aria-label="Suivant"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
                <div class="row__track"></div>
            </div>`;
        const track = $('.row__track', row);
        items.forEach((it) => track.appendChild(makeCard(it)));
        const scrollBy = () => Math.round(track.clientWidth * 0.8);
        $('.row__arrow--prev', row).addEventListener('click', () => track.scrollBy({ left: -scrollBy(), behavior: 'smooth' }));
        $('.row__arrow--next', row).addEventListener('click', () => track.scrollBy({ left: scrollBy(), behavior: 'smooth' }));
        return row;
    }

    function skeletonRow() {
        const row = el('section', 'row');
        row.innerHTML = `<div class="row__head"><div class="sk" style="width:180px;height:22px;border-radius:6px"></div></div><div class="row__track"></div>`;
        const track = $('.row__track', row);
        for (let i = 0; i < 7; i++) track.appendChild(skeletonCard());
        return row;
    }


    /* ---- Hero ------------------------------------------------------------ */
    function renderHero() {
        const items = state.heroItems;
        const content = $('#heroContent');
        const dots = $('#heroDots');
        if (!items.length) {
            content.innerHTML = `<div class="hero__badge">Bienvenue</div><h1 class="hero__title">Anime-Sama</h1><p class="hero__desc">Aucun anime dans la base. Ajoutez des animes via le panel admin pour les voir ici.</p><div class="hero__actions"><a class="btn btn--play" href="/admin">Panel Admin</a></div>`;
            dots.innerHTML = '';
            return;
        }
        const item = items[state.heroIndex % items.length];
        if (item.image_url) setBackground(item.image_url);
        scheduleHeroTrailer(item);

        const tags = [].concat((item.languages || []).map((l) => `<span class="chip chip--lang">${escapeHtml(l)}</span>`))
            .concat((item.categories || []).map((c) => `<span class="chip">${escapeHtml(c)}</span>`)).join('');

        content.classList.remove('fade-in');
        void content.offsetWidth;
        content.classList.add('fade-in');
        content.innerHTML = `
            <div class="hero__badge">Ajout récent</div>
            <h1 class="hero__title">${escapeHtml(item.name)}</h1>
            <div class="hero__meta">${tags}</div>
            <p class="hero__desc">${escapeHtml(item.descriptive || item.synopsis || '')}</p>
            <div class="hero__actions">
                <button class="btn btn--play" id="heroPlay"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> Regarder</button>
                <button class="btn btn--ghost" id="heroInfo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01" stroke-linecap="round"/></svg> Plus d'infos</button>
                <button class="btn btn--icon btn--ghost" id="heroMute" title="Activer le son" style="display:none"></button>
            </div>`;

        $('#heroInfo').addEventListener('click', () => openDetail(item.url, item));
        $('#heroPlay').addEventListener('click', () => openDetail(item.url, item));
        const muteBtn = $('#heroMute');
        if (muteBtn) muteBtn.addEventListener('click', () => { state.trailerMuted = !state.trailerMuted; if (state.currentTrailerKey) showHeroTrailer(state.currentTrailerKey); });
        updateHeroMuteBtn();

        dots.innerHTML = '';
        items.forEach((_, i) => {
            const d = el('button', 'hero__dot' + (i === state.heroIndex % items.length ? ' is-active' : ''));
            d.addEventListener('click', () => { state.heroIndex = i; renderHero(); restartHeroTimer(); });
            dots.appendChild(d);
        });
    }

    function restartHeroTimer() {
        clearInterval(state.heroTimer);
        if (state.heroItems.length > 1) {
            state.heroTimer = setInterval(() => { state.heroIndex = (state.heroIndex + 1) % state.heroItems.length; renderHero(); }, HERO_ROTATE_MS);
        }
    }


    /* ---- Hero trailer ---------------------------------------------------- */
    function scheduleHeroTrailer(item) {
        clearTimeout(state.trailerTimer);
        removeHeroTrailer();
        state.trailerTimer = setTimeout(() => loadHeroTrailer(item), 1600);
    }

    async function loadHeroTrailer(item) {
        const name = item && item.name;
        if (!name) return;
        let key = state.trailerCache.get(name);
        if (key === undefined) {
            try { const res = await api('/anime/trailer', { params: { title: name } }); key = (res && res.data && res.data.youtube_key) || null; }
            catch (_) { key = null; }
            state.trailerCache.set(name, key);
        }
        const current = state.heroItems[state.heroIndex % (state.heroItems.length || 1)];
        if (!key || !current || current.name !== name) return;
        if ($('#hero').style.display === 'none') return;
        showHeroTrailer(key);
    }

    function showHeroTrailer(key) {
        state.currentTrailerKey = key;
        const box = $('#bgTrailer');
        if (!box) return;
        const params = new URLSearchParams({ autoplay:'1', mute: state.trailerMuted?'1':'0', controls:'0', loop:'1', playlist:key, modestbranding:'1', playsinline:'1', rel:'0', showinfo:'0', iv_load_policy:'3', disablekb:'1', fs:'0' });
        box.innerHTML = `<iframe src="https://www.youtube-nocookie.com/embed/${encodeURIComponent(key)}?${params}" allow="autoplay; encrypted-media" frameborder="0"></iframe>`;
        box.classList.add('is-on');
        state.trailerActive = true;
        updateHeroMuteBtn();
    }

    function removeHeroTrailer() {
        const box = $('#bgTrailer');
        if (box) { box.classList.remove('is-on'); box.innerHTML = ''; }
        state.trailerActive = false;
        state.currentTrailerKey = null;
        updateHeroMuteBtn();
    }

    function updateHeroMuteBtn() {
        const b = $('#heroMute');
        if (!b) return;
        b.style.display = state.trailerActive ? 'inline-flex' : 'none';
        b.title = state.trailerMuted ? 'Activer le son' : 'Couper le son';
        b.innerHTML = state.trailerMuted
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M23 9l-6 6M17 9l6 6" stroke-linecap="round"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 010 7M19 5a9 9 0 010 14" stroke-linecap="round"/></svg>';
    }


    /* ---- Views ----------------------------------------------------------- */
    function showHomeChrome(show) { $('#hero').style.display = show ? '' : 'none'; }

    async function renderHome() {
        setActiveNav('home');
        showHomeChrome(true);
        const rows = $('#rows');
        rows.style.marginTop = '';
        rows.innerHTML = '';
        rows.appendChild(skeletonRow());

        let latest = [];
        try {
            const res = await api('/anime/new-episodes');
            latest = (res.data || []).filter((x) => x.name);
        } catch (e) {
            state.heroItems = [];
            renderHero();
            rows.innerHTML = `<div class="empty">Impossible de charger le contenu : ${escapeHtml(e.message)}</div>`;
            return;
        }

        // Hero: items with images
        state.heroItems = latest.filter((x) => x.image_url).slice(0, 7);
        if (!state.heroItems.length && latest.length) state.heroItems = latest.slice(0, 5);
        state.heroIndex = 0;
        renderHero();
        restartHeroTimer();

        rows.innerHTML = '';
        if (latest.length) {
            rows.appendChild(makeRow('Ajouts récents', latest));
        }

        // Load all catalogues grouped by category if available
        try {
            const all = await api('/anime/all-catalogues');
            const allItems = (all.data || []).filter((x) => x.name);
            // Group by first category
            const groups = {};
            allItems.forEach((it) => {
                const cat = (it.categories && it.categories[0]) || 'Autres';
                if (!groups[cat]) groups[cat] = [];
                groups[cat].push(it);
            });
            Object.entries(groups).forEach(([cat, items]) => {
                if (items.length > 0) rows.appendChild(makeRow(cat, items));
            });
        } catch (_) { /* no extra rows */ }
    }


    function renderGridView(title, sub, items, emptyMsg) {
        clearInterval(state.heroTimer);
        clearTimeout(state.trailerTimer);
        removeHeroTrailer();
        showHomeChrome(false);
        const rows = $('#rows');
        rows.style.marginTop = '0';
        rows.innerHTML = '';
        const view = el('section', 'view');
        view.innerHTML = `
            <div class="view__head">
                <h1 class="view__title">${escapeHtml(title)}</h1>
                <span class="view__sub">${escapeHtml(sub || '')}</span>
                ${items.length > 1 ? `
                <div class="view__sort">
                    <button class="sort-btn is-active" data-sort="newest"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg> Plus récent</button>
                    <button class="sort-btn" data-sort="oldest"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Plus ancien</button>
                </div>` : ''}
            </div>`;
        if (!items.length) {
            view.appendChild(el('div', 'empty', escapeHtml(emptyMsg || 'Aucun résultat.')));
        } else {
            const grid = el('div', 'grid');
            items.forEach((it) => grid.appendChild(makeCard(it)));
            view.appendChild(grid);
            const sortBtns = view.querySelectorAll('[data-sort]');
            sortBtns.forEach((btn) => {
                btn.addEventListener('click', () => {
                    sortBtns.forEach((b) => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    const sorted = btn.dataset.sort === 'oldest' ? [...items].reverse() : [...items];
                    grid.innerHTML = '';
                    sorted.forEach((it) => grid.appendChild(makeCard(it)));
                });
            });
        }
        rows.appendChild(view);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function loadingView(title) {
        clearTimeout(state.trailerTimer); removeHeroTrailer(); showHomeChrome(false);
        const rows = $('#rows'); rows.style.marginTop = '0'; rows.innerHTML = '';
        const view = el('section', 'view');
        view.innerHTML = `<div class="view__head"><h1 class="view__title">${escapeHtml(title)}</h1></div>`;
        const grid = el('div', 'grid');
        for (let i = 0; i < 12; i++) grid.appendChild(skeletonCard());
        view.appendChild(grid); rows.appendChild(view);
    }

    async function runSearch(query) {
        setActiveNav('search'); loadingView('Recherche\u2026');
        try { const res = await api('/anime/search', { params: { q: query } }); renderGridView(`R\u00e9sultats \u00b7 ${query}`, `${(res.data||[]).length} anime(s)`, res.data||[], `Aucun r\u00e9sultat pour \u00ab ${query} \u00bb.`); }
        catch (e) { renderGridView('Recherche', '', [], e.message); }
    }

    async function showNewEpisodes() {
        setActiveNav('new'); loadingView('Tous les animes');
        try { const res = await api('/anime/all-catalogues'); renderGridView('Tous les animes', `${(res.data||[]).length} anime(s)`, res.data||[], 'Aucun anime dans la base.'); }
        catch (e) { renderGridView('Tous les animes', '', [], e.message); }
    }

    async function showMyList() {
        setActiveNav('list');
        if (!state.token) { openAuth(); return; }
        loadingView('Ma liste');
        try { const res = await api('/favorites', { auth: true }); const items = (res.data||[]).map((f) => ({ url: f.catalogue_url, _catalogueUrl: f.catalogue_url, name: f.name, image_url: f.image_url })); renderGridView('Ma liste', `${items.length} favori(s)`, items, 'Votre liste est vide.'); }
        catch (e) { renderGridView('Ma liste', '', [], e.message); }
    }


    /* ---- Detail modal ---------------------------------------------------- */
    async function openDetail(catalogueUrl, hint = {}, autoPlaySeasonUrl = null) {
        openModal('detail');
        const body = $('#detailBody');
        body.innerHTML = `<div class="center-state"><span class="spinner"></span><p>Chargement\u2026</p></div>`;

        let data;
        try { const res = await api('/anime/catalogue', { params: { url: catalogueUrl } }); data = res.data || {}; }
        catch (e) { body.innerHTML = `<div class="center-state"><p>${escapeHtml(e.message)}</p></div>`; return; }

        const name = data.name || hint.name || 'Anime';
        const fav = state.favorites.has(catalogueUrl);
        const meta = [].concat((data.languages||[]).map((l) => `<span class="chip chip--lang">${escapeHtml(l)}</span>`))
            .concat((data.genres||[]).slice(0,5).map((g) => `<span class="chip">${escapeHtml(g)}</span>`))
            .concat(data.is_mature ? ['<span class="chip" style="border-color:rgba(255,90,120,.5);color:#ff9db0">18+</span>'] : []).join('');
        const detailImage = data.image_url || hint.image_url || '';

        body.innerHTML = `
            <div class="detail__hero" id="detailHero" style="position:relative">
                ${detailImage ? `<img src="${escapeHtml(detailImage)}" alt="" referrerpolicy="no-referrer">` : '<div class="card__ph" style="position:relative;height:100%">' + escapeHtml(initial(name)) + '</div>'}
                <div class="detail__heroText"><h2 class="detail__title">${escapeHtml(name)}</h2></div>
            </div>
            <div class="detail__content">
                <div class="detail__meta">${meta}</div>
                <div class="detail__actions">
                    <button class="btn btn--ghost" id="detailTrailer" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16v12H4z"/><path d="M10 9l5 3-5 3z" fill="currentColor" stroke="none"/></svg> Bande-annonce</button>
                    <button class="btn ${fav?'btn--primary':'btn--ghost'}" id="detailFav"><svg viewBox="0 0 24 24" fill="${fav?'currentColor':'none'}" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.35-9.5-8.5C1 9 2.5 5.5 6 5.5c2 0 3.2 1.2 4 2.3.8-1.1 2-2.3 4-2.3 3.5 0 5 3.5 3.5 7C19 16.65 12 21 12 21z"/></svg> ${fav?'Dans ma liste':'Ma liste'}</button>
                </div>
                ${data.synopsis ? `<p class="detail__synopsis">${escapeHtml(data.synopsis)}</p>` : ''}
                <div class="detail__section-title">Saisons & \u00e9pisodes</div>
                <div class="seasons" id="seasons"></div>
                <div id="episodesWrap"><div class="center-state"><span class="spinner"></span></div></div>
            </div>`;

        $('#detailFav').addEventListener('click', () => toggleFavorite(catalogueUrl, name, detailImage));
        setupDetailTrailer(data, name);

        const seasons = data.seasons || [];
        const seasonsEl = $('#seasons');
        if (!seasons.length) { seasonsEl.innerHTML = ''; $('#episodesWrap').innerHTML = '<div class="empty" style="padding:20px 0">Aucune saison trouv\u00e9e.</div>'; return; }

        seasons.forEach((s, i) => {
            const pill = el('button', 'season-pill' + (i === 0 ? ' is-active' : ''), escapeHtml(s.name || `Saison ${i+1}`));
            pill.addEventListener('click', () => { $$('.season-pill', seasonsEl).forEach((p) => p.classList.remove('is-active')); pill.classList.add('is-active'); loadEpisodes(s, name); });
            seasonsEl.appendChild(pill);
        });
        await loadEpisodes(seasons[0], name);

        if (autoPlaySeasonUrl) {
            const target = seasons.find((s) => autoPlaySeasonUrl.startsWith(s.url)) || seasons[0];
            const idx = seasons.indexOf(target);
            if (idx > 0) { $$('.season-pill', seasonsEl).forEach((p, i) => p.classList.toggle('is-active', i === idx)); await loadEpisodes(target, name); }
            const firstEp = $('#episodesWrap .ep');
            if (firstEp) firstEp.click();
        }
    }


    async function setupDetailTrailer(data, name) {
        const btn = $('#detailTrailer');
        if (!btn) return;
        let key = data.trailer_key || null;
        if (!key) { try { const r = await api('/anime/trailer', { params: { title: name } }); key = (r&&r.data&&r.data.youtube_key)||null; } catch (_) {} }
        if (!key || !$('#detailModal').classList.contains('is-open')) return;
        btn.style.display = '';
        btn.addEventListener('click', () => playDetailTrailer(key));
    }

    function playDetailTrailer(key) {
        const hero = $('#detailHero');
        if (!hero) return;
        const params = new URLSearchParams({ autoplay:'1', rel:'0', modestbranding:'1', playsinline:'1' });
        let box = hero.querySelector('.detail__hero-trailer');
        if (!box) { box = el('div', 'detail__hero-trailer'); box.style.cssText = 'position:absolute;inset:0;z-index:3;background:#000'; hero.appendChild(box); }
        box.innerHTML = `<iframe src="https://www.youtube-nocookie.com/embed/${encodeURIComponent(key)}?${params}" allow="autoplay; encrypted-media; fullscreen" allowfullscreen frameborder="0" style="width:100%;height:100%"></iframe>`;
    }

    async function loadEpisodes(season, serieName) {
        const wrap = $('#episodesWrap');
        wrap.innerHTML = `<div class="center-state"><span class="spinner"></span><p>Chargement\u2026</p></div>`;
        let episodes = [];
        try { const res = await api('/anime/episodes', { params: { url: season.url } }); episodes = res.data || []; }
        catch (e) { wrap.innerHTML = `<div class="empty" style="padding:20px 0">${escapeHtml(e.message)}</div>`; return; }
        if (!episodes.length) { wrap.innerHTML = '<div class="empty" style="padding:20px 0">Aucun \u00e9pisode disponible.</div>'; return; }

        const allLangs = new Set();
        episodes.forEach((ep) => (ep.available_languages||[]).forEach((l) => allLangs.add(l)));
        const sortedLangs = orderLangs([...allLangs]);
        if (!state.preferredLang || !allLangs.has(state.preferredLang)) {
            state.preferredLang = sortedLangs.includes('VF') ? 'VF' : (sortedLangs[0] || null);
        }

        wrap.innerHTML = '';
        if (sortedLangs.length > 1) {
            const langBar = el('div', 'detail__lang-bar');
            langBar.innerHTML = `<span class="detail__lang-label">Langue :</span>`;
            sortedLangs.forEach((lang) => {
                const btn = el('button', 'lang-pill' + (lang === state.preferredLang ? ' is-active' : ''), escapeHtml(LANG_LABEL[lang] || lang));
                btn.addEventListener('click', () => { state.preferredLang = lang; $$('.lang-pill', langBar).forEach((b) => b.classList.remove('is-active')); btn.classList.add('is-active'); });
                langBar.appendChild(btn);
            });
            wrap.appendChild(langBar);
        }

        const grid = el('div', 'episodes');
        episodes.forEach((ep) => {
            const langs = (ep.available_languages||[]).join(' \u00b7 ');
            const item = el('button', 'ep', `<span class="ep__num">${ep.index}</span><span style="min-width:0"><span class="ep__name">${escapeHtml(ep.name||('\u00c9pisode '+ep.index))}</span><span class="ep__langs">${escapeHtml(langs)}</span></span><span class="ep__play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>`);
            item.addEventListener('click', () => openPlayer(ep, { serieName, seasonUrl: season.url, seasonName: season.name }));
            grid.appendChild(item);
        });
        wrap.appendChild(grid);
    }


    /* ---- Player modal ---------------------------------------------------- */
    function mirrorsForLanguage(ep, lang) { const groups = (ep.languages||{})[lang]||[]; return groups.flat(); }
    function orderLangs(langs) { return [...(langs||[])].sort((a,b) => { const ia=LANG_ORDER.indexOf(a); const ib=LANG_ORDER.indexOf(b); return (ia===-1?99:ia)-(ib===-1?99:ib); }); }

    function openPlayer(ep, context) {
        state.currentEpisode = ep; state.currentContext = context; openModal('player');
        const langs = orderLangs(ep.available_languages || []);
        let defaultLang;
        if (state.preferredLang && langs.includes(state.preferredLang)) defaultLang = state.preferredLang;
        else defaultLang = langs.includes('VF') ? 'VF' : (langs[0] || null);
        renderPlayerBar(defaultLang, 0);
        const mirrors = defaultLang ? mirrorsForLanguage(ep, defaultLang) : [];
        const first = mirrors[0] || ep.best_player;
        if (first) playUrl(first);
        else $('#playerLoader').innerHTML = '<p style="color:var(--muted)">Aucun lecteur disponible.</p>';
        recordHistory(ep, context, defaultLang, first);
    }

    function renderPlayerBar(activeLang, activeMirror) {
        const ep = state.currentEpisode; const bar = $('#playerBar');
        if (!ep) return;
        const langs = orderLangs(ep.available_languages || []);
        const mirrors = activeLang ? mirrorsForLanguage(ep, activeLang) : [];
        const langBtns = langs.map((l) => `<button class="mirror ${l===activeLang?'is-active':''}" data-lang="${escapeHtml(l)}">${escapeHtml(LANG_LABEL[l]||l)}</button>`).join('');
        const mirrorBtns = mirrors.map((m, i) => { let host=''; try{host=new URL(m).hostname.replace(/^www\./,'');}catch(_){host='Lecteur '+(i+1);} return `<button class="mirror ${i===activeMirror?'is-active':''}" data-mirror="${i}">${escapeHtml(host)}</button>`; }).join('');
        bar.innerHTML = `<span class="player__title">${escapeHtml((state.currentContext?.serieName||'')+' \u2014 '+(ep.name||'\u00c9pisode '+ep.index))}</span>${langs.length?`<span class="player__label">Langue</span>${langBtns}`:''} ${mirrors.length?`<span class="player__label">Lecteur</span>${mirrorBtns}`:''}`;
        $$('[data-lang]', bar).forEach((b) => { b.addEventListener('click', (e) => { e.preventDefault(); const lang=b.getAttribute('data-lang'); const ms=mirrorsForLanguage(ep,lang); renderPlayerBar(lang,0); if(ms[0]){playUrl(ms[0]);recordHistory(ep,state.currentContext,lang,ms[0]);} }); });
        $$('[data-mirror]', bar).forEach((b) => { b.addEventListener('click', (e) => { e.preventDefault(); const i=+b.getAttribute('data-mirror'); renderPlayerBar(activeLang,i); if(mirrors[i]){playUrl(mirrors[i]);recordHistory(ep,state.currentContext,activeLang,mirrors[i]);} }); });
    }

    function playUrl(url) {
        const frame = $('#playerIframe'); const loader = $('#playerLoader');
        loader.style.display = 'grid'; loader.innerHTML = '<span class="spinner"></span>';
        frame.onload = () => { loader.style.display = 'none'; };
        frame.src = url;
    }

    function recordHistory(ep, context, language, playerUrl) {
        if (!state.token || !context) return;
        api('/history', { method:'POST', auth:true, body:{ serie_name:context.serieName||'Anime', season_url:context.seasonUrl, season_name:context.seasonName||null, episode_index:ep.index, episode_name:ep.name||null, player_url:playerUrl||null, language:language||null } }).catch(()=>{});
    }


    /* ---- Favorites ------------------------------------------------------- */
    async function loadFavorites() {
        if (!state.token) { state.favorites.clear(); return; }
        try { const res = await api('/favorites', { auth: true }); state.favorites.clear(); (res.data||[]).forEach((f) => state.favorites.set(f.catalogue_url, f.id)); } catch (_) {}
    }

    async function toggleFavorite(url, name, image) {
        if (!state.token) { openAuth(); return; }
        try {
            if (state.favorites.has(url)) { const id = state.favorites.get(url); await api(`/favorites/${id}`, { method:'DELETE', auth:true }); state.favorites.delete(url); toast('Retir\u00e9 de ma liste'); }
            else { const res = await api('/favorites', { method:'POST', auth:true, body:{ catalogue_url:url, name, image_url:image||null } }); state.favorites.set(url, res.data.id); toast('Ajout\u00e9 \u00e0 ma liste', 'ok'); }
            const btn = $('#detailFav');
            if (btn) { const fav = state.favorites.has(url); btn.className = `btn ${fav?'btn--primary':'btn--ghost'}`; btn.innerHTML = `<svg viewBox="0 0 24 24" fill="${fav?'currentColor':'none'}" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.35-9.5-8.5C1 9 2.5 5.5 6 5.5c2 0 3.2 1.2 4 2.3.8-1.1 2-2.3 4-2.3 3.5 0 5 3.5 3.5 7C19 16.65 12 21 12 21z"/></svg> ${fav?'Dans ma liste':'Ma liste'}`; }
        } catch (e) { toast(e.message, 'err'); }
    }

    /* ---- Auth ------------------------------------------------------------ */
    function openAuth() { setAuthMode('login'); openModal('auth'); $('#authError').textContent = ''; }
    function setAuthMode(mode) {
        state.authMode = mode;
        const panel = $('#authModal .auth');
        panel.classList.toggle('mode-login', mode === 'login');
        panel.classList.toggle('mode-register', mode === 'register');
        $$('.auth__tab').forEach((t) => t.classList.toggle('is-active', t.dataset.tab === mode));
        $('#authSubmit').textContent = mode === 'login' ? 'Se connecter' : "S'inscrire";
    }

    async function submitAuth(e) {
        e.preventDefault();
        const form = e.target; const btn = $('#authSubmit'); const errEl = $('#authError');
        errEl.textContent = ''; btn.disabled = true; const original = btn.textContent; btn.textContent = '\u2026';
        const payload = { email: form.email.value.trim(), password: form.password.value, device_name: 'web' };
        let path = '/auth/login';
        if (state.authMode === 'register') { path = '/auth/register'; payload.name = form.name.value.trim(); payload.password_confirmation = form.password_confirmation.value; }
        try {
            const res = await api(path, { method:'POST', body:payload });
            state.token = res.token; state.user = res.user;
            localStorage.setItem('as_token', res.token); localStorage.setItem('as_user', JSON.stringify(res.user));
            await loadFavorites(); renderAuthUI(); closeModal('auth'); form.reset(); toast(`Bienvenue ${res.user.name} !`);
        } catch (err) { errEl.textContent = err.message; }
        finally { btn.disabled = false; btn.textContent = original; }
    }

    async function logout() {
        try { await api('/auth/logout', { method:'POST', auth:true }); } catch (_) {}
        state.token = null; state.user = null; state.favorites.clear();
        localStorage.removeItem('as_token'); localStorage.removeItem('as_user');
        renderAuthUI(); toast('D\u00e9connect\u00e9'); renderHome();
    }


    function renderAuthUI() {
        const holder = $('#authButton');
        if (!state.user) { const btn = el('button', 'btn btn--auth', 'Se connecter'); btn.id = 'authButton'; btn.addEventListener('click', openAuth); holder.replaceWith(btn); return; }
        const menu = el('div', 'usermenu'); menu.id = 'authButton';
        menu.innerHTML = `<button class="avatar" aria-label="Compte">${escapeHtml(initial(state.user.name))}</button><div class="usermenu__pop"><div class="usermenu__name"><b>${escapeHtml(state.user.name)}</b><span>${escapeHtml(state.user.email)}</span></div><button class="usermenu__item" data-act="list">\u2665 Ma liste</button><button class="usermenu__item" data-act="logout">\u238b Se d\u00e9connecter</button></div>`;
        holder.replaceWith(menu);
        $('.avatar', menu).addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('is-open'); });
        $('[data-act="list"]', menu).addEventListener('click', () => { menu.classList.remove('is-open'); showMyList(); });
        $('[data-act="logout"]', menu).addEventListener('click', () => { menu.classList.remove('is-open'); logout(); });
        document.addEventListener('click', () => menu.classList.remove('is-open'));
    }

    /* ---- Modals ---------------------------------------------------------- */
    function openModal(name) { clearTimeout(state.trailerTimer); removeHeroTrailer(); $(`#${name}Modal`).classList.add('is-open'); document.body.style.overflow = 'hidden'; }
    function closeModal(name) {
        $(`#${name}Modal`).classList.remove('is-open'); document.body.style.overflow = '';
        if (name === 'player') { $('#playerIframe').src = 'about:blank'; }
        if (name === 'detail') { const box = $('#detailModal .detail__hero-trailer'); if (box) box.remove(); }
        if (!anyModalOpen() && $('#hero').style.display !== 'none' && state.heroItems.length) scheduleHeroTrailer(state.heroItems[state.heroIndex % state.heroItems.length]);
    }
    function anyModalOpen() { return ['detail','player','auth'].some((n) => $(`#${n}Modal`).classList.contains('is-open')); }
    function closeAllModals() { ['detail','player','auth'].forEach(closeModal); }

    /* ---- Navigation & wiring -------------------------------------------- */
    function setActiveNav(which) {
        $$('.nav__link').forEach((l) => l.classList.toggle('is-active', l.dataset.nav === which));
        $$('.tabbar__item').forEach((t) => t.classList.toggle('is-active', t.dataset.tab === which));
    }

    function handleTab(tab) {
        if (tab === 'home') renderHome();
        else if (tab === 'new') showNewEpisodes();
        else if (tab === 'list') showMyList();
        else if (tab === 'search') { window.scrollTo({top:0,behavior:'smooth'}); const input=$('#searchInput'); if(input){input.focus();input.select();} }
        else if (tab === 'account') { if (!state.token) openAuth(); else { const avatar=$('.usermenu .avatar'); if(avatar) avatar.click(); else showMyList(); } }
    }

    function wire() {
        const nav = $('#nav');
        const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 30);
        window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
        $$('.nav__link, .brand').forEach((a) => { a.addEventListener('click', (e) => { const target=a.dataset.nav; if(!target) return; e.preventDefault(); if(target==='home') renderHome(); else if(target==='new') showNewEpisodes(); else if(target==='list') showMyList(); }); });
        $$('.tabbar__item').forEach((btn) => { btn.addEventListener('click', () => handleTab(btn.dataset.tab)); });
        const input = $('#searchInput'); let deb;
        $('#searchForm').addEventListener('submit', (e) => { e.preventDefault(); if(input.value.trim()) runSearch(input.value.trim()); });
        input.addEventListener('input', () => { clearTimeout(deb); const v=input.value.trim(); if(!v){renderHome();return;} deb=setTimeout(()=>{if(input.value.trim()) runSearch(input.value.trim());},550); });
        $$('[data-close]').forEach((b) => b.addEventListener('click', () => closeModal(b.dataset.close)));
        document.addEventListener('keydown', (e) => { if (e.key==='Escape') closeAllModals(); });
        $('#authButton').addEventListener('click', openAuth);
        $$('.auth__tab').forEach((t) => t.addEventListener('click', () => { setAuthMode(t.dataset.tab); $('#authError').textContent=''; }));
        $('#authForm').addEventListener('submit', submitAuth);
    }

    /* ---- Boot ------------------------------------------------------------ */
    async function boot() {
        setupBackground(); wire(); renderAuthUI();
        if (state.token) {
            try { const me = await api('/auth/me', { auth: true }); state.user = me; localStorage.setItem('as_user', JSON.stringify(me)); renderAuthUI(); await loadFavorites(); }
            catch (_) { state.token=null; state.user=null; localStorage.removeItem('as_token'); localStorage.removeItem('as_user'); renderAuthUI(); }
        }
        renderHome();
    }

    document.addEventListener('DOMContentLoaded', boot);
})();
