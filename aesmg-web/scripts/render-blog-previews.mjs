import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import satori from 'satori'
import { Resvg } from '@resvg/resvg-js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const rootDir = path.resolve(__dirname, '..')
const exportsDir = path.join(rootDir, 'exports')
fs.mkdirSync(exportsDir, { recursive: true })

const h = (type, props, ...children) => ({
  type,
  props: {
    ...(props || {}),
    children: children.length === 0 ? undefined : children.length === 1 ? children[0] : children,
  },
})

const readFont = (relPath) => fs.readFileSync(path.join(rootDir, 'node_modules', relPath))

const fonts = [
  { name: 'Fraunces', data: readFont('@fontsource/fraunces/files/fraunces-latin-600-normal.woff'), weight: 600, style: 'normal' },
  { name: 'Fraunces', data: readFont('@fontsource/fraunces/files/fraunces-latin-700-normal.woff'), weight: 700, style: 'normal' },
  { name: 'Fraunces', data: readFont('@fontsource/fraunces/files/fraunces-latin-600-italic.woff'), weight: 600, style: 'italic' },
  { name: 'Plus Jakarta Sans', data: readFont('@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-400-normal.woff'), weight: 400, style: 'normal' },
  { name: 'Plus Jakarta Sans', data: readFont('@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-500-normal.woff'), weight: 500, style: 'normal' },
  { name: 'Plus Jakarta Sans', data: readFont('@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-600-normal.woff'), weight: 600, style: 'normal' },
  { name: 'Plus Jakarta Sans', data: readFont('@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-700-normal.woff'), weight: 700, style: 'normal' },
  { name: 'JetBrains Mono', data: readFont('@fontsource/jetbrains-mono/files/jetbrains-mono-latin-500-normal.woff'), weight: 500, style: 'normal' },
  { name: 'JetBrains Mono', data: readFont('@fontsource/jetbrains-mono/files/jetbrains-mono-latin-600-normal.woff'), weight: 600, style: 'normal' },
]

const loadImg = (filename) => {
  const buf = fs.readFileSync(path.join(rootDir, 'public/images', filename))
  return `data:image/jpeg;base64,${buf.toString('base64')}`
}

const IMG = {
  blogAccra: loadImg('blog-accra-skyline.jpg'),
  blogCulture: loadImg('blog-culture-heritage.jpg'),
  academic: loadImg('academic-mentorship.jpg'),
  gala: loadImg('community-gala.jpg'),
}

const aesmgSealSvg = `data:image/svg+xml;base64,${Buffer.from(
  `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64" fill="none">
    <rect x="2" y="2" width="60" height="60" rx="18" fill="#062319" stroke="#E2B643" stroke-width="2"/>
    <path d="M32 9L55 32L32 55L9 32L32 9Z" stroke="#E2B643" stroke-opacity="0.35" stroke-width="1.4"/>
    <circle cx="32" cy="32" r="19" stroke="#E2B643" stroke-width="1.2" stroke-dasharray="2.5 2.5"/>
    <path d="M32 16.5L35.7 26.2L46 26.6L37.9 33L40.7 43L32 37.2L23.3 43L26.1 33L18 26.6L28.3 26.2L32 16.5Z" fill="#F7EBC6"/>
    <circle cx="25" cy="49.5" r="2.2" fill="#007A4D"/>
    <circle cx="32" cy="50.5" r="2.4" fill="#E2B643"/>
    <circle cx="39" cy="49.5" r="2.2" fill="#B91C2E"/>
  </svg>`
).toString('base64')}`

const TricoloreBar = (height = 4) =>
  h(
    'div',
    { style: { display: 'flex', width: '100%', height: `${height}px` } },
    h('div', { style: { flex: 1, backgroundColor: '#007A4D' } }),
    h('div', { style: { flex: 1, backgroundColor: '#E2B643' } }),
    h('div', { style: { flex: 1, backgroundColor: '#B91C2E' } })
  )

