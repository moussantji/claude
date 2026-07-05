import { useMemo, useState } from 'react'
import { useCollection, insert, update, remove } from '../../data/store.js'
import { dateFr } from '../../data/format.js'
import { statusClass } from '../../components/admin/status.js'
import Modal from '../../components/admin/Modal.jsx'

const CATEGORIES = ['Conseils', 'E-commerce', 'Streaming', 'Logiciel', 'Actualites', 'IPTV']

function slugify(s) {
  return String(s)
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

const blank = {
  title: '', slug: '', category: 'Conseils', author: 'Amadou Traore',
  date: new Date().toISOString().slice(0, 10), status: 'Brouillon', excerpt: '', content: '',
}

export default function BlogAdmin() {
  const posts = useCollection('posts')
  const [filter, setFilter] = useState('Tous')
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(blank)

  const filtered = useMemo(
    () => (filter === 'Tous' ? posts : posts.filter((p) => p.status === filter)),
    [posts, filter]
  )

  function openNew() { setForm(blank); setEditing('new') }
  function openEdit(p) { setForm({ ...p }); setEditing(p.id) }
  function set(k, v) { setForm((f) => ({ ...f, [k]: v })) }
  function save(e) {
    e.preventDefault()
    const data = { ...form, slug: form.slug || slugify(form.title) }
    if (editing === 'new') insert('posts', data)
    else update('posts', editing, data)
    setEditing(null)
  }
  function del(id) { if (confirm('Supprimer cet article ?')) remove('posts', id) }
  function togglePublish(p) {
    update('posts', p.id, { status: p.status === 'Publie' ? 'Brouillon' : 'Publie' })
  }

  return (
    <>
      <div className="toolbar">
        <div className="chips">
          {['Tous', 'Publie', 'Brouillon'].map((s) => (
            <button key={s} className={`chip ${filter === s ? 'active' : ''}`} onClick={() => setFilter(s)}>{s}</button>
          ))}
        </div>
        <div style={{ flex: 1 }} />
        <button className="btn btn-primary btn-sm" onClick={openNew}>+ Nouvel article</button>
      </div>

      <div className="panel">
        <table className="table">
          <thead>
            <tr><th>Article</th><th>Categorie</th><th>Auteur</th><th>Date</th><th>Statut</th><th></th></tr>
          </thead>
          <tbody>
            {filtered.map((p) => (
              <tr key={p.id}>
                <td><div className="cell-strong">{p.title}</div><div className="cell-sub">/blog/{p.slug}</div></td>
                <td><span className="pill pill-gray">{p.category}</span></td>
                <td>{p.author}</td>
                <td className="cell-sub num">{dateFr(p.date)}</td>
                <td><span className={`pill ${statusClass(p.status)}`}>{p.status}</span></td>
                <td>
                  <div className="actions">
                    <button className="icon-btn" onClick={() => togglePublish(p)} title={p.status === 'Publie' ? 'Depublier' : 'Publier'}>
                      {p.status === 'Publie' ? '\u{1F513}' : '\u{1F310}'}
                    </button>
                    <button className="icon-btn" onClick={() => openEdit(p)} title="Modifier">&#9998;</button>
                    <button className="icon-btn del" onClick={() => del(p.id)} title="Supprimer">&#128465;</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="empty"><div className="big">&#128221;</div>Aucun article.</div>}
      </div>

      {editing && (
        <Modal
          title={editing === 'new' ? 'Nouvel article' : 'Modifier l\'article'}
          onClose={() => setEditing(null)}
          footer={
            <>
              <button className="btn btn-ghost btn-sm" onClick={() => setEditing(null)}>Annuler</button>
              <button className="btn btn-primary btn-sm" form="post-form" type="submit">Enregistrer</button>
            </>
          }
        >
          <form id="post-form" onSubmit={save}>
            <div className="field"><label>Titre *</label><input className="input" required value={form.title} onChange={(e) => set('title', e.target.value)} /></div>
            <div className="field"><label>Slug (URL)</label><input className="input" value={form.slug} onChange={(e) => set('slug', e.target.value)} placeholder={slugify(form.title) || 'genere-automatiquement'} /></div>
            <div className="form-row">
              <div className="field"><label>Categorie</label><select className="select" value={form.category} onChange={(e) => set('category', e.target.value)}>{CATEGORIES.map((c) => <option key={c}>{c}</option>)}</select></div>
              <div className="field"><label>Auteur</label><input className="input" value={form.author} onChange={(e) => set('author', e.target.value)} /></div>
            </div>
            <div className="form-row">
              <div className="field"><label>Date</label><input className="input" type="date" value={form.date} onChange={(e) => set('date', e.target.value)} /></div>
              <div className="field"><label>Statut</label><select className="select" value={form.status} onChange={(e) => set('status', e.target.value)}><option>Brouillon</option><option>Publie</option></select></div>
            </div>
            <div className="field"><label>Extrait</label><textarea className="textarea" style={{ minHeight: 70 }} value={form.excerpt} onChange={(e) => set('excerpt', e.target.value)} /></div>
            <div className="field"><label>Contenu</label><textarea className="textarea" style={{ minHeight: 180 }} value={form.content} onChange={(e) => set('content', e.target.value)} placeholder="Separez les paragraphes par des retours a la ligne." /></div>
          </form>
        </Modal>
      )}
    </>
  )
}
