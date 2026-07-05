import { useState } from 'react'
import { useCollection, getAll, setAll, insert, remove } from '../../data/store.js'
import { resetStore } from '../../data/store.js'
import { useAuth } from '../../context/AuthContext.jsx'
import Modal from '../../components/admin/Modal.jsx'

const blankUser = { name: '', email: '', password: '', role: 'Chef de projet', avatar: '' }

export default function Settings() {
  const { user } = useAuth()
  const users = useCollection('users')
  const settings = useCollection('settings') // objet
  const [form, setForm] = useState(settings)
  const [saved, setSaved] = useState(false)
  const [addingUser, setAddingUser] = useState(false)
  const [newUser, setNewUser] = useState(blankUser)

  function setS(k, v) { setForm((f) => ({ ...f, [k]: v })) }
  function saveSettings(e) {
    e.preventDefault()
    setAll('settings', form)
    setSaved(true)
    setTimeout(() => setSaved(false), 2500)
  }

  function saveUser(e) {
    e.preventDefault()
    const initials = newUser.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()
    insert('users', { ...newUser, avatar: newUser.avatar || initials })
    setNewUser(blankUser)
    setAddingUser(false)
  }
  function delUser(id) {
    if (getAll('users').length <= 1) { alert('Impossible de supprimer le dernier administrateur.'); return }
    if (confirm('Supprimer cet utilisateur ?')) remove('users', id)
  }
  function doReset() {
    if (confirm('Reinitialiser toutes les donnees de demonstration ? Vos modifications seront perdues.')) {
      resetStore()
      alert('Donnees reinitialisees.')
    }
  }

  return (
    <>
      <div className="two-col">
        <div className="panel">
          <div className="panel-head"><h3>Informations de l&apos;entreprise</h3></div>
          <div className="panel-body">
            {saved && <div className="auth-error" style={{ background: 'rgba(20,184,102,.12)', color: 'var(--green-dark)' }}>Parametres enregistres.</div>}
            <form onSubmit={saveSettings}>
              <div className="field"><label>Nom de l&apos;entreprise</label><input className="input" value={form.company || ''} onChange={(e) => setS('company', e.target.value)} /></div>
              <div className="field"><label>Slogan</label><input className="input" value={form.slogan || ''} onChange={(e) => setS('slogan', e.target.value)} /></div>
              <div className="form-row">
                <div className="field"><label>Email</label><input className="input" value={form.email || ''} onChange={(e) => setS('email', e.target.value)} /></div>
                <div className="field"><label>Telephone</label><input className="input" value={form.phone || ''} onChange={(e) => setS('phone', e.target.value)} /></div>
              </div>
              <div className="field"><label>Adresse</label><input className="input" value={form.address || ''} onChange={(e) => setS('address', e.target.value)} /></div>
              <button className="btn btn-primary btn-sm" type="submit">Enregistrer</button>
            </form>
          </div>
        </div>

        <div className="panel">
          <div className="panel-head">
            <h3>Utilisateurs administrateurs</h3>
            <button className="btn btn-ghost btn-sm" onClick={() => setAddingUser(true)}>+ Ajouter</button>
          </div>
          <div className="panel-body panel-pad">
            <table className="table">
              <tbody>
                {users.map((u) => (
                  <tr key={u.id}>
                    <td style={{ width: 46 }}><div className="avatar">{u.avatar}</div></td>
                    <td>
                      <div className="cell-strong">{u.name} {u.id === user?.id && <span className="pill pill-green" style={{ marginLeft: 6 }}>vous</span>}</div>
                      <div className="cell-sub">{u.email} &middot; {u.role}</div>
                    </td>
                    <td style={{ width: 44 }}>
                      <button className="icon-btn del" onClick={() => delUser(u.id)} title="Supprimer">&#128465;</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div className="panel" style={{ marginTop: 20 }}>
        <div className="panel-head"><h3>Zone de maintenance</h3></div>
        <div className="panel-body" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap' }}>
          <div>
            <div className="cell-strong">Reinitialiser les donnees de demonstration</div>
            <div className="cell-sub">Restaure prospects, clients, projets, produits, commandes, articles et messages d&apos;origine.</div>
          </div>
          <button className="btn btn-danger btn-sm" onClick={doReset}>Reinitialiser</button>
        </div>
      </div>

      {addingUser && (
        <Modal
          title="Ajouter un administrateur"
          onClose={() => setAddingUser(false)}
          footer={
            <>
              <button className="btn btn-ghost btn-sm" onClick={() => setAddingUser(false)}>Annuler</button>
              <button className="btn btn-primary btn-sm" form="user-form" type="submit">Creer</button>
            </>
          }
        >
          <form id="user-form" onSubmit={saveUser}>
            <div className="field"><label>Nom complet *</label><input className="input" required value={newUser.name} onChange={(e) => setNewUser({ ...newUser, name: e.target.value })} /></div>
            <div className="field"><label>Email *</label><input className="input" type="email" required value={newUser.email} onChange={(e) => setNewUser({ ...newUser, email: e.target.value })} /></div>
            <div className="form-row">
              <div className="field"><label>Mot de passe *</label><input className="input" required value={newUser.password} onChange={(e) => setNewUser({ ...newUser, password: e.target.value })} /></div>
              <div className="field"><label>Role</label><select className="select" value={newUser.role} onChange={(e) => setNewUser({ ...newUser, role: e.target.value })}><option>Administrateur</option><option>Chef de projet</option><option>Commercial</option><option>Redacteur</option></select></div>
            </div>
          </form>
        </Modal>
      )}
    </>
  )
}
