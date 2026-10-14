import { Link } from 'react-router-dom'
import PageHero from '../../components/PageHero.jsx'
import Reveal from '../../components/Reveal.jsx'
import { useCollection } from '../../data/store.js'
import { fcfa } from '../../data/format.js'

export default function Streaming() {
  const plans = useCollection('products').filter((p) => p.category === 'Streaming' && p.status === 'Publie')

  const genres = ['Films africains', 'Series', 'Musique & clips', 'Documentaires', 'Jeunesse', 'Comedie', 'Action', 'Concerts live']

  return (
    <>
      <PageHero
        eyebrow="MandenStream"
        title="Le streaming pense pour l'Afrique"
        lead="Films, series et musique en bambara, francais et anglais. Une lecture fluide, meme en connexion moderee, sur tous vos ecrans."
      >
        <Link to="/contact" className="btn btn-gold">Demander un acces</Link>
      </PageHero>

      <section className="section">
        <div className="container">
          <div className="grid grid-4">
            {[
              { i: '\u{1F3AC}', t: '10 000+ titres', d: 'Un catalogue riche en contenus locaux et internationaux.' },
              { i: '\u{1F4F6}', t: 'Adapte au reseau', d: 'Qualite ajustee automatiquement selon votre connexion.' },
              { i: '\u{1F4F1}', t: 'Multi-ecrans', d: 'TV, ordinateur, tablette et smartphone.' },
              { i: '\u{1F1F2}\u{1F1F1}', t: 'Contenu malien', d: 'Films et musiques du terroir mis en avant.' },
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
        <div className="container center">
          <span className="eyebrow">Categories</span>
          <h2 className="section-title">Il y en a pour tous les gouts</h2>
          <div className="chips" style={{ justifyContent: 'center', marginTop: 24 }}>
            {genres.map((g) => (
              <span key={g} className="chip">{g}</span>
            ))}
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="center" style={{ marginBottom: 44 }}>
            <span className="eyebrow">Abonnements</span>
            <h2 className="section-title">Choisissez votre formule</h2>
          </div>
          <div className="grid grid-2" style={{ maxWidth: 760, margin: '0 auto' }}>
            {plans.map((p, i) => (
              <div key={p.id} className={`card price-card ${i === 1 ? 'pop' : ''}`}>
                <span className="pill pill-red">{p.name.replace('MandenStream - ', '')}</span>
                <div className="price">{fcfa(p.price)}</div>
                <p className="cell-sub">{p.cycle}</p>
                <ul className="feature-list" style={{ textAlign: 'left' }}>
                  <li><span className="fi">&#10003;</span> Catalogue complet</li>
                  <li><span className="fi">&#10003;</span> {i === 1 ? 'Qualite 4K Ultra HD' : 'Qualite HD'}</li>
                  <li><span className="fi">&#10003;</span> {i === 1 ? '4 ecrans simultanes' : '1 ecran'}</li>
                  <li><span className="fi">&#10003;</span> Sans engagement</li>
                </ul>
                <Link to="/contact" className={`btn ${i === 1 ? 'btn-primary' : 'btn-ghost'} btn-block`} style={{ marginTop: 16 }}>
                  S&apos;abonner
                </Link>
              </div>
            ))}
          </div>
        </div>
      </section>
    </>
  )
}
