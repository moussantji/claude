// Donnees de demonstration pour le CRM Manden Baoubab
// Montants en FCFA (XOF). Villes et noms maliens.

export const seed = {
  settings: {
    company: 'Manden Baoubab',
    email: 'contact@mandenbaoubab.com',
    phone: '+223 20 00 00 00',
    address: 'ACI 2000, Bamako - Mali',
    currency: 'FCFA',
    slogan: 'Le numerique qui fait grandir votre entreprise',
  },

  users: [
    {
      id: 'usr_admin',
      name: 'Amadou Traore',
      email: 'admin@mandenbaoubab.com',
      password: 'admin123',
      role: 'Administrateur',
      avatar: 'AT',
    },
    {
      id: 'usr_dev',
      name: 'Fatoumata Keita',
      email: 'fatou@mandenbaoubab.com',
      password: 'manden2025',
      role: 'Chef de projet',
      avatar: 'FK',
    },
  ],

  leads: [
    { id: 'lead_1', name: 'Ibrahim Diallo', company: 'Sahel Import SARL', email: 'i.diallo@sahelimport.ml', phone: '+223 76 12 34 56', city: 'Bamako', service: 'Site web', budget: 1500000, status: 'Qualifie', source: 'Site web', note: 'Souhaite une refonte + boutique en ligne.', createdAt: '2026-06-28T09:00:00Z' },
    { id: 'lead_2', name: 'Aicha Cisse', company: 'Boutique Aicha', email: 'aicha.cisse@gmail.com', phone: '+223 65 88 77 22', city: 'Sikasso', service: 'E-commerce', budget: 900000, status: 'Nouveau', source: 'Facebook', note: 'Vente de tissus bazin en ligne.', createdAt: '2026-07-02T14:20:00Z' },
    { id: 'lead_3', name: 'Moussa Sangare', company: 'TransSahel Logistique', email: 'm.sangare@transsahel.ml', phone: '+223 79 45 12 90', city: 'Bamako', service: 'Logiciel PC', budget: 3200000, status: 'Contacte', source: 'Recommandation', note: 'Logiciel de gestion de flotte hors-ligne.', createdAt: '2026-06-20T11:10:00Z' },
    { id: 'lead_4', name: 'Kadiatou Toure', company: 'Clinique Espoir', email: 'contact@cliniqueespoir.ml', phone: '+223 66 33 21 45', city: 'Segou', service: 'Application web', budget: 2500000, status: 'Gagne', source: 'Google', note: 'Prise de rendez-vous en ligne + dossier patient.', createdAt: '2026-05-30T08:00:00Z' },
    { id: 'lead_5', name: 'Oumar Ba', company: 'Radio Kayira', email: 'oumar.ba@kayira.ml', phone: '+223 78 90 11 22', city: 'Bamako', service: 'Streaming', budget: 1200000, status: 'Nouveau', source: 'Site web', note: 'Plateforme de streaming audio + podcasts.', createdAt: '2026-07-03T16:45:00Z' },
    { id: 'lead_6', name: 'Salif Coulibaly', company: 'Coulibaly & Fils', email: 's.coulibaly@cfils.ml', phone: '+223 76 55 44 33', city: 'Mopti', service: 'IPTV', budget: 700000, status: 'Perdu', source: 'Salon Bamako Tech', note: 'Budget insuffisant cette annee.', createdAt: '2026-05-12T10:30:00Z' },
    { id: 'lead_7', name: 'Mariam Sidibe', company: 'EcoFerme Kati', email: 'mariam@ecoferme.ml', phone: '+223 63 22 88 77', city: 'Kati', service: 'Site web', budget: 600000, status: 'Contacte', source: 'Instagram', note: 'Site vitrine + catalogue produits bio.', createdAt: '2026-06-25T13:00:00Z' },
  ],

  clients: [
    { id: 'cli_1', name: 'Clinique Espoir', contact: 'Kadiatou Toure', email: 'contact@cliniqueespoir.ml', phone: '+223 66 33 21 45', city: 'Segou', sector: 'Sante', since: '2026-06-01', value: 2500000, status: 'Actif' },
    { id: 'cli_2', name: 'Groupe Diarra Distribution', contact: 'Seydou Diarra', email: 'seydou@diarradist.ml', phone: '+223 76 10 20 30', city: 'Bamako', sector: 'Distribution', since: '2025-11-15', value: 6800000, status: 'Actif' },
    { id: 'cli_3', name: 'Hotel Djenne Palace', contact: 'Awa Maiga', email: 'reservation@djennepalace.ml', phone: '+223 65 44 55 66', city: 'Djenne', sector: 'Tourisme', since: '2025-09-02', value: 1800000, status: 'Actif' },
    { id: 'cli_4', name: 'Universite Populaire du Mali', contact: 'Pr. Bakary Sow', email: 'info@upmali.edu.ml', phone: '+223 20 22 33 44', city: 'Bamako', sector: 'Education', since: '2024-10-10', value: 4200000, status: 'Inactif' },
    { id: 'cli_5', name: 'Manden Mode', contact: 'Nana Sylla', email: 'contact@mandenmode.ml', phone: '+223 79 88 66 44', city: 'Bamako', sector: 'Mode', since: '2026-02-20', value: 1500000, status: 'Actif' },
  ],

  projects: [
    { id: 'prj_1', name: 'Refonte site + boutique', client: 'Manden Mode', type: 'E-commerce', budget: 1500000, status: 'En cours', progress: 65, deadline: '2026-08-15', lead: 'Fatoumata Keita' },
    { id: 'prj_2', name: 'App prise de RDV clinique', client: 'Clinique Espoir', type: 'Application web', budget: 2500000, status: 'En cours', progress: 40, deadline: '2026-09-30', lead: 'Amadou Traore' },
    { id: 'prj_3', name: 'Plateforme e-learning', client: 'Universite Populaire du Mali', type: 'Application web', budget: 4200000, status: 'Termine', progress: 100, deadline: '2026-03-01', lead: 'Fatoumata Keita' },
    { id: 'prj_4', name: 'Logiciel gestion de stock', client: 'Groupe Diarra Distribution', type: 'Logiciel PC', budget: 3800000, status: 'En cours', progress: 25, deadline: '2026-11-20', lead: 'Amadou Traore' },
    { id: 'prj_5', name: 'Site de reservation hotel', client: 'Hotel Djenne Palace', type: 'Site web', budget: 1800000, status: 'En attente', progress: 10, deadline: '2026-10-05', lead: 'Fatoumata Keita' },
  ],

  // Offres / produits des plateformes maison
  products: [
    { id: 'prd_1', name: 'Manden Stream - Standard', category: 'Streaming', price: 3000, cycle: '/mois', subscribers: 428, status: 'Publie' },
    { id: 'prd_2', name: 'Manden Stream - Premium 4K', category: 'Streaming', price: 6000, cycle: '/mois', subscribers: 176, status: 'Publie' },
    { id: 'prd_3', name: 'Manden TV IPTV - 3 mois', category: 'IPTV', price: 12000, cycle: '/3 mois', subscribers: 512, status: 'Publie' },
    { id: 'prd_4', name: 'Manden TV IPTV - 12 mois', category: 'IPTV', price: 40000, cycle: '/an', subscribers: 233, status: 'Publie' },
    { id: 'prd_5', name: 'Bazin Riche Premium (5m)', category: 'E-commerce', price: 45000, cycle: '', subscribers: 0, status: 'Publie' },
    { id: 'prd_6', name: 'Smartphone Manden X2 64Go', category: 'E-commerce', price: 89000, cycle: '', subscribers: 0, status: 'Publie' },
    { id: 'prd_7', name: 'Casque audio sans fil', category: 'E-commerce', price: 22000, cycle: '', subscribers: 0, status: 'Brouillon' },
  ],

  orders: [
    { id: 'ord_1', ref: 'CMD-2601', customer: 'Ousmane Fofana', type: 'E-commerce', item: 'Smartphone Manden X2 64Go', amount: 89000, status: 'Livre', date: '2026-07-01', city: 'Bamako' },
    { id: 'ord_2', ref: 'ABO-1187', customer: 'Rokia Konate', type: 'IPTV', item: 'Manden TV IPTV - 12 mois', amount: 40000, status: 'Paye', date: '2026-07-03', city: 'Kayes' },
    { id: 'ord_3', ref: 'ABO-1190', customer: 'Drissa Kone', type: 'Streaming', item: 'Manden Stream - Premium 4K', amount: 6000, status: 'Paye', date: '2026-07-04', city: 'Bamako' },
    { id: 'ord_4', ref: 'CMD-2603', customer: 'Aminata Diarra', type: 'E-commerce', item: 'Bazin Riche Premium (5m)', amount: 45000, status: 'En preparation', date: '2026-07-04', city: 'Sikasso' },
    { id: 'ord_5', ref: 'CMD-2604', customer: 'Modibo Sissoko', type: 'E-commerce', item: 'Casque audio sans fil', amount: 22000, status: 'En attente', date: '2026-07-05', city: 'Segou' },
    { id: 'ord_6', ref: 'ABO-1193', customer: 'Hawa Berthe', type: 'Streaming', item: 'Manden Stream - Standard', amount: 3000, status: 'Paye', date: '2026-07-05', city: 'Mopti' },
  ],

  posts: [
    {
      id: 'post_1',
      title: 'Pourquoi chaque PME malienne a besoin d\'un site web en 2026',
      slug: 'pme-malienne-site-web-2026',
      category: 'Conseils',
      author: 'Amadou Traore',
      date: '2026-06-30',
      status: 'Publie',
      excerpt: 'Un site web professionnel n\'est plus un luxe : c\'est le premier vendeur de votre entreprise, disponible 24h/24.',
      content: 'Au Mali, l\'usage d\'Internet et des smartphones explose. Vos clients cherchent des produits et services en ligne avant meme de se deplacer.\n\nUn site web bien concu inspire confiance, presente votre catalogue et capte des prospects pendant que vous dormez. Chez Manden Baoubab, nous concevons des sites rapides, adaptes au mobile et optimises pour Google.\n\nInvestir dans le numerique aujourd\'hui, c\'est prendre de l\'avance sur vos concurrents demain.',
    },
    {
      id: 'post_2',
      title: 'Lancer sa boutique e-commerce au Mali : le guide complet',
      slug: 'lancer-boutique-ecommerce-mali',
      category: 'E-commerce',
      author: 'Fatoumata Keita',
      date: '2026-06-18',
      status: 'Publie',
      excerpt: 'Du choix des produits au paiement mobile (Orange Money, Moov Money), voici les etapes clefs.',
      content: 'Le e-commerce malien connait une croissance rapide. Pour reussir, integrez les moyens de paiement locaux comme Orange Money et Moov Money, et proposez une livraison fiable a Bamako et en region.\n\nNotre plateforme Manden Shop gere le catalogue, les commandes, les paiements et les livraisons dans une seule interface simple a utiliser.',
    },
    {
      id: 'post_3',
      title: 'IPTV et streaming : le nouveau visage du divertissement africain',
      slug: 'iptv-streaming-divertissement-africain',
      category: 'Streaming',
      author: 'Amadou Traore',
      date: '2026-05-28',
      status: 'Publie',
      excerpt: 'Chaines locales, films africains, sport en direct : le contenu de qualite devient accessible partout.',
      content: 'Avec Manden TV et Manden Stream, nous rapprochons le meilleur du divertissement des foyers maliens : chaines nationales et internationales, films en bambara et en francais, evenements sportifs en direct.\n\nUne connexion internet suffit pour profiter d\'une experience fluide sur TV, ordinateur et mobile.',
    },
    {
      id: 'post_4',
      title: 'Logiciels metier sur mesure : gagnez du temps au quotidien',
      slug: 'logiciels-metier-sur-mesure',
      category: 'Logiciel',
      author: 'Fatoumata Keita',
      date: '2026-05-10',
      status: 'Brouillon',
      excerpt: 'Gestion de stock, facturation, RH : un logiciel adapte a vos processus fait toute la difference.',
      content: 'Chaque entreprise est unique. Un logiciel sur mesure epouse vos processus au lieu de vous forcer a vous adapter. Nous developpons des applications de bureau (Windows) fiables, meme hors-ligne, pour les zones a faible connexion.',
    },
  ],

  messages: [
    { id: 'msg_1', name: 'Bakary Kone', email: 'bakary.kone@gmail.com', phone: '+223 76 00 11 22', subject: 'Demande de devis site vitrine', message: 'Bonjour, je souhaite un site pour mon garage a Bamako. Merci de me rappeler.', service: 'Site web', status: 'Nouveau', date: '2026-07-05T10:15:00Z' },
    { id: 'msg_2', name: 'Djeneba Traore', email: 'djeneba@boutiquedj.ml', phone: '+223 65 12 90 34', subject: 'Boutique en ligne', message: 'Combien coute une boutique en ligne avec paiement Orange Money ?', service: 'E-commerce', status: 'Lu', date: '2026-07-04T18:40:00Z' },
    { id: 'msg_3', name: 'Cheick Oumar Diarra', email: 'cheick.diarra@entreprise.ml', phone: '+223 79 33 44 55', subject: 'Abonnement IPTV entreprise', message: 'Nous voulons equiper notre restaurant de Manden TV. Quelles offres ?', service: 'IPTV', status: 'Traite', date: '2026-07-02T09:05:00Z' },
  ],
}
