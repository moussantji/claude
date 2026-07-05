import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { initStore, getAll } from '../data/store.js'
import { API_ENABLED, apiLogin, apiLogout, apiMe, getToken, setToken } from '../api/client.js'

const AuthContext = createContext(null)
const SESSION_KEY = 'faso.session'

function toSafeUser(u) {
  return { id: u.id, name: u.name, email: u.email, role: u.role, avatar: u.avatar }
}

export function AuthProvider({ children }) {
  // On amorce les donnees de demonstration au premier chargement
  const [user, setUser] = useState(() => {
    initStore()
    try {
      const raw = localStorage.getItem(SESSION_KEY)
      return raw ? JSON.parse(raw) : null
    } catch {
      return null
    }
  })

  useEffect(() => {
    if (user) localStorage.setItem(SESSION_KEY, JSON.stringify(user))
    else localStorage.removeItem(SESSION_KEY)
  }, [user])

  // En mode API : si un jeton existe, on tente de restaurer la session
  useEffect(() => {
    if (API_ENABLED && getToken()) {
      apiMe()
        .then((u) => u && setUser(toSafeUser(u)))
        .catch(() => {
          setToken(null)
          setUser(null)
        })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function login(email, password) {
    // --- Mode API (Laravel + Sanctum) ---
    if (API_ENABLED) {
      try {
        const data = await apiLogin(email, password)
        setUser(toSafeUser(data.user))
        return { ok: true }
      } catch (e) {
        return { ok: false, error: e.message || 'Connexion impossible.' }
      }
    }

    // --- Mode demo (localStorage) ---
    const users = getAll('users')
    const found = users.find(
      (u) => u.email.toLowerCase() === String(email).trim().toLowerCase() && u.password === password
    )
    if (!found) {
      return { ok: false, error: 'Identifiants incorrects. Verifiez votre email et mot de passe.' }
    }
    setUser(toSafeUser(found))
    return { ok: true }
  }

  async function logout() {
    if (API_ENABLED) await apiLogout()
    setUser(null)
  }

  const value = useMemo(() => ({ user, isAuthenticated: !!user, login, logout }), [user])
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth doit etre utilise dans AuthProvider')
  return ctx
}
