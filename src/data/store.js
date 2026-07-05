// ----------------------------------------------------------
// Faso Digital - Couche de donnees
// * Mode demo (par defaut) : persistance via localStorage.
// * Mode API (si VITE_API_URL defini) : lecture/ecriture via
//   l'API Laravel + Sanctum, avec cache local pour l'affichage.
// L'interface (getAll / insert / update / remove / useCollection)
// reste identique pour les composants, qu'on soit en demo ou en API.
// ----------------------------------------------------------

import { useEffect, useState } from 'react'
import { seed } from './seed.js'
import { API_ENABLED, resource, settingsApi } from '../api/client.js'

const PREFIX = 'faso.'
const listeners = new Map() // collection -> Set<fn>

// Collections gerees par l'API (les autres restent locales : users)
const API_COLLECTIONS = ['leads', 'clients', 'projects', 'products', 'orders', 'posts', 'messages']
const isApi = (name) => API_ENABLED && API_COLLECTIONS.includes(name)

function keyOf(name) {
  return PREFIX + name
}

function read(name) {
  try {
    const raw = localStorage.getItem(keyOf(name))
    if (raw == null) return null
    return JSON.parse(raw)
  } catch {
    return null
  }
}

function write(name, value) {
  localStorage.setItem(keyOf(name), JSON.stringify(value))
  const set = listeners.get(name)
  if (set) set.forEach((fn) => fn(value))
}

// Initialise les collections avec les donnees de demonstration.
// En mode API, on ne seede pas les collections servies par le backend.
export function initStore() {
  Object.entries(seed).forEach(([name, value]) => {
    if (isApi(name)) return
    if (read(name) == null) write(name, value)
  })
  if (read('_seeded_v') !== 1) write('_seeded_v', 1)
}

export function getAll(name) {
  const v = read(name)
  return Array.isArray(v) ? v : v || []
}

export function setAll(name, value) {
  // Cas particulier : parametres (objet unique) en mode API
  if (API_ENABLED && name === 'settings') {
    write(name, value)
    settingsApi.update(value).catch(() => {})
    return
  }
  write(name, value)
}

export function genId(prefix = 'id') {
  return prefix + '_' + Math.random().toString(36).slice(2, 9)
}

// Recharge une collection depuis l'API vers le cache local
export function hydrate(name) {
  if (name === 'settings') {
    if (!API_ENABLED) return Promise.resolve()
    return settingsApi
      .get()
      .then((data) => { if (data) write(name, data) })
      .catch(() => {})
  }
  if (!isApi(name)) return Promise.resolve()
  return resource
    .list(name)
    .then((list) => write(name, Array.isArray(list) ? list : []))
    .catch(() => {})
}

export function insert(name, item) {
  if (isApi(name)) {
    // Optimiste : on affiche tout de suite, puis on synchronise
    const optimistic = { id: genId(name.slice(0, 3)), createdAt: new Date().toISOString(), ...item }
    write(name, [optimistic, ...getAll(name)])
    resource
      .create(name, item)
      .then(() => hydrate(name))
      .catch(() => hydrate(name))
    return optimistic
  }
  const list = getAll(name)
  const record = { id: genId(name.slice(0, 3)), createdAt: new Date().toISOString(), ...item }
  write(name, [record, ...list])
  return record
}

export function update(name, id, patch) {
  const list = getAll(name)
  write(name, list.map((r) => (r.id === id ? { ...r, ...patch } : r)))
  if (isApi(name)) {
    resource.update(name, id, patch).then(() => hydrate(name)).catch(() => hydrate(name))
  }
}

export function remove(name, id) {
  const list = getAll(name)
  write(name, list.filter((r) => r.id !== id))
  if (isApi(name)) {
    resource.remove(name, id).then(() => hydrate(name)).catch(() => hydrate(name))
  }
}

export function subscribe(name, fn) {
  if (!listeners.has(name)) listeners.set(name, new Set())
  listeners.get(name).add(fn)
  return () => listeners.get(name)?.delete(fn)
}

// Hook React : renvoie la liste et se met a jour en direct.
// En mode API, declenche aussi un rafraichissement depuis le backend.
export function useCollection(name) {
  const [data, setData] = useState(() => getAll(name))
  useEffect(() => {
    setData(getAll(name))
    const unsub = subscribe(name, () => setData(getAll(name)))
    if (isApi(name) || (API_ENABLED && name === 'settings')) hydrate(name)
    return unsub
  }, [name])
  return data
}

// Reinitialise les donnees de demonstration (mode demo uniquement)
export function resetStore() {
  Object.keys(seed).forEach((name) => write(name, seed[name]))
}
