// ----------------------------------------------------------
// Faso Digital - Mini "base de donnees" cote client
// Persistance via localStorage + petit systeme d'abonnement
// pour que les composants React se rafraichissent tout seuls.
// ----------------------------------------------------------

import { useEffect, useState } from 'react'
import { seed } from './seed.js'

const PREFIX = 'faso.'
const listeners = new Map() // collection -> Set<fn>

function keyOf(name) {
  return PREFIX + name
}

function read(name) {
  try {
    const raw = localStorage.getItem(keyOf(name))
    if (raw == null) return null
    return JSON.parse(raw)
  } catch (e) {
    return null
  }
}

function write(name, value) {
  localStorage.setItem(keyOf(name), JSON.stringify(value))
  const set = listeners.get(name)
  if (set) set.forEach((fn) => fn(value))
}

// Initialise les collections avec les donnees de demonstration
export function initStore() {
  Object.entries(seed).forEach(([name, value]) => {
    if (read(name) == null) write(name, value)
  })
  if (read('_seeded_v') !== 1) write('_seeded_v', 1)
}

export function getAll(name) {
  const v = read(name)
  return Array.isArray(v) ? v : v || []
}

export function setAll(name, value) {
  write(name, value)
}

export function genId(prefix = 'id') {
  return prefix + '_' + Math.random().toString(36).slice(2, 9)
}

export function insert(name, item) {
  const list = getAll(name)
  const record = { id: genId(name.slice(0, 3)), createdAt: new Date().toISOString(), ...item }
  write(name, [record, ...list])
  return record
}

export function update(name, id, patch) {
  const list = getAll(name)
  const next = list.map((r) => (r.id === id ? { ...r, ...patch } : r))
  write(name, next)
}

export function remove(name, id) {
  const list = getAll(name)
  write(name, list.filter((r) => r.id !== id))
}

export function subscribe(name, fn) {
  if (!listeners.has(name)) listeners.set(name, new Set())
  listeners.get(name).add(fn)
  return () => listeners.get(name)?.delete(fn)
}

// Hook React : renvoie [liste, helpers] et se met a jour en direct
export function useCollection(name) {
  const [data, setData] = useState(() => getAll(name))
  useEffect(() => {
    setData(getAll(name))
    const unsub = subscribe(name, () => setData(getAll(name)))
    return unsub
  }, [name])
  return data
}

// Reinitialise toutes les donnees de demonstration
export function resetStore() {
  Object.keys(seed).forEach((name) => write(name, seed[name]))
}
