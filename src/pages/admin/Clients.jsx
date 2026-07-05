import { useMemo, useState } from 'react'
import { useCollection, insert, update, remove } from '../../data/store.js'
import { fcfa, dateFr } from '../../data/format.js'
import { statusClass } from '../../components/admin/status.js'
import Modal from '../../components/admin/Modal.jsx'

const SECTORS = ['Sante', 'Distribution', 'Tourisme', 'Education', 'Mode', 'Agroalimentaire', 'Services', 'Autre']
const blank = { name: '', contact: '', email: '', phone: '', city: '', sector: 'Services', value: 0, status: 'Actif', since: new Date().toISOString().slice(0, 10) }

export default function Clients() {
  const clients = useCollection('clients')
  const [q, setQ] = useState('')
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(blank)

  const filtered = useMemo(
    () => clients.filter((c) => (c.name + c.contact + c.city + c.sector).toLowerCase().includes(q.toLowerCase())),
    [clients, q]
  )
  const totalValue = clients.reduce((s, c) => s + (Number(c.value) || 0), 0)

  function openNew() { setForm(blank); setEditing('new') }
  function openEdit(c) { setForm({ ...c }); setEditing(c.id) }
  function set(k, v) { setForm((f) => ({ ...f, [k]: v })) }
  function save(e) {
    e.preventDefault()
    const data = { ...form, value: Number(form.value) || 0 }
    if (editing === 'new') insert('clients', data)
    else update('clients', editing, data)
    setEditing(null)
  }
  function del(id) { if (confirm('Supprimer ce client ?')) remove('clients', id) }

  return (
    <>
      <div className="toolbar">
        <input className="input search" placeholder="Rechercher un client..." value={q} onChange={(e) => setQ(e.target.value)} />
        <span className="pill pill-green">Valeur portefeuille : {fcfa(totalValue)}</span>
        <button className="btn btn-primary btn-sm" onClick={openNew}>+ Nouveau client</button>
      </div>

      <div className="panel">
        <table className="table">
          <thead>
            <tr><th>Client</th><th>Contact</th><th>Secteur</th><th>Valeur</th><th>Client depuis</th><th>Statut</th><th></th></tr>
          </thead>
          <tbody>
            {filtered.map((c) => (
              <tr key={c.id}>
                <td><div className="cell-strong">{c.name}</div><div className="cell-sub">{c.city}</div></td>
                <td>{c.contact}<div className="cell-sub">{c.phone}</div></td>
                <td>{c.sector}</td>
                <td className="num">{fcfa(c.value)}</td>
                <td className="cell-sub num">{dateFr(c.since)}</td>
                <td><span className={`pill ${statusClass(c.status)}`}>{c.status}</span></td>
                <td>
                  <div className="actions">
                    <button className="icon-btn" onClick={() => openEdit(c)} title="Modifier">&#9998;</button>
                    <button className="icon-btn del" onClick={() => del(c.id)} title="Supprimer">&#128465;</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="empty"><div className="big">&#128101;</div>Aucun client trouve.</div>}
      </div>

      {editing && (
        <Modal
          title={editing === 'new' ? 'Nouveau client' : 'Modifier le client'}
          onClose={() => setEditing(null)}
          footer={
            <>
              <button className="btn btn-ghost btn-sm" onClick={() => setEditing(null)}>Annuler</button>
              <button className="btn btn-primary btn-sm" form="client-form" type="submit">Enregistrer</button>
            </>
          }
        >
          <form id="client-form" onSubmit={save}>
            <div className="form-row">
              <div className="field"><label>Nom / Entreprise *</label><input className="input" required value={form.name} onChange={(e) => set('name', e.target.value)} /></div>
              <div className="field"><label>Personne de contact</label><input className="input" value={form.contact} onChange={(e) => set('contact', e.target.value)} /></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Email</label><input className="input" type="email" value={form.email} onChange={(e) => set('email', e.target.value)} /></div>
              <div className="field"><label>Telephone</label><input className="input" value={form.phone} onChange={(e) => set('phone', e.target.value)} /></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Ville</label><input className="input" value={form.city} onChange={(e) => set('city', e.target.value)} /></div>
              <div className="field"><label>Secteur</label><select className="select" value={form.sector} onChange={(e) => set('sector', e.target.value)}>{SECTORS.map((s) => <option key={s}>{s}</option>)}</select></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Valeur (FCFA)</label><input className="input" type="number" min="0" value={form.value} onChange={(e) => set('value', e.target.value)} /></div>
              <div className="field"><label>Client depuis</label><input className="input" type="date" value={form.since} onChange={(e) => set('since', e.target.value)} /></div>
            </div>
            <div className="field"><label>Statut</label><select className="select" value={form.status} onChange={(e) => set('status', e.target.value)}><option>Actif</option><option>Inactif</option></select></div>
          </form>
        </Modal>
      )}
    </>
  )
}