const BLOG_CARDS = [
  {
    cat: 'ACADÉMIQUE',
    time: '4 min',
    pub: 'Pôle Académique & Carrière',
    title: 'Bilinguisme français–anglais au Ghana : transformer son séjour en accélérateur de carrière',
    desc: 'Comment tirer parti de l’immersion anglophone, des clubs universitaires et des stages locaux pour bâtir un profil recherché en Afrique de l’Ouest.',
    author: 'Commission Éducation AESMG',
    img: IMG.academic,
  },
  {
    cat: 'CULTURE & FRATERNITÉ',
    time: '4 min',
    pub: 'Vie Culturelle & Cohésion',
    title: 'Entre Bamako et Accra : faire rayonner la culture malienne et l’esprit de fraternité',
    desc: 'Bogolan, musique, gastronomie et valeurs de solidarité : comment nos rencontres culturelles et sportives tissent des liens durables Mali–Ghana.',
    author: 'Pôle Culture & Social AESMG',
    img: IMG.blogCulture,
  },
  {
    cat: 'DROITS & VIE',
    time: '3 min',
    pub: 'Protection & Accompagnement',
    title: 'Droits moraux, académiques et sociaux : comment l’AESMG accompagne chaque membre',
    desc: 'Un défi administratif ou académique ? Découvrez le rôle d’écoute, de médiation et de liaison du Bureau avec les autorités compétentes.',
    author: 'Présidence & Secrétariat Général',
    img: IMG.gala,
  },
]

