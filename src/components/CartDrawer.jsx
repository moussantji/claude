import { useEffect, useState } from 'react'
import { useCart } from '../context/CartContext.jsx'
import { insert } from '../data/store.js'
import { fcfa } from '../data/format.js'

const emptyForm = { name: '', phone: '', city: 'Bamako', payment: 'Orange Money' }

export default function CartDrawer() {
  const { items, removeItem, setQty, clear, total, count, open, setOpen } = useCart()
  const [step, setStep] = useState('cart') // cart | checkout | done
  const [form, setForm] = useState(emptyForm)
  const [ref, setRef] = useState('')

  useEffect(() => {
    if (open) setStep((s) => (s === 'done' ? 'cart' : s))
    document.body.style.overflow = open ? 'hidden' : ''
    return () => {
      document.body.style.overflow = ''
    }
  }, [open])

  function set(k, v) {
    setForm((f) => ({ ...f, [k]: v }))
  }

  function checkout(e) {
    e.preventDefault()
    const orderRef = 'CMD-' + Math.floor(1000 + Math.random() * 9000)
    const summary =
      items.length === 1 ? items[0].name : `${items[0].name} + ${items.length - 1} autre(s)`
    insert('orders', {
      ref: orderRef,
      customer: form.name,
      type: 'E-commerce',
      item: summary,
      amount: total,
      status: 'En attente',
      city: form.city,
      date: new Date().toISOString().slice(0, 10),
    })
    setRef(orderRef)
    clear()
    setForm(emptyForm)
    setStep('done')
  }

  return (
    <>
      <div className={`drawer-overlay ${open ? 'show' : ''}`} onClick={() => setOpen(false)} />
      <aside className={`drawer ${open ? 'open' : ''}`} aria-hidden={!open}>
        <div className="drawer-head">
          <h3>
            {step === 'checkout' ? 'Validation de la commande' : step === 'done' ? 'Commande confirmee' : `Panier (${count})`}
          </h3>
          <button className="close-x" onClick={() => setOpen(false)} aria-label="Fermer">&times;</button>
        </div>

        <div className="drawer-body">
          {/* --- Etape panier --- */}
          {step === 'cart' && (
            items.length === 0 ? (
              <div className="empty">
                <div className="big">&#128722;</div>
                Votre panier est vide.
              </div>
            ) : (
              <ul className="cart-list">
                {items.map((i) => (
                  <li key={i.id} className="cart-row">
                    <div className="cart-thumb">&#128230;</div>
                    <div className="cart-info">
                      <div className="cell-strong">{i.name}</div>
                      <div className="cell-sub">{fcfa(i.price)}</div>
                      <div className="qty">
                        <button type="button" onClick={() => setQty(i.id, i.qty - 1)} aria-label="Moins">&minus;</button>
                        <span>{i.qty}</span>
                        <button type="button" onClick={() => setQty(i.id, i.qty + 1)} aria-label="Plus">+</button>
                      </div>
                    </div>
                    <div className="cart-line">
                      <div className="cell-strong num">{fcfa(i.price * i.qty)}</div>
                      <button className="link-del" onClick={() => removeItem(i.id)}>Retirer</button>
                    </div>
                  </li>
                ))}
              </ul>
            )
          )}

          {/* --- Etape checkout --- */}
          {step === 'checkout' && (
            <form id="checkout-form" onSubmit={checkout}>
              <div className="field">
                <label>Nom complet *</label>
                <input className="input" required value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="Ex : Awa Diarra" />
              </div>
              <div className="field">
                <label>Telephone *</label>
                <input className="input" required value={form.phone} onChange={(e) => set('phone', e.target.value)} placeholder="+223 ..." />
              </div>
              <div className="field">
                <label>Ville de livraison</label>
                <input className="input" value={form.city} onChange={(e) => set('city', e.target.value)} />
              </div>
              <div className="field">
                <label>Moyen de paiement</label>
                <select className="select" value={form.payment} onChange={(e) => set('payment', e.target.value)}>
                  <option>Orange Money</option>
                  <option>Moov Money</option>
                  <option>Carte bancaire</option>
                  <option>Paiement a la livraison</option>
                </select>
              </div>
              <div className="cart-total">
                <span>Total a payer</span>
                <b>{fcfa(total)}</b>
              </div>
            </form>
          )}

          {/* --- Etape confirmation --- */}
          {step === 'done' && (
            <div className="empty">
              <div className="big">&#9989;</div>
              <h3>Merci pour votre commande !</h3>
              <p className="cell-sub">
                Votre commande <b>{ref}</b> a bien ete enregistree. Notre equipe vous contactera
                pour la livraison et le paiement.
              </p>
            </div>
          )}
        </div>

        {/* --- Pied de tiroir --- */}
        {step === 'cart' && items.length > 0 && (
          <div className="drawer-foot">
            <div className="cart-total">
              <span>Total</span>
              <b>{fcfa(total)}</b>
            </div>
            <button className="btn btn-primary btn-block" onClick={() => setStep('checkout')}>
              Commander
            </button>
            <button className="btn btn-ghost btn-block btn-sm" onClick={clear}>Vider le panier</button>
          </div>
        )}
        {step === 'checkout' && (
          <div className="drawer-foot">
            <button className="btn btn-primary btn-block" form="checkout-form" type="submit">
              Confirmer la commande
            </button>
            <button className="btn btn-ghost btn-block btn-sm" onClick={() => setStep('cart')}>
              &larr; Retour au panier
            </button>
          </div>
        )}
        {step === 'done' && (
          <div className="drawer-foot">
            <button className="btn btn-primary btn-block" onClick={() => setOpen(false)}>
              Continuer mes achats
            </button>
          </div>
        )}
      </aside>
    </>
  )
}
