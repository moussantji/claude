import { Link } from 'react-router-dom'

export default function NotFound() {
  return (
    <section className="section">
      <div className="container empty" style={{ padding: '90px 20px' }}>
        <div className="big" style={{ fontSize: '3rem' }}>404</div>
        <h2>Page introuvable</h2>
        <p>La page que vous cherchez n&apos;existe pas ou a ete deplacee.</p>
        <Link to="/" className="btn btn-primary btn-sm">Retour a l&apos;accueil</Link>
      </div>
    </section>
  )
}
