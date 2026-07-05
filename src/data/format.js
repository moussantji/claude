// Helpers de formatage (FCFA, dates)

export function fcfa(n) {
  const v = Number(n) || 0
  return v.toLocaleString('fr-FR') + ' FCFA'
}

export function fcfaShort(n) {
  const v = Number(n) || 0
  if (v >= 1000000) return (v / 1000000).toFixed(v % 1000000 === 0 ? 0 : 1) + ' M'
  if (v >= 1000) return Math.round(v / 1000) + ' k'
  return String(v)
}

export function dateFr(iso) {
  if (!iso) return '-'
  const d = new Date(iso)
  if (isNaN(d)) return iso
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

export function timeAgo(iso) {
  const d = new Date(iso)
  if (isNaN(d)) return ''
  const diff = (Date.now() - d.getTime()) / 1000
  if (diff < 60) return 'a l\'instant'
  if (diff < 3600) return Math.floor(diff / 60) + ' min'
  if (diff < 86400) return Math.floor(diff / 3600) + ' h'
  if (diff < 604800) return Math.floor(diff / 86400) + ' j'
  return dateFr(iso)
}
