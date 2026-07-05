import { useParams, Link } from 'react-router-dom'
import { useCollection } from '../../data/store.js'
import { dateFr } from '../../data/format.js'

export default function BlogPost() {
  const { slug } = useParams()
  const posts = useCollection('posts')
  const post = posts.find((p) => p.slug === slug && p.status === 'Publie')

  if (!post) {
    return (
      <section className="section">
        <div className="container empty">
          <div className="big">&#128533;</div>
          <h2>Article introuvable</h2>
          <p>Cet article n&apos;existe pas ou n&apos;est plus disponible.</p>
          <Link to="/blog" className="btn btn-primary btn-sm">Retour au blog</Link>
        </div>
      </section>
    )
  }

  const related = posts
    .filter((p) => p.status === 'Publie' && p.id !== post.id && p.category === post.category)
    .slice(0, 2)

  return (
    <>
      <section className="hero">
        <div className="container" style={{ padding: '64px 0 60px', maxWidth: 800 }}>
          <span className="pill pill-gold">{post.category}</span>
          <h1 style={{ fontSize: 'clamp(1.8rem, 4vw, 2.8rem)', marginTop: 14 }}>{post.title}</h1>
          <div className="blog-meta" style={{ color: 'rgba(255,255,255,.75)' }}>
            <span>Par {post.author}</span><span>&middot;</span><span>{dateFr(post.date)}</span>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <article className="prose" style={{ margin: '0 auto' }}>
            <p style={{ fontSize: '1.2rem', fontWeight: 600, color: 'var(--green-dark)' }}>{post.excerpt}</p>
            {String(post.content).split('\n').filter(Boolean).map((para, i) => (
              <p key={i}>{para}</p>
            ))}
            <div style={{ marginTop: 30 }}>
              <Link to="/blog" className="btn btn-ghost btn-sm">&larr; Tous les articles</Link>
            </div>
          </article>

          {related.length > 0 && (
            <div style={{ maxWidth: 760, margin: '56px auto 0' }}>
              <h3>A lire aussi</h3>
              <div className="grid grid-2">
                {related.map((r) => (
                  <Link to={`/blog/${r.slug}`} key={r.id} className="card hover">
                    <span className="pill pill-green">{r.category}</span>
                    <h3 style={{ fontSize: '1.05rem', marginTop: 10 }}>{r.title}</h3>
                    <p className="cell-sub">{r.excerpt}</p>
                  </Link>
                ))}
              </div>
            </div>
          )}
        </div>
      </section>
    </>
  )
}
