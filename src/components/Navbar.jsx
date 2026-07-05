import { useState } from 'react'
import { NavLink, Link } from 'react-router-dom'
import { useCart } from '../context/CartContext.jsx'

const links = [
  { to: '/', label: 'Accueil', end: true },
  { to: '/services', label: 'Services' },
  { to: '/streaming', label: 'Streaming' },
  { to: '/e-commerce', label: 'E-commerce' },
  { to: '/iptv', label: 'IPTV' },
  { to: '/blog', label: 'Blog' },
]

export default function Navbar() {
  const [open, setOpen] = useState(false)
  const { count, setOpen: setCartOpen } = useCart()
  return (
    <header className="nav">
      <div className="container nav-inner">
        <Link to="/" className="brand" onClick={() => setOpen(false)}>
          <span className="logo">
            <span /><span /><span />
          </span>
          <span>
            Faso Digital
            <small>Bamako, Mali</small>
          </span>
        </Link>

        <nav className={`nav-links ${open ? 'open' : ''}`}>
          {links.map((l) => (
            <NavLink key={l.to} to={l.to} end={l.end} onClick={() => setOpen(false)}>
              {l.label}
            </NavLink>
          ))}
          <Link to="/contact" className="btn btn-primary btn-sm" onClick={() => setOpen(false)}>
            Demander un devis
          </Link>
        </nav>

        <div className="nav-cta">
          <button className="cart-btn" aria-label="Panier" onClick={() => setCartOpen(true)}>
            <span aria-hidden="true">{'\u{1F6D2}'}</span>
            {count > 0 && <span className="cart-badge">{count}</span>}
          </button>
          <button className="nav-toggle" aria-label="Menu" onClick={() => setOpen((v) => !v)}>
            {open ? '\u2715' : '\u2630'}
          </button>
        </div>
      </div>
    </header>
  )
}
