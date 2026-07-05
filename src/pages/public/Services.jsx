import { Link } from 'react-router-dom'
import Reveal from '../../components/Reveal.jsx'

function PageHero({ eyebrow, title, lead }) {
  return (
    <section className="hero">
      <div className="container" style={{ padding: '72px 0 76px', textAlign: 'center' }}>
        <span className="eyebrow" style={{ background: 'rgba(255,255,255,.14)', color: '#ffe27a' }}>{eyebrow}</span>
        <h1 style={{ fontSize: 'clamp(2rem, 4.5vw, 3rem)' }}>{title}</h1>
        <p className="lead" style={{ margin: '0 auto', color: 'rgba(255,255,255,.82)' }}>{lead}</p>
      </div>
    </section>
  )
}

const services = [
  {
    icon: '\u{1F310}',
    title: 'Creation de sites web',
    desc: 'Nous concevons des sites qui inspirent confiance et convertissent vos visiteurs en clients.',
    items: ['Sites vitrines & institutionnels', 'Boutiques e-commerce', 'Design responsive (mobile, tablette, PC)', 'Optimisation SEO pour Google', 'Integration paiement mobile money', 'Hebergement & nom de domaine'],
  },
  {
    icon: '\u{1F4F1}',
    title: 'Applications web',
    desc: 'Des plateformes metier sur mesure, accessibles depuis n\'importe quel navigateur.',
    items: ['Espaces clients & portails', 'Applications de gestion', 'Tableaux de bord & statistiques', 'Prise de rendez-vous en ligne', 'Fonctionnalites temps reel', 'Architecture securisee et evolutive'],
  },
  {
    icon: '\u{1F4BB}',
    title: 'Logiciels PC',
    desc: 'Des logiciels de bureau robustes qui fonctionnent meme sans connexion internet.',
    items: ['Gestion de stock & inventaire', 'Facturation & comptabilite', 'Gestion RH & paie', 'Point de vente (caisse)', 'Fonctionnement hors-ligne', 'Sauvegarde & synchronisation'],
  },
]

export default function Services() {
  return (
    <>
      <PageHero
        eyebrow="Nos services"
        title="Des solutions numeriques completes"
        lead="Site web, application ou logiciel : nous transformons votre besoin en un produit fiable, moderne et adapte au marche malien."
      />

      <section className="section">
        <div className="container">
          <div className="grid" style={{ gap: 28 }}>
            {services.map((s, idx) => (
              <Reveal key={s.title} className={`split ${idx % 2 ? 'rev' : ''}`}>
                <div>
                  <div className="ico">{s.icon}</div>
                  <h2 style={{ fontSize: '1.8rem' }}>{s.title}</h2>
                  <p className="section-lead">{s.desc}</p>
                  <Link to="/contact" className="btn btn-primary btn-sm">Demander un devis</Link>
                </div>
                <div className="split-media">
                  <div className="card">
                    <ul className="feature-list">
                      {s.items.map((it) => (
                        <li key={it}><span className="fi">&#10003;</span> {it}</li>
                      ))}
                    </ul>
                  </div>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      <section className="section section-pattern">
        <div className="container center">
          <span className="eyebrow">Technologies</span>
          <h2 className="section-title">Des outils modernes et eprouves</h2>
          <p className="section-lead">Nous utilisons les meilleures technologies pour garantir performance et securite.</p>
          <div className="grid grid-4" style={{ marginTop: 34 }}>
            {['React', 'Node.js', 'React Native', 'Python', 'PostgreSQL', '.NET / C#', 'Orange Money API', 'Cloud & VPS'].map((t) => (
              <div key={t} className="card center" style={{ fontWeight: 700 }}>{t}</div>
            ))}
          </div>
        </div>
      </section>

      <section className="section-tight">
        <div className="container">
          <div className="cta-band">
            <h2>Discutons de votre projet</h2>
            <p>Un premier echange gratuit pour cadrer vos besoins et estimer le budget.</p>
            <Link to="/contact" className="btn btn-gold">Nous contacter</Link>
          </div>
        </div>
      </section>
    </>
  )
}
