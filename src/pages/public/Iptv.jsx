import { Link } from 'react-router-dom'
import PageHero from '../../components/PageHero.jsx'
import Reveal from '../../components/Reveal.jsx'
import { useCollection } from '../../data/store.js'
import { fcfa } from '../../data/format.js'

export default function Iptv() {
  const plans = useCollection('products').filter((p) => p.category === 'IPTV' && p.status === 'Publie')

  return (
    <>
      <PageHero
        eyebrow="MandenTV IPTV"
        title="La television en direct, partout avec vous"
        lead="Des centaines de chaines nationales et internationales, le sport en direct, les films et la replay sur TV, PC et smartphone."
      >
        <Link to="/contact" className="btn btn-gold">Souscrire maintenant</Link>
      </PageHero>

      <section className="section">
        <div className="container">
          <div className="grid grid-4">
            {[
              { i: '\u{1F4FA}', t: '500+ chaines', d: 'Chaines maliennes, africaines et internationales.' },
              { i: '\u26BD', t: 'Sport en direct', d: 'Football, basket et grands evenements en live.' },
              { i: '\u23EA', t: 'Replay 7 jours', d: 'Rattrapez vos programmes preferes quand vous voulez.' },
              { i: '\u{1F5A5}', t: 'Multi-appareils', d: 'Box TV, smart TV, ordinateur et mobile.' },
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
            <span className="eyebrow">Abonnements IPTV</span>
            <h2 className="section-title">Des offres pour chaque budget</h2>
          </div>
          <div className="grid grid-2" style={{ maxWidth: 760, margin: '0 auto' }}>
            {plans.map((p, i) => (
              <div key={p.id} className={`card price-card ${i === plans.length - 1 ? 'pop' : ''}`}>
                <span className="pill pill-blue">{p.name.replace('MandenTV IPTV - ', '')}</span>
                <div className="price">{fcfa(p.price)}</div>
                <p className="cell-sub">{p.cycle}</p>
                <ul className="feature-list" style={{ textAlign: 'left' }}>
                  <li><span className="fi">&#10003;</span> Toutes les chaines incluses</li>
                  <li><span className="fi">&#10003;</span> Qualite HD / Full HD</li>
                  <li><span className="fi">&#10003;</span> Replay & VOD</li>
                  <li><span className="fi">&#10003;</span> Support technique inclus</li>
                </ul>
                <Link to="/contact" className={`btn ${i === plans.length - 1 ? 'btn-primary' : 'btn-ghost'} btn-block`} style={{ marginTop: 16 }}>
                  Souscrire
                </Link>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container center">
          <span className="eyebrow">Compatibilite</span>
          <h2 className="section-title">Fonctionne sur vos appareils</h2>
          <div className="grid grid-4" style={{ marginTop: 30 }}>
            {['Smart TV', 'Box Android', 'Windows / Mac', 'Android & iOS'].map((d) => (
              <div key={d} className="card center" style={{ fontWeight: 700 }}>{d}</div>
            ))}
          </div>
        </div>
      </section>
    </>
  )
}
