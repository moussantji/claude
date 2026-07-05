import { useMemo, useState } from 'react'
import { useCollection, insert, update, remove } from '../../data/store.js'
import { fcfa, dateFr } from '../../data/format.js'
import { statusClass } from '../../components/admin/status.js'
import Modal from '../../components/admin/Modal.jsx'

const TYPES = ['Site web', 'Application web', 'Logiciel PC', 'E-commerce', 'Streaming', 'IPTV']
const STATUSES = ['En attente', 'En cours', 'Termine']
const blank = { name: '', client: '', type: 'Site web', budget: 0, status: 'En attente', progress: 0, deadline: '', lead: '' }

export default function Projects() {
  const projects = useCollection('projects')
  const clients = useCollection('clients')
  const users = useCollection('users')
  const [filter, setFilter] = useState('Tous')
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(blank)

  const filtered = useMemo(
    () => (filter === 'Tous' ? projects : projects.filter((p) => p.status === filter)),
    [projects, filter]
  )

  function openNew() { setForm({ ...blank, client: clients[0]?.name || '', lead: users[0]?.name || '' }); setEditing('new') }
  function openEdit(p) { setForm({ ...p }); setEditing(p.id) }
  function set(k, v) { setForm((f) => ({ ...f, [k]: v })) }
  function save(e) {
    e.preventDefault()
    const data = { ...form, budget: Number(form.budget) || 0, progress: Math.min(100, Math.max(0, Number(form.progress) || 0)) }
    if (editing === 'new') insert('projects', data)
    else update('projects', editing, data)
    setEditing(null)
  }
  function del(id) { if (confirm('Supprimer ce projet ?')) remove('projects', id) }

  return (
    <>
      <div className="toolbar">
        <div className="chips">
          {['Tous', ...STATUSES].map((s) => (
            <button key={s} className={`chip ${filter === s ? 'active' : ''}`} onClick={() => setFilter(s)}>{s}</button>
          ))}
        </div>
        <div style={{ flex: 1 }} />
        <button className="btn btn-primary btn-sm" onClick={openNew}>+ Nouveau projet</button>
      </div>

      <div className="panel">
        <table className="table">
          <thead>
            <tr><th>Projet</th><th>Client</th><th>Type</th><th>Budget</th><th>Avancement</th><th>Echeance</th><th>Statut</th><th></th></tr>
          </thead>
          <tbody>
            {filtered.map((p) => (
              <tr key={p.id}>
                <td><div className="cell-strong">{p.name}</div><div className="cell-sub">Resp. {p.lead}</div></td>
                <td>{p.client}</td>
                <td>{p.type}</td>
                <td className="num">{fcfa(p.budget)}</td>
                <td>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <div className="progress"><span style={{ width: `${p.progress}%` }} /></div>
                    <span className="cell-sub num">{p.progress}%</span>
                  </div>
                </td>
                <td className="cell-sub num">{dateFr(p.deadline)}</td>
                <td><span className={`pill ${statusClass(p.status)}`}>{p.status}</span></td>
                <td>
                  <div className="actions">
                    <button className="icon-btn" onClick={() => openEdit(p)} title="Modifier">&#9998;</button>
                    <button className="icon-btn del" onClick={() => del(p.id)} title="Supprimer">&#128465;</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="empty"><div className="big">&#128193;</div>Aucun projet.</div>}
      </div>

      {editing && (
        <Modal
          title={editing === 'new' ? 'Nouveau projet' : 'Modifier le projet'}
          onClose={() => setEditing(null)}
          footer={
            <>
              <button className="btn btn-ghost btn-sm" onClick={() => setEditing(null)}>Annuler</button>
              <button className="btn btn-primary btn-sm" form="prj-form" type="submit">Enregistrer</button>
            </>
          }
        >
          <form id="prj-form" onSubmit={save}>
            <div className="field"><label>Nom du projet *</label><input className="input" required value={form.name} onChange={(e) => set('name', e.target.value)} /></div>
            <div className="form-row">
              <div className="field">
                <label>Client</label>
                <select className="select" value={form.client} onChange={(e) => set('client', e.target.value)}>
                  <option value="">-- Choisir --</option>
                  {clients.map((c) => <option key={c.id}>{c.name}</option>)}
                </select>
              </div>
              <div className="field"><label>Type</label><select className="select" value={form.type} onChange={(e) => set('type', e.target.value)}>{TYPES.map((t) => <option key={t}>{t}</option>)}</select></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Budget (FCFA)</label><input className="input" type="number" min="0" value={form.budget} onChange={(e) => set('budget', e.target.value)} /></div>
              <div className="field"><label>Echeance</label><input className="input" type="date" value={form.deadline} onChange={(e) => set('deadline', e.target.value)} /></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Avancement (%)</label><input className="input" type="number" min="0" max="100" value={form.progress} onChange={(e) => set('progress', e.target.value)} /></div>
              <div className="field"><label>Statut</label><select className="select" value={form.status} onChange={(e) => set('status', e.target.value)}>{STATUSES.map((s) => <option key={s}>{s}</option>)}</select></div>
            </div>
            <div className="field">
              <label>Responsable</label>
              <select className="select" value={form.lead} onChange={(e) => set('lead', e.target.value)}>
                <option value="">-- Choisir --</option>
                {users.map((u) => <option key={u.id}>{u.name}</option>)}
              </select>
            </div>
          </form>
        </Modal>
      )}
    </>
  )
}
