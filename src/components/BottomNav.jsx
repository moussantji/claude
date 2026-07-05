import { NavLink } from 'react-router-dom'

// Barre d'onglets facon application mobile, visible uniquement sur petit ecran.
const tabs = [
  { to: '/', label: 'Accueil', icon: '\u{1F3E0}', end: true },
  { to: '/services', label: 'Services', icon: '\u{1F9E9}' },
  { to: '/streaming', label: 'Stream', icon: '\u25B6\uFE0F' },
  { to: '/blog', label: 'Blog', icon: '\u{1F4DD}' },
  { to: '/contact', label: 'Devis', icon: '\u2709\uFE0F' },
]

export default function BottomNav() {
  return (
    <nav className="bottom-nav" aria-label="Navigation mobile">
      {tabs.map((t) => (
        <NavLink key={t.to} to={t.to} end={t.end} className="bn-item">
          <span className="bn-ico">{t.icon}</span>
          <span className="bn-label">{t.label}</span>
        </NavLink>
      ))}
    </nav>
  )
}
