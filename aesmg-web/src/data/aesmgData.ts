export interface ExecutiveMember {
  id: string
  role: string
  roleBadge: string
  name: string
  initials: string
  phoneDisplay: string
  phoneDial: string
  whatsappDisplay: string
  whatsappNumber: string
  whatsappNote: string
  mandateNote: string
  locationLabel: string
}

export interface ObjectiveItem {
  id: string
  number: string
  title: string
  description: string
  pillarTag: string
  concreteActions: string[]
  iconName: 'users' | 'sparkles' | 'heart' | 'compass' | 'shield'
}

export interface CommunityStory {
  id: string
  category: 'Éducatif' | 'Culturel' | 'Social' | 'Sportif'
  title: string
  subtitle: string
  description: string
  image: string
  highlights: string[]
}

export interface GuideStep {
  id: string
  step: string
  title: string
  summary: string
  checklist: string[]
  recommendedContactId: 'president' | 'secretary'
}

export const AESMG_INFO = {
  acronym: 'AESMG',
  fullName: 'Association des Étudiants et Stagiaires Maliens au Ghana',
  motto: 'Unité · Fraternité · Excellence',
  greeting: 'Aw ni ce · Akwaaba',
  countryBridge: 'Mali 🇲🇱  —  Ghana 🇬🇭',
  cities: ['Accra', 'Kumasi', 'Cape Coast', 'Takoradi', 'Tamale', 'Tema', 'Autre ville'],
} as const

export const EXECUTIVE_BUREAU: ExecutiveMember[] = [
  {
    id: 'president',
    role: 'Président',
    roleBadge: 'Présidence · AESMG',
    name: 'Diadié GUITTEYE',
    initials: 'DG',
    phoneDisplay: '+233 535478560',
    phoneDial: '+233535478560',
    whatsappDisplay: '+223 91035026',
    whatsappNumber: '22391035026',
    whatsappNote: 'Ligne WhatsApp dédiée (indicatif Mali +223)',
    mandateNote:
      'Coordination générale, représentation institutionnelle auprès des autorités compétentes et accueil des nouveaux membres au Ghana.',
    locationLabel: 'Ghana · Ligne Directe & WhatsApp',
  },
  {
    id: 'secretary',
    role: 'Secrétaire général',
    roleBadge: 'Secrétariat Général · AESMG',
    name: 'Georgette Jeanne MARGNA',
    initials: 'GJM',
    phoneDisplay: '+233 50 615 5639',
    phoneDial: '+233506155639',
    whatsappDisplay: '+233 50 615 5639',
    whatsappNumber: '233506155639',
    whatsappNote: 'Contact direct & messagerie WhatsApp Ghana',
    mandateNote:
      'Administration de l’association, suivi des adhésions, accompagnement académique et animation de la vie communautaire.',
    locationLabel: 'Ghana · Secrétariat & Adhésions',
  },
]

export const OBJECTIVES: ObjectiveItem[] = [
  {
    id: 'federer',
    number: '01',
    title: 'Fédérer la communauté malienne au Ghana',
    description:
      'Rassembler tous les étudiants et stagiaires maliens vivant au Ghana, sans distinction de sexe, d’ethnie, de religion, d’opinion ou de classe sociale.',
    pillarTag: 'Inclusion & Rassemblement',
    concreteActions: [
      'Recensement bienveillant dans toutes les villes universitaires du Ghana',
      'Réseau d’accueil ouvert à chaque étudiant et stagiaire malien',
      'Espace d’échange fraternel fondé sur le respect et l’égalité',
    ],
    iconName: 'users',
  },
  {
    id: 'epanouissement',
    number: '02',
    title: 'Œuvrer pour l’épanouissement des membres',
    description:
      'Créer un cadre propice au développement personnel, académique et professionnel des membres de l’association.',
    pillarTag: 'Excellence & Carrière',
    concreteActions: [
      'Partage d’opportunités de stages, formations et bourses',
      'Parrainage entre anciens et nouveaux étudiants par filière',
      'Ateliers d’orientation linguistique et d’insertion professionnelle',
    ],
    iconName: 'sparkles',
  },
  {
    id: 'unite',
    number: '03',
    title: 'Promouvoir l’unité et la fraternité',
    description:
      'Renforcer les liens de solidarité, d’amitié et de fraternité entre tous les étudiants et stagiaires maliens.',
    pillarTag: 'Solidarité Active',
    concreteActions: [
      'Entraide quotidienne face aux défis de la vie à l’étranger',
      'Rencontres inter-campus entre Accra, Kumasi, Cape Coast et régions',
      'Culture de bienveillance et de soutien mutuel durable',
    ],
    iconName: 'heart',
  },
  {
    id: 'vie-communautaire',
    number: '04',
    title: 'Animer la vie communautaire',
    description:
      'Organiser des activités sociales, culturelles, éducatives et sportives pour stimuler la cohésion et le dynamisme de la communauté.',
    pillarTag: 'Rayonnement & Énergie',
    concreteActions: [
      'Journées culturelles mettant à l’honneur le patrimoine malien',
      'Conférences, panels éducatifs et rencontres de réseautage',
      'Tournois sportifs amicaux et sorties collectives de cohésion',
    ],
    iconName: 'compass',
  },
  {
    id: 'droits',
    number: '05',
    title: 'Défendre les droits et intérêts des membres',
    description:
      'Assurer la protection des droits moraux, académiques et sociaux des étudiants et stagiaires maliens au Ghana, en collaboration avec les autorités compétentes.',
    pillarTag: 'Protection & Plaidoyer',
    concreteActions: [
      'Écoute, orientation et médiation en cas de difficulté académique ou sociale',
      'Interface constructive avec les administrations et autorités compétentes',
      'Veille solidaire pour la dignité et la sécurité de chaque membre',
    ],
    iconName: 'shield',
  },
]

