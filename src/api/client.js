// ----------------------------------------------------------
// Client HTTP pour l'API Laravel + Sanctum.
// Active uniquement si VITE_API_URL est defini (fichier .env).
// Sinon, l'application fonctionne en mode demo (localStorage).
// ----------------------------------------------------------

const BASE = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '')
export const API_ENABLED = !!BASE

const TOKEN_KEY = 'faso.token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}
export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

async function request(method, path, body) {
  const headers = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = 'Bearer ' + token

  const opts = { method, headers }
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    opts.body = JSON.stringify(body)
  }

  const res = await fetch(BASE + path, opts)
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

// --- Authentification ---
export async function apiLogin(email, password) {
  const data = await api.post('/api/login', { email, password })
  if (data && data.token) setToken(data.token)
  return data // { token, user }
}
export async function apiLogout() {
  try {
    await api.post('/api/logout')
  } catch {
    // on ignore : on nettoie le jeton quoi qu'il arrive
  }
  setToken(null)
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
