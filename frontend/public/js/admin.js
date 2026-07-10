/* Admin dashboard for the normalized library (animes/seasons/episodes/links). */
(() => {
    'use strict';

    const API = '/api';
    // Reuse the main app's token so admins logged in on the site stay logged in here.
    let token = localStorage.getItem('as_token') || null;

    const $ = (s) => document.querySelector(s);
    const state = { page: 1, lastPage: 1, q: '', editing: null, season: null };

    async function api(path, { method = 'GET', body } = {}) {
        const headers = { Accept: 'application/json' };
        if (body) headers['Content-Type'] = 'application/json';
        if (token) headers.Authorization = 'Bearer ' + token;
        const res = await fetch(API + path, { method, headers, body: body ? JSON.stringify(body) : undefined });
        let json = {};
        try { json = await res.json(); } catch (_) { /* noop */ }
        if (!res.ok) throw Object.assign(new Error(json.message || `Erreur ${res.status}`), { status: res.status });
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

    /* ---- Editor ---- */
    async function openEditor(id) {
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
            renderSeasons(data.seasons || []);
            $('#editModal').classList.add('open');
        } catch (e) { toast(e.message); }
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

    document.addEventListener('DOMContentLoaded', boot);
})();
