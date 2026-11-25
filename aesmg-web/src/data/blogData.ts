export type BlogCategory =
  | 'Guides & Ghana'
  | 'Académique'
  | 'Culture & Fraternité'
  | 'Droits & Vie'

export type BlogStatus = 'published' | 'draft'

export interface BlogArticleSection {
  heading: string
  paragraphs: string[]
}

export interface BlogArticle {
  id: string
  status: BlogStatus
  category: BlogCategory
  featured?: boolean
  title: string
  excerpt: string
  readTime: string
  publishedLabel: string
  author: string
  authorRole: string
  image: string
  tags: string[]
  keyTakeaways: string[]
  pullQuote: string
  sections: BlogArticleSection[]
}

export const BLOG_CATEGORIES: Array<'Tous' | BlogCategory> = [
  'Tous',
  'Guides & Ghana',
  'Académique',
  'Culture & Fraternité',
  'Droits & Vie',
]

export const BLOG_COVER_GALLERY = [
  { label: 'Accra · Golden Hour', url: '/images/blog-accra-skyline.jpg' },
  { label: 'Héritage Bogolan', url: '/images/blog-culture-heritage.jpg' },
  { label: 'Campus & Mentorat', url: '/images/academic-mentorship.jpg' },
  { label: 'Gala & Fraternité', url: '/images/community-gala.jpg' },
  { label: 'Étudiants au Ghana', url: '/images/hero-accra-students.jpg' },
  { label: 'Sport & Cohésion', url: '/images/sports-solidarity.jpg' },
] as const

export const BLOG_AUTHOR_PRESETS = [
  { author: 'Diadié GUITTEYE', role: 'Président · AESMG' },
  { author: 'Georgette Jeanne MARGNA', role: 'Secrétaire général · AESMG' },
  { author: 'Bureau Exécutif AESMG', role: 'Communication Officielle' },
  { author: 'Commission Éducation AESMG', role: 'Mentorat & Stages' },
  { author: 'Pôle Culture & Social AESMG', role: 'Vie Communautaire' },
] as const

