import { NavLink } from 'react-router-dom'

const groups = [
  {
    title: 'Pilotage',
    items: [
      { to: '/admin', label: 'Tableau de bord', icon: '\u{1F4CA}', end: true },
    ],
  },
  {
    title: 'Relation client',
    items: [
      { to: '/admin/prospects', label: 'Prospects', icon: '\u{1F3AF}' },
      { to: '/admin/clients', label: 'Clients', icon: '\u{1F465}' },
      { to: '/admin/projets', label: 'Projets', icon: '\u{1F4C1}' },
      { to: '/admin/messages', label: 'Messages', icon: '\u2709\uFE0F' },
    ],
  },
  {
    title: 'Plateformes',
    items: [
      { to: '/admin/produits', label: 'Produits & offres', icon: '\u{1F3F7}\uFE0F' },
      { to: '/admin/commandes', label: 'Commandes & abos', icon: '\u{1F6D2}' },
    ],
  },
  {
    title: 'Contenu',
    items: [
      { to: '/admin/blog', label: 'Blog', icon: '\u{1F4DD}' },
    ],
  },
  {
    title: 'Systeme',
    items: [
      { to: '/admin/parametres', label: 'Parametres', icon: '\u2699\uFE0F' },
    ],
  },
]

export default function Sidebar({ open, onNavigate }) {
  return (
    <aside className={`sidebar ${open ? 'open' : ''}`}>
      <div className="brand">
        <span className="logo"><span /><span /><span /></span>
        <span>
          Manden Baoubab
          <small style={{ display: 'block', fontSize: '.6rem', letterSpacing: '.14em', color: 'rgba(255,255,255,.5)' }}>CRM</small>
        </span>
      </div>

      {groups.map((g) => (
        <div key={g.title} className="nav-group">
          <div className="grp-title">{g.title}</div>
          {g.items.map((it) => (
            <NavLink key={it.to} to={it.to} end={it.end} onClick={onNavigate}>
              <span className="si">{it.icon}</span>
              {it.label}
            </NavLink>
          ))}
        </div>
      ))}

      <div className="side-foot">
        <NavLink to="/" onClick={onNavigate}>
          <span className="si">&#8599;</span> Voir le site public
        </NavLink>
      </div>
    </aside>
  )
}
