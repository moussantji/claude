import { useState } from 'react'
import { Link } from 'react-router-dom'
import PageHero from '../../components/PageHero.jsx'
import { useCollection } from '../../data/store.js'
import { dateFr } from '../../data/format.js'

export default function Blog() {
  const all = useCollection('posts').filter((p) => p.status === 'Publie')
  const [cat, setCat] = useState('Tous')

  const categories = ['Tous', ...Array.from(new Set(all.map((p) => p.category)))]
  const posts = cat === 'Tous' ? all : all.filter((p) => p.category === cat)

  return (
    <>
      <PageHero
        eyebrow="Le blog"
        title="Conseils, actualites & tendances numeriques"
        lead="Nos articles pour vous aider a reussir votre transformation digitale au Mali."
      />

      <section className="section">
        <div className="container">
          <div className="chips" style={{ marginBottom: 30 }}>
            {categories.map((c) => (
              <button key={c} className={`chip ${cat === c ? 'active' : ''}`} onClick={() => setCat(c)}>
                {c}
              </button>
            ))}
          </div>

          {posts.length === 0 ? (
            <div className="empty"><div className="big">&#128221;</div>Aucun article dans cette categorie.</div>
          ) : (
            <div className="grid grid-3">
              {posts.map((p) => (
                <Link to={`/blog/${p.slug}`} key={p.id} className="card blog-card hover">
                  <div className="thumb"><span className="cat pill pill-gold">{p.category}</span></div>
                  <div className="body">
                    <h3>{p.title}</h3>
                    <p className="cell-sub">{p.excerpt}</p>
                    <div className="blog-meta">
                      <span>{p.author}</span><span>&middot;</span><span>{dateFr(p.date)}</span>
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </div>
      </section>
    </>
  )
}
