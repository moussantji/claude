import { useState } from 'react'
import PageHero from '../../components/PageHero.jsx'
import { insert } from '../../data/store.js'

const services = ['Site web', 'Application web', 'Logiciel PC', 'E-commerce', 'Streaming', 'IPTV', 'Autre']

const empty = { name: '', email: '', phone: '', service: 'Site web', subject: '', message: '' }

export default function Contact() {
  const [form, setForm] = useState(empty)
  const [sent, setSent] = useState(false)

  function set(k, v) {
    setForm((f) => ({ ...f, [k]: v }))
  }

  function submit(e) {
    e.preventDefault()
    // Le message atterrit directement dans le CRM (collection "messages")
    insert('messages', {
      name: form.name,
      email: form.email,
      phone: form.phone,
      service: form.service,
      subject: form.subject || `Demande - ${form.service}`,
      message: form.message,
      status: 'Nouveau',
      date: new Date().toISOString(),
    })
    setSent(true)
    setForm(empty)
  }

  return (
    <>
      <PageHero
        eyebrow="Contact"
        title="Parlons de votre projet"
        lead="Devis gratuit sous 48h. Remplissez le formulaire ou appelez-nous directement."
      />

      <section className="section">
        <div className="container split">
          <div>
            <h2 className="section-title" style={{ fontSize: '1.8rem' }}>Nos coordonnees</h2>
            <ul className="feature-list">
              <li><span className="fi">&#128205;</span> ACI 2000, Immeuble Manden, Bamako - Mali</li>
              <li><span className="fi">&#128222;</span> +223 20 00 00 00 / +223 76 00 00 00</li>
              <li><span className="fi">&#9993;</span> contact@mandenbaoubab.com</li>
              <li><span className="fi">&#128337;</span> Lun - Ven : 8h - 18h &middot; Sam : 9h - 13h</li>
            </ul>
            <div className="card" style={{ marginTop: 20, background: 'var(--bg-soft)' }}>
              <h3 style={{ fontSize: '1.1rem' }}>Reponse rapide sur WhatsApp</h3>
              <p className="cell-sub">Ecrivez-nous au +223 76 00 00 00 pour un echange immediat.</p>
            </div>
          </div>

          <div className="card">
            {sent ? (
              <div className="empty">
                <div className="big">&#9989;</div>
                <h3>Message envoye, merci !</h3>
                <p className="cell-sub">
                  Votre demande a bien ete transmise a notre equipe. Nous vous recontactons sous 48h.
                </p>
                <button className="btn btn-ghost btn-sm" onClick={() => setSent(false)}>
                  Envoyer un autre message
                </button>
              </div>
            ) : (
              <form onSubmit={submit}>
                <div className="form-row">
                  <div className="field">
                    <label>Nom complet *</label>
                    <input className="input" required value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="Ex : Amadou Traore" />
                  </div>
                  <div className="field">
                    <label>Telephone *</label>
                    <input className="input" required value={form.phone} onChange={(e) => set('phone', e.target.value)} placeholder="+223 ..." />
                  </div>
                </div>
                <div className="field">
                  <label>Email *</label>
                  <input className="input" type="email" required value={form.email} onChange={(e) => set('email', e.target.value)} placeholder="vous@exemple.ml" />
                </div>
                <div className="field">
                  <label>Service souhaite</label>
                  <select className="select" value={form.service} onChange={(e) => set('service', e.target.value)}>
                    {services.map((s) => <option key={s}>{s}</option>)}
                  </select>
                </div>
                <div className="field">
                  <label>Sujet</label>
                  <input className="input" value={form.subject} onChange={(e) => set('subject', e.target.value)} placeholder="Objet de votre demande" />
                </div>
                <div className="field">
                  <label>Votre message *</label>
                  <textarea className="textarea" required value={form.message} onChange={(e) => set('message', e.target.value)} placeholder="Decrivez votre projet..." />
                </div>
                <button type="submit" className="btn btn-primary btn-block">Envoyer ma demande</button>
              </form>
            )}
          </div>
        </div>
      </section>
    </>
  )
}
