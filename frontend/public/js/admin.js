/* Admin dashboard for the normalized library (animes/seasons/episodes/links). */
(() => {
    'use strict';

    const API = '/api';
    // Reuse the main app's token so admins logged in on the site stay logged in here.
    let token = localStorage.getItem('as_token') || null;

    const $ = (s) => document.querySelector(s);
    const state = { page: 1, lastPage: 1, q: '', editing: null };

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
            const labels = { animes: 'Animes', seasons: 'Saisons', episodes: 'Épisodes', episode_links: 'Liens vidéo', uploads: 'Uploads', users: 'Utilisateurs' };
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
        const hasTrailer = a.trailer_url ? '✅' : '—';
        const syn = a.synopsis ? (a.synopsis.length > 60 ? a.synopsis.slice(0, 60) + '…' : a.synopsis) : '—';
        return `<tr>
            <td>${a.image_url ? `<img class="thumb" src="${escapeHtml(a.image_url)}" referrerpolicy="no-referrer" onerror="this.style.visibility='hidden'">` : ''}</td>
            <td>${escapeHtml(a.name)}</td>
            <td>${a.seasons_count ?? '—'}</td>
            <td>${hasTrailer}</td>
            <td class="muted">${escapeHtml(syn)}</td>
            <td><div class="row-actions">
                <button class="btn" id="edit-${a.id}">Éditer</button>
                <button class="btn btn--danger" id="del-${a.id}">Suppr.</button>
            </div></td>
        </tr>`;
    }

    async function openEditor(id) {
        try {
            const { data } = await api(`/admin/animes/${id}`);
            state.editing = data;
            const f = $('#editForm');
            $('#editTitle').textContent = data.name;
            $('#editUrl').textContent = data.url;
            f.name.value = data.name || '';
            f.trailer_url.value = data.trailer_url || '';
            f.image_url.value = data.image_url || '';
            f.synopsis.value = data.synopsis || '';
            f.is_mature.checked = !!data.is_mature;
            $('#editErr').textContent = '';
            $('#editModal').classList.add('open');
        } catch (e) { toast(e.message); }
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
                    trailer_url: f.trailer_url.value.trim() || null,
                    image_url: f.image_url.value.trim() || null,
                    synopsis: f.synopsis.value.trim() || null,
                    is_mature: f.is_mature.checked,
                },
            });
            $('#editModal').classList.remove('open');
            toast('Enregistré');
            loadAnimes();
        } catch (err) {
            $('#editErr').textContent = err.message;
        }
    }

    async function deleteAnime(a) {
        if (!confirm(`Supprimer « ${a.name} » et toutes ses saisons/épisodes/liens ?`)) return;
        try {
            await api(`/admin/animes/${a.id}`, { method: 'DELETE' });
            toast('Supprimé');
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
    }

    document.addEventListener('DOMContentLoaded', boot);
})();