export const COMMUNITY_STORIES: CommunityStory[] = [
  {
    id: 'educatif',
    category: 'Éducatif',
    title: 'Mentorat, Études & Stages',
    subtitle: 'Développement académique & professionnel',
    description:
      'Sessions d’entraide universitaire, partage de méthodes, orientation dans les écoles ghanéennes et préparation aux stages professionnels.',
    image: '/images/academic-mentorship.jpg',
    highlights: ['Parrainage par filière', 'Orientation universitaire', 'Réseau stagiaires'],
  },
  {
    id: 'culturel',
    category: 'Culturel',
    title: 'Gala & Rayonnement Malien',
    subtitle: 'Fierté identitaire et partage culturel',
    description:
      'Célébration de notre patrimoine, soirées fraternelles et rencontres interculturelles valorisant les valeurs d’hospitalité du Mali au Ghana.',
    image: '/images/community-gala.jpg',
    highlights: ['Soirées culturelles', 'Partage culinaire', 'Dialogue Mali–Ghana'],
  },
  {
    id: 'social',
    category: 'Social',
    title: 'Accueil & Fraternité de Campus',
    subtitle: 'Ne jamais être seul loin du pays',
    description:
      'Accompagnement dès les premiers jours au Ghana : repères pratiques, mise en réseau et solidarité permanente entre membres.',
    image: '/images/hero-accra-students.jpg',
    highlights: ['Accueil nouveaux arrivants', 'Solidarité morale', 'Vie de campus'],
  },
  {
    id: 'sportif',
    category: 'Sportif',
    title: 'Sport, Énergie & Cohésion',
    subtitle: 'L’esprit d’équipe sur tous les terrains',
    description:
      'Tournois de football amicaux, journées sportives et rencontres récréatives pour renforcer la fraternité dans la joie et le fair-play.',
    image: '/images/sports-solidarity.jpg',
    highlights: ['Tournois inter-villes', 'Esprit d’équipe', 'Bien-être collectif'],
  },
]

export const NEWCOMER_GUIDE: GuideStep[] = [
  {
    id: 'arrivee',
    step: 'Étape 01',
    title: 'Se signaler dès l’arrivée au Ghana',
    summary:
      'Que vous arriviez pour un cursus universitaire, une formation linguistique ou un stage, le premier réflexe est de vous faire connaître auprès du Bureau.',
    checklist: [
      'Enregistrer les numéros officiels du Président et du Secrétaire général',
      'Indiquer votre ville (Accra, Kumasi, Cape Coast…) et votre établissement',
      'Rejoindre le cercle fraternel des étudiants et stagiaires de votre zone',
    ],
    recommendedContactId: 'president',
  },
  {
    id: 'integration',
    step: 'Étape 02',
    title: 'S’intégrer sereinement dans son campus',
    summary:
      'Bénéficiez de l’expérience des membres déjà installés pour franchir plus vite les étapes académiques, linguistiques et pratiques.',
    checklist: [
      'Échanger avec un parrain ou une marraine dans votre domaine d’études',
      'Participer aux rencontres mensuelles et aux activités communautaires',
      'Partager vos besoins en logement, stage ou orientation',
    ],
    recommendedContactId: 'secretary',
  },
  {
    id: 'protection',
    step: 'Étape 03',
    title: 'Connaître ses droits & rester accompagné',
    summary:
      'L’AESMG veille à la défense des droits moraux, académiques et sociaux de ses membres en lien avec les autorités compétentes.',
    checklist: [
      'Conserver des copies numériques de vos documents d’identité et d’inscription',
      'Solliciter le Bureau en toute confidentialité face à toute difficulté',
      'Respecter les règles locales tout en faisant rayonner l’image du Mali',
    ],
    recommendedContactId: 'president',
  },
]

export function buildWhatsAppLink(phoneDigits: string, message: string): string {
  const clean = phoneDigits.replace(/[^0-9]/g, '')
  return `https://wa.me/${clean}?text=${encodeURIComponent(message)}`
}

export function downloadVCard(member: ExecutiveMember): void {
  const vcard = [
    'BEGIN:VCARD',
    'VERSION:3.0',
    `FN:${member.name} (AESMG)`,
    `ORG:Association des Etudiants et Stagiaires Maliens au Ghana (AESMG)`,
    `TITLE:${member.role} - AESMG`,
    `TEL;TYPE=CELL,VOICE:${member.phoneDial}`,
    member.whatsappDisplay !== member.phoneDisplay
      ? `TEL;TYPE=CELL,MSG:+${member.whatsappNumber}`
      : '',
    `NOTE:${member.role} de l'Association des Etudiants et Stagiaires Maliens au Ghana (AESMG).`,
    'END:VCARD',
  ]
    .filter(Boolean)
    .join('\r\n')

  const blob = new Blob([vcard], { type: 'text/vcard;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `AESMG-${member.name.replace(/\s+/g, '-')}.vcf`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}
