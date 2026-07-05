import { useMemo, useState } from 'react'
import { useCollection, insert, update, remove } from '../../data/store.js'
import { fcfa } from '../../data/format.js'
import { statusClass } from '../../components/admin/status.js'
import Modal from '../../components/admin/Modal.jsx'

const CATEGORIES = ['Streaming', 'IPTV', 'E-commerce']
const blank = { name: '', category: 'Streaming', price: 0, cycle: '/mois', subscribers: 0, status: 'Publie' }

export default function Products() {
  const products = useCollection('products')
  const [filter, setFilter] = useState('Tous')
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(blank)

  const filtered = useMemo(
    () => (filter === 'Tous' ? products : products.filter((p) => p.category === filter)),
    [products, filter]
  )

  function openNew() { setForm(blank); setEditing('new') }
  function openEdit(p) { setForm({ ...p }); setEditing(p.id) }
  function set(k, v) { setForm((f) => ({ ...f, [k]: v })) }
  function save(e) {
    e.preventDefault()
    const data = { ...form, price: Number(form.price) || 0, subscribers: Number(form.subscribers) || 0 }
    if (editing === 'new') insert('products', data)
    else update('products', editing, data)
    setEditing(null)
  }
  function del(id) { if (confirm('Supprimer cette offre ?')) remove('products', id) }

  return (
    <>
      <div className="toolbar">
        <div className="chips">
          {['Tous', ...CATEGORIES].map((c) => (
            <button key={c} className={`chip ${filter === c ? 'active' : ''}`} onClick={() => setFilter(c)}>{c}</button>
          ))}
        </div>
        <div style={{ flex: 1 }} />
        <button className="btn btn-primary btn-sm" onClick={openNew}>+ Nouvelle offre</button>
      </div>

      <div className="panel">
        <table className="table">
          <thead>
            <tr><th>Offre / Produit</th><th>Categorie</th><th>Prix</th><th>Abonnes / Ventes</th><th>Statut</th><th></th></tr>
          </thead>
          <tbody>
            {filtered.map((p) => (
              <tr key={p.id}>
                <td className="cell-strong">{p.name}</td>
                <td><span className="pill pill-gray">{p.category}</span></td>
                <td className="num">{fcfa(p.price)}<span className="cell-sub"> {p.cycle}</span></td>
                <td className="num">{p.subscribers ? p.subscribers.toLocaleString('fr-FR') : '-'}</td>
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
        {filtered.length === 0 && <div className="empty"><div className="big">&#127991;</div>Aucune offre.</div>}
      </div>

      {editing && (
        <Modal
          title={editing === 'new' ? 'Nouvelle offre' : 'Modifier l\'offre'}
          onClose={() => setEditing(null)}
          footer={
            <>
              <button className="btn btn-ghost btn-sm" onClick={() => setEditing(null)}>Annuler</button>
              <button className="btn btn-primary btn-sm" form="prd-form" type="submit">Enregistrer</button>
            </>
          }
        >
          <form id="prd-form" onSubmit={save}>
            <div className="field"><label>Nom *</label><input className="input" required value={form.name} onChange={(e) => set('name', e.target.value)} /></div>
            <div className="form-row">
              <div className="field"><label>Categorie</label><select className="select" value={form.category} onChange={(e) => set('category', e.target.value)}>{CATEGORIES.map((c) => <option key={c}>{c}</option>)}</select></div>
              <div className="field"><label>Statut</label><select className="select" value={form.status} onChange={(e) => set('status', e.target.value)}><option>Publie</option><option>Brouillon</option></select></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Prix (FCFA)</label><input className="input" type="number" min="0" value={form.price} onChange={(e) => set('price', e.target.value)} /></div>
              <div className="field"><label>Cycle</label><input className="input" value={form.cycle} onChange={(e) => set('cycle', e.target.value)} placeholder="/mois, /an, ..." /></div>
            </div>
            <div className="field"><label>Abonnes / ventes</label><input className="input" type="number" min="0" value={form.subscribers} onChange={(e) => set('subscribers', e.target.value)} /></div>
          </form>
        </Modal>
      )}
    </>
  )
}
