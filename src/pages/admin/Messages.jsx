import { useMemo, useState } from 'react'
import { useCollection, update, remove, insert } from '../../data/store.js'
import { timeAgo } from '../../data/format.js'
import { statusClass } from '../../components/admin/status.js'
import Modal from '../../components/admin/Modal.jsx'

const STATUSES = ['Nouveau', 'Lu', 'Traite']

export default function Messages() {
  const messages = useCollection('messages')
  const [filter, setFilter] = useState('Tous')
  const [viewing, setViewing] = useState(null)

  const filtered = useMemo(
    () => (filter === 'Tous' ? messages : messages.filter((m) => m.status === filter)),
    [messages, filter]
  )

  function open(m) {
    setViewing(m)
    if (m.status === 'Nouveau') update('messages', m.id, { status: 'Lu' })
  }
  function del(id) {
    if (confirm('Supprimer ce message ?')) { remove('messages', id); setViewing(null) }
  }
  function convert(m) {
    insert('leads', {
      name: m.name, company: '', email: m.email, phone: m.phone, city: '',
      service: m.service || 'Autre', budget: 0, status: 'Nouveau', source: 'Site web',
      note: m.message,
    })
    update('messages', m.id, { status: 'Traite' })
    setViewing(null)
    alert('Message converti en prospect dans le pipeline commercial.')
  }

  return (
    <>
      <div className="toolbar">
        <div className="chips">
          {['Tous', ...STATUSES].map((s) => (
            <button key={s} className={`chip ${filter === s ? 'active' : ''}`} onClick={() => setFilter(s)}>{s}</button>
          ))}
        </div>
      </div>

      <div className="panel">
        <table className="table">
          <thead>
            <tr><th>Expediteur</th><th>Sujet</th><th>Service</th><th>Recu</th><th>Statut</th><th></th></tr>
          </thead>
          <tbody>
            {filtered.map((m) => (
              <tr key={m.id} style={{ cursor: 'pointer' }} onClick={() => open(m)}>
                <td>
                  <div className="cell-strong">
                    {m.status === 'Nouveau' && <span className="dot" style={{ background: 'var(--red)' }} />}
                    {m.name}
                  </div>
                  <div className="cell-sub">{m.email}</div>
                </td>
                <td>{m.subject}</td>
                <td><span className="pill pill-gray">{m.service}</span></td>
                <td className="cell-sub">{timeAgo(m.date)}</td>
                <td><span className={`pill ${statusClass(m.status)}`}>{m.status}</span></td>
                <td onClick={(e) => e.stopPropagation()}>
                  <div className="actions">
                    <button className="icon-btn" onClick={() => open(m)} title="Voir">&#128065;</button>
                    <button className="icon-btn del" onClick={() => del(m.id)} title="Supprimer">&#128465;</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="empty"><div className="big">&#9993;</div>Aucun message.</div>}
      </div>

      {viewing && (
        <Modal
          title={viewing.subject}
          onClose={() => setViewing(null)}
          footer={
            <>
              <button className="btn btn-danger btn-sm" onClick={() => del(viewing.id)}>Supprimer</button>
              <div style={{ flex: 1 }} />
              <button className="btn btn-ghost btn-sm" onClick={() => { update('messages', viewing.id, { status: 'Traite' }); setViewing(null) }}>Marquer traite</button>
              <button className="btn btn-primary btn-sm" onClick={() => convert(viewing)}>Convertir en prospect</button>
            </>
          }
        >
          <div className="field">
            <div className="cell-sub">De</div>
            <div className="cell-strong">{viewing.name}</div>
          </div>
          <div className="form-row">
            <div className="field"><div className="cell-sub">Email</div><a href={`mailto:${viewing.email}`}>{viewing.email}</a></div>
            <div className="field"><div className="cell-sub">Telephone</div><a href={`tel:${viewing.phone}`}>{viewing.phone}</a></div>
          </div>
          <div className="field"><div className="cell-sub">Service concerne</div><span className="pill pill-green">{viewing.service}</span></div>
          <div className="field">
            <div className="cell-sub">Message</div>
            <div className="card" style={{ background: 'var(--bg-soft)', marginTop: 6 }}>{viewing.message}</div>
          </div>
        </Modal>
      )}
    </>
  )
}
