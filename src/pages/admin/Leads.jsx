import { useMemo, useState } from 'react'
import { useCollection, insert, update, remove } from '../../data/store.js'
import { fcfa, dateFr } from '../../data/format.js'
import { statusClass } from '../../components/admin/status.js'
import Modal from '../../components/admin/Modal.jsx'

const STATUSES = ['Nouveau', 'Contacte', 'Qualifie', 'Gagne', 'Perdu']
const SERVICES = ['Site web', 'Application web', 'Logiciel PC', 'E-commerce', 'Streaming', 'IPTV', 'Autre']
const SOURCES = ['Site web', 'Facebook', 'Instagram', 'Google', 'Recommandation', 'Salon Bamako Tech', 'Autre']

const blank = { name: '', company: '', email: '', phone: '', city: '', service: 'Site web', budget: 0, status: 'Nouveau', source: 'Site web', note: '' }

export default function Leads() {
  const leads = useCollection('leads')
  const [q, setQ] = useState('')
  const [filter, setFilter] = useState('Tous')
  const [editing, setEditing] = useState(null) // objet ou null
  const [form, setForm] = useState(blank)

  const filtered = useMemo(() => {
    return leads.filter((l) => {
      const matchQ = (l.name + l.company + l.email + l.city).toLowerCase().includes(q.toLowerCase())
      const matchF = filter === 'Tous' || l.status === filter
      return matchQ && matchF
    })
  }, [leads, q, filter])

  function openNew() {
    setForm(blank)
    setEditing('new')
  }
  function openEdit(l) {
    setForm({ ...l })
    setEditing(l.id)
  }
  function set(k, v) {
    setForm((f) => ({ ...f, [k]: v }))
  }
  function save(e) {
    e.preventDefault()
    const data = { ...form, budget: Number(form.budget) || 0 }
    if (editing === 'new') insert('leads', data)
    else update('leads', editing, data)
    setEditing(null)
  }
  function del(id) {
    if (confirm('Supprimer ce prospect ?')) remove('leads', id)
  }

  return (
    <>
      <div className="toolbar">
        <input className="input search" placeholder="Rechercher un prospect..." value={q} onChange={(e) => setQ(e.target.value)} />
        <div className="chips">
          {['Tous', ...STATUSES].map((s) => (
            <button key={s} className={`chip ${filter === s ? 'active' : ''}`} onClick={() => setFilter(s)}>{s}</button>
          ))}
        </div>
        <button className="btn btn-primary btn-sm" onClick={openNew}>+ Nouveau prospect</button>
      </div>

      <div className="panel">
        <table className="table">
          <thead>
            <tr>
              <th>Prospect</th><th>Service</th><th>Budget</th><th>Source</th><th>Statut</th><th>Date</th><th></th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((l) => (
              <tr key={l.id}>
                <td>
                  <div className="cell-strong">{l.name}</div>
                  <div className="cell-sub">{l.company} &middot; {l.city}</div>
                </td>
                <td>{l.service}</td>
                <td className="num">{fcfa(l.budget)}</td>
                <td className="cell-sub">{l.source}</td>
                <td><span className={`pill ${statusClass(l.status)}`}>{l.status}</span></td>
                <td className="cell-sub num">{dateFr(l.createdAt)}</td>
                <td>
                  <div className="actions">
                    <button className="icon-btn" onClick={() => openEdit(l)} title="Modifier">&#9998;</button>
                    <button className="icon-btn del" onClick={() => del(l.id)} title="Supprimer">&#128465;</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="empty"><div className="big">&#128269;</div>Aucun prospect trouve.</div>}
      </div>

      {editing && (
        <Modal
          title={editing === 'new' ? 'Nouveau prospect' : 'Modifier le prospect'}
          onClose={() => setEditing(null)}
          footer={
            <>
              <button className="btn btn-ghost btn-sm" onClick={() => setEditing(null)}>Annuler</button>
              <button className="btn btn-primary btn-sm" form="lead-form" type="submit">Enregistrer</button>
            </>
          }
        >
          <form id="lead-form" onSubmit={save}>
            <div className="form-row">
              <div className="field"><label>Nom complet *</label><input className="input" required value={form.name} onChange={(e) => set('name', e.target.value)} /></div>
              <div className="field"><label>Entreprise</label><input className="input" value={form.company} onChange={(e) => set('company', e.target.value)} /></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Email</label><input className="input" type="email" value={form.email} onChange={(e) => set('email', e.target.value)} /></div>
              <div className="field"><label>Telephone</label><input className="input" value={form.phone} onChange={(e) => set('phone', e.target.value)} /></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Ville</label><input className="input" value={form.city} onChange={(e) => set('city', e.target.value)} /></div>
              <div className="field"><label>Budget (FCFA)</label><input className="input" type="number" min="0" value={form.budget} onChange={(e) => set('budget', e.target.value)} /></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Service</label><select className="select" value={form.service} onChange={(e) => set('service', e.target.value)}>{SERVICES.map((s) => <option key={s}>{s}</option>)}</select></div>
              <div className="field"><label>Source</label><select className="select" value={form.source} onChange={(e) => set('source', e.target.value)}>{SOURCES.map((s) => <option key={s}>{s}</option>)}</select></div>
            </div>
            <div className="field"><label>Statut</label><select className="select" value={form.status} onChange={(e) => set('status', e.target.value)}>{STATUSES.map((s) => <option key={s}>{s}</option>)}</select></div>
            <div className="field"><label>Note</label><textarea className="textarea" value={form.note} onChange={(e) => set('note', e.target.value)} /></div>
          </form>
        </Modal>
      )}
    </>
  )
}