// =========================================================================
// 1. BLOG PC COMPLET (1440 x 2680 — Catalogue + Lecteur d'Article Ouvert)
// =========================================================================
const BlogDesktopPage = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 1440,
        height: 2300,
        backgroundColor: '#FAF8F3',
        color: '#0E1E18',
        fontFamily: 'Plus Jakarta Sans',
        overflow: 'hidden',
      },
    },
    TricoloreBar(4),
    // Clean Website Desktop Navbar (Blog active, NO browser chrome, NO iPhone button)
    h(
      'div',
      {
        style: {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '18px 80px',
          backgroundColor: '#062319',
          color: '#FAF8F3',
        },
      },
      h(
        'div',
        { style: { display: 'flex', alignItems: 'center', gap: 12 } },
        h('img', { src: aesmgSealSvg, width: 42, height: 42 }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h('span', { style: { fontFamily: 'Fraunces', fontSize: 18, fontWeight: 700 } }, 'AESMG'),
          h('span', { style: { fontSize: 11, color: '#D3E5DC' } }, 'Étudiants & Stagiaires au Ghana')
        )
      ),
      h(
        'div',
        { style: { display: 'flex', alignItems: 'center', gap: 8 } },
        ...['Accueil', 'Objectifs', 'Vie', 'Blog', 'Bureau', 'Adhérer'].map((item) => {
          const active = item === 'Blog'
          return h(
            'div',
            {
              style: {
                display: 'flex',
                padding: '8px 16px',
                borderRadius: 99,
                backgroundColor: active ? '#E2B643' : 'transparent',
                color: active ? '#062319' : '#EEF5F1',
                fontSize: 13,
                fontWeight: active ? 700 : 600,
              },
            },
            item
          )
        })
      ),
      h(
        'div',
        {
          style: {
            display: 'flex',
            padding: '9px 18px',
            borderRadius: 99,
            border: '1px solid rgba(226,182,67,0.55)',
            color: '#F7EBC6',
            fontSize: 12.5,
            fontWeight: 700,
          },
        },
        'Contact Bureau'
      )
    ),

    // BLOG HUB SECTION
    h(
      'div',
      { style: { display: 'flex', flexDirection: 'column', padding: '50px 80px 44px' } },
      // Editorial Title
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center' } },
        h(
          'span',
          {
            style: {
              padding: '6px 16px',
              borderRadius: 99,
              border: '1px solid rgba(11,59,43,0.14)',
              backgroundColor: '#FFFFFF',
              fontFamily: 'JetBrains Mono',
              fontSize: 11,
              fontWeight: 600,
              letterSpacing: '0.18em',
              color: '#0B3B2B',
            },
          },
          'LE JOURNAL · BLOG AESMG'
        ),
        h(
          'span',
          { style: { marginTop: 12, fontFamily: 'Fraunces', fontSize: 42, fontWeight: 700, color: '#062319' } },
          'Récits, Guides & Vie au Ghana'
        ),
        h(
          'span',
          { style: { marginTop: 8, fontSize: 15, color: '#425A50', maxWidth: 660 } },
          'Conseils d’installation, orientation académique, culture malienne et accompagnement des étudiants et stagiaires maliens au Ghana.'
        )
      ),
      // Search + Favoris + Category Pills
      h(
        'div',
        { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 30, gap: 16 } },
        h(
          'div',
          { style: { display: 'flex', gap: 8 } },
          ...['Tous', 'Guides & Ghana', 'Académique', 'Culture & Fraternité', 'Droits & Vie'].map((cat, i) =>
            h(
              'span',
              {
                style: {
                  padding: '9px 16px',
                  borderRadius: 99,
                  backgroundColor: i === 0 ? '#062319' : '#FFFFFF',
                  border: i === 0 ? 'none' : '1px solid rgba(11,59,43,0.14)',
                  color: i === 0 ? '#F7EBC6' : '#264237',
                  fontSize: 12,
                  fontWeight: 600,
                },
              },
              cat
            )
          )
        ),
        h(
          'div',
          { style: { display: 'flex', gap: 10, alignItems: 'center' } },
          h(
            'div',
            {
              style: {
                display: 'flex',
                width: 310,
                padding: '10px 16px',
                borderRadius: 14,
                border: '1px solid rgba(11,59,43,0.14)',
                backgroundColor: '#FFFFFF',
                fontSize: 12.5,
                color: '#7E948B',
              },
            },
            'Rechercher un guide, Accra, bilinguisme…'
          ),
          h(
            'div',
            {
              style: {
                display: 'flex',
                padding: '10px 16px',
                borderRadius: 14,
                border: '1px solid rgba(201,150,26,0.45)',
                backgroundColor: '#F7EBC6',
                color: '#062319',
                fontSize: 12,
                fontWeight: 700,
              },
            },
            'Favoris (2)'
          )
        )
      ),
      // Featured Story ("À la une")
      h(
        'div',
        {
          style: {
            display: 'flex',
            marginTop: 26,
            borderRadius: 26,
            overflow: 'hidden',
            backgroundColor: '#062319',
            height: 310,
          },
        },
        h('img', { src: IMG.blogAccra, width: 600, height: 310, style: { width: 600, height: 310, objectFit: 'cover' } }),
        h(
          'div',
          { style: { display: 'flex', flex: 1, flexDirection: 'column', justifyContent: 'space-between', padding: '30px 36px' } },
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h(
              'div',
              { style: { display: 'flex', gap: 10, alignItems: 'center' } },
              h('span', { style: { padding: '5px 12px', borderRadius: 99, backgroundColor: '#E2B643', color: '#062319', fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 700 } }, 'À LA UNE'),
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 11, color: '#E2B643' } }, 'GUIDES & GHANA · 5 MIN · ÉDITION 2026')
            ),
            h(
              'span',
              { style: { marginTop: 14, fontFamily: 'Fraunces', fontSize: 27, lineHeight: 1.18, fontWeight: 700, color: '#FAF8F3' } },
              'Réussir son installation au Ghana : les 7 repères essentiels pour les nouveaux étudiants et stagiaires maliens'
            ),
            h(
              'span',
              { style: { marginTop: 12, fontSize: 14, lineHeight: 1.55, color: '#D7E6DF' } },
              'Dès votre arrivée à Accra, Kumasi ou Cape Coast, découvrez les réflexes concrets pour vous loger sereinement, vous repérer sur votre campus et rejoindre le réseau fraternel de l’AESMG.'
            )
          ),
          h(
            'div',
            { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
            h('span', { style: { fontSize: 12.5, fontWeight: 600, color: '#B7D0C4' } }, 'Bureau Exécutif AESMG · Accueil & Orientation'),
            h('span', { style: { padding: '10px 20px', borderRadius: 12, backgroundColor: '#E2B643', color: '#062319', fontSize: 12.5, fontWeight: 700 } }, 'Lire l’article ›')
          )
        )
      ),
      // 3 Editorial Cards Row
      h(
        'div',
        { style: { display: 'flex', gap: 22, marginTop: 24 } },
        ...BLOG_CARDS.map((b) =>
          h(
            'div',
            {
              style: {
                display: 'flex',
                flex: 1,
                flexDirection: 'column',
                borderRadius: 22,
                overflow: 'hidden',
                backgroundColor: '#FFFFFF',
                border: '1px solid rgba(11,59,43,0.1)',
              },
            },
            h('img', { src: b.img, width: 420, height: 195, style: { width: '100%', height: 195, objectFit: 'cover' } }),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', padding: '20px 22px', flex: 1, justifyContent: 'space-between' } },
              h(
                'div',
                { style: { display: 'flex', flexDirection: 'column' } },
                h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 600, color: '#9B7210' } }, `${b.cat} · ${b.time}`),
                h('span', { style: { marginTop: 8, fontFamily: 'Fraunces', fontSize: 19, lineHeight: 1.24, fontWeight: 700, color: '#062319' } }, b.title),
                h('span', { style: { marginTop: 9, fontSize: 13, lineHeight: 1.5, color: '#465E54' } }, b.desc)
              ),
              h(
                'div',
                { style: { display: 'flex', marginTop: 16, paddingTop: 12, borderTop: '1px solid rgba(11,59,43,0.08)', justifyContent: 'space-between', alignItems: 'center' } },
                h('span', { style: { fontSize: 11.5, fontWeight: 600, color: '#3C554B' } }, b.author),
                h('span', { style: { fontSize: 12, fontWeight: 700, color: '#062319' } }, 'Lire ›')
              )
            )
          )
        )
      ),
      // Tribune Étudiante Banner
      h(
        'div',
        {
          style: {
            display: 'flex',
            marginTop: 26,
            padding: '22px 28px',
            borderRadius: 22,
            border: '1px solid rgba(201,150,26,0.38)',
            background: 'linear-gradient(90deg, rgba(247,235,198,0.5) 0%, #FFFFFF 60%, rgba(247,235,198,0.35) 100%)',
            justifyContent: 'space-between',
            alignItems: 'center',
          },
        },
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 700, color: '#8C640B', letterSpacing: '0.16em' } }, 'TRIBUNE ÉTUDIANTE & STAGIAIRE'),
          h('span', { style: { marginTop: 4, fontFamily: 'Fraunces', fontSize: 21, fontWeight: 700, color: '#062319' } }, 'Partagez votre expérience ou un conseil sur le Blog AESMG'),
          h('span', { style: { marginTop: 3, fontSize: 13, color: '#425A50' } }, 'Récit de campus, guide de filière, bon plan stage : chaque plume malienne au Ghana est la bienvenue.')
        ),
        h(
          'span',
          { style: { padding: '13px 22px', borderRadius: 14, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 13, fontWeight: 700 } },
          'Proposer un article via WhatsApp ›'
        )
      )
    ),

    // OPEN ARTICLE READER SECTION (Showing how an article looks when opened on PC!)
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          flex: 1,
          padding: '48px 80px 52px',
          backgroundColor: '#F3EFE5',
          borderTop: '1px solid rgba(11,59,43,0.1)',
        },
      },
      h(
        'div',
        { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 } },
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 600, color: '#9B7210', letterSpacing: '0.18em' } }, 'LECTEUR D’ARTICLE · VUE LECTURE COMPLÈTE'),
          h('span', { style: { marginTop: 4, fontFamily: 'Fraunces', fontSize: 30, fontWeight: 700, color: '#062319' } }, 'Exemple d’article ouvert dans le Blog AESMG')
        ),
        h('span', { style: { padding: '8px 16px', borderRadius: 99, backgroundColor: '#062319', color: '#F7EBC6', fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 600 } }, 'PROGRESSION · FAVORIS · PARTAGE WHATSAPP')
      ),
      // Editorial Reader Sheet Card
      h(
        'div',
        {
          style: {
            display: 'flex',
            flex: 1,
            borderRadius: 28,
            overflow: 'hidden',
            backgroundColor: '#FFFFFF',
            border: '1px solid rgba(11,59,43,0.12)',
          },
        },
        // Left Column: Cover + Key Takeaways + Pull Quote
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', width: 520, padding: 30, backgroundColor: '#FAF8F3', borderRight: '1px solid rgba(11,59,43,0.08)', justifyContent: 'space-between' } },
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h('img', { src: IMG.blogAccra, width: 460, height: 240, style: { width: '100%', height: 240, borderRadius: 18, objectFit: 'cover' } }),
            // Key Takeaways Box
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', marginTop: 20, padding: 20, borderRadius: 18, backgroundColor: 'rgba(247,235,198,0.45)', border: '1px solid rgba(201,150,26,0.35)' } },
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 700, color: '#8C640B', letterSpacing: '0.16em' } }, 'À RETENIR EN 3 POINTS'),
              h('span', { style: { marginTop: 10, fontSize: 12.5, lineHeight: 1.48, color: '#173026' } }, '• Contacter le Bureau AESMG dès les premiers jours pour intégrer votre cercle de ville.'),
              h('span', { style: { marginTop: 6, fontSize: 12.5, lineHeight: 1.48, color: '#173026' } }, '• Sécuriser vos démarches d’inscription, votre hébergement et vos documents.'),
              h('span', { style: { marginTop: 6, fontSize: 12.5, lineHeight: 1.48, color: '#173026' } }, '• S’appuyer sur le parrainage entre anciens et nouveaux par filière.')
            ),
            // Pull Quote
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', marginTop: 18, padding: 22, borderRadius: 18, backgroundColor: '#062319', color: '#F7EBC6' } },
              h('span', { style: { fontFamily: 'Fraunces', fontStyle: 'italic', fontSize: 17, lineHeight: 1.4 } }, '« Arriver dans un nouveau pays devient une force dès lors que l’on peut compter sur une communauté fraternelle, structurée et bienveillante. »')
            )
          ),
          h(
            'div',
            { style: { display: 'flex', gap: 10, marginTop: 18 } },
            h('div', { style: { display: 'flex', flex: 1, justifyContent: 'center', padding: '12px', borderRadius: 12, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 12.5, fontWeight: 700 } }, 'Partager sur WhatsApp'),
            h('div', { style: { display: 'flex', padding: '12px 18px', borderRadius: 12, border: '1px solid rgba(11,59,43,0.16)', backgroundColor: '#FFFFFF', color: '#062319', fontSize: 12.5, fontWeight: 600 } }, 'Favori')
          )
        ),
        // Right Column: Full Article Editorial Text
        h(
          'div',
          { style: { display: 'flex', flex: 1, flexDirection: 'column', padding: '32px 38px', justifyContent: 'space-between' } },
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h(
              'div',
              { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
              h('span', { style: { padding: '5px 12px', borderRadius: 99, backgroundColor: '#062319', color: '#F7EBC6', fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 600 } }, 'GUIDES & GHANA · 5 MIN'),
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10.5, color: '#536B61' } }, 'Guide Officiel · Édition 2026')
            ),
            h('span', { style: { marginTop: 14, fontFamily: 'Fraunces', fontSize: 29, lineHeight: 1.18, fontWeight: 700, color: '#062319' } }, 'Réussir son installation au Ghana : les 7 repères essentiels pour les nouveaux étudiants et stagiaires maliens'),
            h('div', { style: { display: 'flex', gap: 8, marginTop: 12 } }, ...['#NouveauxArrivants', '#Accra', '#Kumasi', '#Logement'].map((tag) => h('span', { style: { padding: '4px 10px', borderRadius: 8, backgroundColor: 'rgba(11,59,43,0.07)', color: '#0B3B2B', fontSize: 11, fontWeight: 600 } }, tag))),
            h('span', { style: { marginTop: 22, fontFamily: 'Fraunces', fontSize: 20, fontWeight: 700, color: '#062319' } }, '1. Le premier réflexe : rejoindre la communauté dès l’arrivée'),
            h('span', { style: { marginTop: 8, fontSize: 14, lineHeight: 1.65, color: '#2B4339' } }, 'Que vous débarquiez à l’aéroport international de Kotoka à Accra ou que vous rejoigniez une université à Kumasi, Cape Coast ou Takoradi, l’isolement est le premier obstacle à éviter. En vous présentant au Bureau de l’AESMG, vous êtes immédiatement mis en relation avec des étudiants et stagiaires maliens déjà familiers de votre ville et de votre établissement.'),
            h('span', { style: { marginTop: 18, fontFamily: 'Fraunces', fontSize: 20, fontWeight: 700, color: '#062319' } }, '2. Logement, transport et repères du quotidien'),
            h('span', { style: { marginTop: 8, fontSize: 14, lineHeight: 1.65, color: '#2B4339' } }, 'Avant de signer un contrat de résidence étudiante (hostel) ou de colocation, prenez toujours le temps de visiter le quartier ou de demander conseil à un membre résidant sur place. Procurez-vous une carte SIM locale, configurez un portefeuille Mobile Money et conservez les numéros officiels du Bureau Exécutif : Diadié GUITTEYE (+233 535478560 / WhatsApp +223 91035026) et Georgette Jeanne MARGNA (+233 50 615 5639).'),
            h('span', { style: { marginTop: 18, fontFamily: 'Fraunces', fontSize: 20, fontWeight: 700, color: '#062319' } }, '3. Préparer sereinement son intégration académique'),
            h('span', { style: { marginTop: 8, fontSize: 14, lineHeight: 1.65, color: '#2B4339' } }, 'Le système universitaire ghanéen valorise fortement la ponctualité, la participation orale et les travaux de groupe. Sollicitez un parrain ou une marraine AESMG de votre filière pour découvrir les attentes des enseignants et les ressources disponibles.')
          ),
          // Reading progress hairline
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column', gap: 8, marginTop: 18, paddingTop: 14, borderTop: '1px solid rgba(11,59,43,0.1)' } },
            h(
              'div',
              { style: { display: 'flex', justifyContent: 'space-between', fontFamily: 'JetBrains Mono', fontSize: 10, color: '#536B61' } },
              h('span', {}, 'AUTEUR : BUREAU EXÉCUTIF AESMG'),
              h('span', { style: { color: '#0B3B2B', fontWeight: 600 } }, 'LECTURE · 100%')
            ),
            h('div', { style: { display: 'flex', width: '100%', height: 4, borderRadius: 99, backgroundColor: 'rgba(11,59,43,0.1)', overflow: 'hidden' } }, h('div', { style: { width: '78%', height: '100%', backgroundColor: '#E2B643' } }))
          )
        )
      )
    )
  )

