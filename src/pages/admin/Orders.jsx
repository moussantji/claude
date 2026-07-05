import { useMemo, useState } from 'react'
import { useCollection, insert, update, remove } from '../../data/store.js'
import { fcfa, dateFr } from '../../data/format.js'
import { statusClass } from '../../components/admin/status.js'
import Modal from '../../components/admin/Modal.jsx'

const TYPES = ['E-commerce', 'Streaming', 'IPTV']
const STATUSES = ['En attente', 'En preparation', 'Paye', 'Livre', 'Annule']
const blank = { ref: '', customer: '', type: 'E-commerce', item: '', amount: 0, status: 'En attente', city: '', date: new Date().toISOString().slice(0, 10) }

export default function Orders() {
  const orders = useCollection('orders')
  const [filter, setFilter] = useState('Tous')
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(blank)

  const filtered = useMemo(
    () => (filter === 'Tous' ? orders : orders.filter((o) => o.type === filter)),
    [orders, filter]
  )
  const total = filtered.reduce((s, o) => s + (Number(o.amount) || 0), 0)

  function openNew() { setForm({ ...blank, ref: 'CMD-' + Math.floor(1000 + Math.random() * 9000) }); setEditing('new') }
  function openEdit(o) { setForm({ ...o }); setEditing(o.id) }
  function set(k, v) { setForm((f) => ({ ...f, [k]: v })) }
  function save(e) {
    e.preventDefault()
    const data = { ...form, amount: Number(form.amount) || 0 }
    if (editing === 'new') insert('orders', data)
    else update('orders', editing, data)
    setEditing(null)
  }
  function del(id) { if (confirm('Supprimer cette commande ?')) remove('orders', id) }
  function changeStatus(id, status) { update('orders', id, { status }) }

  return (
    <>
      <div className="toolbar">
        <div className="chips">
          {['Tous', ...TYPES].map((t) => (
            <button key={t} className={`chip ${filter === t ? 'active' : ''}`} onClick={() => setFilter(t)}>{t}</button>
          ))}
        </div>
        <span className="pill pill-green">Total affiche : {fcfa(total)}</span>
        <div style={{ flex: 1 }} />
        <button className="btn btn-primary btn-sm" onClick={openNew}>+ Nouvelle commande</button>
      </div>

      <div className="panel">
        <table className="table">
          <thead>
            <tr><th>Reference</th><th>Client</th><th>Type</th><th>Article</th><th>Montant</th><th>Date</th><th>Statut</th><th></th></tr>
          </thead>
          <tbody>
            {filtered.map((o) => (
              <tr key={o.id}>
                <td className="cell-strong">{o.ref}</td>
                <td>{o.customer}<div className="cell-sub">{o.city}</div></td>
                <td><span className="pill pill-gray">{o.type}</span></td>
                <td className="cell-sub">{o.item}</td>
                <td className="num cell-strong">{fcfa(o.amount)}</td>
                <td className="cell-sub num">{dateFr(o.date)}</td>
                <td>
                  <select
                    className="select"
                    style={{ padding: '5px 8px', width: 'auto', fontSize: '.82rem' }}
                    value={o.status}
                    onChange={(e) => changeStatus(o.id, e.target.value)}
                  >
                    {STATUSES.map((s) => <option key={s}>{s}</option>)}
                  </select>
                </td>
                <td>
                  <div className="actions">
                    <button className="icon-btn" onClick={() => openEdit(o)} title="Modifier">&#9998;</button>
                    <button className="icon-btn del" onClick={() => del(o.id)} title="Supprimer">&#128465;</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="empty"><div className="big">&#128722;</div>Aucune commande.</div>}
      </div>

      {editing && (
        <Modal
          title={editing === 'new' ? 'Nouvelle commande' : 'Modifier la commande'}
          onClose={() => setEditing(null)}
          footer={
            <>
              <button className="btn btn-ghost btn-sm" onClick={() => setEditing(null)}>Annuler</button>
              <button className="btn btn-primary btn-sm" form="ord-form" type="submit">Enregistrer</button>
            </>
          }
        >
          <form id="ord-form" onSubmit={save}>
            <div className="form-row">
              <div className="field"><label>Reference</label><input className="input" value={form.ref} onChange={(e) => set('ref', e.target.value)} /></div>
              <div className="field"><label>Type</label><select className="select" value={form.type} onChange={(e) => set('type', e.target.value)}>{TYPES.map((t) => <option key={t}>{t}</option>)}</select></div>
            </div>
            <div className="field"><label>Client *</label><input className="input" required value={form.customer} onChange={(e) => set('customer', e.target.value)} /></div>
            <div className="field"><label>Article / Offre</label><input className="input" value={form.item} onChange={(e) => set('item', e.target.value)} /></div>
            <div className="form-row">
              <div className="field"><label>Montant (FCFA)</label><input className="input" type="number" min="0" value={form.amount} onChange={(e) => set('amount', e.target.value)} /></div>
              <div className="field"><label>Ville</label><input className="input" value={form.city} onChange={(e) => set('city', e.target.value)} /></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Date</label><input className="input" type="date" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
              <div className="field"><label>Statut</label><select className="select" value={form.status} onChange={(e) => set('status', e.target.value)}>{STATUSES.map((s) => <option key={s}>{s}</option>)}</select></div>
            </div>
          </form>
        </Modal>
      )}
    </>
  )
}