export const BLOG_ARTICLES: BlogArticle[] = [
  {
    id: 'guide-installation-ghana',
    status: 'published',
    category: 'Guides & Ghana',
    featured: true,
    title:
      'Réussir son installation au Ghana : les 7 repères essentiels pour les nouveaux étudiants et stagiaires maliens',
    excerpt:
      'Dès votre arrivée à Accra, Kumasi ou Cape Coast, découvrez les réflexes concrets pour vous loger sereinement, vous repérer sur votre campus et rejoindre le réseau fraternel de l’AESMG.',
    readTime: '5 min',
    publishedLabel: 'Guide Officiel · Édition 2026',
    author: 'Bureau Exécutif AESMG',
    authorRole: 'Accueil & Orientation',
    image: '/images/blog-accra-skyline.jpg',
    tags: ['Nouveaux arrivants', 'Accra', 'Kumasi', 'Logement'],
    keyTakeaways: [
      'Prendre contact avec le Bureau AESMG dès les premiers jours pour intégrer votre cercle de ville.',
      'Sécuriser vos démarches d’inscription, votre hébergement et vos copies numériques de documents.',
      'S’appuyer sur le parrainage entre anciens et nouveaux pour gagner un temps précieux.',
    ],
    pullQuote:
      'Arriver dans un nouveau pays devient une force dès lors que l’on peut compter sur une communauté fraternelle, structurée et bienveillante.',
    sections: [
      {
        heading: '1. Le premier réflexe : rejoindre la communauté dès l’arrivée',
        paragraphs: [
          'Que vous débarquiez à l’aéroport international de Kotoka à Accra ou que vous rejoigniez une université à Kumasi, Cape Coast ou Takoradi, l’isolement est le premier obstacle à éviter. En vous présentant au Bureau de l’AESMG, vous êtes immédiatement mis en relation avec des étudiants et stagiaires maliens déjà familiers de votre ville et de votre établissement.',
          'Notre mission fondatrice est claire : fédérer tous les étudiants et stagiaires maliens vivant au Ghana, sans distinction de sexe, d’ethnie, de religion, d’opinion ou de classe sociale.',
        ],
      },
      {
        heading: '2. Logement, transport et repères du quotidien',
        paragraphs: [
          'Avant de signer un contrat de résidence étudiante (hostel) ou de colocation, prenez toujours le temps de visiter le quartier ou de demander conseil à un membre résidant sur place. Vérifiez la proximité avec votre campus, l’accès aux transports, la fiabilité de l’eau et de l’électricité ainsi que les conditions écrites du bail.',
          'Dès la première semaine, procurez-vous une carte SIM locale, configurez un portefeuille Mobile Money indispensable au Ghana pour régler facilement les dépenses courantes, et conservez hors ligne les numéros officiels du Bureau Exécutif.',
        ],
      },
      {
        heading: '3. Préparer sereinement son intégration académique',
        paragraphs: [
          'Le système universitaire ghanéen valorise fortement la ponctualité, la participation orale et les travaux de groupe. N’hésitez pas à solliciter un parrain ou une marraine AESMG de votre filière pour comprendre les attentes des enseignants, la structure des crédits et les ressources bibliothécaires disponibles.',
        ],
      },
    ],
  },
  {
    id: 'bilinguisme-accelerateur-carriere',
    status: 'published',
    category: 'Académique',
    title:
      'Bilinguisme français–anglais au Ghana : transformer son séjour en véritable accélérateur de carrière',
    excerpt:
      'Comment tirer parti de l’immersion anglophone, des clubs universitaires et des stages locaux pour bâtir un profil recherché partout en Afrique de l’Ouest et à l’international.',
    readTime: '4 min',
    publishedLabel: 'Pôle Académique & Carrière',
    author: 'Commission Éducation AESMG',
    authorRole: 'Mentorat & Stages',
    image: '/images/academic-mentorship.jpg',
    tags: ['Bilinguisme', 'Stages', 'Carrière', 'Mentorat'],
    keyTakeaways: [
      'Pratiquer l’anglais au quotidien hors de la salle de classe accélère nettement la fluidité.',
      'Combiner diplôme universitaire, stage local et réseau communautaire démultiplie les débouchés.',
      'Les ateliers d’entraide AESMG aident à structurer CV, lettres de motivation et présentations orales.',
    ],
    pullQuote:
      'Maîtriser à la fois le français et l’anglais en Afrique de l’Ouest ouvre les portes des entreprises régionales, des institutions internationales et de l’entrepreneuriat.',
    sections: [
      {
        heading: 'Sortir de sa zone de confort dès le premier semestre',
        paragraphs: [
          'Beaucoup de nouveaux arrivants francophones hésitent à prendre la parole en anglais par crainte de faire des fautes. Pourtant, l’hospitalité ghanéenne offre un terrain idéal pour progresser rapidement : chaque discussion sur le campus, chaque présentation et chaque échange avec vos camarades est un entraînement gratuit et précieux.',
          'Fixez-vous des rituels simples : lire chaque matin un article de presse ghanéenne dans votre domaine d’études, rejoindre une association étudiante de votre faculté et rédiger vos prises de notes en anglais.',
        ],
      },
      {
        heading: 'Valoriser son séjour par un stage structurant',
        paragraphs: [
          'Pour les étudiants comme pour les stagiaires, l’expérience professionnelle au Ghana constitue un atout majeur. L’AESMG encourage le partage d’opportunités de stages, les retours d’expérience d’anciens et les sessions de préparation aux entretiens afin de créer un véritable cadre d’épanouissement personnel, académique et professionnel.',
        ],
      },
    ],
  },
  {
    id: 'heritage-malien-fraternite-accra',
    status: 'published',
    category: 'Culture & Fraternité',
    title:
      'Entre Bamako et Accra : faire rayonner la culture malienne et l’esprit de fraternité au Ghana',
    excerpt:
      'Bogolan, musique, gastronomie et valeurs de solidarité : comment nos rencontres culturelles, sociales et sportives tissent des liens durables entre le Mali et le Ghana.',
    readTime: '4 min',
    publishedLabel: 'Vie Culturelle & Cohésion',
    author: 'Pôle Culture & Social AESMG',
    authorRole: 'Animation Communautaire',
    image: '/images/blog-culture-heritage.jpg',
    tags: ['Culture', 'Fraternité', 'Bogolan', 'Solidarité'],
    keyTakeaways: [
      'Chaque étudiant et stagiaire malien est un ambassadeur vivant des valeurs d’hospitalité du Mali.',
      'Les activités culturelles, sociales et sportives renforcent la cohésion inter-campus.',
      'La fraternité malienne s’exprime dans l’entraide quotidienne et le respect mutuel.',
    ],
    pullQuote:
      'De la vallée du Niger aux côtes d’Accra, la fraternité est notre plus belle richesse et le socle de notre réussite collective.',
    sections: [
      {
        heading: 'Un patrimoine vivant qui rassemble',
        paragraphs: [
          'Loin du pays natal, retrouver l’énergie d’une rencontre culturelle, la chaleur d’un repas partagé ou l’élégance des motifs traditionnels maliens rappelle à chacun que la communauté est une véritable famille. Nos événements sont pensés pour célébrer nos racines tout en dialoguant avec la richesse culturelle du Ghana.',
        ],
      },
      {
        heading: 'Le sport et la vie associative comme moteurs d’unité',
        paragraphs: [
          'Au-delà des soirées culturelles et des conférences éducatives, les tournois sportifs et sorties collectives créent des amitiés fortes entre étudiants d’universités différentes. C’est cette dynamique constante qui permet à l’AESMG d’animer la vie communautaire et de cultiver une solidarité sincère.',
        ],
      },
    ],
  },
  {
    id: 'droits-accompagnement-membres',
    status: 'published',
    category: 'Droits & Vie',
    title:
      'Droits moraux, académiques et sociaux : comment l’AESMG protège et accompagne chaque membre',
    excerpt:
      'Un défi administratif, une question académique ou une situation d’urgence ? Découvrez le rôle de médiation, d’écoute et de plaidoyer de votre association auprès des autorités compétentes.',
    readTime: '3 min',
    publishedLabel: 'Protection & Accompagnement',
    author: 'Présidence & Secrétariat Général',
    authorRole: 'Défense des Intérêts',
    image: '/images/community-gala.jpg',
    tags: ['Droits', 'Entraide', 'Démarches', 'Bureau Exécutif'],
    keyTakeaways: [
      'Ne restez jamais seul face à une difficulté académique, administrative ou sociale.',
      'Le Bureau Exécutif agit dans la confidentialité et en collaboration avec les autorités compétentes.',
      'Garder ses documents administratifs à jour reste la meilleure prévention au quotidien.',
    ],
    pullQuote:
      'Défendre les droits et intérêts des membres, c’est veiller chaque jour à la dignité, à la sérénité et à la sécurité de nos étudiants et stagiaires.',
    sections: [
      {
        heading: 'Une écoute disponible et confidentielle',
        paragraphs: [
          'Un parcours d’études ou de stage à l’étranger comporte parfois des imprévus : incompréhension administrative, souci de santé, difficulté académique ou besoin d’orientation juridique. L’un des cinq piliers de l’AESMG consiste précisément à défendre les droits moraux, académiques et sociaux de ses membres.',
        ],
      },
      {
        heading: 'Agir en lien constructif avec les autorités compétentes',
        paragraphs: [
          'Lorsque la situation l’exige, le Bureau Exécutif accompagne le membre concerné, facilite le dialogue avec les administrations universitaires et assure la liaison avec les autorités compétentes. Pour toute question urgente, le Président Diadié GUITTEYE (+233 535478560 / WhatsApp +223 91035026) et le Secrétaire général Georgette Jeanne MARGNA (+233 50 615 5639) restent directement joignables.',
        ],
      },
    ],
  },
]
