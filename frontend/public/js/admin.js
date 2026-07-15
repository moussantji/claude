/* Admin dashboard for the normalized library (animes/seasons/episodes/links). */
(() => {
    'use strict';

    const API = '/api';
    // Reuse the main app's token so admins logged in on the site stay logged in here.
    let token = localStorage.getItem('as_token') || null;

    const $ = (s) => document.querySelector(s);
    const state = { page: 1, lastPage: 1, q: '', editing: null, season: null };
    const team = { page: 1, lastPage: 1, q: '', filter: '' };

    async function api(path, { method = 'GET', body } = {}) {
        const headers = { Accept: 'application/json' };
        if (body) headers['Content-Type'] = 'application/json';
        if (token) headers.Authorization = 'Bearer ' + token;
        const res = await fetch(API + path, { method, headers, body: body ? JSON.stringify(body) : undefined });
        let json = {};
        try { json = await res.json(); } catch (_) { /* noop */ }
        if (!res.ok) throw Object.assign(new Error(json.message || `Erreur ${res.status}`), { status: res.status, errors: json.errors });
        return json;
    }

    /** Like api() but sends FormData (multipart/form-data) — used for file uploads. */
    async function apiFormData(path, formData) {
        const headers = { Accept: 'application/json' };
        if (token) headers.Authorization = 'Bearer ' + token;
        const res = await fetch(API + path, { method: 'POST', headers, body: formData });
        let json = {};
        try { json = await res.json(); } catch (_) { /* noop */ }
        if (!res.ok) throw Object.assign(new Error(json.message || `Erreur ${res.status}`), { status: res.status, errors: json.errors });
        return json;
    }

    function toast(msg) {
        const t = $('#toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2200);
    }

    const escapeHtml = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    function show(view) {
        $('#login').classList.toggle('hide', view !== 'login');
        $('#dash').classList.toggle('hide', view !== 'dash');
    }

    /* ---- Auth ---- */
    async function boot() {
        wire();
        if (!token) { show('login'); return; }
        try {
            const me = await api('/auth/me');
            if (!me.is_admin) throw new Error('not admin');
            startDash();
        } catch (_) {
            show('login');
        }
    }

    async function doLogin(e) {
        e.preventDefault();
        const f = e.target;
        $('#loginErr').textContent = '';
        try {
            const res = await api('/auth/login', { method: 'POST', body: { email: f.email.value.trim(), password: f.password.value, device_name: 'admin' } });
            if (!res.user || !res.user.is_admin) { $('#loginErr').textContent = "Ce compte n'est pas administrateur."; return; }
            token = res.token;
            localStorage.setItem('as_token', token);
            localStorage.setItem('as_user', JSON.stringify(res.user));
            startDash();
        } catch (err) {
            $('#loginErr').textContent = err.message;
        }
    }

    function logout() {
        api('/auth/logout', { method: 'POST' }).catch(() => {});
        token = null;
        localStorage.removeItem('as_token');
        show('login');
    }

    /* ---- Dashboard ---- */
    async function startDash() {
        show('dash');
        loadStats();
        loadAnimes();
    }

    async function loadStats() {
        try {
            const { data } = await api('/admin/stats');
            const labels = { animes: 'Animes', seasons: 'Saisons', episodes: '\u00c9pisodes', episode_links: 'Liens vid\u00e9o', uploads: 'Uploads', users: 'Utilisateurs', requests: 'Demandes' };
            $('#stats').innerHTML = Object.entries(labels)
                .map(([k, label]) => `<div class="stat"><b>${data[k] ?? 0}</b><span>${label}</span></div>`).join('');
        } catch (_) { /* noop */ }
    }

    async function loadAnimes() {
        try {
            const res = await api(`/admin/animes?q=${encodeURIComponent(state.q)}&per_page=30&page=${state.page}`);
            state.lastPage = res.last_page || 1;
            state.page = res.current_page || 1;
            $('#rows').innerHTML = (res.data || []).map(rowHtml).join('') || '<tr><td colspan="6" class="muted">Aucun résultat.</td></tr>';
            (res.data || []).forEach((a) => {
                $(`#edit-${a.id}`).addEventListener('click', () => openEditor(a.id));
                $(`#del-${a.id}`).addEventListener('click', () => deleteAnime(a));
            });
            $('#pageInfo').textContent = `Page ${state.page} / ${state.lastPage} · ${res.total ?? 0} animes`;
        } catch (e) {
            $('#rows').innerHTML = `<tr><td colspan="6" class="err">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    function rowHtml(a) {
        const hasTrailer = a.trailer_url ? '\u2705' : '\u2014';
        const syn = a.synopsis ? (a.synopsis.length > 60 ? a.synopsis.slice(0, 60) + '\u2026' : a.synopsis) : '\u2014';
        return `<tr>
            <td><code style="font-size:12px;color:var(--accent)">${a.id}</code></td>
            <td>${a.image_url ? `<img class="thumb" src="${escapeHtml(a.image_url)}" referrerpolicy="no-referrer" onerror="this.style.visibility='hidden'">` : ''}</td>
            <td>${escapeHtml(a.name)}</td>
            <td>${a.seasons_count ?? '\u2014'}</td>
            <td>${hasTrailer}</td>
            <td class="muted">${escapeHtml(syn)}</td>
            <td><div class="row-actions">
                <button class="btn" id="edit-${a.id}">\u00c9diter</button>
                <button class="btn btn--danger" id="del-${a.id}">Suppr.</button>
            </div></td>
        </tr>`;
    }

    /* ---- Create Anime ---- */
    function openCreateAnime() {
        const f = $('#createAnimeForm');
        f.reset();
        $('#createAnimeErr').textContent = '';
        $('#createAnimeModal').classList.add('open');
    }

    async function submitCreateAnime(e) {
        e.preventDefault();
        const f = e.target;
        $('#createAnimeErr').textContent = '';
        const body = {
            name: f.name.value.trim(),
            synopsis: f.synopsis.value.trim() || null,
            image_url: f.image_url.value.trim() || null,
            trailer_url: f.trailer_url.value.trim() || null,
            is_mature: f.is_mature.checked,
        };
        if (!body.name) { $('#createAnimeErr').textContent = 'Le nom est requis.'; return; }
        try {
            await api('/admin/animes', { method: 'POST', body });
            $('#createAnimeModal').classList.remove('open');
            toast('Anime créé !');
            loadStats();
            loadAnimes();
        } catch (err) {
            $('#createAnimeErr').textContent = err.message;
        }
    }

    /** All known genres (loaded once from the public filters API). */
    let allGenres = [];
    async function loadGenres() {
        if (allGenres.length) return;
        try {
            const f = await api('/anime/filters');
            allGenres = f.genres || [];
        } catch (_) { /* noop */ }
    }

    /* ---- Editor ---- */
    async function openEditor(id) {
        await loadGenres();
        try {
            const { data } = await api(`/admin/animes/${id}`);
            state.editing = data;
            const f = $('#editForm');
            $('#editTitle').textContent = data.name;
            $('#editUrl').textContent = data.url;
            f.name.value = data.name || '';
            f.url.value = data.url || '';
            f.trailer_url.value = data.trailer_url || '';
            f.image_url.value = data.image_url || '';
            f.synopsis.value = data.synopsis || '';
            f.is_mature.checked = !!data.is_mature;
            $('#editErr').textContent = '';
            renderGenreSelector(data.genres || []);
            renderSeasons(data.seasons || []);
            $('#editModal').classList.add('open');
        } catch (e) { toast(e.message); }
    }

    /** Multi-select genre tags (click to toggle, type to add new). */
    function renderGenreSelector(selectedGenres) {
        const container = document.getElementById('genreSelector');
        if (!container) return;
        const selected = new Set(selectedGenres.map(g => g.trim()).filter(Boolean));
        const combined = [...new Set([...allGenres, ...selected])].sort();

        let html = `<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px" id="genreTags">`;
        combined.forEach(g => {
            const active = selected.has(g);
            html += `<button type="button" class="genre-tag ${active ? 'active' : ''}" data-genre="${escapeHtml(g)}">${escapeHtml(g)}</button>`;
        });
        html += `</div>`;
        html += `<div style="display:flex;gap:8px;align-items:center">
            <input id="genreCustom" placeholder="Ajouter un genre…" style="flex:1">
            <button type="button" class="btn" id="genreAddBtn">+</button>
        </div>`;
        container.innerHTML = html;

        // Toggle genre on click
        container.querySelectorAll('.genre-tag').forEach(tag => {
            tag.addEventListener('click', () => tag.classList.toggle('active'));
        });

        // Add custom genre
        const addCustom = () => {
            const input = document.getElementById('genreCustom');
            const val = input.value.trim();
            if (!val) return;
            // Check if already exists
            const existing = container.querySelector(`[data-genre="${CSS.escape(val)}"]`);
            if (existing) { existing.classList.add('active'); input.value = ''; return; }
            // Add new tag
            const tag = document.createElement('button');
            tag.type = 'button';
            tag.className = 'genre-tag active';
            tag.dataset.genre = val;
            tag.textContent = val;
            tag.addEventListener('click', () => tag.classList.toggle('active'));
            document.getElementById('genreTags').appendChild(tag);
            input.value = '';
        };
        document.getElementById('genreAddBtn').addEventListener('click', addCustom);
        document.getElementById('genreCustom').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); addCustom(); }
        });
    }

    /** Read the currently selected genres from the tag UI. */
    function getSelectedGenres() {
        const container = document.getElementById('genreTags');
        if (!container) return [];
        return Array.from(container.querySelectorAll('.genre-tag.active')).map(t => t.dataset.genre);
    }

    async function refreshEditor() {
        if (!state.editing) return;
        const { data } = await api(`/admin/animes/${state.editing.id}`);
        state.editing = data;
        renderSeasons(data.seasons || []);
    }

    /* ---- Seasons ---- */
    function renderSeasons(seasons) {
        const box = $('#editSeasons');
        let html = '';
        if (seasons.length) {
            html = seasons.map((s) => `
                <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)">
                    <span style="flex:1">${escapeHtml(s.name)} <span class="muted">\u00b7 ${s.episodes_count ?? 0} \u00e9p.</span></span>
                    <button class="btn" data-ep="${s.id}" data-title="${escapeHtml(s.name)}">Épisodes</button>
                    <button class="btn" data-sedit="${s.id}" data-name="${escapeHtml(s.name)}">Renommer</button>
                    <button class="btn btn--danger" data-sdel="${s.id}" data-name="${escapeHtml(s.name)}">Suppr.</button>
                </div>`).join('');
        } else {
            html = '<span class="muted">Aucune saison enregistrée.</span>';
        }
        // Add season creation form
        html += `
            <div style="display:flex;gap:8px;align-items:center;margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">
                <input id="newSeasonName" placeholder="Nom de la nouvelle saison" style="flex:1">
                <button class="btn btn--primary" id="addSeasonBtn">+ Ajouter saison</button>
            </div>`;
        box.innerHTML = html;

        // Wire the add season button
        $('#addSeasonBtn').addEventListener('click', addSeason);
        $('#newSeasonName').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addSeason(); } });
    }

    async function addSeason() {
        if (!state.editing) return;
        const input = $('#newSeasonName');
        const name = input.value.trim();
        if (!name) { toast('Le nom de la saison est requis'); return; }
        try {
            await api(`/admin/animes/${state.editing.id}/seasons`, { method: 'POST', body: { name } });
            toast('Saison ajoutée !');
            input.value = '';
            refreshEditor();
            loadStats();
        } catch (e) { toast(e.message); }
    }

    async function editSeason(id, current) {
        const name = prompt('Nom de la saison :', current);
        if (name === null) return;
        try { await api(`/admin/seasons/${id}`, { method: 'PUT', body: { name: name.trim() } }); toast('Saison mise à jour'); refreshEditor(); }
        catch (e) { toast(e.message); }
    }

    async function deleteSeason(id, name) {
        if (!confirm(`Supprimer la saison \u00ab ${name} \u00bb et ses \u00e9pisodes/liens ?`)) return;
        try { await api(`/admin/seasons/${id}`, { method: 'DELETE' }); toast('Saison supprimée'); refreshEditor(); loadStats(); }
        catch (e) { toast(e.message); }
    }

    /* ---- Episodes & links ---- */
    async function openEpisodes(seasonId, title) {
        state.season = seasonId;
        $('#epTitle').textContent = '\u00c9pisodes \u2014 ' + (title || '');
        $('#epList').innerHTML = '<span class="muted">Chargement\u2026</span>';
        $('#episodesModal').classList.add('open');
        reloadEpisodes();
    }

    async function reloadEpisodes() {
        if (!state.season) return;
        try {
            const { data } = await api(`/admin/seasons/${state.season}/episodes`);
            renderEpisodes(data.episodes || []);
        } catch (e) { $('#epList').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`; }
    }

    function renderEpisodes(episodes) {
        let html = '';
        if (episodes.length) {
            html = episodes.map((ep) => {
                const links = (ep.links || []).map((l) => `
                    <div style="display:flex;gap:8px;align-items:center;font-size:13px;padding:2px 0">
                        <span class="muted" style="width:70px">${escapeHtml(l.language)}</span>
                        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(l.url)}</span>
                        <button class="btn btn--danger" data-ldel="${l.id}">\u00d7</button>
                    </div>`).join('') || '<span class="muted" style="font-size:12px">Aucun lien.</span>';
                return `
                    <div style="border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:10px">
                        <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
                            <b style="flex:1">#${ep.index} \u2014 ${escapeHtml(ep.name || '\u00c9pisode ' + ep.index)}</b>
                            <button class="btn" data-eedit="${ep.id}" data-name="${escapeHtml(ep.name || '')}">Renommer</button>
                            <button class="btn btn--danger" data-edel="${ep.id}">Suppr.</button>
                        </div>
                        ${links}
                        <div style="display:flex;gap:6px;margin-top:8px">
                            <input data-lang="${ep.id}" placeholder="Langue (VF\u2026)" style="width:110px">
                            <input data-url="${ep.id}" placeholder="URL du lecteur">
                            <button class="btn" data-ladd="${ep.id}">Ajouter lien</button>
                        </div>
                    </div>`;
            }).join('');
        } else {
            html = '<span class="muted">Aucun \u00e9pisode.</span>';
        }

        // Add episode creation form
        html += `
            <div style="border:1px solid var(--accent);border-radius:10px;padding:12px;margin-top:14px;background:rgba(124,92,255,0.05)">
                <b style="font-size:13px;display:block;margin-bottom:8px">Ajouter un \u00e9pisode</b>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <input id="newEpName" placeholder="Nom (\u00e9pisode 1, ...)" style="flex:1;min-width:140px">
                    <input id="newEpLang" placeholder="Langue (VF, VOSTFR...)" style="width:120px">
                    <input id="newEpUrl" placeholder="URL du lecteur vid\u00e9o" style="flex:2;min-width:200px">
                    <button class="btn btn--primary" id="addEpisodeBtn">+ Ajouter \u00e9pisode</button>
                </div>
            </div>`;
        $('#epList').innerHTML = html;

        // Wire the add episode button
        $('#addEpisodeBtn').addEventListener('click', addEpisode);
    }

    async function addEpisode() {
        if (!state.season) return;
        const nameInput = $('#newEpName');
        const langInput = $('#newEpLang');
        const urlInput = $('#newEpUrl');
        const name = nameInput.value.trim() || null;
        const lang = langInput.value.trim();
        const url = urlInput.value.trim();

        try {
            // Create the episode
            const { data: episode } = await api(`/admin/seasons/${state.season}/episodes`, {
                method: 'POST',
                body: { name }
            });
            toast('\u00c9pisode cr\u00e9\u00e9 !');

            // If language and URL provided, also add a link
            if (lang && url) {
                await api(`/admin/episodes/${episode.id}/links`, {
                    method: 'POST',
                    body: { language: lang, url }
                });
                toast('Lien vid\u00e9o ajout\u00e9 !');
            }

            nameInput.value = '';
            langInput.value = '';
            urlInput.value = '';
            reloadEpisodes();
            loadStats();
        } catch (e) { toast(e.message); }
    }

    async function editEpisode(id, current) {
        const name = prompt('Nom de l\u2019\u00e9pisode :', current);
        if (name === null) return;
        try { await api(`/admin/episodes/${id}`, { method: 'PUT', body: { name: name.trim() || null } }); reloadEpisodes(); }
        catch (e) { toast(e.message); }
    }

    async function deleteEpisode(id) {
        if (!confirm('Supprimer cet \u00e9pisode et ses liens ?')) return;
        try { await api(`/admin/episodes/${id}`, { method: 'DELETE' }); toast('\u00c9pisode supprim\u00e9'); reloadEpisodes(); }
        catch (e) { toast(e.message); }
    }

    async function addLink(epId) {
        const lang = document.querySelector(`[data-lang="${epId}"]`).value.trim();
        const url = document.querySelector(`[data-url="${epId}"]`).value.trim();
        if (!lang || !url) { toast('Langue et URL requis'); return; }
        try { await api(`/admin/episodes/${epId}/links`, { method: 'POST', body: { language: lang, url } }); toast('Lien ajout\u00e9'); reloadEpisodes(); }
        catch (e) { toast(e.message); }
    }

    async function deleteLink(id) {
        if (!confirm('Supprimer ce lien ?')) return;
        try { await api(`/admin/links/${id}`, { method: 'DELETE' }); reloadEpisodes(); }
        catch (e) { toast(e.message); }
    }

    async function saveAnime(e) {
        e.preventDefault();
        if (!state.editing) return;
        const f = e.target;
        try {
            await api(`/admin/animes/${state.editing.id}`, {
                method: 'PUT',
                body: {
                    name: f.name.value.trim(),
                    url: f.url.value.trim() || null,
                    trailer_url: f.trailer_url.value.trim() || null,
                    image_url: f.image_url.value.trim() || null,
                    synopsis: f.synopsis.value.trim() || null,
                    is_mature: f.is_mature.checked,
                    genres: getSelectedGenres(),
                },
            });
            $('#editModal').classList.remove('open');
            toast('Enregistr\u00e9');
            loadAnimes();
        } catch (err) {
            $('#editErr').textContent = err.message;
        }
    }

    async function deleteAnime(a) {
        if (!confirm(`Supprimer \u00ab ${a.name} \u00bb et toutes ses saisons/\u00e9pisodes/liens ?`)) return;
        try {
            await api(`/admin/animes/${a.id}`, { method: 'DELETE' });
            toast('Supprim\u00e9');
            loadStats();
            loadAnimes();
        } catch (e) { toast(e.message); }
    }

    function wire() {
        $('#loginForm').addEventListener('submit', doLogin);
        $('#logout').addEventListener('click', logout);
        $('#searchBtn').addEventListener('click', () => { state.q = $('#q').value.trim(); state.page = 1; loadAnimes(); });
        $('#q').addEventListener('keydown', (e) => { if (e.key === 'Enter') { state.q = $('#q').value.trim(); state.page = 1; loadAnimes(); } });
        $('#prev').addEventListener('click', () => { if (state.page > 1) { state.page--; loadAnimes(); } });
        $('#next').addEventListener('click', () => { if (state.page < state.lastPage) { state.page++; loadAnimes(); } });
        $('#editForm').addEventListener('submit', saveAnime);
        $('#editCancel').addEventListener('click', () => $('#editModal').classList.remove('open'));
        $('#editModal').addEventListener('click', (e) => { if (e.target === $('#editModal')) $('#editModal').classList.remove('open'); });

        // Create anime
        $('#createAnimeBtn').addEventListener('click', openCreateAnime);
        $('#createAnimeForm').addEventListener('submit', submitCreateAnime);
        $('#createAnimeCancel').addEventListener('click', () => $('#createAnimeModal').classList.remove('open'));
        $('#createAnimeModal').addEventListener('click', (e) => { if (e.target === $('#createAnimeModal')) $('#createAnimeModal').classList.remove('open'); });

        // Season actions (delegated).
        $('#editSeasons').addEventListener('click', (e) => {
            const t = e.target.closest('button');
            if (!t) return;
            if (t.dataset.ep) openEpisodes(t.dataset.ep, t.dataset.title);
            else if (t.dataset.sedit) editSeason(t.dataset.sedit, t.dataset.name);
            else if (t.dataset.sdel) deleteSeason(t.dataset.sdel, t.dataset.name);
        });

        // Episode + link actions (delegated).
        $('#epList').addEventListener('click', (e) => {
            const t = e.target.closest('button');
            if (!t) return;
            if (t.dataset.eedit) editEpisode(t.dataset.eedit, t.dataset.name);
            else if (t.dataset.edel) deleteEpisode(t.dataset.edel);
            else if (t.dataset.ldel) deleteLink(t.dataset.ldel);
            else if (t.dataset.ladd) addLink(t.dataset.ladd);
        });
        $('#epClose').addEventListener('click', () => $('#episodesModal').classList.remove('open'));
        $('#episodesModal').addEventListener('click', (e) => { if (e.target === $('#episodesModal')) $('#episodesModal').classList.remove('open'); });

        // Planning
        $('#planningBtn').addEventListener('click', openPlanningAdmin);
        $('#planningClose').addEventListener('click', () => $('#planningModal').classList.remove('open'));
        $('#planningModal').addEventListener('click', (e) => { if (e.target === $('#planningModal')) $('#planningModal').classList.remove('open'); });

        // Content requests
        $('#requestsBtn').addEventListener('click', openRequestsAdmin);
        $('#requestsClose').addEventListener('click', () => $('#requestsModal').classList.remove('open'));
        $('#requestsModal').addEventListener('click', (e) => { if (e.target === $('#requestsModal')) $('#requestsModal').classList.remove('open'); });

        // Transfers monitoring
        $('#transfersBtn').addEventListener('click', openTransfers);
        $('#transfersClose').addEventListener('click', () => $('#transfersModal').classList.remove('open'));
        $('#transfersModal').addEventListener('click', (e) => { if (e.target === $('#transfersModal')) $('#transfersModal').classList.remove('open'); });
        $('#refreshTransfersBtn').addEventListener('click', reloadTransfers);
        $('#retryAllBtn').addEventListener('click', () => transferAction('/admin/transfers/retry', 'Réessayer TOUS les échecs ?'));
        $('#redoAllBtn').addEventListener('click', () => transferAction('/admin/transfers/redo', 'Remettre TOUS les terminés en file (re-upload) ?'));

        // Torrents (remote upload)
        $('#torrentsBtn').addEventListener('click', openTorrents);
        $('#torrentsClose').addEventListener('click', () => $('#torrentsModal').classList.remove('open'));
        $('#torrentsModal').addEventListener('click', (e) => { if (e.target === $('#torrentsModal')) $('#torrentsModal').classList.remove('open'); });
        $('#tSubmitBtn').addEventListener('click', submitTorrent);
        $('#tRefreshBtn').addEventListener('click', reloadTorrents);
        $('#tRetryAllBtn').addEventListener('click', () => torrentAction('/admin/torrents/retry-all', 'Réarmer TOUS les échecs (batches + liens) ?'));
        $('#tCleanupBtn').addEventListener('click', cleanupLinks);
        $('#tKeepManagedBtn').addEventListener('click', keepOnlyManagedLinks);
        $('#tMigrateBtn').addEventListener('click', runMigrate);
        $('#tProcessBtn').addEventListener('click', processTorrentsNow);
        $('#torrentFilesClose').addEventListener('click', () => $('#torrentFilesModal').classList.remove('open'));
        $('#torrentFilesModal').addEventListener('click', (e) => { if (e.target === $('#torrentFilesModal')) $('#torrentFilesModal').classList.remove('open'); });

        // Recently added/modified content (verify player links)
        $('#tContentBtn').addEventListener('click', openRecentContent);
        $('#rcRefreshBtn').addEventListener('click', reloadRecentContent);
        $('#rcSearch').addEventListener('input', debounce(reloadRecentContent, 300));
        $('#recentContentClose').addEventListener('click', () => $('#recentContentModal').classList.remove('open'));
        $('#recentContentModal').addEventListener('click', (e) => { if (e.target === $('#recentContentModal')) $('#recentContentModal').classList.remove('open'); });
        $('#recentLinksClose').addEventListener('click', () => $('#recentLinksModal').classList.remove('open'));
        $('#recentLinksModal').addEventListener('click', (e) => { if (e.target === $('#recentLinksModal')) $('#recentLinksModal').classList.remove('open'); });

        // Team manual tracking
        $('#teamBtn').addEventListener('click', openTeam);
        $('#teamClose').addEventListener('click', () => $('#teamModal').classList.remove('open'));
        $('#teamModal').addEventListener('click', (e) => { if (e.target === $('#teamModal')) $('#teamModal').classList.remove('open'); });
        $('#teamSearchBtn').addEventListener('click', () => { team.q = $('#teamQ').value.trim(); team.page = 1; loadTeamTasks(); });
        $('#teamQ').addEventListener('keydown', (e) => { if (e.key === 'Enter') { team.q = $('#teamQ').value.trim(); team.page = 1; loadTeamTasks(); } });
        $('#teamPrev').addEventListener('click', () => { if (team.page > 1) { team.page--; loadTeamTasks(); } });
        $('#teamNext').addEventListener('click', () => { if (team.page < team.lastPage) { team.page++; loadTeamTasks(); } });
        $('#teamMember').addEventListener('change', () => localStorage.setItem('as_team_member', teamMember()));
    }

    /* ---- Transfers Admin ---- */
    async function openTransfers() {
        $('#transfersModal').classList.add('open');
        $('#transferStats').innerHTML = '<span class="muted">Chargement...</span>';
        $('#transferErrors').innerHTML = '';
        reloadTransfers();
    }

    async function reloadTransfers() {
        try {
            const { data } = await api('/admin/transfers/stats');
            const labels = { total: 'Total', done: 'Termin\u00e9s', queued: 'En file', pending: 'En attente', failed: 'Erreurs', done_today: "Aujourd'hui" };
            let html = Object.entries(labels)
                .map(([k, label]) => {
                    const color = k === 'failed' && data[k] > 0 ? 'color:var(--danger)' : (k === 'done' ? 'color:#4fe0a0' : (k === 'queued' && data[k] > 0 ? 'color:#4fb0ff' : ''));
                    return `<div class="stat"><b style="${color}">${data[k] ?? 0}</b><span>${label}</span></div>`;
                }).join('') + `<div class="stat"><b>${data.percent ?? 0}%</b><span>Progression</span></div>`;
            $('#transferStats').innerHTML = html;

            // Per-host usage today
            const ph = data.per_host || [];
            const phBox = $('#transferPerHost');
            if (phBox) {
                phBox.innerHTML = ph.map(h => {
                    const cap = h.limit ? `/${h.limit}` : '';
                    const full = h.limit && h.used_today >= h.limit;
                    return `<div class="stat" style="${full ? 'border-color:var(--danger)' : ''}"><b>${h.used_today}${cap}</b><span>${escapeHtml(h.host)} auj.</span></div>`;
                }).join('');
            }
        } catch (e) {
            $('#transferStats').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`;
        }
        loadTransferErrors();
    }

    async function loadTransferErrors() {
        try {
            const { data } = await api('/admin/transfers/errors');
            const box = $('#transferErrors');
            if (!data.length) { box.innerHTML = '<span class="muted">Aucune erreur \uD83C\uDF89</span>'; return; }
            box.innerHTML = `<table><thead><tr><th>Anime</th><th>\u00c9p.</th><th>Lang</th><th>Host</th><th>Essais</th><th>Erreur</th><th></th></tr></thead><tbody>${
                data.map(e => `<tr>
                    <td>${escapeHtml(e.anime)}</td>
                    <td>S${String(e.season).padStart(2,'0')}E${String(e.episode ?? 0).padStart(2,'0')}</td>
                    <td>${escapeHtml(e.language || '')}</td>
                    <td>${escapeHtml(e.host || '')}</td>
                    <td>${e.attempts}</td>
                    <td class="muted" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escapeHtml(e.error || '')}">${escapeHtml(e.error || '')}</td>
                    <td><button class="btn" data-retry-anime="${e.anime_id}">↻</button></td>
                </tr>`).join('')
            }</tbody></table>`;
            box.querySelectorAll('[data-retry-anime]').forEach(btn => {
                btn.addEventListener('click', () => transferAction('/admin/transfers/retry', null, { anime_id: +btn.dataset.retryAnime }));
            });
        } catch (e) {
            $('#transferErrors').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`;
        }
    }

    async function transferAction(path, confirmMsg, body = {}) {
        if (confirmMsg && !confirm(confirmMsg)) return;
        try {
            const res = await api(path, { method: 'POST', body });
            toast(res.message || 'OK');
            reloadTransfers();
        } catch (e) { toast(e.message); }
    }

    /* ---- Planning Admin ---- */
    const DAYS_FR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];

    async function openPlanningAdmin() {
        $('#planningModal').classList.add('open');
        $('#planningList').innerHTML = '<span class="muted">Chargement...</span>';
        reloadPlanning();
    }

    async function reloadPlanning() {
        try {
            const { data } = await api('/admin/planning');
            renderPlanningAdmin(data || []);
        } catch (e) { $('#planningList').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`; }
    }

    function renderPlanningAdmin(entries) {
        const box = $('#planningList');
        let html = '';
        if (entries.length) {
            html = entries.map(p => `
                <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span style="flex:1"><b>${escapeHtml(p.anime ? p.anime.name : '?')}</b> — ${DAYS_FR[p.day_of_week]||'?'}${p.time ? ' '+escapeHtml(p.time) : ''}${p.note ? ' <span class="muted">('+escapeHtml(p.note)+')</span>' : ''}</span>
                    <button class="btn btn--danger" data-pdel="${p.id}">Suppr.</button>
                </div>`).join('');
        } else {
            html = '<span class="muted">Aucune entrée dans le planning.</span>';
        }
        // Add form
        html += `
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
                <b style="font-size:13px;display:block;margin-bottom:8px">Ajouter au planning</b>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <input id="planAnimeId" placeholder="ID anime" style="width:80px" type="number">
                    <select id="planDay" style="width:120px;padding:8px;border-radius:8px;background:#0f0f18;border:1px solid var(--border);color:var(--text)">
                        ${DAYS_FR.map((d,i)=>`<option value="${i}">${d}</option>`).join('')}
                    </select>
                    <input id="planTime" placeholder="HH:MM" style="width:80px">
                    <input id="planNote" placeholder="Note (optionnel)" style="flex:1;min-width:120px">
                    <button class="btn btn--primary" id="addPlanBtn">+ Ajouter</button>
                </div>
            </div>`;
        box.innerHTML = html;

        // Wire delete buttons
        box.querySelectorAll('[data-pdel]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Supprimer cette entrée ?')) return;
                try { await api(`/admin/planning/${btn.dataset.pdel}`, { method: 'DELETE' }); toast('Supprimé'); reloadPlanning(); }
                catch (e) { toast(e.message); }
            });
        });
        // Wire add button
        $('#addPlanBtn').addEventListener('click', addPlanningEntry);
    }

    async function addPlanningEntry() {
        const animeId = $('#planAnimeId').value.trim();
        const day = $('#planDay').value;
        const time = $('#planTime').value.trim() || null;
        const note = $('#planNote').value.trim() || null;
        if (!animeId) { toast('ID anime requis'); return; }
        try {
            await api('/admin/planning', { method: 'POST', body: { anime_id: +animeId, day_of_week: +day, time, note } });
            toast('Ajouté au planning');
            $('#planAnimeId').value = '';
            $('#planTime').value = '';
            $('#planNote').value = '';
            reloadPlanning();
        } catch (e) { toast(e.message); }
    }

    /* ---- Content Requests Admin ---- */
    async function openRequestsAdmin() {
        $('#requestsModal').classList.add('open');
        $('#requestsList').innerHTML = '<span class="muted">Chargement...</span>';
        reloadRequests();
    }

    async function reloadRequests() {
        try {
            const { data } = await api('/admin/content-requests');
            renderRequestsAdmin(data || []);
        } catch (e) { $('#requestsList').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`; }
    }

    function renderRequestsAdmin(requests) {
        const box = $('#requestsList');
        if (!requests.length) { box.innerHTML = '<span class="muted">Aucune demande.</span>'; return; }

        const statusColors = { pending: '#f0ad4e', approved: '#4fe0a0', rejected: '#ff4d6d' };
        const statusLabels = { pending: 'En attente', approved: 'Approuv\u00e9', rejected: 'Rejet\u00e9' };

        box.innerHTML = requests.map(r => `
            <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)">
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        <b style="font-size:14px">${escapeHtml(r.title)}</b>
                        <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:${statusColors[r.status]||'#666'};color:#000;font-weight:700">${statusLabels[r.status]||r.status}</span>
                    </div>
                    ${r.message ? `<p class="muted" style="font-size:12px;margin:0">${escapeHtml(r.message)}</p>` : ''}
                    <span class="muted" style="font-size:11px">${r.user ? escapeHtml(r.user.name) + ' (' + escapeHtml(r.user.email) + ')' : 'Visiteur'} \u2014 ${new Date(r.created_at).toLocaleDateString('fr-FR')}</span>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0">
                    ${r.status === 'pending' ? `
                        <button class="btn" data-rapprove="${r.id}" title="Approuver">\u2705</button>
                        <button class="btn btn--danger" data-rreject="${r.id}" title="Rejeter">\u274c</button>
                    ` : ''}
                    <button class="btn btn--danger" data-rdel="${r.id}" title="Supprimer">\u00d7</button>
                </div>
            </div>`).join('');

        // Wire buttons
        box.querySelectorAll('[data-rapprove]').forEach(btn => {
            btn.addEventListener('click', async () => {
                try { await api(`/admin/content-requests/${btn.dataset.rapprove}`, { method: 'PUT', body: { status: 'approved' } }); toast('Approuv\u00e9'); reloadRequests(); }
                catch (e) { toast(e.message); }
            });
        });
        box.querySelectorAll('[data-rreject]').forEach(btn => {
            btn.addEventListener('click', async () => {
                try { await api(`/admin/content-requests/${btn.dataset.rreject}`, { method: 'PUT', body: { status: 'rejected' } }); toast('Rejet\u00e9'); reloadRequests(); }
                catch (e) { toast(e.message); }
            });
        });
        box.querySelectorAll('[data-rdel]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Supprimer cette demande ?')) return;
                try { await api(`/admin/content-requests/${btn.dataset.rdel}`, { method: 'DELETE' }); reloadRequests(); }
                catch (e) { toast(e.message); }
            });
        });
    }

    /* ---- Team manual tracking ---- */
    const teamMember = () => (($('#teamMember') && $('#teamMember').value) || '').trim();

    async function openTeam() {
        $('#teamModal').classList.add('open');
        $('#teamMember').value = localStorage.getItem('as_team_member') || '';
        team.page = 1; team.q = ''; team.filter = '';
        $('#teamQ').value = '';
        renderTeamFilters();
        loadTeamStats();
        loadTeamTasks();
    }

    function renderTeamFilters() {
        const filters = [['', 'Tout'], ['todo', '\u00c0 faire'], ['in_progress', 'En cours'], ['done', 'Fait'], ['modified', 'Modifi\u00e9s'], ['big', '+25 \u00e9p'], ['deferred', 'Plus tard']];
        $('#teamFilters').innerHTML = filters.map(([v, label]) =>
            `<button class="btn ${team.filter === v ? 'btn--primary' : ''}" data-tf="${v}">${label}</button>`).join('');
        $('#teamFilters').querySelectorAll('[data-tf]').forEach((b) => b.addEventListener('click', () => {
            team.filter = b.dataset.tf; team.page = 1; renderTeamFilters(); loadTeamTasks();
        }));
    }

    async function loadTeamStats() {
        try {
            const { data } = await api('/admin/tasks/stats');
            const labels = { total: 'Total', todo: '\u00c0 faire', in_progress: 'En cours', done: 'Fait', modified: 'Modifi\u00e9s', deferred: 'Plus tard', big: '+25 \u00e9p' };
            $('#teamStats').innerHTML = Object.entries(labels).map(([k, l]) => {
                const color = k === 'done' ? 'color:#4fe0a0' : (k === 'big' ? 'color:#f0ad4e' : (k === 'modified' ? 'color:#4fb0ff' : ''));
                return `<div class="stat"><b style="${color}">${data[k] ?? 0}</b><span>${l}</span></div>`;
            }).join('');
        } catch (_) { /* noop */ }
    }

    async function loadTeamTasks() {
        $('#teamRows').innerHTML = '<tr><td colspan="7" class="muted">Chargement\u2026</td></tr>';
        try {
            const res = await api(`/admin/tasks?q=${encodeURIComponent(team.q)}&filter=${team.filter}&per_page=40&page=${team.page}`);
            team.lastPage = res.last_page || 1;
            team.page = res.current_page || 1;
            renderTeamRows(res.data || []);
            $('#teamPageInfo').textContent = `Page ${team.page}/${team.lastPage} \u00b7 ${res.total ?? 0} animes`;
        } catch (e) {
            $('#teamRows').innerHTML = `<tr><td colspan="7" class="err">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    function renderTeamRows(rows) {
        if (!rows.length) { $('#teamRows').innerHTML = '<tr><td colspan="7" class="muted">Aucun anime.</td></tr>'; return; }
        $('#teamRows').innerHTML = rows.map((a) => {
            const done = a.status === 'done';
            const inProg = a.status === 'in_progress';
            const bigBadge = a.is_big ? ' <span style="font-size:10px;background:#f0ad4e;color:#000;padding:1px 6px;border-radius:999px;font-weight:700">+25</span>' : '';
            const who = a.assigned_to || a.updated_by || '';
            const whoWhen = who ? (a.updated_at ? `${escapeHtml(who)} \u00b7 ${escapeHtml(a.updated_at.slice(0, 10))}` : escapeHtml(who)) : '';
            return `<tr data-id="${a.id}" style="${done ? 'opacity:.5' : (inProg ? 'background:rgba(240,173,78,.06)' : '')}">
                <td><code style="color:var(--accent)">${a.id}</code></td>
                <td>${escapeHtml(a.name)}${inProg ? ' <span class="muted" style="font-size:11px">(en cours)</span>' : ''}</td>
                <td>${a.episodes_count}${bigBadge}</td>
                <td style="text-align:center"><input type="checkbox" data-act="done" ${done ? 'checked' : ''} title="Marquer comme fait"></td>
                <td style="text-align:center"><input type="checkbox" data-act="modified" ${a.is_modified ? 'checked' : ''} title="Anime modifié"></td>
                <td style="text-align:center"><input type="checkbox" data-act="deferred" ${a.is_deferred ? 'checked' : ''} title="À faire plus tard"></td>
                <td class="muted" style="font-size:12px">${whoWhen}</td>
            </tr>`;
        }).join('');
        $('#teamRows').querySelectorAll('input[type=checkbox]').forEach((cb) => cb.addEventListener('change', () => onTeamCheck(cb)));
    }

    async function onTeamCheck(cb) {
        const tr = cb.closest('tr');
        const id = tr.dataset.id;
        const act = cb.dataset.act;
        const member = teamMember();
        if (member) localStorage.setItem('as_team_member', member);

        const body = { assigned_to: member || null };
        if (act === 'done') body.status = cb.checked ? 'done' : 'todo';
        if (act === 'modified') body.is_modified = cb.checked;
        if (act === 'deferred') body.is_deferred = cb.checked;

        try {
            await api(`/admin/tasks/${id}`, { method: 'PUT', body });
            toast('Enregistr\u00e9' + (member ? ' \u2014 ' + member : ''));
            if (act === 'done') tr.style.opacity = cb.checked ? '.5' : '';
            const whoCell = tr.children[6];
            if (whoCell && member) whoCell.textContent = member + ' \u00b7 ' + new Date().toISOString().slice(0, 10);
            loadTeamStats();
        } catch (e) {
            toast(e.message);
            cb.checked = !cb.checked; // revert on error
        }
    }

    /* ---- Torrents (magnet → aria2/Real-Debrid → player host) ---- */
    const torrentState = { platforms: ['doodstream', 'vidmoly', 'streamtape', 'mp4upload'], provider: 'aria2' };

    const TORRENT_STATUS_LABELS = { pending: 'En attente', downloading: 'Téléchargement', resolving: 'Real-Debrid', processing: 'Envoi lecteur', done: 'Terminé', failed: 'Échec' };
    const FILE_STATUS = {
        pending: ['#9a9ab0', '· en attente'],
        queued: ['#4fb0ff', '↻ chez le lecteur'],
        done: ['#4fe0a0', '✓ en ligne'],
        failed: ['#ff4d6d', '✗ échec'],
        skipped: ['#f0ad4e', '⊘ non mappé'],
    };

    async function openTorrents() {
        $('#torrentsModal').classList.add('open');
        $('#tErr').textContent = '';
        $('#torrentStats').innerHTML = '<span class="muted">Chargement…</span>';
        $('#torrentList').innerHTML = '';
        // Render the player checkboxes immediately (with defaults) so the admin
        // can always pick players even if the stats endpoint fails.
        renderPlatformSelect();
        await reloadTorrents();
    }

    async function reloadTorrents() {
        try {
            const { data } = await api('/admin/torrents/stats');
            if (Array.isArray(data.platforms) && data.platforms.length) {
                torrentState.platforms = data.platforms;
            }
            renderPlatformSelect();
            torrentState.provider = data.provider || 'aria2';
            renderProviderInfo(data);
            renderDailyUsage(data.daily_usage || []);
            // Show the migrate helper if the multi-target table is missing.
            $('#tMigrateWarn').classList.toggle('hide', data.migrated !== false);
            const labels = { total: 'Total', pending: 'En attente', downloading: 'Téléchargement', resolving: 'Real-Debrid', processing: 'Envoi lecteur', done: 'Terminés', failed: 'Échecs', links_unmatched: 'Non mappés', links_failed: 'Liens KO' };
            $('#torrentStats').innerHTML = Object.entries(labels).map(([k, label]) => {
                const val = data[k] ?? 0;
                const color = (k === 'failed' || k === 'links_failed') && val > 0 ? 'color:var(--danger)'
                    : (k === 'done' ? 'color:#4fe0a0' : ((k === 'processing' || k === 'resolving' || k === 'downloading') && val > 0 ? 'color:#4fb0ff' : (k === 'links_unmatched' && val > 0 ? 'color:#f0ad4e' : '')));
                return `<div class="stat"><b style="${color}">${val}</b><span>${label}</span></div>`;
            }).join('');
        } catch (e) {
            $('#torrentStats').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`;
        }
        loadTorrentList();
    }

    async function processTorrentsNow() {
        const btn = $('#tProcessBtn');
        btn.disabled = true;
        const prev = btn.textContent;
        btn.textContent = '⏳ Traitement…';
        try {
            const { data } = await api('/admin/torrents/process', { method: 'POST' });
            toast(data.message || 'File avancée.');
            await reloadTorrents();
        } catch (e) {
            toast(e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = prev;
        }
    }

    async function runMigrate() {
        const btn = $('#tMigrateBtn');
        const out = $('#tMigrateOut');
        if (!confirm('Lancer les migrations de base de données maintenant ?')) return;
        btn.disabled = true;
        const prev = btn.textContent;
        btn.textContent = 'Migration en cours…';
        out.classList.add('hide');
        try {
            const { data } = await api('/admin/system/migrate', { method: 'POST' });
            out.textContent = data.output || 'Terminé.';
            out.classList.remove('hide');
            toast(data.migrated ? 'Base migrée ✓' : 'Migrations exécutées.');
            if (data.migrated) $('#tMigrateWarn').classList.add('hide');
            await reloadTorrents();
        } catch (e) {
            out.textContent = e.message;
            out.classList.remove('hide');
            toast(e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = prev;
        }
    }

    function renderPlatformSelect() {
        const box = $('#tPlatforms');
        if (!box) return;
        // Preserve current selection across refreshes.
        const checked = new Set(Array.from(box.querySelectorAll('input:checked')).map(i => i.value));
        // Default: first platform checked on first render.
        const first = torrentState.platforms[0];
        box.innerHTML = torrentState.platforms.map(p => {
            const isOn = checked.size ? checked.has(p) : (p === first);
            return `<label class="genre-tag ${isOn ? 'active' : ''}" style="cursor:pointer">
                <input type="checkbox" value="${escapeHtml(p)}" ${isOn ? 'checked' : ''} style="width:auto;margin-right:5px"> ${escapeHtml(p)}
            </label>`;
        }).join('');
        // Toggle the visual "active" class on change.
        box.querySelectorAll('input[type=checkbox]').forEach(cb => cb.addEventListener('change', () => {
            cb.closest('label').classList.toggle('active', cb.checked);
        }));
    }

    function selectedPlatforms() {
        return Array.from(document.querySelectorAll('#tPlatforms input:checked')).map(i => i.value);
    }

    /** Per-host daily upload quota usage (used / limit). */
    function renderDailyUsage(usage) {
        const box = $('#torrentDailyUsage');
        if (!box) return;
        if (!usage.length) { box.innerHTML = '<span class="muted" style="font-size:12px">—</span>'; return; }
        box.innerHTML = usage.map(u => {
            const color = u.capped ? 'color:var(--danger)' : (u.remaining <= Math.max(1, u.limit * 0.1) ? 'color:#f0ad4e' : '');
            return `<div class="stat" style="${u.capped ? 'border-color:var(--danger)' : ''}">
                <b style="${color}">${u.used}/${u.limit}</b>
                <span>${escapeHtml(u.host)}${u.capped ? ' · plein' : ''}</span>
            </div>`;
        }).join('');
    }

    /** Show which provider handles magnets and whether it's ready. */
    function renderProviderInfo(data) {
        const provider = data.provider || 'aria2';
        const isAria2 = provider === 'aria2';
        const tag = $('#tProviderTag');
        const info = $('#tProviderInfo');
        const warn = $('#tProviderWarn');

        if (tag) {
            tag.textContent = isAria2 ? '(téléchargé par le VPS — aria2)' : '(résolu par Real-Debrid)';
        }
        if (info) {
            info.innerHTML = isAria2
                ? 'Fournisseur : <b>aria2 (VPS)</b> — le VPS télécharge le torrent directement, puis met chaque épisode en ligne.'
                : 'Fournisseur : <b>Real-Debrid</b> — le magnet/.torrent est résolu puis récupéré par le lecteur cible.';
        }
        if (warn) {
            let msg = '';
            if (isAria2 && data.aria2_ready === false) {
                msg = 'ℹ aria2c n\'est pas détecté sur CE serveur. Sur ton VPS, installe aria2 (apt install aria2) et configure ARIA2_* dans .env, puis lance « php artisan torrents:process » (ou un cron). Tu peux déjà soumettre : les jobs attendent en file.';
            } else if (!isAria2 && data.debrid_ready === false) {
                msg = 'ℹ Real-Debrid non configuré sur CE serveur (REALDEBRID_API_TOKEN dans .env).';
            }
            warn.textContent = msg;
            warn.classList.toggle('hide', msg === '');
        }
    }

    async function loadTorrentList() {
        try {
            const res = await api('/admin/torrents?per_page=50');
            renderTorrentList(res.data || []);
        } catch (e) {
            $('#torrentList').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`;
        }
    }

    function renderTorrentList(rows) {
        if (!rows.length) { $('#torrentList').innerHTML = '<span class="muted">Aucun batch pour le moment.</span>'; return; }
        $('#torrentList').innerHTML = rows.map(t => {
            const [color] = statusColor(t.status);
            const target = t.season ? `${escapeHtml(t.season.anime ? t.season.anime.name : '?')} · ${escapeHtml(t.season.name)}`
                : (t.episode ? `Épisode #${t.episode.index}` : '—');
            const canDelete = t.status !== 'done';
            // "Mettre à jour la base" appears once at least one upload is done
            // (a player target has an embed URL) and links haven't been synced.
            // Uses uploads_done (robust) + falls back to files_done for safety.
            const uploadsDone = (t.uploads_done ?? 0) || (t.files_done ?? 0);
            const canSync = uploadsDone > 0 && !t.links_synced;
            const syncedBadge = t.links_synced
                ? '<span class="muted" style="font-size:11px">✓ base à jour</span>' : '';
            return `<div style="border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <span style="font-weight:700;flex:1;min-width:140px">${escapeHtml(t.title || 'Batch #' + t.id)}</span>
                    <span style="font-size:11px;padding:2px 10px;border-radius:999px;background:${color};color:#000;font-weight:700">${TORRENT_STATUS_LABELS[t.status] || t.status}</span>
                    <span class="muted" style="font-size:12px">→ ${escapeHtml((Array.isArray(t.target_platforms) && t.target_platforms.length ? t.target_platforms : [t.target_platform]).join(', '))}</span>
                </div>
                <div class="muted" style="font-size:12px;margin:4px 0">${target} · ${(t.status === 'resolving' || t.status === 'downloading') ? (t.status === 'downloading' ? 'aria2 ' : 'Real-Debrid ') + (t.debrid_progress || 0) + '%' : t.files_done + '/' + t.files_total + ' en ligne' + (t.files_failed ? ' · <span style="color:var(--danger)">' + t.files_failed + ' KO</span>' : '')} ${syncedBadge}</div>
                ${t.error ? `<div class="err" style="font-size:12px">${escapeHtml(t.error)}</div>` : ''}
                <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">
                    <button class="btn" data-tfiles="${t.id}" data-title="${escapeHtml(t.title || 'Batch #' + t.id)}">Épisodes</button>
                    ${canSync ? `<button class="btn btn--primary" data-tsync="${t.id}" title="Créez les liens dans la base après avoir vérifié sur le lecteur">🔄 Mettre à jour la base</button>` : ''}
                    <button class="btn" data-tretry="${t.id}">↻ Réessayer</button>
                    ${canDelete ? `<button class="btn btn--danger" data-tdel="${t.id}">Suppr.</button>` : ''}
                </div>
            </div>`;
        }).join('');

        $('#torrentList').querySelectorAll('[data-tfiles]').forEach(b => b.addEventListener('click', () => openTorrentFiles(b.dataset.tfiles, b.dataset.title)));
        $('#torrentList').querySelectorAll('[data-tsync]').forEach(b => b.addEventListener('click', () => torrentSyncLinks(b.dataset.tsync)));
        $('#torrentList').querySelectorAll('[data-tretry]').forEach(b => b.addEventListener('click', () => torrentAction(`/admin/torrents/${b.dataset.tretry}/retry`, null)));
        $('#torrentList').querySelectorAll('[data-tdel]').forEach(b => b.addEventListener('click', () => {
            if (!confirm('Supprimer ce batch et ses liens ?')) return;
            torrentDelete(b.dataset.tdel);
        }));
    }

    async function cleanupLinks() {
        if (!confirm('Dédupliquer les liens de TOUT le catalogue ?\n\nChaque épisode ne gardera qu\'UN lien par lecteur et par langue (le plus récent). Les doublons sont supprimés. Action irréversible.')) return;
        try {
            const res = await api('/admin/torrents/cleanup-links', { method: 'POST' });
            toast(res.message || 'Liens dédupliqués');
        } catch (e) { toast(e.message); }
    }

    async function keepOnlyManagedLinks() {
        if (!confirm('⚠️ SUPPRIMER tous les liens SAUF doodstream / vidmoly / streamtape / mp4upload ?\n\nTous les autres lecteurs (sibnet, sendvid, myvi, dailymotion, etc.) seront retirés de TOUT le catalogue, et il ne restera qu\'un lien par lecteur/langue. Action IRRÉVERSIBLE.')) return;
        if (!confirm('Confirmez une seconde fois : cette action supprime définitivement les liens des autres lecteurs.')) return;
        try {
            const res = await api('/admin/torrents/cleanup-links', { method: 'POST', body: { keep_only_managed: true } });
            toast(res.message || 'Nettoyage effectué');
        } catch (e) { toast(e.message); }
    }

    async function torrentSyncLinks(id) {
        if (!confirm('Créer les liens dans la base ET ranger les fichiers en dossiers sur le lecteur (Anime/Saison/Episode) ?\n\nÀ faire une fois que vous avez vérifié sur le lecteur que les épisodes sont bien en ligne. Les liens ne changent pas.')) return;
        try {
            const res = await api(`/admin/torrents/${id}/sync-links`, { method: 'POST' });
            toast(res.message || 'Base mise à jour');
            reloadTorrents();
        } catch (e) { toast(e.message); }
    }

    function statusColor(status) {
        return { pending: ['#9a9ab0'], downloading: ['#4fb0ff'], resolving: ['#4fb0ff'], processing: ['#4fb0ff'], done: ['#4fe0a0'], failed: ['#ff4d6d'] }[status] || ['#666'];
    }

    async function submitTorrent() {
        $('#tErr').textContent = '';
        const platforms = selectedPlatforms();
        const lang = $('#tLang').value.trim() || 'VOSTFR';
        const animeId = $('#tAnimeId').value.trim();
        const seasonId = $('#tSeasonId').value.trim();
        const episodeId = $('#tEpisodeId').value.trim();
        const title = $('#tTitle').value.trim() || null;
        const torrentUrl = $('#tTorrentUrl').value.trim();
        const links = $('#tLinks').value.trim();

        if (!platforms.length) { $('#tErr').textContent = 'Cochez au moins un lecteur cible.'; return; }
        if (!torrentUrl && !links) { $('#tErr').textContent = 'Collez un lien magnet/.torrent OU des liens HTTP directs.'; return; }
        if (!seasonId && !episodeId) { $('#tErr').textContent = 'Indiquez une Saison ID (pack) ou un Épisode ID (unique).'; return; }

        // JSON submission (magnet/URL or direct links).
        const body = { target_platforms: platforms, language: lang, title };
        if (animeId) body.anime_id = +animeId;
        if (seasonId) body.season_id = +seasonId;
        if (episodeId) body.episode_id = +episodeId;
        // A magnet/.torrent takes precedence over pasted links.
        if (torrentUrl) body.torrent_url = torrentUrl;
        else body.links = links;

        try {
            const res = await api('/admin/torrents', { method: 'POST', body });
            toast(res.message || 'Batch créé');
            $('#tLinks').value = '';
            $('#tTorrentUrl').value = '';
            $('#tTitle').value = '';
            reloadTorrents();
        } catch (e) {
            $('#tErr').textContent = e.message + (e.errors ? ' — ' + Object.values(e.errors).flat().join(' ') : '');
        }
    }

    async function torrentAction(path, confirmMsg) {
        if (confirmMsg && !confirm(confirmMsg)) return;
        try {
            const res = await api(path, { method: 'POST' });
            toast(res.message || 'OK');
            reloadTorrents();
        } catch (e) { toast(e.message); }
    }

    async function torrentDelete(id) {
        try {
            const res = await api(`/admin/torrents/${id}`, { method: 'DELETE' });
            toast(res.message || 'Supprimé');
            reloadTorrents();
        } catch (e) { toast(e.message); }
    }

    /* ---- Torrent files (per-episode) ---- */
    let currentTorrentId = null;

    async function openTorrentFiles(id, title) {
        currentTorrentId = id;
        $('#tfTitle').textContent = 'Épisodes — ' + (title || '');
        $('#torrentFilesList').innerHTML = '<span class="muted">Chargement…</span>';
        $('#torrentFilesModal').classList.add('open');
        reloadTorrentFiles();
    }

    async function reloadTorrentFiles() {
        if (!currentTorrentId) return;
        try {
            const { data } = await api(`/admin/torrents/${currentTorrentId}/files`);
            renderTorrentFiles(data || []);
        } catch (e) {
            $('#torrentFilesList').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`;
        }
    }

    function renderTorrentFiles(files) {
        if (!files.length) { $('#torrentFilesList').innerHTML = '<span class="muted">Aucun lien.</span>'; return; }
        $('#torrentFilesList').innerHTML = files.map(f => {
            const [color, label] = FILE_STATUS[f.status] || ['#666', f.status];
            const ep = f.episode ? `#${f.episode.index}${f.episode.name ? ' — ' + escapeHtml(f.episode.name) : ''}`
                : (f.detected_episode ? `E${String(f.detected_episode).padStart(2, '0')} (détecté)` : '<span style="color:#f0ad4e">non mappé</span>');
            const needsMap = f.status === 'skipped' || !f.episode;
            // Codec badge (HEVC highlighted — problematic for Streamtape).
            const codec = f.video_codec ? (() => {
                const isHevc = ['hevc', 'h265', 'x265'].includes(String(f.video_codec).toLowerCase());
                const bg = isHevc ? '#f0ad4e' : '#3a3a4a';
                return `<span title="Codec vidéo" style="font-size:10px;padding:2px 7px;border-radius:999px;background:${bg};color:#000;font-weight:700">${escapeHtml(String(f.video_codec).toUpperCase())}</span>`;
            })() : '';
            // Per-player results (status pill + clickable embed link to verify).
            const targets = (f.targets || []).map(t => {
                const tc = FILE_STATUS[t.status] || ['#666', t.status];
                const link = t.embed_url
                    ? `<a href="${escapeHtml(t.embed_url)}" target="_blank" rel="noopener" title="Vérifier ${escapeHtml(t.platform)}">${escapeHtml(t.platform)} ↗</a>`
                    : `<span>${escapeHtml(t.platform)}</span>`;
                const err = t.error ? ` title="${escapeHtml(t.error)}"` : '';
                return `<span style="font-size:11px;padding:2px 8px;border-radius:999px;background:${tc[0]};color:#000;font-weight:700"${err}>${link}</span>`;
            }).join(' ');
            return `<div style="border:1px solid var(--border);border-radius:8px;padding:8px;margin-bottom:6px">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:${color};color:#000;font-weight:700">${label}</span>
                    <span style="flex:1;min-width:120px">${ep}</span>
                    ${codec}
                </div>
                ${targets ? `<div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:6px">${targets}</div>` : ''}
                <div class="muted" style="font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:3px">${escapeHtml(f.file_name || f.source_url || '')}</div>
                ${f.error ? `<div class="err" style="font-size:11px">${escapeHtml(f.error)}</div>` : ''}
                ${needsMap ? `<div style="display:flex;gap:6px;margin-top:6px">
                    <input data-mapep="${f.id}" type="number" placeholder="Épisode ID" style="width:130px">
                    <button class="btn" data-mapbtn="${f.id}">Rattacher</button>
                </div>` : ''}
            </div>`;
        }).join('');

        $('#torrentFilesList').querySelectorAll('[data-mapbtn]').forEach(b => b.addEventListener('click', () => {
            const id = b.dataset.mapbtn;
            const val = document.querySelector(`[data-mapep="${id}"]`).value.trim();
            if (!val) { toast('Indiquez un Épisode ID'); return; }
            mapTorrentFile(id, +val);
        }));
    }

    async function mapTorrentFile(fileId, episodeId) {
        try {
            await api(`/admin/torrent-files/${fileId}`, { method: 'PUT', body: { episode_id: episodeId } });
            toast('Rattaché — sera renvoyé au lecteur.');
            reloadTorrentFiles();
        } catch (e) { toast(e.message); }
    }

    /* ---- Recently added / modified content (verify player links) ---- */
    let recentRdReady = false;

    function openRecentContent() {
        $('#recentContentModal').classList.add('open');
        reloadRecentContent();
    }

    async function reloadRecentContent() {
        const q = $('#rcSearch').value.trim();
        $('#recentContentList').innerHTML = '<span class="muted">Chargement…</span>';
        try {
            const res = await api('/admin/recent-content?per_page=30' + (q ? '&q=' + encodeURIComponent(q) : ''));
            renderRecentContent(res.data || []);
        } catch (e) {
            $('#recentContentList').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`;
        }
    }

    function renderRecentContent(items) {
        if (!items.length) {
            $('#recentContentList').innerHTML = '<span class="muted">Aucun anime avec des liens.</span>';
            return;
        }
        $('#recentContentList').innerHTML = items.map(a => {
            const hosts = (a.hosts || []).map(h => `<span class="genre-tag">${escapeHtml(h)}</span>`).join(' ');
            return `<div style="display:flex;gap:10px;align-items:center;border:1px solid var(--border);border-radius:8px;padding:8px;margin-bottom:6px">
                ${a.image_url ? `<img class="thumb" src="${escapeHtml(a.image_url)}" referrerpolicy="no-referrer" onerror="this.style.visibility='hidden'" style="width:40px;height:56px;object-fit:cover;border-radius:4px">` : ''}
                <div style="flex:1;min-width:120px">
                    <div style="font-weight:700">${escapeHtml(a.name)}</div>
                    <div class="muted" style="font-size:11px">${a.seasons_count} saison(s) · ${a.links_count} lien(s) · maj ${escapeHtml(a.updated_at || '')}</div>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px">${hosts}</div>
                </div>
                <button class="btn" data-rclinks="${a.id}" data-rcname="${escapeHtml(a.name)}">Voir liens</button>
            </div>`;
        }).join('');

        $('#recentContentList').querySelectorAll('[data-rclinks]').forEach(b => b.addEventListener('click', () => {
            openRecentLinks(b.dataset.rclinks, b.dataset.rcname);
        }));
    }

    async function openRecentLinks(animeId, name) {
        $('#rcLinksTitle').textContent = 'Liens — ' + (name || '');
        $('#recentLinksList').innerHTML = '<span class="muted">Chargement…</span>';
        $('#recentLinksModal').classList.add('open');
        try {
            const { data } = await api(`/admin/recent-content/${animeId}`);
            recentRdReady = !!(data && data.realdebrid_ready);
            renderRecentLinks(data);
        } catch (e) {
            $('#recentLinksList').innerHTML = `<span class="err">${escapeHtml(e.message)}</span>`;
        }
    }

    function renderRecentLinks(data) {
        $('#rcLinksHint').textContent = recentRdReady
            ? 'Cliquez un lien pour vérifier la lecture. Real-Debrid est configuré (vérif possible côté serveur).'
            : 'Cliquez un lien pour l\u2019ouvrir et vérifier la lecture.';

        const seasons = (data && data.seasons) || [];
        const withEpisodes = seasons.filter(s => (s.episodes || []).length);
        if (!withEpisodes.length) {
            $('#recentLinksList').innerHTML = '<span class="muted">Aucun épisode avec des liens.</span>';
            return;
        }
        $('#recentLinksList').innerHTML = withEpisodes.map(s => {
            const eps = s.episodes.map(ep => {
                const links = ep.links.map(l => {
                    const label = `${escapeHtml(l.host || 'lien')}${l.language ? ' · ' + escapeHtml(l.language) : ''}`;
                    return `<a href="${escapeHtml(l.url)}" target="_blank" rel="noopener" class="genre-tag" style="text-decoration:none">${label} ↗</a>`;
                }).join(' ');
                const epTitle = `#${ep.index}${ep.name ? ' — ' + escapeHtml(ep.name) : ''}`;
                return `<div style="border-top:1px solid var(--border);padding:6px 0">
                    <div style="font-size:13px;margin-bottom:4px">${epTitle}</div>
                    <div style="display:flex;flex-wrap:wrap;gap:5px">${links}</div>
                </div>`;
            }).join('');
            return `<div style="margin-bottom:12px">
                <b style="font-size:13px">${escapeHtml(s.name || 'Saison ' + s.position)}</b>
                ${eps}
            </div>`;
        }).join('');
    }

    document.addEventListener('DOMContentLoaded', boot);
})();
