import { Link } from 'react-router-dom'
import { useCollection } from '../../data/store.js'
import { fcfa, fcfaShort, timeAgo } from '../../data/format.js'

export default function Dashboard() {
  const leads = useCollection('leads')
  const clients = useCollection('clients')
  const projects = useCollection('projects')
  const products = useCollection('products')
  const orders = useCollection('orders')
  const messages = useCollection('messages')
  const posts = useCollection('posts')

  const revenue = orders.reduce((s, o) => s + (Number(o.amount) || 0), 0)
  const subscribers = products.reduce((s, p) => s + (Number(p.subscribers) || 0), 0)
  const activeProjects = projects.filter((p) => p.status === 'En cours').length
  const newLeads = leads.filter((l) => l.status === 'Nouveau').length
  const unreadMsg = messages.filter((m) => m.status === 'Nouveau').length

  const kpis = [
    { label: 'Chiffre d\'affaires', val: fcfaShort(revenue) + ' F', trend: '+18%', up: true, icon: '\u{1F4B0}', bg: 'rgba(20,184,102,.14)' },
    { label: 'Prospects actifs', val: leads.length, sub: `${newLeads} nouveaux`, trend: '+5', up: true, icon: '\u{1F3AF}', bg: 'rgba(252,209,22,.22)' },
    { label: 'Clients', val: clients.length, trend: '+2', up: true, icon: '\u{1F465}', bg: 'rgba(37,99,235,.12)' },
    { label: 'Abonnes plateformes', val: subscribers.toLocaleString('fr-FR'), trend: '+124', up: true, icon: '\u{1F4FA}', bg: 'rgba(206,17,38,.12)' },
  ]

  // Ventes par plateforme (repartition du CA)
  const byType = {}
  orders.forEach((o) => { byType[o.type] = (byType[o.type] || 0) + (Number(o.amount) || 0) })
  const maxType = Math.max(1, ...Object.values(byType))

  // Pipeline commercial
  const pipeline = ['Nouveau', 'Contacte', 'Qualifie', 'Gagne', 'Perdu'].map((s) => ({
    s,
    n: leads.filter((l) => l.status === s).length,
  }))

  const activity = [
    ...messages.slice(0, 3).map((m) => ({ icon: '\u2709\uFE0F', text: `Nouveau message de ${m.name}`, time: m.date, to: '/admin/messages' })),
    ...orders.slice(0, 2).map((o) => ({ icon: '\u{1F6D2}', text: `Commande ${o.ref} - ${fcfa(o.amount)}`, time: o.date, to: '/admin/commandes' })),
    ...leads.slice(0, 2).map((l) => ({ icon: '\u{1F3AF}', text: `Prospect ${l.name} (${l.service})`, time: l.createdAt, to: '/admin/prospects' })),
  ].sort((a, b) => new Date(b.time) - new Date(a.time)).slice(0, 6)

  return (
    <>
      <div className="kpi-grid">
        {kpis.map((k) => (
          <div key={k.label} className="kpi">
            <div className="kpi-top">
              <div className="kpi-ico" style={{ background: k.bg }}>{k.icon}</div>
              <span className={`kpi-trend ${k.up ? 'trend-up' : 'trend-down'}`}>{k.up ? '\u25B2' : '\u25BC'} {k.trend}</span>
            </div>
            <div className="kpi-val">{k.val}</div>
            <div className="kpi-label">{k.label}{k.sub ? ` \u00b7 ${k.sub}` : ''}</div>
          </div>
        ))}
      </div>

      <div className="two-col">
        <div className="panel">
          <div className="panel-head">
            <h3>Revenus par plateforme</h3>
            <span className="cell-sub">Total {fcfa(revenue)}</span>
          </div>
          <div className="panel-body">
            <div className="bar-chart">
              {Object.entries(byType).map(([type, val]) => (
                <div className="bar" key={type}>
                  <i style={{ height: `${Math.max(8, (val / maxType) * 100)}%` }} title={fcfa(val)} />
                  <b>{type}</b>
                </div>
              ))}
              {Object.keys(byType).length === 0 && <p className="cell-sub">Aucune donnee.</p>}
            </div>
          </div>
        </div>

        <div className="panel">
          <div className="panel-head"><h3>Activite recente</h3></div>
          <div className="panel-body panel-pad">
            <ul className="list-plain activity" style={{ padding: '0 14px' }}>
              {activity.map((a, i) => (
                <li key={i}>
                  <span className="a-dot">{a.icon}</span>
                  <div style={{ flex: 1 }}>
                    <Link to={a.to} style={{ fontWeight: 600 }}>{a.text}</Link>
                    <div className="a-time">{timeAgo(a.time)}</div>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>

      <div className="two-col" style={{ marginTop: 20 }}>
        <div className="panel">
          <div className="panel-head">
            <h3>Pipeline commercial</h3>
            <Link to="/admin/prospects" className="btn btn-ghost btn-sm">Gerer</Link>
          </div>
          <div className="panel-body">
            {pipeline.map((p) => {
              const pct = leads.length ? (p.n / leads.length) * 100 : 0
              return (
                <div key={p.s} style={{ marginBottom: 14 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.88rem', marginBottom: 6 }}>
                    <span className="cell-strong">{p.s}</span>
                    <span className="cell-sub">{p.n}</span>
                  </div>
                  <div className="progress"><span style={{ width: `${pct}%` }} /></div>
                </div>
              )
            })}
          </div>
        </div>

        <div className="panel">
          <div className="panel-head"><h3>Apercu rapide</h3></div>
          <div className="panel-body">
            <ul className="feature-list">
              <li><span className="fi">&#128193;</span> {activeProjects} projet(s) en cours</li>
              <li><span className="fi">&#9993;</span> {unreadMsg} message(s) non lu(s)</li>
              <li><span className="fi">&#128221;</span> {posts.filter((p) => p.status === 'Publie').length} article(s) publie(s)</li>
              <li><span className="fi">&#127991;</span> {products.filter((p) => p.status === 'Publie').length} offre(s) en ligne</li>
              <li><span className="fi">&#128722;</span> {orders.length} commande(s) au total</li>
            </ul>
          </div>
        </div>
      </div>
    </>
  )
}
