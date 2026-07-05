import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { initStore, getAll } from '../data/store.js'

const AuthContext = createContext(null)
const SESSION_KEY = 'faso.session'

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

  function login(email, password) {
    const users = getAll('users')
    const found = users.find(
      (u) => u.email.toLowerCase() === String(email).trim().toLowerCase() && u.password === password
    )
    if (!found) {
      return { ok: false, error: 'Identifiants incorrects. Verifiez votre email et mot de passe.' }
    }
    const safe = { id: found.id, name: found.name, email: found.email, role: found.role, avatar: found.avatar }
    setUser(safe)
    return { ok: true }
  }

  function logout() {
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
