/* =========================================================================
   Anime-Sama — front-end application (vanilla JS, no build step)
   Talks to the Laravel API at /api — serves from MySQL only.
   ========================================================================= */
(() => {
    'use strict';

    const API = '/api';
    const HERO_ROTATE_MS = 10000;
    const INFINITE_SCROLL_THRESHOLD = 400;

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
        // Infinite scroll
        loadingMore: false,
        currentPage: 1,
        lastPage: 1,
        currentView: null, // 'home' | 'catalogue' | null
    };

    const LANG_LABEL = { VF:'VF', VOSTFR:'VOSTFR', VASTFR:'VASTFR', VCN:'VCN', VJSTFR:'VJSTFR', VKR:'VKR', VQC:'VQC' };
    const LANG_ORDER = ['VF','VOSTFR','VASTFR','VJSTFR','VCN','VKR','VQC'];

    // Planning days
    const DAYS = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];

    /* ---- tiny DOM helpers ------------------------------------------------ */
    const $ = (sel, root=document) => root.querySelector(sel);
    const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));
    const el = (tag, cls, html) => { const n=document.createElement(tag); if(cls) n.className=cls; if(html!=null) n.innerHTML=html; return n; };
    const initial = (s) => (s||'?').trim().charAt(0).toUpperCase();

    /* ---- API client ------------------------------------------------------ */
    async function api(path, { method='GET', params, body, auth=false }={}) {
        const url = new URL(API + path, location.origin);
        if (params) Object.entries(params).forEach(([k,v]) => { if (v !== undefined && v !== null && v !== '') url.searchParams.set(k,v); });
        const headers = { Accept:'application/json' };
        if (body) headers['Content-Type'] = 'application/json';
        if (auth && state.token) headers.Authorization = 'Bearer ' + state.token;
        const res = await fetch(url, { method, headers, body: body ? JSON.stringify(body) : undefined });
        let json = {};
        try { json = await res.json(); } catch(_){}
        if (!res.ok) throw new Error(json.message || `Erreur (${res.status})`);
        return json;
    }

    /* ---- Toasts ---------------------------------------------------------- */
    function toast(message, kind='ok') {
        const t = el('div', `toast toast--${kind}`, `<span class="toast__dot"></span><span>${escapeHtml(message)}</span>`);
        $('#toasts').appendChild(t);
        setTimeout(() => { t.style.transition='opacity .3s, transform .3s'; t.style.opacity='0'; t.style.transform='translateY(10px)'; setTimeout(()=>t.remove(),300); }, 3200);
    }
    function escapeHtml(s) { return String(s==null?'':s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    /* ---- Cinematic background ------------------------------------------- */
    function setupBackground() {
        const stage = $('#bgStage'); stage.innerHTML = '';
        state.bgLayers = [el('div','bg-layer'), el('div','bg-layer')];
        state.bgLayers.forEach(l => stage.appendChild(l));
    }
    function setBackground(imageUrl) {
        if (!imageUrl || !state.bgLayers.length) return;
        const next = (state.bgActive+1)%2;
        const layer = state.bgLayers[next];
        const img = new Image(); img.referrerPolicy='no-referrer';
        img.onload = () => { layer.style.backgroundImage=`url("${imageUrl}")`; state.bgLayers.forEach((l,i)=>l.classList.toggle('is-active',i===next)); state.bgActive=next; };
        img.src = imageUrl;
    }

    /* ---- Cards ----------------------------------------------------------- */
    function posterHtml(item) {
        if (item.image_url) return `<img class="card__poster" src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.name)}" loading="lazy" referrerpolicy="no-referrer" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'card__poster card__ph',textContent:'${escapeHtml(initial(item.name))}'}))">`;
        return `<div class="card__poster card__ph">${escapeHtml(initial(item.name))}</div>`;
    }
    function makeCard(item) {
        const url = item._catalogueUrl || item.url;
        const tags = [].concat(item.languages||[]).concat(item.categories||[]).slice(0,3).map(t=>`<span class="card__tag">${escapeHtml(t)}</span>`).join('');
        const card = el('div','card',`${posterHtml(item)}<button class="card__play" aria-label="Ouvrir"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></button><div class="card__info"><div class="card__name">${escapeHtml(item.name)}</div><div class="card__tags">${tags}</div></div>`);
        card.addEventListener('click', () => openDetail(url, item));
        return card;
    }
    function skeletonCard() { return el('div','card card--sk','<div class="card__poster sk"></div>'); }

    /* ---- History card (continue watching) -------------------------------- */
    function makeHistoryCard(entry) {
        const hasImage = entry.image_url;
        const posterContent = hasImage
            ? `<img class="card__poster" src="${escapeHtml(entry.image_url)}" alt="${escapeHtml(entry.serie_name)}" loading="lazy" referrerpolicy="no-referrer" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'card__poster card__ph card__ph--history',textContent:'${escapeHtml(initial(entry.serie_name))}'}))">`
            : `<div class="card__poster card__ph card__ph--history">${escapeHtml(initial(entry.serie_name))}</div>`;
        const card = el('div','card card--history',`
            ${posterContent}
            <div class="card__progress"><div class="card__progress-bar" style="width:${Math.min(95, Math.max(8, Math.random()*80+10))}%"></div></div>
            <div class="card__info card__info--visible">
                <div class="card__name">${escapeHtml(entry.serie_name)}</div>
                <div class="card__ep">\u00c9p. ${entry.episode_index}${entry.episode_name?' \u2014 '+escapeHtml(entry.episode_name):''}</div>
            </div>`);
        card.addEventListener('click', () => {
            // If we have a catalogue_url, open the detail page
            if (entry.catalogue_url) {
                openDetail(entry.catalogue_url, { name: entry.serie_name, image_url: entry.image_url });
            } else if (entry.player_url) {
                // Otherwise play directly
                openModal('player');
                playUrl(entry.player_url);
                state.currentEpisode = { index:entry.episode_index, name:entry.episode_name, available_languages:[entry.language||'VF'], languages:{} };
                state.currentContext = { serieName:entry.serie_name, seasonUrl:entry.season_url, seasonName:entry.season_name };
                renderPlayerBar(entry.language||'VF', 0);
            }
        });
        return card;
    }

    /* ---- Rows ------------------------------------------------------------ */
    function makeRow(title, items, cardFn) {
        const row = el('section','row');
        row.innerHTML = `<div class="row__head"><h2 class="row__title">${escapeHtml(title)}</h2><span class="row__count">${items.length}</span></div><div class="row__viewport"><button class="row__arrow row__arrow--prev" aria-label="Précédent"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button><button class="row__arrow row__arrow--next" aria-label="Suivant"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button><div class="row__track"></div></div>`;
        const track = $('.row__track', row);
        const fn = cardFn || makeCard;
        items.forEach(it => track.appendChild(fn(it)));
        const scrollBy = () => Math.round(track.clientWidth * 0.8);
        $('.row__arrow--prev',row).addEventListener('click',()=>track.scrollBy({left:-scrollBy(),behavior:'smooth'}));
        $('.row__arrow--next',row).addEventListener('click',()=>track.scrollBy({left:scrollBy(),behavior:'smooth'}));
        return row;
    }
    function skeletonRow() {
        const row = el('section','row');
        row.innerHTML = `<div class="row__head"><div class="sk" style="width:180px;height:22px;border-radius:6px"></div></div><div class="row__track"></div>`;
        const track = $('.row__track',row);
        for(let i=0;i<7;i++) track.appendChild(skeletonCard());
        return row;
    }

    /* ---- Hero ------------------------------------------------------------ */
    function renderHero() {
        const items = state.heroItems;
        const content = $('#heroContent');
        const dots = $('#heroDots');
        if (!items.length) {
            content.innerHTML = `<div class="hero__badge">Bienvenue</div><h1 class="hero__title">Anime-Sama</h1><p class="hero__desc">Découvrez plus de 1000 animes en streaming.</p><div class="hero__actions"><button class="btn btn--play" id="heroExplore"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg> Explorer</button></div>`;
            const exploreBtn=$('#heroExplore'); if(exploreBtn) exploreBtn.addEventListener('click', showNewEpisodes);
            dots.innerHTML=''; return;
        }
        const item = items[state.heroIndex % items.length];
        if (item.image_url) setBackground(item.image_url);

        const tags = [].concat((item.languages||[]).map(l=>`<span class="chip chip--lang">${escapeHtml(l)}</span>`)).concat((item.categories||[]).map(c=>`<span class="chip">${escapeHtml(c)}</span>`)).join('');
        content.classList.remove('fade-in'); void content.offsetWidth; content.classList.add('fade-in');
        content.innerHTML = `
            <div class="hero__badge">Ajout récent</div>
            <h1 class="hero__title">${escapeHtml(item.name)}</h1>
            <div class="hero__meta">${tags}</div>
            <p class="hero__desc">${escapeHtml(item.descriptive||item.synopsis||'')}</p>
            <div class="hero__actions">
                <button class="btn btn--play" id="heroPlay"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> Regarder</button>
                <button class="btn btn--ghost" id="heroInfo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01" stroke-linecap="round"/></svg> Plus d'infos</button>
                <button class="btn btn--icon btn--ghost" id="heroMute" title="Son" style="display:none"></button>
            </div>`;
        $('#heroInfo').addEventListener('click',()=>openDetail(item.url,item));
        $('#heroPlay').addEventListener('click',()=>openDetail(item.url,item));
        const muteBtn=$('#heroMute');
        if(muteBtn) muteBtn.addEventListener('click',()=>{state.trailerMuted=!state.trailerMuted;if(state.currentTrailerKey)showHeroTrailer(state.currentTrailerKey);});
        updateHeroMuteBtn();
        dots.innerHTML='';
        items.forEach((_,i)=>{const d=el('button','hero__dot'+(i===state.heroIndex%items.length?' is-active':''));d.addEventListener('click',()=>{state.heroIndex=i;renderHero();restartHeroTimer();});dots.appendChild(d);});
        // Play the trailer of the current hero anime directly
        scheduleHeroTrailer(item);
    }
    function restartHeroTimer(){clearInterval(state.heroTimer);if(state.heroItems.length>1){state.heroTimer=setInterval(()=>{state.heroIndex=(state.heroIndex+1)%state.heroItems.length;renderHero();},HERO_ROTATE_MS);}}

    /* ---- Hero trailer ---------------------------------------------------- */
    function scheduleHeroTrailer(item){clearTimeout(state.trailerTimer);removeHeroTrailer();state.trailerTimer=setTimeout(()=>loadHeroTrailer(item),600);}
    async function loadHeroTrailer(item){
        const name=item&&item.name; if(!name) return;
        let key=state.trailerCache.get(name);
        if(key===undefined){try{const res=await api('/anime/trailer',{params:{title:name}});key=(res&&res.data&&res.data.youtube_key)||null;}catch(_){key=null;}state.trailerCache.set(name,key);}
        const current=state.heroItems[state.heroIndex%(state.heroItems.length||1)];
        if(!key||!current||current.name!==name) return;
        if($('#hero').style.display==='none') return;
        showHeroTrailer(key);
    }
    function showHeroTrailer(key){
        state.currentTrailerKey=key; const box=$('#bgTrailer'); if(!box) return;
        // Use youtube.com/embed (not youtube-nocookie) + origin to avoid error 150/153
        const origin = location.origin;
        const params=new URLSearchParams({autoplay:'1',mute:state.trailerMuted?'1':'0',controls:'0',loop:'1',playlist:key,modestbranding:'1',playsinline:'1',rel:'0',showinfo:'0',iv_load_policy:'3',disablekb:'1',fs:'0',origin:origin,widget_referrer:origin});
        box.innerHTML=`<iframe src="https://www.youtube.com/embed/${encodeURIComponent(key)}?${params}" allow="autoplay; encrypted-media" frameborder="0" referrerpolicy="no-referrer-when-downgrade"></iframe>`;
        box.classList.add('is-on'); state.trailerActive=true; updateHeroMuteBtn();
    }
    function removeHeroTrailer(){const box=$('#bgTrailer');if(box){box.classList.remove('is-on');box.innerHTML='';}state.trailerActive=false;state.currentTrailerKey=null;updateHeroMuteBtn();}
    function updateHeroMuteBtn(){const b=$('#heroMute');if(!b)return;b.style.display=state.trailerActive?'inline-flex':'none';b.title=state.trailerMuted?'Activer le son':'Couper le son';b.innerHTML=state.trailerMuted?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M23 9l-6 6M17 9l6 6" stroke-linecap="round"/></svg>':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 010 7M19 5a9 9 0 010 14" stroke-linecap="round"/></svg>';}


    /* ---- Views ----------------------------------------------------------- */
    function showHomeChrome(show){$('#hero').style.display=show?'':'none';}

    async function renderHome() {
        setActiveNav('home'); showHomeChrome(true); state.currentView='home';
        // Home no longer uses infinite scroll — drop any lingering handler
        if(state._scrollHandler){window.removeEventListener('scroll',state._scrollHandler);state._scrollHandler=null;}
        const rows=$('#rows'); rows.style.marginTop=''; rows.innerHTML=''; rows.appendChild(skeletonRow());

        let latest=[];
        try { const res=await api('/anime/new-episodes',{params:{per_page:30}}); latest=(res.data||[]).filter(x=>x.name); }
        catch(e){ state.heroItems=[]; renderHero(); rows.innerHTML=`<div class="empty">Impossible de charger : ${escapeHtml(e.message)}</div>`; return; }

        state.heroItems=latest.filter(x=>x.image_url).slice(0,7);
        if(!state.heroItems.length&&latest.length) state.heroItems=latest.slice(0,5);
        state.heroIndex=0; renderHero(); restartHeroTimer();

        rows.innerHTML='';

        // Continue watching row (history)
        if(state.token){
            try{
                const hist=await api('/history',{auth:true});
                const entries=(hist.data||[]).slice(0,15);
                if(entries.length) rows.appendChild(makeRow('Reprendre la lecture',entries,makeHistoryCard));
            }catch(_){}
        }

        if(latest.length) rows.appendChild(makeRow('Ajouts récents',latest));

        // Genre-based rows (one carousel per genre) instead of repeated pages
        await renderGenreRows(rows);
    }

    /* ---- Home genre rows ------------------------------------------------- */
    async function renderGenreRows(rows){
        let genres=[];
        try{ const f=await api('/anime/filters'); genres=(f.genres||[]).filter(Boolean); }catch(_){}
        if(!genres.length) return;
        // Limit to a reasonable number of genre rows for the homepage
        genres=genres.slice(0,14);

        // Placeholder skeletons so the page doesn't look empty while loading
        const loader=el('div','scroll-sentinel'); loader.innerHTML='<span class="spinner"></span>';
        rows.appendChild(loader);

        // Fetch every genre in parallel, then render in a stable order
        const results=await Promise.all(genres.map(genre=>
            api('/anime/all-catalogues',{params:{genre,per_page:20,sort:'newest'}})
                .then(res=>({genre,items:(res.data||[]).filter(x=>x.name)}))
                .catch(()=>({genre,items:[]}))
        ));

        // Bail out if the user navigated away while loading
        if(state.currentView!=='home'){ if(loader.parentNode) loader.remove(); return; }
        if(loader.parentNode) loader.remove();

        results.forEach(({genre,items})=>{
            if(items.length) rows.appendChild(makeRow(genre,items));
        });
    }

    /* ---- Infinite scroll ------------------------------------------------- */
    function setupInfiniteScroll(){
        const onScroll=()=>{
            if(state.loadingMore||state.currentPage>=state.lastPage) return;
            const sentinel=$('#scrollSentinel');
            if(!sentinel) return;
            const rect=sentinel.getBoundingClientRect();
            if(rect.top<window.innerHeight+INFINITE_SCROLL_THRESHOLD) loadMoreAnimes();
        };
        window.addEventListener('scroll',onScroll,{passive:true});
        // Store cleanup ref
        state._scrollHandler=onScroll;
    }
    async function loadMoreAnimes(){
        if(state.loadingMore) return;
        state.loadingMore=true;
        if(state.currentView==='catalogue'){
            await loadCatalogueItems(false);
            return;
        }
        const sentinel=$('#scrollSentinel');
        if(sentinel) sentinel.innerHTML='<div class="center-state" style="padding:20px"><span class="spinner"></span></div>';
        try{
            const nextPage=state.currentPage+1;
            const res=await api('/anime/new-episodes',{params:{page:nextPage,per_page:30}});
            state.currentPage=res.current_page||nextPage;
            state.lastPage=res.last_page||state.lastPage;
            const items=(res.data||[]).filter(x=>x.name);
            if(items.length){
                const rows=$('#rows');
                const row=makeRow(``,items);
                if(sentinel) rows.insertBefore(row,sentinel);
                else rows.appendChild(row);
            }
            if(state.currentPage>=state.lastPage&&sentinel) sentinel.remove();
            else if(sentinel) sentinel.innerHTML='';
        }catch(_){if(sentinel)sentinel.innerHTML='';}
        state.loadingMore=false;
    }

    function renderGridView(title,sub,items,emptyMsg){
        clearInterval(state.heroTimer);clearTimeout(state.trailerTimer);removeHeroTrailer();showHomeChrome(false);
        if(state._scrollHandler){window.removeEventListener('scroll',state._scrollHandler);state._scrollHandler=null;}
        const rows=$('#rows'); rows.style.marginTop='0'; rows.innerHTML='';
        const view=el('section','view');
        view.innerHTML=`<div class="view__head"><h1 class="view__title">${escapeHtml(title)}</h1><span class="view__sub">${escapeHtml(sub||'')}</span>${items.length>1?`<div class="view__sort"><button class="sort-btn is-active" data-sort="newest"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg> Plus récent</button><button class="sort-btn" data-sort="oldest"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Plus ancien</button></div>`:''}</div>`;
        if(!items.length){view.appendChild(el('div','empty',escapeHtml(emptyMsg||'Aucun résultat.')));}
        else{
            const grid=el('div','grid');items.forEach(it=>grid.appendChild(makeCard(it)));view.appendChild(grid);
            const sortBtns=view.querySelectorAll('[data-sort]');
            sortBtns.forEach(btn=>{btn.addEventListener('click',()=>{sortBtns.forEach(b=>b.classList.remove('is-active'));btn.classList.add('is-active');const sorted=btn.dataset.sort==='oldest'?[...items].reverse():[...items];grid.innerHTML='';sorted.forEach(it=>grid.appendChild(makeCard(it)));});});
        }
        rows.appendChild(view);
        window.scrollTo({top:0,behavior:'smooth'});
    }

    function loadingView(title){clearTimeout(state.trailerTimer);removeHeroTrailer();showHomeChrome(false);const rows=$('#rows');rows.style.marginTop='0';rows.innerHTML='';const view=el('section','view');view.innerHTML=`<div class="view__head"><h1 class="view__title">${escapeHtml(title)}</h1></div>`;const grid=el('div','grid');for(let i=0;i<12;i++)grid.appendChild(skeletonCard());view.appendChild(grid);rows.appendChild(view);}

    async function runSearch(query){
        setActiveNav('search');loadingView('Recherche\u2026');
        try{
            const res=await api('/anime/search',{params:{q:query}});
            const items=res.data||[];
            if(items.length){
                renderGridView(`R\u00e9sultats \u00b7 ${query}`,`${items.length} anime(s)`,items,``);
            } else {
                // No results — show content request form
                clearInterval(state.heroTimer);clearTimeout(state.trailerTimer);removeHeroTrailer();showHomeChrome(false);
                const rows=$('#rows');rows.style.marginTop='0';rows.innerHTML='';
                const view=el('section','view');
                view.innerHTML=`
                    <div class="view__head"><h1 class="view__title">Aucun r\u00e9sultat pour \u00ab ${escapeHtml(query)} \u00bb</h1></div>
                    <div class="request-box">
                        <div class="request-box__icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01" stroke-linecap="round"/></svg></div>
                        <h3>Cet anime n'est pas encore disponible</h3>
                        <p>Envoyez une demande et nous l'ajouterons au catalogue !</p>
                        <div class="request-box__form">
                            <input id="requestTitle" value="${escapeHtml(query)}" placeholder="Nom de l'anime" class="request-box__input">
                            <textarea id="requestMsg" placeholder="Message (optionnel, ex: lien, saison souhait\u00e9e...)" class="request-box__textarea"></textarea>
                            <button class="btn btn--primary btn--block" id="requestSubmit">Envoyer la demande</button>
                        </div>
                        <p class="request-box__status" id="requestStatus"></p>
                    </div>`;
                rows.appendChild(view);
                $('#requestSubmit').addEventListener('click', async()=>{
                    const title=$('#requestTitle').value.trim();
                    const message=$('#requestMsg').value.trim();
                    const statusEl=$('#requestStatus');
                    if(!title){statusEl.textContent='Veuillez entrer un nom.';statusEl.style.color='var(--accent-2)';return;}
                    try{
                        await api('/anime/request',{method:'POST',body:{title,message:message||null},auth:!!state.token});
                        statusEl.textContent='\u2705 Demande envoy\u00e9e ! Merci.';statusEl.style.color='#4fe0a0';
                        $('#requestSubmit').disabled=true;$('#requestSubmit').textContent='Envoy\u00e9';
                    }catch(e){statusEl.textContent=e.message;statusEl.style.color='var(--accent-2)';}
                });
                window.scrollTo({top:0,behavior:'smooth'});
            }
        }catch(e){renderGridView('Recherche','',[], e.message);}
    }

    async function showNewEpisodes(){
        setActiveNav('new'); state.currentView='catalogue';
        clearInterval(state.heroTimer);clearTimeout(state.trailerTimer);removeHeroTrailer();showHomeChrome(false);
        if(state._scrollHandler){window.removeEventListener('scroll',state._scrollHandler);state._scrollHandler=null;}
        const rows=$('#rows'); rows.style.marginTop='0'; rows.innerHTML='<div class="center-state"><span class="spinner"></span></div>';

        // Load filters
        let genres=[],languages=[],categories=[];
        try{ const f=await api('/anime/filters'); genres=f.genres||[]; languages=f.languages||[]; categories=f.categories||[]; }catch(_){}

        // Initial load
        state.catalogueFilters={genre:null,language:null,category:null,sort:'newest'};
        state.currentPage=1; state.lastPage=1;

        rows.innerHTML='';
        const view=el('section','view');

        // Filter bar (like anime-sama.to)
        const filterHtml=`
            <div class="view__head">
                <h1 class="view__title">Catalogue</h1>
                <span class="view__sub" id="catalogueCount"></span>
            </div>
            <div class="filter-bar" id="filterBar">
                <div class="filter-group">
                    <span class="filter-group__label">Genre</span>
                    <div class="filter-chips" id="filterGenres">
                        <button class="filter-chip is-active" data-filter="genre" data-value="">Tous</button>
                        ${genres.slice(0,20).map(g=>`<button class="filter-chip" data-filter="genre" data-value="${escapeHtml(g)}">${escapeHtml(g)}</button>`).join('')}
                    </div>
                </div>
                <div class="filter-group">
                    <span class="filter-group__label">Langue</span>
                    <div class="filter-chips" id="filterLangs">
                        <button class="filter-chip is-active" data-filter="language" data-value="">Toutes</button>
                        ${languages.map(l=>`<button class="filter-chip" data-filter="language" data-value="${escapeHtml(l)}">${escapeHtml(LANG_LABEL[l]||l)}</button>`).join('')}
                    </div>
                </div>
                <div class="filter-group">
                    <span class="filter-group__label">Type</span>
                    <div class="filter-chips" id="filterCats">
                        <button class="filter-chip is-active" data-filter="category" data-value="">Tous</button>
                        ${categories.slice(0,15).map(c=>`<button class="filter-chip" data-filter="category" data-value="${escapeHtml(c)}">${escapeHtml(c)}</button>`).join('')}
                    </div>
                </div>
                <div class="filter-group">
                    <span class="filter-group__label">Tri</span>
                    <div class="filter-chips">
                        <button class="filter-chip is-active" data-filter="sort" data-value="newest">Plus récent</button>
                        <button class="filter-chip" data-filter="sort" data-value="oldest">Plus ancien</button>
                        <button class="filter-chip" data-filter="sort" data-value="name">A → Z</button>
                    </div>
                </div>
            </div>
            <div class="grid" id="catalogueGrid"></div>
            <div class="scroll-sentinel" id="scrollSentinel"></div>`;
        view.innerHTML=filterHtml;
        rows.appendChild(view);

        // Wire filter clicks
        $$('.filter-chip',view).forEach(chip=>{
            chip.addEventListener('click',()=>{
                const filterType=chip.dataset.filter;
                const filterValue=chip.dataset.value;
                // Toggle active state within same group
                const parent=chip.closest('.filter-chips')||chip.closest('.filter-group');
                $$('.filter-chip',parent).forEach(c=>c.classList.remove('is-active'));
                chip.classList.add('is-active');
                // Update state
                state.catalogueFilters[filterType]=filterValue||null;
                // Reload
                state.currentPage=1; state.lastPage=1;
                loadCatalogueItems(true);
            });
        });

        loadCatalogueItems(true);
        setupInfiniteScroll();
    }

    async function loadCatalogueItems(clear){
        const grid=$('#catalogueGrid');
        const sentinel=$('#scrollSentinel');
        if(!grid) return;
        if(clear) grid.innerHTML='<div class="center-state" style="grid-column:1/-1"><span class="spinner"></span></div>';
        else if(sentinel) sentinel.innerHTML='<div class="center-state" style="padding:20px"><span class="spinner"></span></div>';

        const params={per_page:30, page: clear?1:state.currentPage+1};
        const f=state.catalogueFilters||{};
        if(f.genre) params.genre=f.genre;
        if(f.language) params.language=f.language;
        if(f.category) params.category=f.category;
        if(f.sort) params.sort=f.sort;

        try{
            const res=await api('/anime/all-catalogues',{params});
            state.currentPage=res.current_page||1;
            state.lastPage=res.last_page||1;
            const items=(res.data||[]).filter(x=>x.name);

            if(clear) grid.innerHTML='';
            items.forEach(it=>grid.appendChild(makeCard(it)));

            const countEl=$('#catalogueCount');
            if(countEl) countEl.textContent=`${res.total||0} anime(s)`;

            if(clear&&!items.length) grid.innerHTML='<div class="empty" style="grid-column:1/-1">Aucun anime ne correspond aux filtres.</div>';
            if(sentinel){
                sentinel.innerHTML='';
                if(state.currentPage>=state.lastPage) sentinel.style.display='none';
                else sentinel.style.display='';
            }
        }catch(e){
            if(clear) grid.innerHTML=`<div class="empty" style="grid-column:1/-1">${escapeHtml(e.message)}</div>`;
            if(sentinel) sentinel.innerHTML='';
        }
        state.loadingMore=false;
    }

    async function showMyList(){
        setActiveNav('list');if(!state.token){openAuth();return;}loadingView('Ma liste');
        try{const res=await api('/favorites',{auth:true});const items=(res.data||[]).map(f=>({url:f.catalogue_url,_catalogueUrl:f.catalogue_url,name:f.name,image_url:f.image_url}));renderGridView('Ma liste',`${items.length} favori(s)`,items,'Votre liste est vide.');}
        catch(e){renderGridView('Ma liste','',[], e.message);}
    }


    /* ---- Planning modal -------------------------------------------------- */
    async function openPlanning(){
        openModal('planning');
        const grid=$('#planningGrid');
        grid.innerHTML='<div class="center-state"><span class="spinner"></span></div>';

        try{
            const res=await api('/anime/planning');
            const data=res.data||{};
            grid.innerHTML=DAYS.map((day,i)=>{
                const entries=data[i]||[];
                const content=entries.length?entries.map(e=>`
                    <div class="planning__entry">
                        ${e.anime&&e.anime.image_url?`<img class="planning__thumb" src="${escapeHtml(e.anime.image_url)}" referrerpolicy="no-referrer" onerror="this.style.display='none'">`:''}
                        <div class="planning__info">
                            <span class="planning__anime-name">${escapeHtml(e.anime?e.anime.name:'?')}</span>
                            ${e.time?`<span class="planning__time">${escapeHtml(e.time)}</span>`:''}
                            ${e.note?`<span class="planning__note">${escapeHtml(e.note)}</span>`:''}
                        </div>
                    </div>`).join(''):'<span class="planning__empty">Pas de sortie prévue</span>';
                return `<div class="planning__day"><h3 class="planning__day-name">${day}</h3><div class="planning__day-content">${content}</div></div>`;
            }).join('');
        }catch(e){
            grid.innerHTML=`<div class="empty">${escapeHtml(e.message)}</div>`;
        }
    }

    /* ---- Account / Profile (mobile) -------------------------------------- */
    function openAccount(){
        if(!state.token){openAuth();return;}
        openModal('account');
        renderAccountPanel();
    }
    function renderAccountPanel(){
        const user=state.user; if(!user) return;
        const body=$('#accountBody');
        const avatarChar=initial(user.name);
        const isAdmin=user.is_admin;
        body.innerHTML=`
            <div class="account__header">
                <div class="account__avatar">${user.avatar_url?`<img src="${escapeHtml(user.avatar_url)}" alt="">`:`<span>${escapeHtml(avatarChar)}</span>`}</div>
                <h2 class="account__name">${escapeHtml(user.name)}</h2>
                <p class="account__email">${escapeHtml(user.email)}</p>
                ${isAdmin?'<span class="account__badge">Admin</span>':''}
            </div>
            <div class="account__section">
                <h3>Profil</h3>
                <div class="account__field">
                    <label>Nom</label>
                    <input id="accName" value="${escapeHtml(user.name)}" placeholder="Votre nom">
                </div>
                <div class="account__field">
                    <label>Photo de profil (URL)</label>
                    <input id="accAvatar" value="${escapeHtml(user.avatar_url||'')}" placeholder="https://...">
                </div>
                <div class="account__field">
                    <label>Langue préférée</label>
                    <select id="accLang">
                        <option value="">Auto</option>
                        ${LANG_ORDER.map(l=>`<option value="${l}" ${(user.preferred_language||'')===l?'selected':''}>${LANG_LABEL[l]||l}</option>`).join('')}
                    </select>
                </div>
                <button class="btn btn--primary btn--block" id="accSave">Enregistrer</button>
            </div>
            <div class="account__section">
                <h3>Sécurité</h3>
                <div class="account__field"><label>Mot de passe actuel</label><input id="accPwdOld" type="password" placeholder="••••••••"></div>
                <div class="account__field"><label>Nouveau mot de passe</label><input id="accPwdNew" type="password" placeholder="••••••••"></div>
                <div class="account__field"><label>Confirmer</label><input id="accPwdConf" type="password" placeholder="••••••••"></div>
                <button class="btn btn--ghost btn--block" id="accPwdSave">Changer le mot de passe</button>
            </div>
            ${isAdmin?'<div class="account__section"><a class="btn btn--auth btn--block" href="/admin">Accéder au Panel Admin</a></div>':''}
            <div class="account__section">
                <button class="btn btn--ghost btn--block" id="accLogout" style="color:var(--accent-2)">Se déconnecter</button>
            </div>
            <p class="account__msg" id="accMsg"></p>`;
        $('#accSave').addEventListener('click', saveProfile);
        $('#accPwdSave').addEventListener('click', changePassword);
        $('#accLogout').addEventListener('click', ()=>{closeModal('account');logout();});
    }
    async function saveProfile(){
        const msg=$('#accMsg'); msg.textContent='';
        const body={name:$('#accName').value.trim(),avatar_url:$('#accAvatar').value.trim()||null,preferred_language:$('#accLang').value||null};
        try{
            const res=await api('/auth/profile',{method:'PUT',auth:true,body});
            state.user=res.data; localStorage.setItem('as_user',JSON.stringify(res.data));
            if(res.data.preferred_language) state.preferredLang=res.data.preferred_language;
            renderAuthUI(); toast('Profil mis à jour');
            renderAccountPanel();
        }catch(e){msg.textContent=e.message;msg.style.color='var(--danger,#ff4d6d)';}
    }
    async function changePassword(){
        const msg=$('#accMsg'); msg.textContent='';
        const body={current_password:$('#accPwdOld').value,password:$('#accPwdNew').value,password_confirmation:$('#accPwdConf').value};
        if(!body.current_password||!body.password){msg.textContent='Remplissez tous les champs.';msg.style.color='var(--danger,#ff4d6d)';return;}
        try{await api('/auth/password',{method:'PUT',auth:true,body});toast('Mot de passe changé');$('#accPwdOld').value='';$('#accPwdNew').value='';$('#accPwdConf').value='';}
        catch(e){msg.textContent=e.message;msg.style.color='var(--danger,#ff4d6d)';}
    }


    /* ---- Detail modal ---------------------------------------------------- */
    async function openDetail(catalogueUrl, hint={}, autoPlaySeasonUrl=null){
        openModal('detail');const body=$('#detailBody');
        body.innerHTML=`<div class="center-state"><span class="spinner"></span><p>Chargement\u2026</p></div>`;
        let data;try{const res=await api('/anime/catalogue',{params:{url:catalogueUrl}});data=res.data||{};}catch(e){body.innerHTML=`<div class="center-state"><p>${escapeHtml(e.message)}</p></div>`;return;}
        const name=data.name||hint.name||'Anime';const fav=state.favorites.has(catalogueUrl);
        const meta=[].concat((data.languages||[]).map(l=>`<span class="chip chip--lang">${escapeHtml(l)}</span>`)).concat((data.genres||[]).slice(0,5).map(g=>`<span class="chip">${escapeHtml(g)}</span>`)).concat(data.is_mature?['<span class="chip" style="border-color:rgba(255,90,120,.5);color:#ff9db0">18+</span>']:[]).join('');
        const detailImage=data.image_url||hint.image_url||'';
        body.innerHTML=`
            <div class="detail__hero" id="detailHero" style="position:relative">${detailImage?`<img src="${escapeHtml(detailImage)}" alt="" referrerpolicy="no-referrer">`:'<div class="card__ph" style="position:relative;height:100%">'+escapeHtml(initial(name))+'</div>'}<button class="detail__hero-play" id="detailHeroPlay" style="display:none" aria-label="Lire la bande-annonce"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></button><div class="detail__heroText"><h2 class="detail__title">${escapeHtml(name)}</h2></div></div>
            <div class="detail__content">
                <div class="detail__meta">${meta}</div>
                <div class="detail__actions">
                    <button class="btn btn--ghost" id="detailTrailer" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16v12H4z"/><path d="M10 9l5 3-5 3z" fill="currentColor" stroke="none"/></svg> Bande-annonce</button>
                    <button class="btn ${fav?'btn--primary':'btn--ghost'}" id="detailFav"><svg viewBox="0 0 24 24" fill="${fav?'currentColor':'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg> ${fav?'Dans ma liste':'Ma liste'}</button>
                </div>
                ${data.synopsis?`<p class="detail__synopsis">${escapeHtml(data.synopsis)}</p>`:''}
                <div class="detail__section-title">Saisons & \u00e9pisodes</div>
                <div class="seasons" id="seasons"></div>
                <div id="episodesWrap"><div class="center-state"><span class="spinner"></span></div></div>
            </div>`;
        $('#detailFav').addEventListener('click',()=>toggleFavorite(catalogueUrl,name,detailImage));
        setupDetailTrailer(data,name);
        const seasons=data.seasons||[];const seasonsEl=$('#seasons');
        if(!seasons.length){seasonsEl.innerHTML='';$('#episodesWrap').innerHTML='<div class="empty" style="padding:20px 0">Aucune saison.</div>';return;}
        seasons.forEach((s,i)=>{const pill=el('button','season-pill'+(i===0?' is-active':''),escapeHtml(s.name||`Saison ${i+1}`));pill.addEventListener('click',()=>{$$('.season-pill',seasonsEl).forEach(p=>p.classList.remove('is-active'));pill.classList.add('is-active');loadEpisodes(s,name,detailImage,catalogueUrl);});seasonsEl.appendChild(pill);});
        await loadEpisodes(seasons[0],name,detailImage,catalogueUrl);
        if(autoPlaySeasonUrl){const target=seasons.find(s=>autoPlaySeasonUrl.startsWith(s.url))||seasons[0];const idx=seasons.indexOf(target);if(idx>0){$$('.season-pill',seasonsEl).forEach((p,i)=>p.classList.toggle('is-active',i===idx));await loadEpisodes(target,name,detailImage,catalogueUrl);}const firstEp=$('#episodesWrap .ep');if(firstEp)firstEp.click();}
    }

    async function setupDetailTrailer(data,name){
        const btn=$('#detailTrailer');if(!btn) return;
        // Use trailer_key from the anime data directly (set by admin via trailer_url)
        let key=data.trailer_key||null;
        if(!key && data.trailer_url){
            // Try to extract key from URL directly
            const m=String(data.trailer_url).match(/(?:youtu\.be\/|[?&]v=|\/embed\/|\/shorts\/)([A-Za-z0-9_-]{11})/);
            if(m) key=m[1];
            else if(/^[A-Za-z0-9_-]{11}$/.test(data.trailer_url)) key=data.trailer_url;
        }
        if(!key){
            // Last resort: lookup by title in DB
            try{const r=await api('/anime/trailer',{params:{title:name}});key=(r&&r.data&&r.data.youtube_key)||null;}catch(_){}
        }
        if(!key||!$('#detailModal').classList.contains('is-open')) return;
        btn.style.display='';
        btn.addEventListener('click',()=>playDetailTrailer(key));
        // Big play button overlaid on the hero image → launches the trailer directly
        const heroPlay=$('#detailHeroPlay');
        if(heroPlay){heroPlay.style.display='';heroPlay.addEventListener('click',()=>playDetailTrailer(key));}
    }
    function playDetailTrailer(key){
        const hero=$('#detailHero');if(!hero) return;
        const heroPlay=$('#detailHeroPlay');if(heroPlay) heroPlay.style.display='none';
        const origin=location.origin;
        const params=new URLSearchParams({autoplay:'1',rel:'0',modestbranding:'1',playsinline:'1',origin:origin});
        let box=hero.querySelector('.detail__hero-trailer');
        if(!box){box=el('div','detail__hero-trailer');box.style.cssText='position:absolute;inset:0;z-index:3;background:#000';hero.appendChild(box);}
        box.innerHTML=`<iframe src="https://www.youtube.com/embed/${encodeURIComponent(key)}?${params}" allow="autoplay; encrypted-media; fullscreen" allowfullscreen frameborder="0" style="width:100%;height:100%" referrerpolicy="no-referrer-when-downgrade"></iframe>`;
    }

    async function loadEpisodes(season,serieName,imageUrl,catalogueUrl){
        const wrap=$('#episodesWrap');wrap.innerHTML=`<div class="center-state"><span class="spinner"></span><p>Chargement\u2026</p></div>`;
        let episodes=[];try{const res=await api('/anime/episodes',{params:{url:season.url}});episodes=res.data||[];}catch(e){wrap.innerHTML=`<div class="empty" style="padding:20px 0">${escapeHtml(e.message)}</div>`;return;}
        if(!episodes.length){wrap.innerHTML='<div class="empty" style="padding:20px 0">Aucun \u00e9pisode.</div>';return;}
        const allLangs=new Set();episodes.forEach(ep=>(ep.available_languages||[]).forEach(l=>allLangs.add(l)));
        const sortedLangs=orderLangs([...allLangs]);
        if(!state.preferredLang||!allLangs.has(state.preferredLang)) state.preferredLang=sortedLangs.includes('VF')?'VF':(sortedLangs[0]||null);
        wrap.innerHTML='';
        if(sortedLangs.length>1){const langBar=el('div','detail__lang-bar');langBar.innerHTML=`<span class="detail__lang-label">Langue :</span>`;sortedLangs.forEach(lang=>{const btn=el('button','lang-pill'+(lang===state.preferredLang?' is-active':''),escapeHtml(LANG_LABEL[lang]||lang));btn.addEventListener('click',()=>{state.preferredLang=lang;$$('.lang-pill',langBar).forEach(b=>b.classList.remove('is-active'));btn.classList.add('is-active');});langBar.appendChild(btn);});wrap.appendChild(langBar);}
        const grid=el('div','episodes');
        episodes.forEach(ep=>{const langs=(ep.available_languages||[]).join(' \u00b7 ');const item=el('button','ep',`<span class="ep__num">${ep.index}</span><span style="min-width:0"><span class="ep__name">${escapeHtml(ep.name||('\u00c9pisode '+ep.index))}</span><span class="ep__langs">${escapeHtml(langs)}</span></span><span class="ep__play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>`);item.addEventListener('click',()=>openPlayer(ep,{serieName,seasonUrl:season.url,seasonName:season.name,imageUrl:imageUrl||null,catalogueUrl:catalogueUrl||null}));grid.appendChild(item);});
        wrap.appendChild(grid);
    }


    /* ---- Player modal ---------------------------------------------------- */
    function mirrorsForLanguage(ep,lang){const groups=(ep.languages||{})[lang]||[];return groups.flat();}
    function orderLangs(langs){return[...(langs||[])].sort((a,b)=>{const ia=LANG_ORDER.indexOf(a);const ib=LANG_ORDER.indexOf(b);return(ia===-1?99:ia)-(ib===-1?99:ib);});}
    function openPlayer(ep,context){state.currentEpisode=ep;state.currentContext=context;openModal('player');const langs=orderLangs(ep.available_languages||[]);let defaultLang;if(state.preferredLang&&langs.includes(state.preferredLang))defaultLang=state.preferredLang;else defaultLang=langs.includes('VF')?'VF':(langs[0]||null);renderPlayerBar(defaultLang,0);const mirrors=defaultLang?mirrorsForLanguage(ep,defaultLang):[];const first=mirrors[0]||ep.best_player;if(first)playUrl(first);else $('#playerLoader').innerHTML='<p style="color:var(--muted)">Aucun lecteur disponible.</p>';recordHistory(ep,context,defaultLang,first);}
    function renderPlayerBar(activeLang,activeMirror){const ep=state.currentEpisode;const bar=$('#playerBar');if(!ep)return;const langs=orderLangs(ep.available_languages||[]);const mirrors=activeLang?mirrorsForLanguage(ep,activeLang):[];const langBtns=langs.map(l=>`<button class="mirror ${l===activeLang?'is-active':''}" data-lang="${escapeHtml(l)}">${escapeHtml(LANG_LABEL[l]||l)}</button>`).join('');const mirrorBtns=mirrors.map((m,i)=>{let host='';try{host=new URL(m).hostname.replace(/^www\./,'');}catch(_){host='Lecteur '+(i+1);}return`<button class="mirror ${i===activeMirror?'is-active':''}" data-mirror="${i}">${escapeHtml(host)}</button>`;}).join('');bar.innerHTML=`<span class="player__title">${escapeHtml((state.currentContext?.serieName||'')+' \u2014 '+(ep.name||'\u00c9pisode '+ep.index))}</span>${langs.length?`<span class="player__label">Langue</span>${langBtns}`:''} ${mirrors.length?`<span class="player__label">Lecteur</span>${mirrorBtns}`:''}`;$$('[data-lang]',bar).forEach(b=>{b.addEventListener('click',e=>{e.preventDefault();const lang=b.getAttribute('data-lang');const ms=mirrorsForLanguage(ep,lang);renderPlayerBar(lang,0);if(ms[0]){playUrl(ms[0]);recordHistory(ep,state.currentContext,lang,ms[0]);}});});$$('[data-mirror]',bar).forEach(b=>{b.addEventListener('click',e=>{e.preventDefault();const i=+b.getAttribute('data-mirror');renderPlayerBar(activeLang,i);if(mirrors[i]){playUrl(mirrors[i]);recordHistory(ep,state.currentContext,activeLang,mirrors[i]);}});});}
    function playUrl(url){const frame=$('#playerIframe');const loader=$('#playerLoader');loader.style.display='grid';loader.innerHTML='<span class="spinner"></span>';frame.onload=()=>{loader.style.display='none';};frame.src=url;}
    function recordHistory(ep,context,language,playerUrl){if(!state.token||!context)return;api('/history',{method:'POST',auth:true,body:{serie_name:context.serieName||'Anime',season_url:context.seasonUrl,season_name:context.seasonName||null,episode_index:ep.index,episode_name:ep.name||null,player_url:playerUrl||null,language:language||null,image_url:context.imageUrl||null,catalogue_url:context.catalogueUrl||null}}).catch(()=>{});}

    /* ---- Favorites ------------------------------------------------------- */
    async function loadFavorites(){if(!state.token){state.favorites.clear();return;}try{const res=await api('/favorites',{auth:true});state.favorites.clear();(res.data||[]).forEach(f=>state.favorites.set(f.catalogue_url,f.id));}catch(_){}}
    async function toggleFavorite(url,name,image){if(!state.token){openAuth();return;}try{if(state.favorites.has(url)){const id=state.favorites.get(url);await api(`/favorites/${id}`,{method:'DELETE',auth:true});state.favorites.delete(url);toast('Retiré de ma liste');}else{const res=await api('/favorites',{method:'POST',auth:true,body:{catalogue_url:url,name,image_url:image||null}});state.favorites.set(url,res.data.id);toast('Ajouté à ma liste','ok');}const btn=$('#detailFav');if(btn){const fav=state.favorites.has(url);btn.className=`btn ${fav?'btn--primary':'btn--ghost'}`;btn.innerHTML=`<svg viewBox="0 0 24 24" fill="${fav?'currentColor':'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg> ${fav?'Dans ma liste':'Ma liste'}`;}}catch(e){toast(e.message,'err');}}

    /* ---- Auth ------------------------------------------------------------ */
    function openAuth(){setAuthMode('login');openModal('auth');$('#authError').textContent='';}
    function setAuthMode(mode){state.authMode=mode;const panel=$('#authModal .auth');panel.classList.toggle('mode-login',mode==='login');panel.classList.toggle('mode-register',mode==='register');$$('.auth__tab').forEach(t=>t.classList.toggle('is-active',t.dataset.tab===mode));$('#authSubmit').textContent=mode==='login'?'Se connecter':"S'inscrire";}
    async function submitAuth(e){e.preventDefault();const form=e.target;const btn=$('#authSubmit');const errEl=$('#authError');errEl.textContent='';btn.disabled=true;const original=btn.textContent;btn.textContent='\u2026';const payload={email:form.email.value.trim(),password:form.password.value,device_name:'web'};let path='/auth/login';if(state.authMode==='register'){path='/auth/register';payload.name=form.name.value.trim();payload.password_confirmation=form.password_confirmation.value;}try{const res=await api(path,{method:'POST',body:payload});state.token=res.token;state.user=res.user;localStorage.setItem('as_token',res.token);localStorage.setItem('as_user',JSON.stringify(res.user));if(res.user.preferred_language)state.preferredLang=res.user.preferred_language;await loadFavorites();renderAuthUI();closeModal('auth');form.reset();toast(`Bienvenue ${res.user.name} !`);renderHome();}catch(err){errEl.textContent=err.message;}finally{btn.disabled=false;btn.textContent=original;}}
    async function logout(){try{await api('/auth/logout',{method:'POST',auth:true});}catch(_){}state.token=null;state.user=null;state.favorites.clear();localStorage.removeItem('as_token');localStorage.removeItem('as_user');renderAuthUI();toast('Déconnecté');renderHome();}

    function renderAuthUI(){
        const holder=$('#authButton');
        if(!state.user){const btn=el('button','btn btn--auth','Se connecter');btn.id='authButton';btn.addEventListener('click',openAuth);holder.replaceWith(btn);return;}
        const menu=el('div','usermenu');menu.id='authButton';
        const avatarContent=state.user.avatar_url?`<img src="${escapeHtml(state.user.avatar_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">`:escapeHtml(initial(state.user.name));
        menu.innerHTML=`<button class="avatar" aria-label="Compte">${avatarContent}</button><div class="usermenu__pop"><div class="usermenu__name"><b>${escapeHtml(state.user.name)}</b><span>${escapeHtml(state.user.email)}</span></div>${state.user.is_admin?'<a class="usermenu__item" href="/admin">\u2699 Administration</a>':''}<button class="usermenu__item" data-act="account">\u263a Mon profil</button><button class="usermenu__item" data-act="list">\u2665 Ma liste</button><button class="usermenu__item" data-act="logout">\u238b Se d\u00e9connecter</button></div>`;
        holder.replaceWith(menu);
        $('.avatar',menu).addEventListener('click',e=>{e.stopPropagation();menu.classList.toggle('is-open');});
        const accBtn=$('[data-act="account"]',menu);if(accBtn) accBtn.addEventListener('click',()=>{menu.classList.remove('is-open');openAccount();});
        $('[data-act="list"]',menu).addEventListener('click',()=>{menu.classList.remove('is-open');showMyList();});
        $('[data-act="logout"]',menu).addEventListener('click',()=>{menu.classList.remove('is-open');logout();});
        document.addEventListener('click',()=>menu.classList.remove('is-open'));
    }

    /* ---- Modals ---------------------------------------------------------- */
    function openModal(name){clearTimeout(state.trailerTimer);removeHeroTrailer();$(`#${name}Modal`).classList.add('is-open');document.body.style.overflow='hidden';}
    function closeModal(name){$(`#${name}Modal`).classList.remove('is-open');document.body.style.overflow='';if(name==='player'){$('#playerIframe').src='about:blank';}if(name==='detail'){const box=$('#detailModal .detail__hero-trailer');if(box)box.remove();}}
    function anyModalOpen(){return['detail','player','auth','planning','account'].some(n=>$(`#${n}Modal`)&&$(`#${n}Modal`).classList.contains('is-open'));}
    function closeAllModals(){['detail','player','auth','planning','account'].forEach(closeModal);}

    /* ---- Navigation & wiring -------------------------------------------- */
    function setActiveNav(which){$$('.nav__link').forEach(l=>l.classList.toggle('is-active',l.dataset.nav===which));$$('.tabbar__item').forEach(t=>t.classList.toggle('is-active',t.dataset.tab===which));}
    function handleTab(tab){
        if(tab==='home') renderHome();
        else if(tab==='new') showNewEpisodes();
        else if(tab==='list') showMyList();
        else if(tab==='search'){window.scrollTo({top:0,behavior:'smooth'});const input=$('#searchInput');if(input){input.focus();input.select();}}
        else if(tab==='account') openAccount();
        else if(tab==='planning') openPlanning();
    }

    function wire(){
        const nav=$('#nav');
        const onScroll=()=>nav.classList.toggle('is-scrolled',window.scrollY>30);
        window.addEventListener('scroll',onScroll,{passive:true});onScroll();
        $$('.nav__link, .brand').forEach(a=>{a.addEventListener('click',e=>{const target=a.dataset.nav;if(!target)return;e.preventDefault();handleTab(target);});});
        $$('.tabbar__item').forEach(btn=>{btn.addEventListener('click',()=>handleTab(btn.dataset.tab));});
        const input=$('#searchInput');let deb;
        $('#searchForm').addEventListener('submit',e=>{e.preventDefault();if(input.value.trim())runSearch(input.value.trim());});
        input.addEventListener('input',()=>{clearTimeout(deb);const v=input.value.trim();if(!v){renderHome();return;}deb=setTimeout(()=>{if(input.value.trim())runSearch(input.value.trim());},550);});
        $$('[data-close]').forEach(b=>b.addEventListener('click',()=>closeModal(b.dataset.close)));
        document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAllModals();});
        $('#authButton').addEventListener('click',openAuth);
        $$('.auth__tab').forEach(t=>t.addEventListener('click',()=>{setAuthMode(t.dataset.tab);$('#authError').textContent='';}));
        $('#authForm').addEventListener('submit',submitAuth);
    }

    /* ---- Boot ------------------------------------------------------------ */
    async function boot(){
        setupBackground();wire();renderAuthUI();
        if(state.token){
            try{const me=await api('/auth/me',{auth:true});state.user=me;localStorage.setItem('as_user',JSON.stringify(me));if(me.preferred_language)state.preferredLang=me.preferred_language;renderAuthUI();await loadFavorites();}
            catch(_){state.token=null;state.user=null;localStorage.removeItem('as_token');localStorage.removeItem('as_user');renderAuthUI();}
        }
        renderHome();
    }
    document.addEventListener('DOMContentLoaded',boot);
})();
