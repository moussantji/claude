// ----------------------------------------------------------
// Client HTTP pour l'API Laravel + Sanctum (mode SPA par COOKIE).
// Active uniquement si VITE_API_URL est defini (fichier .env).
// Sinon, l'application fonctionne en mode demo (localStorage).
//
// Flux Sanctum SPA :
//   1. GET /sanctum/csrf-cookie  -> pose le cookie XSRF-TOKEN
//   2. Les requetes mutantes renvoient ce jeton dans l'en-tete X-XSRF-TOKEN
//   3. credentials: 'include' pour transporter le cookie de session
// ----------------------------------------------------------

// Sans slash final et sans suffixe /api (les chemins ajoutent deja /api/...).
// Exemples acceptes : https://api.mandenbaoubab.com ou https://api.mandenbaoubab.com/
const BASE = (import.meta.env.VITE_API_URL || '')
  .trim()
  .replace(/\/+$/, '')
  .replace(/\/api$/i, '')
export const API_ENABLED = !!BASE

function getCookie(name) {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'))
  return match ? decodeURIComponent(match[1]) : null
}

let csrfReady = false
async function ensureCsrf() {
  if (csrfReady) return
  await fetch(BASE + '/sanctum/csrf-cookie', { credentials: 'include' })
  csrfReady = true
}

async function request(method, path, body, _retried = false) {
  const mutating = method !== 'GET' && method !== 'HEAD'
  if (mutating) await ensureCsrf()

  const headers = { Accept: 'application/json' }
  if (mutating) {
    const xsrf = getCookie('XSRF-TOKEN')
    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf
  }

  const opts = { method, headers, credentials: 'include' }
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    opts.body = JSON.stringify(body)
  }

  const res = await fetch(BASE + path, opts)

  // Jeton CSRF expire : on rafraichit le cookie et on retente une fois.
  if (res.status === 419 && mutating && !_retried) {
    csrfReady = false
    await ensureCsrf()
    return request(method, path, body, true)
  }

  if (res.status === 204) return null

  let data = null
  try {
    data = await res.json()
  } catch {
    data = null
  }

  if (!res.ok) {
    const message =
      (data && (data.message || (data.errors && Object.values(data.errors)[0]?.[0]))) ||
      'Erreur ' + res.status
    const err = new Error(message)
    err.status = res.status
    err.data = data
    throw err
  }
  return data
}

export const api = {
  get: (p) => request('GET', p),
  post: (p, b) => request('POST', p, b),
  put: (p, b) => request('PUT', p, b),
  del: (p) => request('DELETE', p),
}

// --- Authentification (par cookie de session) ---
export async function apiLogin(email, password) {
  return api.post('/api/login', { email, password }) // { user }
}
export async function apiLogout() {
  try {
    await api.post('/api/logout')
  } catch {
    // on ignore : la session sera de toute facon consideree fermee cote front
  }
}
export function apiMe() {
  return api.get('/api/user')
}

// --- Ressources CRM generiques ---
export const resource = {
  list: (name) => api.get(`/api/${name}`),
  create: (name, item) => api.post(`/api/${name}`, item),
  update: (name, id, patch) => api.put(`/api/${name}/${id}`, patch),
  remove: (name, id) => api.del(`/api/${name}/${id}`),
}

export const settingsApi = {
  get: () => api.get('/api/settings'),
  update: (patch) => api.put('/api/settings', patch),
}
