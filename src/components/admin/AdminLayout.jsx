import { useState } from 'react'
import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import Sidebar from './Sidebar.jsx'
import { useAuth } from '../../context/AuthContext.jsx'

const titles = {
  '/admin': 'Tableau de bord',
  '/admin/prospects': 'Prospects (Leads)',
  '/admin/clients': 'Clients',
  '/admin/projets': 'Projets',
  '/admin/messages': 'Messages recus',
  '/admin/produits': 'Produits & offres',
  '/admin/commandes': 'Commandes & abonnements',
  '/admin/blog': 'Gestion du blog',
  '/admin/parametres': 'Parametres',
}

export default function AdminLayout() {
  const { user, logout } = useAuth()
  const [open, setOpen] = useState(false)
  const location = useLocation()
  const navigate = useNavigate()

  const title = titles[location.pathname] || 'Administration'

  async function handleLogout() {
    await logout()
    navigate('/admin/login', { replace: true })
  }

  return (
    <div className="admin">
      <Sidebar open={open} onNavigate={() => setOpen(false)} />
      <div className="admin-main">
        <header className="topbar">
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <button
              className="icon-btn menu-btn"
              onClick={() => setOpen((v) => !v)}
              aria-label="Menu"
            >
              &#9776;
            </button>
            <h1>{title}</h1>
          </div>
          <div className="who">
            <div style={{ textAlign: 'right' }} className="hide-sm">
              <div className="cell-strong" style={{ fontSize: '.9rem' }}>{user?.name}</div>
              <div className="cell-sub">{user?.role}</div>
            </div>
            <div className="avatar">{user?.avatar}</div>
            <button className="btn btn-ghost btn-sm" onClick={handleLogout}>Deconnexion</button>
          </div>
        </header>
        <div className="admin-body">
          <Outlet />
        </div>
      </div>
    </div>
  )
}
