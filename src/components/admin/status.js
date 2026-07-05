// Associe chaque statut a une classe de pastille (pill)
const map = {
  // leads
  'Nouveau': 'pill-blue',
  'Contacte': 'pill-gold',
  'Qualifie': 'pill-green',
  'Gagne': 'pill-green',
  'Perdu': 'pill-red',
  // projets
  'En cours': 'pill-gold',
  'Termine': 'pill-green',
  'En attente': 'pill-gray',
  // clients
  'Actif': 'pill-green',
  'Inactif': 'pill-gray',
  // produits / contenu
  'Publie': 'pill-green',
  'Brouillon': 'pill-gray',
  // commandes
  'Paye': 'pill-green',
  'Livre': 'pill-green',
  'En preparation': 'pill-gold',
  'Annule': 'pill-red',
  // messages
  'Lu': 'pill-gold',
  'Traite': 'pill-green',
}

export function statusClass(status) {
  return map[status] || 'pill-gray'
}