// =========================================================================
// 2. BLOG MOBILE COMPLET (430 x 2680 @ 2x = 860px — ZERO IPHONE NOTCH!)
// =========================================================================
const BlogMobilePage = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 430,
        height: 2300,
        backgroundColor: '#FAF8F3',
        color: '#0E1E18',
        fontFamily: 'Plus Jakarta Sans',
        overflow: 'hidden',
      },
    },
    TricoloreBar(4),
    // Mobile App Bar (NO notch, NO fake iPhone header)
    h(
      'div',
      {
        style: {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '14px 18px',
          backgroundColor: '#062319',
          color: '#FAF8F3',
        },
      },
      h(
        'div',
        { style: { display: 'flex', alignItems: 'center', gap: 9 } },
        h('img', { src: aesmgSealSvg, width: 34, height: 34 }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h('span', { style: { fontFamily: 'Fraunces', fontSize: 15, fontWeight: 700 } }, 'Blog AESMG'),
          h('span', { style: { fontSize: 9.5, color: '#D3E5DC' } }, 'Le Journal de la communauté')
        )
      ),
      h('span', { style: { padding: '6px 12px', borderRadius: 99, backgroundColor: '#E2B643', color: '#062319', fontSize: 10.5, fontWeight: 700 } }, 'Favoris (2)')
    ),

    // Mobile Blog Catalogue
    h(
      'div',
      { style: { display: 'flex', flexDirection: 'column', padding: '22px 18px', gap: 14 } },
      h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 600, color: '#9B7210', letterSpacing: '0.16em' } }, 'LE JOURNAL · BLOG AESMG'),
      h('span', { style: { fontFamily: 'Fraunces', fontSize: 27, lineHeight: 1.12, fontWeight: 700, color: '#062319' } }, 'Récits, Guides & Vie au Ghana'),
      // Search input
      h(
        'div',
        { style: { display: 'flex', padding: '11px 14px', borderRadius: 14, border: '1px solid rgba(11,59,43,0.14)', backgroundColor: '#FFFFFF', fontSize: 12, color: '#7E948B' } },
        'Rechercher un guide, Accra, bilinguisme…'
      ),
      // Category Pills
      h(
        'div',
        { style: { display: 'flex', gap: 7 } },
        ...['Tous', 'Guides', 'Académique', 'Culture', 'Droits'].map((c, i) =>
          h(
            'span',
            {
              style: {
                padding: '7px 11px',
                borderRadius: 99,
                backgroundColor: i === 0 ? '#062319' : '#FFFFFF',
                border: i === 0 ? 'none' : '1px solid rgba(11,59,43,0.14)',
                color: i === 0 ? '#F7EBC6' : '#264237',
                fontSize: 10.5,
                fontWeight: 600,
              },
            },
            c
          )
        )
      ),
      // Featured Article Card
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', borderRadius: 22, overflow: 'hidden', backgroundColor: '#062319', color: '#FAF8F3' } },
        h('img', { src: IMG.blogAccra, width: 394, height: 180, style: { width: '100%', height: 180, objectFit: 'cover' } }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', padding: 18 } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 700, color: '#E2B643' } }, 'À LA UNE · GUIDES & GHANA · 5 MIN'),
          h('span', { style: { marginTop: 6, fontFamily: 'Fraunces', fontSize: 19, lineHeight: 1.2, fontWeight: 700 } }, 'Réussir son installation au Ghana : les 7 repères essentiels'),
          h('span', { style: { marginTop: 8, fontSize: 12, lineHeight: 1.48, color: '#D7E6DF' } }, 'Logement, campus à Accra & Kumasi, intégration linguistique et réseau fraternel AESMG.'),
          h(
            'div',
            { style: { display: 'flex', marginTop: 14, justifyContent: 'space-between', alignItems: 'center' } },
            h('span', { style: { fontSize: 10.5, color: '#B7D0C4', fontWeight: 600 } }, 'Bureau Exécutif AESMG'),
            h('span', { style: { padding: '7px 14px', borderRadius: 10, backgroundColor: '#E2B643', color: '#062319', fontSize: 11, fontWeight: 700 } }, 'Lire l’article ›')
          )
        )
      ),
      // 3 Article Cards
      ...BLOG_CARDS.map((b) =>
        h(
          'div',
          { style: { display: 'flex', padding: 12, gap: 12, borderRadius: 18, backgroundColor: '#FFFFFF', border: '1px solid rgba(11,59,43,0.12)', alignItems: 'center' } },
          h('img', { src: b.img, width: 88, height: 88, style: { width: 88, height: 88, borderRadius: 12, objectFit: 'cover' } }),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column', flex: 1 } },
            h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 8.5, fontWeight: 600, color: '#9B7210' } }, `${b.cat} · ${b.time}`),
            h('span', { style: { marginTop: 4, fontFamily: 'Fraunces', fontSize: 14, lineHeight: 1.22, fontWeight: 700, color: '#062319' } }, b.title),
            h('span', { style: { marginTop: 5, fontSize: 10.5, fontWeight: 700, color: '#0F513A' } }, 'Lire l’article ›')
          )
        )
      ),
      // Contribute Banner
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', padding: 16, borderRadius: 18, border: '1px solid rgba(201,150,26,0.4)', backgroundColor: 'rgba(247,235,198,0.42)', gap: 8 } },
        h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9, fontWeight: 700, color: '#8C640B' } }, 'TRIBUNE ÉTUDIANTE'),
        h('span', { style: { fontFamily: 'Fraunces', fontSize: 16, fontWeight: 700, color: '#062319' } }, 'Proposez votre témoignage ou guide de campus'),
        h('span', { style: { alignSelf: 'flex-start', padding: '9px 14px', borderRadius: 11, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 11, fontWeight: 700 } }, 'Proposer un article ›')
      )
    ),

    // Mobile Open Article Reader Preview
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          flex: 1,
          backgroundColor: '#F3EFE5',
          borderTop: '1px solid rgba(11,59,43,0.12)',
          padding: '22px 18px 18px',
          justifyContent: 'space-between',
        },
      },
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', gap: 12 } },
        h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 700, color: '#9B7210', letterSpacing: '0.16em' } }, 'LECTEUR MOBILE · ARTICLE OUVERT'),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', borderRadius: 22, overflow: 'hidden', backgroundColor: '#FFFFFF', border: '1px solid rgba(11,59,43,0.12)' } },
          TricoloreBar(3),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column', padding: 18, gap: 12 } },
            h(
              'div',
              { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
              h('span', { style: { padding: '4px 10px', borderRadius: 99, backgroundColor: '#062319', color: '#F7EBC6', fontFamily: 'JetBrains Mono', fontSize: 9, fontWeight: 600 } }, 'GUIDES & GHANA · 5 MIN'),
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, color: '#9B7210', fontWeight: 600 } }, 'LECTURE 78%')
            ),
            h('span', { style: { fontFamily: 'Fraunces', fontSize: 19, lineHeight: 1.2, fontWeight: 700, color: '#062319' } }, 'Réussir son installation au Ghana : les 7 repères essentiels'),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', padding: 13, borderRadius: 14, backgroundColor: 'rgba(247,235,198,0.45)', border: '1px solid rgba(201,150,26,0.32)', gap: 5 } },
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9, fontWeight: 700, color: '#8C640B' } }, 'À RETENIR EN 3 POINTS'),
              h('span', { style: { fontSize: 11, lineHeight: 1.4, color: '#173026' } }, '• Contacter le Bureau dès les premiers jours au Ghana.'),
              h('span', { style: { fontSize: 11, lineHeight: 1.4, color: '#173026' } }, '• Sécuriser logement, inscription et Mobile Money.'),
              h('span', { style: { fontSize: 11, lineHeight: 1.4, color: '#173026' } }, '• S’appuyer sur le parrainage entre étudiants maliens.')
            ),
            h(
              'div',
              { style: { display: 'flex', padding: 14, borderRadius: 14, backgroundColor: '#062319', color: '#F7EBC6' } },
              h('span', { style: { fontFamily: 'Fraunces', fontStyle: 'italic', fontSize: 13, lineHeight: 1.4 } }, '« Arriver dans un nouveau pays devient une force dès lors que l’on peut compter sur une communauté fraternelle. »')
            ),
            h('span', { style: { fontSize: 11.8, lineHeight: 1.55, color: '#2B4339' } }, 'Que vous arriviez à Accra, Kumasi ou Cape Coast, l’AESMG vous met immédiatement en lien avec les étudiants et stagiaires de votre ville et veille à votre intégration sereine.'),
            h(
              'div',
              { style: { display: 'flex', gap: 8, marginTop: 4 } },
              h('div', { style: { display: 'flex', flex: 1, justifyContent: 'center', padding: '11px', borderRadius: 12, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 11.5, fontWeight: 700 } }, 'Partager sur WhatsApp'),
              h('div', { style: { display: 'flex', padding: '11px 14px', borderRadius: 12, border: '1px solid rgba(11,59,43,0.16)', backgroundColor: '#FAF8F3', color: '#062319', fontSize: 11.5, fontWeight: 600 } }, 'Copier')
            )
          )
        ),
        // Related reading strip in mobile reader
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', gap: 8, marginTop: 4 } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9, fontWeight: 700, color: '#8C640B', letterSpacing: '0.14em' } }, 'CONTINUER LA LECTURE'),
          h(
            'div',
            { style: { display: 'flex', padding: 10, gap: 10, borderRadius: 16, backgroundColor: '#FFFFFF', border: '1px solid rgba(11,59,43,0.1)', alignItems: 'center' } },
            h('img', { src: IMG.blogCulture, width: 54, height: 54, style: { width: 54, height: 54, borderRadius: 10, objectFit: 'cover' } }),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', flex: 1 } },
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 8, fontWeight: 600, color: '#9B7210' } }, 'CULTURE & FRATERNITÉ · 4 MIN'),
              h('span', { style: { marginTop: 2, fontFamily: 'Fraunces', fontSize: 12.5, fontWeight: 700, color: '#062319' } }, 'Entre Bamako et Accra : faire rayonner la culture malienne')
            )
          )
        )
      ),
      // Floating Bottom Dock (Blog active!)
      h(
        'div',
        {
          style: {
            display: 'flex',
            marginTop: 16,
            padding: 6,
            borderRadius: 24,
            backgroundColor: '#062319',
            border: '1px solid rgba(226,182,67,0.38)',
            justifyContent: 'space-between',
          },
        },
        ...['Accueil', 'Objectifs', 'Blog', 'Bureau', 'Adhérer'].map((tab) => {
          const active = tab === 'Blog'
          return h(
            'div',
            {
              style: {
                display: 'flex',
                flex: 1,
                justifyContent: 'center',
                padding: '9px 4px',
                borderRadius: 18,
                backgroundColor: active ? '#E2B643' : 'transparent',
                color: active ? '#062319' : '#CBE0D5',
                fontSize: 10.5,
                fontWeight: active ? 700 : 600,
              },
            },
            tab
          )
        })
      )
    )
  )

