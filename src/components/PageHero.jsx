export default function PageHero({ eyebrow, title, lead, children }) {
  return (
    <section className="hero">
      <div className="container" style={{ padding: '72px 0 76px', textAlign: 'center' }}>
        {eyebrow && (
          <span className="eyebrow" style={{ background: 'rgba(255,255,255,.14)', color: '#ffe27a' }}>
            {eyebrow}
          </span>
        )}
        <h1 style={{ fontSize: 'clamp(2rem, 4.5vw, 3rem)' }}>{title}</h1>
        {lead && <p className="lead" style={{ margin: '0 auto', color: 'rgba(255,255,255,.82)' }}>{lead}</p>}
        {children && <div style={{ marginTop: 22 }}>{children}</div>}
      </div>
    </section>
  )
}
