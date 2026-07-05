import { createContext, useContext, useEffect, useMemo, useState } from 'react'

const CartContext = createContext(null)
const CART_KEY = 'faso.cart'

export function CartProvider({ children }) {
  const [items, setItems] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem(CART_KEY)) || []
    } catch {
      return []
    }
  })
  const [open, setOpen] = useState(false)

  useEffect(() => {
    localStorage.setItem(CART_KEY, JSON.stringify(items))
  }, [items])

  function add(product, openDrawer = true) {
    setItems((prev) => {
      const found = prev.find((i) => i.id === product.id)
      if (found) {
        return prev.map((i) => (i.id === product.id ? { ...i, qty: i.qty + 1 } : i))
      }
      return [...prev, { id: product.id, name: product.name, price: Number(product.price) || 0, qty: 1 }]
    })
    if (openDrawer) setOpen(true)
  }

  function removeItem(id) {
    setItems((prev) => prev.filter((i) => i.id !== id))
  }

  function setQty(id, qty) {
    const q = Math.max(1, Number(qty) || 1)
    setItems((prev) => prev.map((i) => (i.id === id ? { ...i, qty: q } : i)))
  }

  function clear() {
    setItems([])
  }

  const count = useMemo(() => items.reduce((s, i) => s + i.qty, 0), [items])
  const total = useMemo(() => items.reduce((s, i) => s + i.price * i.qty, 0), [items])

  const value = { items, add, removeItem, setQty, clear, count, total, open, setOpen }
  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

export function useCart() {
  const ctx = useContext(CartContext)
  if (!ctx) throw new Error('useCart doit etre utilise dans CartProvider')
  return ctx
}
