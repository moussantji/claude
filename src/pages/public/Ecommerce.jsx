import { Link } from 'react-router-dom'
import PageHero from '../../components/PageHero.jsx'
import Reveal from '../../components/Reveal.jsx'
import { useCollection } from '../../data/store.js'
import { fcfa } from '../../data/format.js'

export default function Ecommerce() {
  const products = useCollection('products').filter((p) => p.category === 'E-commerce' && p.status === 'Publie')

  return (
    <>
      <PageHero
        eyebrow="FasoShop"
        title="Achetez et vendez en toute confiance"
        lead="Notre marketplace et notre application mobile e-commerce : paiement Orange Money / Moov Money, livraison a Bamako et dans tout le Mali."
      >
        <Link to="/contact" className="btn btn-gold">Ouvrir ma boutique</Link>
      </PageHero>

      <section className="section">
        <div className="container">
          <div className="grid grid-4">
            {[
              { i: '\u{1F4B3}', t: 'Mobile Money', d: 'Orange Money, Moov Money et carte bancaire.' },
              { i: '\u{1F69A}', t: 'Livraison rapide', d: 'A Bamako en 24-48h, en region sous 5 jours.' },
              { i: '\u{1F6D2}', t: 'Panier & suivi', d: 'Commandes suivies en temps reel jusqu\'a la livraison.' },
              { i: '\u{1F4F2}', t: 'App mobile', d: 'Application Android pour acheter en un clic.' },
            ].map((f, i) => (
              <Reveal key={f.t} delay={i * 90}>
                <div className="card hover">
                  <div className="ico">{f.i}</div>
                  <h3 style={{ fontSize: '1.1rem' }}>{f.t}</h3>
                  <p>{f.d}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      <section className="section section-pattern">
        <div className="container">
          <div className="center" style={{ marginBottom: 44 }}>
            <span className="eyebrow">Selection du moment</span>
            <h2 className="section-title">Produits populaires</h2>
          </div>
          <div className="grid grid-3">
            {products.map((p) => (
              <div key={p.id} className="card blog-card hover">
                <div className="thumb media-shop" style={{ background: 'linear-gradient(135deg,#0b3d2e,#14b866)' }}>
                  <span className="cat pill pill-gold">Nouveaute</span>
                </div>
                <div className="body">
                  <h3 style={{ fontSize: '1.05rem' }}>{p.name}</h3>
                  <div className="price" style={{ fontSize: '1.4rem', margin: '6px 0' }}>{fcfa(p.price)}</div>
                  <button className="btn btn-primary btn-sm btn-block">Ajouter au panier</button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container split">
          <div>
            <span className="eyebrow">Vendeurs</span>
            <h2 className="section-title">Vendez vos produits en ligne</h2>
            <p className="section-lead">
              Ouvrez votre boutique sur FasoShop et touchez des clients dans tout le Mali. Nous
              gerons la technologie, le paiement et la logistique, vous vous concentrez sur vos ventes.
            </p>
            <ul className="feature-list">
              <li><span className="fi">&#10003;</span> Inscription simple et rapide</li>
              <li><span className="fi">&#10003;</span> Tableau de bord des ventes</li>
              <li><span className="fi">&#10003;</span> Paiements securises reverses chaque semaine</li>
            </ul>
            <Link to="/contact" className="btn btn-primary btn-sm">Devenir vendeur</Link>
          </div>
          <div className="split-media">
            <div className="media-card media-shop">
              <span className="badge">Application FasoShop</span>
              <h3>Vos ventes dans votre poche</h3>
              <p>Recevez une notification a chaque commande.</p>
            </div>
          </div>
        </div>
      </section>
    </>
  )
}