// =========================================================================
// 3. COMBINED BLOG SHEET: PC + MOBILE SIDE BY SIDE (ZERO NOTCH/CHROME)
// =========================================================================
const CombinedBlogSheet = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 2040,
        height: 2500,
        backgroundColor: '#04160F',
        padding: '44px 56px 56px',
        fontFamily: 'Plus Jakarta Sans',
        gap: 32,
      },
    },
    h(
      'div',
      { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: 1928 } },
      h(
        'div',
        { style: { display: 'flex', alignItems: 'center', gap: 16 } },
        h('img', { src: aesmgSealSvg, width: 52, height: 52 }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 12, fontWeight: 600, color: '#E2B643', letterSpacing: '0.18em' } }, 'LE JOURNAL · BLOG AESMG — CATALOGUE & LECTEUR D’ARTICLE'),
          h('span', { style: { marginTop: 2, fontFamily: 'Fraunces', fontSize: 28, fontWeight: 700, color: '#FAF8F3' } }, 'Blog Officiel AESMG — Versions PC & Mobile')
        )
      ),
      h(
        'div',
        { style: { display: 'flex', gap: 10 } },
        h('span', { style: { padding: '8px 15px', borderRadius: 99, border: '1px solid rgba(226,182,67,0.45)', color: '#F7EBC6', fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 600 } }, 'BLOG PC'),
        h('span', { style: { padding: '8px 15px', borderRadius: 99, backgroundColor: '#E2B643', color: '#062319', fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 700 } }, 'BLOG MOBILE')
      )
    ),
    h(
      'div',
      { style: { display: 'flex', gap: 58, alignItems: 'flex-start' } },
      h(
        'div',
        { style: { display: 'flex', borderRadius: 18, overflow: 'hidden', boxShadow: '0 24px 60px rgba(0,0,0,0.45)' } },
        BlogDesktopPage()
      ),
      h(
        'div',
        { style: { display: 'flex', borderRadius: 18, overflow: 'hidden', boxShadow: '0 24px 60px rgba(0,0,0,0.45)' } },
        BlogMobilePage()
      )
    )
  )

async function renderPng(element, width, height, outputWidth, outputFilename) {
  const svg = await satori(element, { width, height, fonts })
  const resvg = new Resvg(svg, {
    fitTo: { mode: 'width', value: outputWidth },
  })
  const pngBuffer = resvg.render().asPng()
  const outPath = path.join(exportsDir, outputFilename)
  fs.writeFileSync(outPath, pngBuffer)
  console.log(`Generated ${outPath} (${outputWidth}px wide, ${Math.round(pngBuffer.length / 1024)} KB)`)
}

async function main() {
  await renderPng(BlogDesktopPage(), 1440, 2300, 1440, 'aesmg-blog-pc.png')
  await renderPng(BlogMobilePage(), 430, 2300, 860, 'aesmg-blog-mobile.png')
  await renderPng(CombinedBlogSheet(), 2040, 2500, 2040, 'aesmg-blog-pc-et-mobile.png')
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
