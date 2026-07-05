import { useState } from 'react'
import { useNavigate, useLocation, Navigate, Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext.jsx'

export default function Login() {
  const { login, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const from = location.state?.from?.pathname || '/admin'

  if (isAuthenticated) return <Navigate to="/admin" replace />

  async function submit(e) {
    e.preventDefault()
    setError('')
    setLoading(true)
    const res = await login(email, password)
    setLoading(false)
    if (res.ok) navigate(from, { replace: true })
    else setError(res.error)
  }

  return (
    <div className="auth-wrap">
      <div className="auth-card">
        <div className="brand">
          <span className="logo"><span /><span /><span /></span>
          <span>Faso Digital</span>
        </div>
        <p className="center cell-sub" style={{ marginBottom: 22 }}>Espace administration &middot; CRM</p>

        {error && <div className="auth-error">{error}</div>}

        <form onSubmit={submit}>
          <div className="field">
            <label>Adresse email</label>
            <input
              className="input"
              type="email"
              required
              autoFocus
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="admin@fasodigital.ml"
            />
          </div>
          <div className="field">
            <label>Mot de passe</label>
            <input
              className="input"
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
            />
          </div>
          <button type="submit" className="btn btn-primary btn-block" disabled={loading}>
            {loading ? 'Connexion...' : 'Se connecter'}
          </button>
        </form>

        <div className="auth-note">
          <b>Comptes de demonstration</b><br />
          Admin : admin@fasodigital.ml / admin123<br />
          Chef de projet : fatou@fasodigital.ml / faso2025
        </div>

        <p className="center" style={{ marginTop: 18, marginBottom: 0 }}>
          <Link to="/" className="cell-sub">&larr; Retour au site</Link>
        </p>
      </div>
    </div>
  )
}
