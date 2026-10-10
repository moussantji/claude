import { Link } from 'react-router-dom'
import { useCollection } from '../../data/store.js'
import { dateFr } from '../../data/format.js'
import Reveal from '../../components/Reveal.jsx'

export default function Home() {
  const posts = useCollection('posts').filter((p) => p.status === 'Publie').slice(0, 3)

  return (
    <>
      {/* HERO */}
      <section className="hero">
        <div className="container hero-grid">
          <Reveal className="reveal-left">
            <span className="eyebrow" style={{ background: 'rgba(255,255,255,.14)', color: '#ffe27a' }}>
              Entreprise numerique malienne
            </span>
            <h1>
              Le numerique qui fait grandir <span className="tag">votre entreprise</span> au Mali
            </h1>
            <p className="lead">
              Sites web, applications, logiciels sur mesure et nos propres plateformes de
              streaming, e-commerce et IPTV. Une equipe basee a Bamako, des solutions pensees
              pour l&apos;Afrique.
            </p>
            <div className="hero-actions">
              <Link to="/contact" className="btn btn-gold">Demander un devis gratuit</Link>
              <Link to="/services" className="btn btn-outline">Decouvrir nos services</Link>
            </div>
            <div className="hero-stats">
              <div className="stat"><b>120+</b><span>projets livres</span></div>
              <div className="stat"><b>1 500+</b><span>abonnes plateformes</span></div>
              <div className="stat"><b>8 ans</b><span>d&apos;experience</span></div>
            </div>
          </Reveal>

          <Reveal className="reveal-right" delay={120}>
            <div className="hero-visual">
              <div className="win-dots"><i /><i /><i /></div>
              <div className="mock-row">
                <div className="mock-card"><div className="k">3.2 M</div><div className="l">FCFA ce mois</div></div>
                <div className="mock-card"><div className="k">+18%</div><div className="l">croissance</div></div>
                <div className="mock-card"><div className="k">1 549</div><div className="l">abonnes</div></div>
              </div>
              <div className="mock-bars">
                <span style={{ height: '45%' }} /><span style={{ height: '62%' }} />
                <span style={{ height: '38%' }} /><span style={{ height: '80%' }} />
                <span style={{ height: '55%' }} /><span style={{ height: '95%' }} />
                <span style={{ height: '70%' }} />
              </div>
            </div>
          </Reveal>
        </div>
      </section>

      {/* TRUST */}
      <div className="trust">
        <div className="container trust-inner">
          <span>Ils nous font confiance :</span>
          <span>Clinique Espoir</span>
          <span>Groupe Diarra</span>
          <span>Djenne Palace</span>
          <span>Manden Mode</span>
          <span>Radio Kayira</span>
        </div>
      </div>

      {/* SERVICES */}
      <section className="section">
        <div className="container">
          <Reveal className="center" style={{ marginBottom: 46 }}>
            <span className="eyebrow">Nos services</span>
            <h2 className="section-title">Tout le numerique, sous un seul toit</h2>
            <p className="section-lead">
              De l&apos;idee au lancement, nous concevons des produits digitaux fiables,
              rapides et adaptes a vos clients.
            </p>
          </Reveal>
          <div className="grid grid-3">
            {[
              { i: '\u{1F310}', t: 'Creation de sites web', d: 'Sites vitrines, institutionnels et boutiques en ligne rapides, responsives et optimises pour Google.', pts: ['Design sur mesure', 'Mobile & SEO', 'Paiement mobile money'] },
              { i: '\u{1F4F1}', t: 'Applications web', d: 'Plateformes metier, espaces clients et applications sur mesure accessibles partout.', pts: ['Interfaces intuitives', 'Temps reel', 'Securise & evolutif'] },
              { i: '\u{1F4BB}', t: 'Logiciels PC', d: 'Logiciels de bureau (Windows) pour la gestion, la facturation ou le stock, meme hors-ligne.', pts: ['Fonctionne hors-ligne', 'Gestion complete', 'Support local'] },
            ].map((s, i) => (
              <Reveal key={s.t} delay={i * 110}>
                <div className="card hover">
                  <div className="ico">{s.i}</div>
                  <h3>{s.t}</h3>
                  <p>{s.d}</p>
                  <ul>
                    {s.pts.map((p) => (
                      <li key={p}><span className="tick">&#10003;</span> {p}</li>
                    ))}
                  </ul>
                </div>
              </Reveal>
            ))}
          </div>
          <div className="center" style={{ marginTop: 34 }}>
            <Link to="/services" className="btn btn-ghost">Voir tous les services &rarr;</Link>
          </div>
        </div>
      </section>

      {/* PLATFORMS */}
      <section className="section section-pattern">
        <div className="container">
          <Reveal className="center" style={{ marginBottom: 46 }}>
            <span className="eyebrow">Nos plateformes maison</span>
            <h2 className="section-title">Des services digitaux qui nous appartiennent</h2>
            <p className="section-lead">
              Nous ne faisons pas que developper pour les autres : nous operons nos propres
              plateformes utilisees par des milliers de Maliens.
            </p>
          </Reveal>

          <div className="grid" style={{ gap: 28 }}>
            <Reveal className="split">
              <div className="split-media">
                <div className="media-card media-stream">
                  <span className="badge">Manden Stream</span>
                  <div className="play">&#9654;</div>
                  <h3>Streaming films & musique</h3>
                  <p>Contenus africains et internationaux, en HD et 4K.</p>
                </div>
              </div>
              <div>
                <span className="pill pill-red">Streaming</span>
                <h3 style={{ fontSize: '1.6rem', marginTop: 12 }}>Manden Stream</h3>
                <p style={{ color: 'var(--muted)' }}>
                  Une plateforme de streaming de films, series et musique pensee pour l&apos;Afrique :
                  contenus locaux en bambara et en francais, lecture fluide meme en connexion moderee.
                </p>
                <Link to="/streaming" className="btn btn-primary btn-sm">Explorer Manden Stream</Link>
              </div>
            </Reveal>

            <Reveal className="split rev">
              <div className="split-media">
                <div className="media-card media-shop">
                  <span className="badge">Manden Shop</span>
                  <h3>Boutique en ligne</h3>
                  <p>Vendez et achetez avec paiement mobile money.</p>
                </div>
              </div>
              <div>
                <span className="pill pill-green">E-commerce</span>
                <h3 style={{ fontSize: '1.6rem', marginTop: 12 }}>Manden Shop</h3>
                <p style={{ color: 'var(--muted)' }}>
                  Notre marketplace et notre application mobile e-commerce : catalogue, panier,
                  paiement Orange Money / Moov Money et livraison a Bamako et en region.
                </p>
                <Link to="/e-commerce" className="btn btn-primary btn-sm">Explorer Manden Shop</Link>
              </div>
            </Reveal>

            <Reveal className="split">
              <div className="split-media">
                <div className="media-card media-iptv">
                  <span className="badge">Manden TV</span>
                  <div className="play">&#9654;</div>
                  <h3>IPTV & TV en direct</h3>
                  <p>Chaines locales, sport et divertissement.</p>
                </div>
              </div>
              <div>
                <span className="pill pill-blue">IPTV</span>
                <h3 style={{ fontSize: '1.6rem', marginTop: 12 }}>Manden TV IPTV</h3>
                <p style={{ color: 'var(--muted)' }}>
                  Des centaines de chaines nationales et internationales, le sport en direct et
                  la replay, sur votre TV, ordinateur ou smartphone.
                </p>
                <Link to="/iptv" className="btn btn-primary btn-sm">Explorer Manden TV</Link>
              </div>
            </Reveal>
          </div>
        </div>
      </section>

      {/* PROCESS */}
      <section className="section">
        <div className="container">
          <Reveal className="center" style={{ marginBottom: 46 }}>
            <span className="eyebrow">Notre methode</span>
            <h2 className="section-title">Un projet livre en 4 etapes claires</h2>
          </Reveal>
          <div className="grid grid-4 steps">
            {[
              { n: 1, t: 'Ecoute & devis', d: 'Nous analysons votre besoin et proposons un devis clair et gratuit.' },
              { n: 2, t: 'Conception', d: 'Maquettes et validation ensemble avant tout developpement.' },
              { n: 3, t: 'Developpement', d: 'Nous construisons, vous suivez l\'avancement en continu.' },
              { n: 4, t: 'Lancement & suivi', d: 'Mise en ligne, formation et support technique local.' },
            ].map((s, i) => (
              <Reveal key={s.n} delay={i * 90}>
                <div className="card">
                  <div className="step-num">{s.n}</div>
                  <h3 style={{ fontSize: '1.1rem' }}>{s.t}</h3>
                  <p>{s.d}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      {/* PRICING */}
      <section className="section section-pattern">
        <div className="container">
          <Reveal className="center" style={{ marginBottom: 46 }}>
            <span className="eyebrow">Tarifs sites web</span>
            <h2 className="section-title">Des formules simples et transparentes</h2>
          </Reveal>
          <div className="grid grid-3">
            {[
              { t: 'Vitrine', p: '250 000', pop: false, feats: ['Jusqu\'a 5 pages', 'Design responsive', 'Formulaire de contact', 'Referencement de base', 'Hebergement 1 an offert'] },
              { t: 'Business', p: '600 000', pop: true, feats: ['Jusqu\'a 15 pages', 'Blog integre', 'SEO avance', 'Espace administrateur', 'Support prioritaire 6 mois'] },
              { t: 'E-commerce', p: '1 200 000', pop: false, feats: ['Boutique complete', 'Paiement mobile money', 'Gestion des stocks', 'Tableau de bord ventes', 'Formation incluse'] },
            ].map((pl, i) => (
              <Reveal key={pl.t} delay={i * 110}>
                <div className={`card price-card ${pl.pop ? 'pop' : ''}`}>
                  <span className="pill pill-green">{pl.t}</span>
                  <div className="price">{pl.p} <small>FCFA</small></div>
                  <p className="cell-sub" style={{ marginBottom: 8 }}>a partir de</p>
                  <ul className="feature-list" style={{ textAlign: 'left' }}>
                    {pl.feats.map((f) => (
                      <li key={f}><span className="fi">&#10003;</span> {f}</li>
                    ))}
                  </ul>
                  <Link to="/contact" className={`btn ${pl.pop ? 'btn-primary' : 'btn-ghost'} btn-block`} style={{ marginTop: 18 }}>
                    Choisir cette formule
                  </Link>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      {/* BLOG PREVIEW */}
      {posts.length > 0 && (
        <section className="section">
          <div className="container">
            <Reveal className="center" style={{ marginBottom: 46 }}>
              <span className="eyebrow">Le blog</span>
              <h2 className="section-title">Conseils & actualites numeriques</h2>
            </Reveal>
            <div className="grid grid-3">
              {posts.map((p, i) => (
                <Reveal key={p.id} delay={i * 110}>
                  <Link to={`/blog/${p.slug}`} className="card blog-card hover">
                    <div className="thumb"><span className="cat pill pill-gold">{p.category}</span></div>
                    <div className="body">
                      <h3>{p.title}</h3>
                      <p className="cell-sub">{p.excerpt}</p>
                      <div className="blog-meta"><span>{p.author}</span><span>&middot;</span><span>{dateFr(p.date)}</span></div>
                    </div>
                  </Link>
                </Reveal>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* CTA */}
      <section className="section-tight">
        <div className="container">
          <Reveal className="reveal-zoom">
            <div className="cta-band">
              <h2>Un projet en tete ? Parlons-en.</h2>
              <p>Devis gratuit sous 48h. Notre equipe a Bamako vous accompagne de A a Z.</p>
              <div style={{ display: 'flex', gap: 14, justifyContent: 'center', flexWrap: 'wrap', marginTop: 8 }}>
                <Link to="/contact" className="btn btn-gold">Demander un devis</Link>
                <a href="tel:+22320000000" className="btn btn-outline">+223 20 00 00 00</a>
              </div>
            </div>
          </Reveal>
        </div>
      </section>
    </>
  )
}
