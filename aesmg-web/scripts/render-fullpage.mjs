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
  hero: loadImg('hero-accra-students.jpg'),
  blogAccra: loadImg('blog-accra-skyline.jpg'),
  blogCulture: loadImg('blog-culture-heritage.jpg'),
  academic: loadImg('academic-mentorship.jpg'),
  gala: loadImg('community-gala.jpg'),
  sports: loadImg('sports-solidarity.jpg'),
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

const OBJECTIVES = [
  {
    num: '01',
    tag: 'INCLUSION & RASSEMBLEMENT',
    title: 'Fédérer la communauté malienne au Ghana',
    desc: 'Rassembler tous les étudiants et stagiaires maliens vivant au Ghana, sans distinction de sexe, d’ethnie, de religion, d’opinion ou de classe sociale.',
    active: true,
  },
  {
    num: '02',
    tag: 'EXCELLENCE & CARRIÈRE',
    title: 'Œuvrer pour l’épanouissement des membres',
    desc: 'Créer un cadre propice au développement personnel, académique et professionnel des membres de l’association.',
    active: false,
  },
  {
    num: '03',
    tag: 'SOLIDARITÉ ACTIVE',
    title: 'Promouvoir l’unité et la fraternité',
    desc: 'Renforcer les liens de solidarité, d’amitié et de fraternité entre tous les étudiants et stagiaires maliens.',
    active: false,
  },
  {
    num: '04',
    tag: 'RAYONNEMENT & ÉNERGIE',
    title: 'Animer la vie communautaire',
    desc: 'Organiser des activités sociales, culturelles, éducatives et sportives pour stimuler la cohésion et le dynamisme de la communauté.',
    active: false,
  },
  {
    num: '05',
    tag: 'PROTECTION & PLAIDOYER',
    title: 'Défendre les droits et intérêts des membres',
    desc: 'Assurer la protection des droits moraux, académiques et sociaux des étudiants et stagiaires maliens au Ghana, en collaboration avec les autorités compétentes.',
    active: false,
  },
]

const COMMUNITY = [
  {
    cat: 'ÉDUCATIF',
    title: 'Mentorat, Études & Stages',
    desc: 'Sessions d’entraide universitaire, orientation dans les écoles ghanéennes et préparation aux stages.',
    img: IMG.academic,
    tags: ['Parrainage', 'Orientation', 'Stages'],
  },
  {
    cat: 'CULTUREL',
    title: 'Gala & Rayonnement Malien',
    desc: 'Célébration de notre patrimoine, soirées fraternelles et dialogue culturel Mali–Ghana.',
    img: IMG.gala,
    tags: ['Culture', 'Gastronomie', 'Fraternité'],
  },
  {
    cat: 'SOCIAL',
    title: 'Accueil & Fraternité de Campus',
    desc: 'Accompagnement dès les premiers jours au Ghana : repères pratiques et solidarité permanente.',
    img: IMG.hero,
    tags: ['Accueil', 'Entraide', 'Campus'],
  },
  {
    cat: 'SPORTIF',
    title: 'Sport, Énergie & Cohésion',
    desc: 'Tournois de football amicaux, journées sportives et rencontres collectives inter-villes.',
    img: IMG.sports,
    tags: ['Tournois', 'Équipe', 'Bien-être'],
  },
]

const BLOG_CARDS = [
  {
    cat: 'ACADÉMIQUE',
    time: '4 min',
    title: 'Bilinguisme français–anglais au Ghana : accélérateur de carrière',
    desc: 'Tirer parti de l’immersion anglophone, des clubs universitaires et des stages locaux en Afrique de l’Ouest.',
    author: 'Commission Éducation AESMG',
    img: IMG.academic,
  },
  {
    cat: 'CULTURE & FRATERNITÉ',
    time: '4 min',
    title: 'Entre Bamako et Accra : faire rayonner la culture malienne',
    desc: 'Bogolan, musique, gastronomie et solidarité : tisser des liens durables entre le Mali et le Ghana.',
    author: 'Pôle Culture & Social AESMG',
    img: IMG.blogCulture,
  },
  {
    cat: 'DROITS & VIE',
    time: '3 min',
    title: 'Droits moraux, académiques et sociaux : l’accompagnement AESMG',
    desc: 'Le rôle d’écoute, de médiation et de liaison du Bureau avec les autorités compétentes.',
    author: 'Présidence & Secrétariat Général',
    img: IMG.gala,
  },
]

// =========================================================================
// 1. PURE FULL-PAGE PC WEBSITE (1440 x 4420 — ZERO BROWSER/IPHONE CHROME!)
// =========================================================================
const FullPageDesktopSite = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 1440,
        height: 4040,
        backgroundColor: '#FAF8F3',
        color: '#0E1E18',
        fontFamily: 'Plus Jakarta Sans',
        overflow: 'hidden',
      },
    },
    TricoloreBar(4),
    // SECTION 1: HERO + CLEAN DESKTOP TOPBAR (NO "Vue Mobile", NO browser dots, NO iPhone header)
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          position: 'relative',
          width: 1440,
          height: 676,
          backgroundColor: '#062319',
          overflow: 'hidden',
        },
      },
      h('img', {
        src: IMG.hero,
        width: 1440,
        height: 676,
        style: { position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', objectFit: 'cover' },
      }),
      h('div', {
        style: {
          position: 'absolute',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background:
            'linear-gradient(100deg, rgba(6,35,25,0.95) 0%, rgba(6,35,25,0.75) 54%, rgba(6,35,25,0.38) 100%)',
        },
      }),
      // Clean Website Header Bar
      h(
        'div',
        {
          style: {
            display: 'flex',
            position: 'relative',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '22px 80px',
            borderBottom: '1px solid rgba(255,255,255,0.1)',
          },
        },
        h(
          'div',
          { style: { display: 'flex', alignItems: 'center', gap: 12 } },
          h('img', { src: aesmgSealSvg, width: 44, height: 44 }),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h(
              'div',
              { style: { display: 'flex', alignItems: 'center', gap: 8 } },
              h('span', { style: { fontFamily: 'Fraunces', fontSize: 19, fontWeight: 700, color: '#FAF8F3' } }, 'AESMG'),
              h(
                'span',
                {
                  style: {
                    padding: '2px 8px',
                    borderRadius: 99,
                    backgroundColor: 'rgba(226,182,67,0.22)',
                    color: '#F7EBC6',
                    fontFamily: 'JetBrains Mono',
                    fontSize: 10,
                    fontWeight: 600,
                  },
                },
                'ML · GH'
              )
            ),
            h('span', { style: { fontSize: 11.5, color: '#D3E5DC' } }, 'Étudiants & Stagiaires au Ghana')
          )
        ),
        h(
          'div',
          { style: { display: 'flex', alignItems: 'center', gap: 8 } },
          ...['Accueil', 'Objectifs', 'Vie', 'Blog', 'Bureau', 'Adhérer'].map((item, idx) =>
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  padding: '8px 16px',
                  borderRadius: 99,
                  backgroundColor: idx === 0 ? '#FAF8F3' : 'transparent',
                  color: idx === 0 ? '#062319' : '#EEF5F1',
                  fontSize: 13,
                  fontWeight: 600,
                },
              },
              item
            )
          )
        ),
        h(
          'div',
          { style: { display: 'flex', alignItems: 'center', gap: 12 } },
          h(
            'div',
            {
              style: {
                display: 'flex',
                alignItems: 'center',
                gap: 7,
                padding: '7px 14px',
                borderRadius: 99,
                border: '1px solid rgba(255,255,255,0.2)',
                backgroundColor: 'rgba(6,35,25,0.55)',
                color: '#F7EBC6',
                fontFamily: 'JetBrains Mono',
                fontSize: 11,
              },
            },
            h('div', { style: { width: 7, height: 7, borderRadius: 99, backgroundColor: '#22C55E' } }),
            'Accra · Bamako GMT'
          ),
          h(
            'div',
            {
              style: {
                display: 'flex',
                padding: '9px 18px',
                borderRadius: 99,
                backgroundColor: '#E2B643',
                color: '#062319',
                fontSize: 13,
                fontWeight: 700,
              },
            },
            'Contact'
          )
        )
      ),
      // Hero Content
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            position: 'relative',
            flex: 1,
            justifyContent: 'space-between',
            padding: '38px 80px 44px',
          },
        },
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', maxWidth: 820 } },
          h(
            'div',
            { style: { display: 'flex', gap: 10, alignItems: 'center' } },
            h(
              'span',
              {
                style: {
                  padding: '6px 14px',
                  borderRadius: 99,
                  border: '1px solid rgba(226,182,67,0.45)',
                  backgroundColor: 'rgba(6,35,25,0.72)',
                  color: '#F7EBC6',
                  fontFamily: 'JetBrains Mono',
                  fontSize: 11,
                  fontWeight: 600,
                },
              },
              'AW NI CE · AKWAABA'
            ),
            h(
              'span',
              {
                style: {
                  padding: '6px 14px',
                  borderRadius: 99,
                  border: '1px solid rgba(255,255,255,0.18)',
                  backgroundColor: 'rgba(255,255,255,0.1)',
                  color: '#FAF8F3',
                  fontSize: 11.5,
                  fontWeight: 500,
                },
              },
              'Accra · Kumasi · Cape Coast · Ghana'
            )
          ),
          h(
            'span',
            {
              style: {
                marginTop: 22,
                fontFamily: 'JetBrains Mono',
                fontSize: 12,
                fontWeight: 600,
                letterSpacing: '0.2em',
                color: '#E2B643',
              },
            },
            'UNITÉ · FRATERNITÉ · EXCELLENCE'
          ),
          h(
            'div',
            {
              style: {
                marginTop: 10,
                fontFamily: 'Fraunces',
                fontSize: 54,
                lineHeight: 1.07,
                fontWeight: 700,
                color: '#FAF8F3',
              },
            },
            'Association des Étudiants et Stagiaires Maliens au Ghana'
          ),
          h(
            'div',
            {
              style: {
                marginTop: 16,
                fontSize: 16.5,
                lineHeight: 1.55,
                color: '#E7EFEA',
                maxWidth: 660,
              },
            },
            'Une maison commune de fraternité, d’excellence académique et de solidarité active pour chaque étudiant et stagiaire malien vivant au Ghana — sans aucune distinction.'
          ),
          h(
            'div',
            { style: { display: 'flex', gap: 14, marginTop: 26 } },
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  padding: '15px 28px',
                  borderRadius: 16,
                  backgroundColor: '#E2B643',
                  color: '#062319',
                  fontSize: 14.5,
                  fontWeight: 700,
                },
              },
              'Rejoindre l’AESMG ›'
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  padding: '15px 28px',
                  borderRadius: 16,
                  border: '1px solid rgba(255,255,255,0.3)',
                  backgroundColor: 'rgba(6,35,25,0.62)',
                  color: '#FAF8F3',
                  fontSize: 14.5,
                  fontWeight: 600,
                },
              },
              'Contacter le Bureau'
            )
          )
        ),
        // Bottom 3 Glassmorphic Institutional Strip
        h(
          'div',
          { style: { display: 'flex', gap: 18, marginTop: 24 } },
          ...[
            ['Étudiants & Stagiaires', 'Tous campus universitaires du Ghana'],
            ['5 Piliers Fondateurs', 'Unité · Entraide · Culture · Sport · Droits'],
            ['Accompagnement', 'Défense des droits moraux, académiques et sociaux'],
          ].map(([title, sub]) =>
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  flex: 1,
                  flexDirection: 'column',
                  padding: '16px 20px',
                  borderRadius: 18,
                  border: '1px solid rgba(255,255,255,0.16)',
                  backgroundColor: 'rgba(6,35,25,0.74)',
                },
              },
              h('span', { style: { fontSize: 14, fontWeight: 700, color: '#FAF8F3' } }, title),
              h('span', { style: { marginTop: 3, fontSize: 12, color: '#CBE0D5' } }, sub)
            )
          )
        )
      )
    ),

    // SECTION 2: NOS BUTS & OBJECTIFS (ALL 5 VERBATIM)
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          padding: '64px 80px',
          backgroundColor: '#FAF8F3',
        },
      },
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center' } },
        h(
          'span',
          {
            style: {
              padding: '6px 16px',
              borderRadius: 99,
              border: '1px solid rgba(11,59,43,0.15)',
              backgroundColor: '#FFFFFF',
              fontFamily: 'JetBrains Mono',
              fontSize: 11,
              fontWeight: 600,
              letterSpacing: '0.16em',
              color: '#0B3B2B',
            },
          },
          'CHARTE FONDATRICE · 5 PILIERS'
        ),
        h(
          'span',
          {
            style: {
              marginTop: 14,
              fontFamily: 'Fraunces',
              fontSize: 38,
              fontWeight: 700,
              color: '#062319',
            },
          },
          'Nos Buts & Objectifs'
        ),
        h(
          'span',
          { style: { marginTop: 8, fontSize: 15, color: '#425A50', maxWidth: 680 } },
          'Chaque engagement de l’AESMG repose sur cinq missions essentielles au service de la dignité, de l’unité et de la réussite de la communauté malienne au Ghana.'
        )
      ),
      // Row 1 (01, 02)
      h(
        'div',
        { style: { display: 'flex', gap: 20, marginTop: 36 } },
        ...OBJECTIVES.slice(0, 2).map((o) =>
          h(
            'div',
            {
              style: {
                display: 'flex',
                flex: 1,
                flexDirection: 'column',
                padding: '26px 28px',
                borderRadius: 24,
                backgroundColor: o.active ? '#062319' : '#FFFFFF',
                border: o.active ? 'none' : '1px solid rgba(11,59,43,0.11)',
              },
            },
            h(
              'span',
              {
                style: {
                  fontFamily: 'JetBrains Mono',
                  fontSize: 10.5,
                  fontWeight: 600,
                  letterSpacing: '0.16em',
                  color: o.active ? '#E2B643' : '#9B7210',
                },
              },
              `OBJECTIF ${o.num} · ${o.tag}`
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 10,
                  fontFamily: 'Fraunces',
                  fontSize: 22,
                  fontWeight: 600,
                  color: o.active ? '#FAF8F3' : '#062319',
                },
              },
              o.title
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 8,
                  fontSize: 14,
                  lineHeight: 1.55,
                  color: o.active ? '#DCEBE3' : '#425A50',
                },
              },
              o.desc
            )
          )
        )
      ),
      // Row 2 (03, 04)
      h(
        'div',
        { style: { display: 'flex', gap: 20, marginTop: 20 } },
        ...OBJECTIVES.slice(2, 4).map((o) =>
          h(
            'div',
            {
              style: {
                display: 'flex',
                flex: 1,
                flexDirection: 'column',
                padding: '26px 28px',
                borderRadius: 24,
                backgroundColor: '#FFFFFF',
                border: '1px solid rgba(11,59,43,0.11)',
              },
            },
            h(
              'span',
              {
                style: {
                  fontFamily: 'JetBrains Mono',
                  fontSize: 10.5,
                  fontWeight: 600,
                  letterSpacing: '0.16em',
                  color: '#9B7210',
                },
              },
              `OBJECTIF ${o.num} · ${o.tag}`
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 10,
                  fontFamily: 'Fraunces',
                  fontSize: 22,
                  fontWeight: 600,
                  color: '#062319',
                },
              },
              o.title
            ),
            h(
              'span',
              { style: { marginTop: 8, fontSize: 14, lineHeight: 1.55, color: '#425A50' } },
              o.desc
            )
          )
        )
      ),
      // Row 3 (05 centered full accent card)
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            marginTop: 20,
            padding: '26px 32px',
            borderRadius: 24,
            backgroundColor: '#FFFFFF',
            border: '1px solid rgba(201,150,26,0.35)',
          },
        },
        h(
          'span',
          {
            style: {
              fontFamily: 'JetBrains Mono',
              fontSize: 10.5,
              fontWeight: 600,
              letterSpacing: '0.16em',
              color: '#9B7210',
            },
          },
          `OBJECTIF ${OBJECTIVES[4].num} · ${OBJECTIVES[4].tag}`
        ),
        h(
          'span',
          {
            style: {
              marginTop: 8,
              fontFamily: 'Fraunces',
              fontSize: 22,
              fontWeight: 600,
              color: '#062319',
            },
          },
          OBJECTIVES[4].title
        ),
        h(
          'span',
          { style: { marginTop: 6, fontSize: 14, lineHeight: 1.55, color: '#425A50' } },
          OBJECTIVES[4].desc
        )
      )
    ),

    // SECTION 3: VIE COMMUNAUTAIRE (4 PÔLES)
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          padding: '58px 80px',
          backgroundColor: '#F3EFE5',
        },
      },
      h(
        'div',
        { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end' } },
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 11,
                fontWeight: 600,
                letterSpacing: '0.18em',
                color: '#0B3B2B',
              },
            },
            'VIE COMMUNAUTAIRE'
          ),
          h(
            'span',
            {
              style: {
                marginTop: 8,
                fontFamily: 'Fraunces',
                fontSize: 36,
                fontWeight: 700,
                color: '#062319',
              },
            },
            'Une communauté vivante, créative & soudée'
          )
        ),
        h(
          'div',
          { style: { display: 'flex', gap: 8 } },
          ...['Tous', 'Éducatif', 'Culturel', 'Social', 'Sportif'].map((pill, i) =>
            h(
              'span',
              {
                style: {
                  padding: '8px 16px',
                  borderRadius: 99,
                  backgroundColor: i === 0 ? '#062319' : '#FAF8F3',
                  border: i === 0 ? 'none' : '1px solid rgba(11,59,43,0.14)',
                  color: i === 0 ? '#F7EBC6' : '#264237',
                  fontSize: 12,
                  fontWeight: 600,
                },
              },
              pill
            )
          )
        )
      ),
      h(
        'div',
        { style: { display: 'flex', gap: 20, marginTop: 30 } },
        ...COMMUNITY.map((c) =>
          h(
            'div',
            {
              style: {
                display: 'flex',
                flex: 1,
                flexDirection: 'column',
                borderRadius: 22,
                overflow: 'hidden',
                backgroundColor: '#FAF8F3',
                border: '1px solid rgba(11,59,43,0.1)',
              },
            },
            h(
              'div',
              { style: { display: 'flex', position: 'relative', height: 175, width: '100%' } },
              h('img', { src: c.img, width: 320, height: 175, style: { width: '100%', height: '100%', objectFit: 'cover' } }),
              h(
                'span',
                {
                  style: {
                    position: 'absolute',
                    top: 12,
                    left: 12,
                    padding: '4px 10px',
                    borderRadius: 99,
                    backgroundColor: 'rgba(6,35,25,0.82)',
                    color: '#F7EBC6',
                    fontFamily: 'JetBrains Mono',
                    fontSize: 9.5,
                    fontWeight: 600,
                  },
                },
                c.cat
              )
            ),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', padding: '18px 18px 20px', flex: 1, justifyContent: 'space-between' } },
              h(
                'div',
                { style: { display: 'flex', flexDirection: 'column' } },
                h('span', { style: { fontFamily: 'Fraunces', fontSize: 18, fontWeight: 700, color: '#062319' } }, c.title),
                h('span', { style: { marginTop: 8, fontSize: 12.5, lineHeight: 1.5, color: '#425A50' } }, c.desc)
              ),
              h(
                'div',
                { style: { display: 'flex', gap: 6, marginTop: 14, flexWrap: 'wrap' } },
                ...c.tags.map((t) =>
                  h(
                    'span',
                    {
                      style: {
                        padding: '4px 9px',
                        borderRadius: 8,
                        backgroundColor: 'rgba(11,59,43,0.07)',
                        color: '#0B3B2B',
                        fontSize: 10.5,
                        fontWeight: 600,
                      },
                    },
                    t
                  )
                )
              )
            )
          )
        )
      )
    ),

    // SECTION 4: LE JOURNAL · BLOG AESMG (FULL SECTION)
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          padding: '64px 80px',
          backgroundColor: '#FAF8F3',
        },
      },
      h(
        'div',
        { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end' } },
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 11,
                fontWeight: 600,
                letterSpacing: '0.18em',
                color: '#9B7210',
              },
            },
            'LE JOURNAL · BLOG AESMG'
          ),
          h(
            'span',
            {
              style: {
                marginTop: 8,
                fontFamily: 'Fraunces',
                fontSize: 38,
                fontWeight: 700,
                color: '#062319',
              },
            },
            'Récits, Guides & Vie au Ghana'
          )
        ),
        h(
          'div',
          { style: { display: 'flex', gap: 8 } },
          ...['Tous', 'Guides & Ghana', 'Académique', 'Culture & Fraternité', 'Droits & Vie'].map((pill, i) =>
            h(
              'span',
              {
                style: {
                  padding: '8px 15px',
                  borderRadius: 99,
                  backgroundColor: i === 0 ? '#062319' : '#FFFFFF',
                  border: i === 0 ? 'none' : '1px solid rgba(11,59,43,0.14)',
                  color: i === 0 ? '#F7EBC6' : '#264237',
                  fontSize: 11.5,
                  fontWeight: 600,
                },
              },
              pill
            )
          )
        )
      ),
      // Featured Blog Article Banner
      h(
        'div',
        {
          style: {
            display: 'flex',
            marginTop: 30,
            borderRadius: 26,
            overflow: 'hidden',
            backgroundColor: '#062319',
            height: 300,
          },
        },
        h('img', {
          src: IMG.blogAccra,
          width: 580,
          height: 300,
          style: { width: 580, height: 300, objectFit: 'cover' },
        }),
        h(
          'div',
          {
            style: {
              display: 'flex',
              flex: 1,
              flexDirection: 'column',
              justifyContent: 'space-between',
              padding: '30px 36px',
            },
          },
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h(
              'div',
              { style: { display: 'flex', gap: 10, alignItems: 'center' } },
              h(
                'span',
                {
                  style: {
                    padding: '5px 12px',
                    borderRadius: 99,
                    backgroundColor: '#E2B643',
                    color: '#062319',
                    fontFamily: 'JetBrains Mono',
                    fontSize: 10,
                    fontWeight: 700,
                  },
                },
                'À LA UNE'
              ),
              h(
                'span',
                {
                  style: {
                    fontFamily: 'JetBrains Mono',
                    fontSize: 11,
                    color: '#E2B643',
                  },
                },
                'GUIDES & GHANA · 5 MIN DE LECTURE'
              )
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 14,
                  fontFamily: 'Fraunces',
                  fontSize: 27,
                  lineHeight: 1.18,
                  fontWeight: 700,
                  color: '#FAF8F3',
                },
              },
              'Réussir son installation au Ghana : les 7 repères essentiels pour les nouveaux étudiants et stagiaires maliens'
            ),
            h(
              'span',
              { style: { marginTop: 12, fontSize: 14, lineHeight: 1.55, color: '#D7E6DF' } },
              'Dès votre arrivée à Accra, Kumasi ou Cape Coast, découvrez les réflexes concrets pour vous loger sereinement, vous repérer sur votre campus et rejoindre le réseau fraternel.'
            )
          ),
          h(
            'div',
            { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
            h('span', { style: { fontSize: 12.5, fontWeight: 600, color: '#B7D0C4' } }, 'Bureau Exécutif AESMG · Accueil & Orientation'),
            h(
              'span',
              {
                style: {
                  padding: '10px 20px',
                  borderRadius: 12,
                  backgroundColor: '#E2B643',
                  color: '#062319',
                  fontSize: 12.5,
                  fontWeight: 700,
                },
              },
              'Lire l’article ›'
            )
          )
        )
      ),
      // 3 Blog Cards Row
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
            h('img', { src: b.img, width: 420, height: 185, style: { width: '100%', height: 185, objectFit: 'cover' } }),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', padding: '18px 20px 20px', flex: 1, justifyContent: 'space-between' } },
              h(
                'div',
                { style: { display: 'flex', flexDirection: 'column' } },
                h(
                  'span',
                  { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 600, color: '#9B7210' } },
                  `${b.cat} · ${b.time}`
                ),
                h(
                  'span',
                  {
                    style: {
                      marginTop: 8,
                      fontFamily: 'Fraunces',
                      fontSize: 18.5,
                      lineHeight: 1.24,
                      fontWeight: 700,
                      color: '#062319',
                    },
                  },
                  b.title
                ),
                h('span', { style: { marginTop: 8, fontSize: 13, lineHeight: 1.5, color: '#465E54' } }, b.desc)
              ),
              h(
                'div',
                {
                  style: {
                    display: 'flex',
                    marginTop: 16,
                    paddingTop: 12,
                    borderTop: '1px solid rgba(11,59,43,0.08)',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                  },
                },
                h('span', { style: { fontSize: 11.5, fontWeight: 600, color: '#3C554B' } }, b.author),
                h('span', { style: { fontSize: 12, fontWeight: 700, color: '#062319' } }, 'Lire ›')
              )
            )
          )
        )
      )
    ),

    // SECTION 5: BUREAU EXÉCUTIF (DEEP EMERALD SOVEREIGN CARDS)
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          padding: '62px 80px',
          backgroundColor: '#062319',
          color: '#FAF8F3',
        },
      },
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center' } },
        h(
          'span',
          {
            style: {
              padding: '6px 16px',
              borderRadius: 99,
              border: '1px solid rgba(226,182,67,0.45)',
              backgroundColor: 'rgba(226,182,67,0.14)',
              fontFamily: 'JetBrains Mono',
              fontSize: 11,
              fontWeight: 600,
              letterSpacing: '0.18em',
              color: '#F7EBC6',
            },
          },
          'CONTACTS OFFICIELS · BUREAU EXÉCUTIF'
        ),
        h(
          'span',
          {
            style: {
              marginTop: 12,
              fontFamily: 'Fraunces',
              fontSize: 38,
              fontWeight: 700,
              color: '#FAF8F3',
            },
          },
          'À l’écoute de chaque membre'
        )
      ),
      h(
        'div',
        { style: { display: 'flex', gap: 28, marginTop: 34 } },
        // Diadié GUITTEYE
        h(
          'div',
          {
            style: {
              display: 'flex',
              flex: 1,
              flexDirection: 'column',
              padding: 30,
              borderRadius: 26,
              backgroundColor: '#0B3527',
              border: '1px solid rgba(226,182,67,0.38)',
              justifyContent: 'space-between',
            },
          },
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h(
              'div',
              { style: { display: 'flex', alignItems: 'center', gap: 16 } },
              h(
                'div',
                {
                  style: {
                    display: 'flex',
                    width: 64,
                    height: 64,
                    borderRadius: 18,
                    backgroundColor: '#062319',
                    border: '1.5px solid rgba(226,182,67,0.55)',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontFamily: 'Fraunces',
                    fontSize: 22,
                    fontWeight: 700,
                    color: '#F7EBC6',
                  },
                },
                'DG'
              ),
              h(
                'div',
                { style: { display: 'flex', flexDirection: 'column' } },
                h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 600, color: '#E2B643' } }, 'PRÉSIDENT · AESMG'),
                h('span', { style: { marginTop: 2, fontFamily: 'Fraunces', fontSize: 26, fontWeight: 700, color: '#FAF8F3' } }, 'Diadié GUITTEYE')
              )
            ),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', marginTop: 20, gap: 10 } },
              h(
                'div',
                {
                  style: {
                    display: 'flex',
                    justifyContent: 'space-between',
                    padding: '13px 18px',
                    borderRadius: 14,
                    backgroundColor: 'rgba(5,27,19,0.8)',
                  },
                },
                h('span', { style: { fontSize: 12.5, color: '#A8C4B6' } }, 'Contact (Ghana)'),
                h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 15.5, fontWeight: 600, color: '#FAF8F3' } }, '+233 535478560')
              ),
              h(
                'div',
                {
                  style: {
                    display: 'flex',
                    justifyContent: 'space-between',
                    padding: '13px 18px',
                    borderRadius: 14,
                    backgroundColor: 'rgba(37,211,102,0.12)',
                    border: '1px solid rgba(37,211,102,0.28)',
                  },
                },
                h('span', { style: { fontSize: 12.5, color: '#7EE8A6' } }, 'WhatsApp Officiel'),
                h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 15.5, fontWeight: 600, color: '#FAF8F3' } }, '+223 91035026')
              )
            )
          ),
          h(
            'div',
            { style: { display: 'flex', gap: 12, marginTop: 20 } },
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  flex: 1,
                  justifyContent: 'center',
                  padding: '13px',
                  borderRadius: 14,
                  backgroundColor: '#E2B643',
                  color: '#062319',
                  fontSize: 13,
                  fontWeight: 700,
                },
              },
              'Appeler'
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  flex: 1,
                  justifyContent: 'center',
                  padding: '13px',
                  borderRadius: 14,
                  backgroundColor: '#166534',
                  color: '#ECFDF3',
                  fontSize: 13,
                  fontWeight: 700,
                },
              },
              'WhatsApp'
            )
          )
        ),
        // Georgette Jeanne MARGNA
        h(
          'div',
          {
            style: {
              display: 'flex',
              flex: 1,
              flexDirection: 'column',
              padding: 30,
              borderRadius: 26,
              backgroundColor: '#0B3527',
              border: '1px solid rgba(226,182,67,0.34)',
              justifyContent: 'space-between',
            },
          },
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h(
              'div',
              { style: { display: 'flex', alignItems: 'center', gap: 16 } },
              h(
                'div',
                {
                  style: {
                    display: 'flex',
                    width: 64,
                    height: 64,
                    borderRadius: 18,
                    backgroundColor: '#062319',
                    border: '1.5px solid rgba(226,182,67,0.55)',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontFamily: 'Fraunces',
                    fontSize: 20,
                    fontWeight: 700,
                    color: '#F7EBC6',
                  },
                },
                'GJM'
              ),
              h(
                'div',
                { style: { display: 'flex', flexDirection: 'column' } },
                h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 600, color: '#E2B643' } }, 'SECRÉTAIRE GÉNÉRAL · AESMG'),
                h('span', { style: { marginTop: 2, fontFamily: 'Fraunces', fontSize: 26, fontWeight: 700, color: '#FAF8F3' } }, 'Georgette Jeanne MARGNA')
              )
            ),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', marginTop: 20, gap: 10 } },
              h(
                'div',
                {
                  style: {
                    display: 'flex',
                    justifyContent: 'space-between',
                    padding: '13px 18px',
                    borderRadius: 14,
                    backgroundColor: 'rgba(5,27,19,0.8)',
                  },
                },
                h('span', { style: { fontSize: 12.5, color: '#A8C4B6' } }, 'Contact (Ghana)'),
                h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 15.5, fontWeight: 600, color: '#FAF8F3' } }, '+233 50 615 5639')
              ),
              h(
                'div',
                {
                  style: {
                    display: 'flex',
                    justifyContent: 'space-between',
                    padding: '13px 18px',
                    borderRadius: 14,
                    backgroundColor: 'rgba(255,255,255,0.05)',
                  },
                },
                h('span', { style: { fontSize: 12.5, color: '#CBE0D5' } }, 'Administration, adhésions & suivi académique'),
                h('div', { style: { width: 9, height: 9, borderRadius: 99, backgroundColor: '#25D366', marginTop: 4 } })
              )
            )
          ),
          h(
            'div',
            { style: { display: 'flex', gap: 12, marginTop: 20 } },
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  flex: 1,
                  justifyContent: 'center',
                  padding: '13px',
                  borderRadius: 14,
                  backgroundColor: '#E2B643',
                  color: '#062319',
                  fontSize: 13,
                  fontWeight: 700,
                },
              },
              'Appeler'
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  flex: 1,
                  justifyContent: 'center',
                  padding: '13px',
                  borderRadius: 14,
                  backgroundColor: '#166534',
                  color: '#ECFDF3',
                  fontSize: 13,
                  fontWeight: 700,
                },
              },
              'WhatsApp'
            )
          )
        )
      )
    ),

    // SECTION 6: ADHÉSION & CARTE DIGITALE
    h(
      'div',
      {
        style: {
          display: 'flex',
          padding: '60px 80px',
          gap: 34,
          backgroundColor: '#FAF8F3',
          alignItems: 'center',
        },
      },
      // Digital Member Pass
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            width: 520,
            padding: 30,
            borderRadius: 28,
            background: 'linear-gradient(135deg, #0A3828 0%, #062319 60%, #041610 100%)',
            border: '1.5px solid rgba(226,182,67,0.48)',
            color: '#FAF8F3',
          },
        },
        h(
          'div',
          { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
          h(
            'div',
            { style: { display: 'flex', alignItems: 'center', gap: 12 } },
            h('img', { src: aesmgSealSvg, width: 44, height: 44 }),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column' } },
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, color: '#E2B643', letterSpacing: '0.18em' } }, 'CARTE COMMUNAUTAIRE'),
              h('span', { style: { fontFamily: 'Fraunces', fontSize: 18, fontWeight: 700 } }, 'AESMG · GHANA')
            )
          ),
          h(
            'span',
            {
              style: {
                padding: '5px 12px',
                borderRadius: 99,
                border: '1px solid rgba(226,182,67,0.45)',
                backgroundColor: 'rgba(226,182,67,0.16)',
                color: '#F7EBC6',
                fontFamily: 'JetBrains Mono',
                fontSize: 10,
                fontWeight: 600,
              },
            },
            'ÉTUDIANT'
          )
        ),
        h('span', { style: { marginTop: 28, fontFamily: 'JetBrains Mono', fontSize: 10, color: '#A8C6B8', letterSpacing: '0.16em' } }, 'MEMBRE DE LA COMMUNAUTÉ'),
        h('span', { style: { marginTop: 4, fontFamily: 'Fraunces', fontSize: 28, fontWeight: 700 } }, 'Aminata TRAORÉ'),
        h('span', { style: { marginTop: 8, fontSize: 13, color: '#D5E6DD' } }, 'Accra, Ghana  •  University of Ghana'),
        h('div', { style: { display: 'flex', marginTop: 24 } }, TricoloreBar(4)),
        h(
          'div',
          { style: { display: 'flex', justifyContent: 'space-between', marginTop: 12, fontFamily: 'JetBrains Mono', fontSize: 10, color: '#A8C6B8' } },
          h('span', {}, 'UNITÉ · FRATERNITÉ · EXCELLENCE'),
          h('span', { style: { color: '#E2B643' } }, 'MALI · GHANA')
        )
      ),
      // Registration Form Panel
      h(
        'div',
        {
          style: {
            display: 'flex',
            flex: 1,
            flexDirection: 'column',
            padding: 32,
            borderRadius: 28,
            backgroundColor: '#FFFFFF',
            border: '1px solid rgba(11,59,43,0.12)',
          },
        },
        h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10.5, fontWeight: 600, color: '#9B7210', letterSpacing: '0.18em' } }, 'ADHÉSION RAPIDE'),
        h('span', { style: { marginTop: 6, fontFamily: 'Fraunces', fontSize: 30, fontWeight: 700, color: '#062319' } }, 'Rejoignez la famille AESMG'),
        h('span', { style: { marginTop: 6, fontSize: 13.5, color: '#425A50' } }, 'Renseignez vos informations et transmettez votre demande d’adhésion directement sur WhatsApp au Bureau Exécutif.'),
        h(
          'div',
          { style: { display: 'flex', gap: 14, marginTop: 20 } },
          h(
            'div',
            { style: { display: 'flex', flex: 1, padding: '13px 16px', borderRadius: 14, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.14)', fontSize: 13, color: '#062319' } },
            'Aminata TRAORÉ'
          ),
          h(
            'div',
            { style: { display: 'flex', flex: 1, padding: '13px 16px', borderRadius: 14, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.14)', fontSize: 13, color: '#062319' } },
            'Accra · University of Ghana'
          )
        ),
        h(
          'div',
          {
            style: {
              display: 'flex',
              marginTop: 18,
              justifyContent: 'center',
              padding: '15px',
              borderRadius: 16,
              backgroundColor: '#062319',
              color: '#F7EBC6',
              fontSize: 14,
              fontWeight: 700,
            },
          },
          'Envoyer ma demande d’adhésion via WhatsApp'
        )
      )
    ),

    // SECTION 7: FOOTER
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          flex: 1,
          backgroundColor: '#051B13',
          color: '#FAF8F3',
        },
      },
      TricoloreBar(4),
      h(
        'div',
        {
          style: {
            display: 'flex',
            flex: 1,
            padding: '38px 80px',
            justifyContent: 'space-between',
            alignItems: 'center',
          },
        },
        h(
          'div',
          { style: { display: 'flex', alignItems: 'center', gap: 14 } },
          h('img', { src: aesmgSealSvg, width: 46, height: 46 }),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h('span', { style: { fontFamily: 'Fraunces', fontSize: 20, fontWeight: 700 } }, 'AESMG'),
            h('span', { style: { fontSize: 12.5, color: '#C4DAD0' } }, 'Association des Étudiants et Stagiaires Maliens au Ghana')
          )
        ),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 4 } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 11.5, color: '#E2B643' } }, 'MALI · GHANA · FRATERNITÉ & EXCELLENCE'),
          h('span', { style: { fontSize: 12, color: '#9BB7AB' } }, '© 2026 AESMG — Président : Diadié GUITTEYE · Secrétaire général : Georgette Jeanne MARGNA')
        )
      )
    )
  )

// =========================================================================
// 2. PURE FULL-PAGE MOBILE WEBSITE (430 logical x 4520 — ZERO IPHONE NOTCH!)
// =========================================================================
const FullPageMobileSite = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 430,
        height: 4040,
        backgroundColor: '#FAF8F3',
        color: '#0E1E18',
        fontFamily: 'Plus Jakarta Sans',
        overflow: 'hidden',
        position: 'relative',
      },
    },
    TricoloreBar(4),
    // MOBILE HERO (Edge-to-Edge, ZERO iPhone notch/status bar!)
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          position: 'relative',
          width: 430,
          height: 580,
          backgroundColor: '#062319',
          overflow: 'hidden',
        },
      },
      h('img', {
        src: IMG.hero,
        width: 430,
        height: 580,
        style: { position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', objectFit: 'cover' },
      }),
      h('div', {
        style: {
          position: 'absolute',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background:
            'linear-gradient(180deg, rgba(6,35,25,0.88) 0%, rgba(6,35,25,0.58) 45%, rgba(6,35,25,0.98) 100%)',
        },
      }),
      // Mobile App Header
      h(
        'div',
        {
          style: {
            display: 'flex',
            position: 'relative',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '16px 20px',
          },
        },
        h(
          'div',
          { style: { display: 'flex', alignItems: 'center', gap: 10 } },
          h('img', { src: aesmgSealSvg, width: 38, height: 38 }),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h('span', { style: { fontFamily: 'Fraunces', fontSize: 16, fontWeight: 700, color: '#FAF8F3' } }, 'AESMG'),
            h('span', { style: { fontSize: 10, color: '#D3E5DC' } }, 'Étudiants & Stagiaires au Ghana')
          )
        ),
        h(
          'div',
          {
            style: {
              display: 'flex',
              padding: '7px 14px',
              borderRadius: 99,
              backgroundColor: '#E2B643',
              color: '#062319',
              fontSize: 11.5,
              fontWeight: 700,
            },
          },
          'Contact'
        )
      ),
      // Hero Content
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            position: 'relative',
            padding: '20px 22px 26px',
            marginTop: 'auto',
          },
        },
        h(
          'span',
          {
            style: {
              alignSelf: 'flex-start',
              padding: '5px 12px',
              borderRadius: 99,
              border: '1px solid rgba(226,182,67,0.45)',
              backgroundColor: 'rgba(6,35,25,0.72)',
              color: '#F7EBC6',
              fontFamily: 'JetBrains Mono',
              fontSize: 10,
              fontWeight: 600,
            },
          },
          'AW NI CE · AKWAABA · GHANA'
        ),
        h(
          'span',
          {
            style: {
              marginTop: 14,
              fontFamily: 'JetBrains Mono',
              fontSize: 10.5,
              fontWeight: 600,
              letterSpacing: '0.18em',
              color: '#E2B643',
            },
          },
          'UNITÉ · FRATERNITÉ · EXCELLENCE'
        ),
        h(
          'div',
          {
            style: {
              marginTop: 8,
              fontFamily: 'Fraunces',
              fontSize: 32,
              lineHeight: 1.1,
              fontWeight: 700,
              color: '#FAF8F3',
            },
          },
          'Association des Étudiants et Stagiaires Maliens au Ghana'
        ),
        h(
          'span',
          { style: { marginTop: 10, fontSize: 13.5, lineHeight: 1.48, color: '#E2EFE8' } },
          'Une maison commune de fraternité, d’excellence académique et de solidarité active pour chaque étudiant et stagiaire malien vivant au Ghana.'
        ),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', gap: 10, marginTop: 20 } },
          h(
            'div',
            {
              style: {
                display: 'flex',
                justifyContent: 'center',
                padding: '14px',
                borderRadius: 15,
                backgroundColor: '#E2B643',
                color: '#062319',
                fontSize: 13.5,
                fontWeight: 700,
              },
            },
            'Rejoindre l’AESMG ›'
          ),
          h(
            'div',
            {
              style: {
                display: 'flex',
                justifyContent: 'center',
                padding: '13px',
                borderRadius: 15,
                border: '1px solid rgba(255,255,255,0.28)',
                backgroundColor: 'rgba(6,35,25,0.62)',
                color: '#FAF8F3',
                fontSize: 13.5,
                fontWeight: 600,
              },
            },
            'Contacter le Bureau'
          )
        )
      )
    ),

    // MOBILE SECTION 2: ALL 5 BUTS & OBJECTIFS
    h(
      'div',
      { style: { display: 'flex', flexDirection: 'column', padding: '32px 20px', gap: 12, backgroundColor: '#FAF8F3' } },
      h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 600, color: '#9B7210', letterSpacing: '0.16em' } }, 'CHARTE FONDATRICE · 5 PILIERS'),
      h('span', { style: { fontFamily: 'Fraunces', fontSize: 28, fontWeight: 700, color: '#062319' } }, 'Nos Buts & Objectifs'),
      ...OBJECTIVES.map((o) =>
        h(
          'div',
          {
            style: {
              display: 'flex',
              flexDirection: 'column',
              padding: '16px 18px',
              borderRadius: 20,
              backgroundColor: o.active ? '#062319' : '#FFFFFF',
              border: o.active ? 'none' : '1px solid rgba(11,59,43,0.12)',
            },
          },
          h(
            'span',
            { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 600, color: o.active ? '#E2B643' : '#9B7210' } },
            `OBJECTIF ${o.num} · ${o.tag}`
          ),
          h(
            'span',
            { style: { marginTop: 6, fontFamily: 'Fraunces', fontSize: 17, fontWeight: 600, color: o.active ? '#FAF8F3' : '#062319' } },
            o.title
          ),
          h(
            'span',
            { style: { marginTop: 5, fontSize: 12, lineHeight: 1.48, color: o.active ? '#DCEBE3' : '#425A50' } },
            o.desc
          )
        )
      )
    ),

    // MOBILE SECTION 3: VIE COMMUNAUTAIRE (2x2 COMPACT GRID)
    h(
      'div',
      { style: { display: 'flex', flexDirection: 'column', padding: '30px 20px', gap: 14, backgroundColor: '#F3EFE5' } },
      h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 600, color: '#0B3B2B', letterSpacing: '0.16em' } }, 'VIE COMMUNAUTAIRE'),
      h('span', { style: { fontFamily: 'Fraunces', fontSize: 26, fontWeight: 700, color: '#062319' } }, 'Social, Culturel, Éducatif & Sportif'),
      h(
        'div',
        { style: { display: 'flex', gap: 12 } },
        ...COMMUNITY.slice(0, 2).map((c) =>
          h(
            'div',
            { style: { display: 'flex', flex: 1, flexDirection: 'column', borderRadius: 18, overflow: 'hidden', backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.1)' } },
            h('img', { src: c.img, width: 190, height: 112, style: { width: '100%', height: 112, objectFit: 'cover' } }),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', padding: 12 } },
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 8.5, fontWeight: 600, color: '#9B7210' } }, c.cat),
              h('span', { style: { marginTop: 3, fontFamily: 'Fraunces', fontSize: 14, fontWeight: 700, color: '#062319' } }, c.title)
            )
          )
        )
      ),
      h(
        'div',
        { style: { display: 'flex', gap: 12 } },
        ...COMMUNITY.slice(2, 4).map((c) =>
          h(
            'div',
            { style: { display: 'flex', flex: 1, flexDirection: 'column', borderRadius: 18, overflow: 'hidden', backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.1)' } },
            h('img', { src: c.img, width: 190, height: 112, style: { width: '100%', height: 112, objectFit: 'cover' } }),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', padding: 12 } },
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 8.5, fontWeight: 600, color: '#9B7210' } }, c.cat),
              h('span', { style: { marginTop: 3, fontFamily: 'Fraunces', fontSize: 14, fontWeight: 700, color: '#062319' } }, c.title)
            )
          )
        )
      )
    ),

    // MOBILE SECTION 4: LE JOURNAL · BLOG AESMG
    h(
      'div',
      { style: { display: 'flex', flexDirection: 'column', padding: '32px 20px', gap: 14, backgroundColor: '#FAF8F3' } },
      h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 600, color: '#9B7210', letterSpacing: '0.16em' } }, 'LE JOURNAL · BLOG AESMG'),
      h('span', { style: { fontFamily: 'Fraunces', fontSize: 27, fontWeight: 700, color: '#062319' } }, 'Récits, Guides & Vie au Ghana'),
      // Featured Blog Article
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', borderRadius: 22, overflow: 'hidden', backgroundColor: '#062319', color: '#FAF8F3' } },
        h('img', { src: IMG.blogAccra, width: 390, height: 185, style: { width: '100%', height: 185, objectFit: 'cover' } }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', padding: 18 } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 700, color: '#E2B643' } }, 'À LA UNE · GUIDES & GHANA · 5 MIN'),
          h('span', { style: { marginTop: 6, fontFamily: 'Fraunces', fontSize: 19, lineHeight: 1.2, fontWeight: 700 } }, 'Réussir son installation au Ghana : les 7 repères essentiels'),
          h('span', { style: { marginTop: 8, fontSize: 12, lineHeight: 1.48, color: '#D7E6DF' } }, 'Logement, campus à Accra & Kumasi, intégration et accueil fraternel au sein de l’AESMG.'),
          h(
            'div',
            { style: { display: 'flex', marginTop: 14, justifyContent: 'space-between', alignItems: 'center' } },
            h('span', { style: { fontSize: 11, color: '#B7D0C4', fontWeight: 600 } }, 'Bureau Exécutif AESMG'),
            h('span', { style: { padding: '7px 14px', borderRadius: 10, backgroundColor: '#E2B643', color: '#062319', fontSize: 11, fontWeight: 700 } }, 'Lire l’article ›')
          )
        )
      ),
      // 3 Compact Blog Article Rows
      ...BLOG_CARDS.map((b) =>
        h(
          'div',
          {
            style: {
              display: 'flex',
              padding: 12,
              gap: 12,
              borderRadius: 18,
              backgroundColor: '#FFFFFF',
              border: '1px solid rgba(11,59,43,0.12)',
              alignItems: 'center',
            },
          },
          h('img', { src: b.img, width: 88, height: 88, style: { width: 88, height: 88, borderRadius: 12, objectFit: 'cover' } }),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column', flex: 1 } },
            h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 8.5, fontWeight: 600, color: '#9B7210' } }, `${b.cat} · ${b.time}`),
            h('span', { style: { marginTop: 4, fontFamily: 'Fraunces', fontSize: 14.5, lineHeight: 1.22, fontWeight: 700, color: '#062319' } }, b.title),
            h('span', { style: { marginTop: 5, fontSize: 10.5, fontWeight: 700, color: '#0F513A' } }, 'Lire ›')
          )
        )
      )
    ),

    // MOBILE SECTION 5: BUREAU EXÉCUTIF
    h(
      'div',
      { style: { display: 'flex', flexDirection: 'column', padding: '34px 20px', gap: 14, backgroundColor: '#062319', color: '#FAF8F3' } },
      h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 600, color: '#E2B643', letterSpacing: '0.16em' } }, 'CONTACTS OFFICIELS · BUREAU'),
      h('span', { style: { fontFamily: 'Fraunces', fontSize: 27, fontWeight: 700 } }, 'Le Bureau Exécutif'),
      // Président
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', padding: 18, borderRadius: 22, backgroundColor: '#0B3527', border: '1px solid rgba(226,182,67,0.4)' } },
        h(
          'div',
          { style: { display: 'flex', alignItems: 'center', gap: 12 } },
          h('div', { style: { display: 'flex', width: 48, height: 48, borderRadius: 14, backgroundColor: '#062319', border: '1px solid rgba(226,182,67,0.55)', alignItems: 'center', justifyContent: 'center', fontFamily: 'Fraunces', fontSize: 17, fontWeight: 700, color: '#F7EBC6' } }, 'DG'),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 600, color: '#E2B643' } }, 'PRÉSIDENT'),
            h('span', { style: { fontFamily: 'Fraunces', fontSize: 19, fontWeight: 700 } }, 'Diadié GUITTEYE')
          )
        ),
        h(
          'div',
          { style: { display: 'flex', justifyContent: 'space-between', marginTop: 12, padding: '10px 12px', borderRadius: 12, backgroundColor: 'rgba(5,27,19,0.82)' } },
          h('span', { style: { fontSize: 11, color: '#A8C4B6' } }, 'Contact'),
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 13, fontWeight: 600 } }, '+233 535478560')
        ),
        h(
          'div',
          { style: { display: 'flex', justifyContent: 'space-between', marginTop: 8, padding: '10px 12px', borderRadius: 12, backgroundColor: 'rgba(37,211,102,0.14)', border: '1px solid rgba(37,211,102,0.28)' } },
          h('span', { style: { fontSize: 11, color: '#86EFAC' } }, 'WhatsApp'),
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 13, fontWeight: 600 } }, '+223 91035026')
        ),
        h(
          'div',
          { style: { display: 'flex', gap: 10, marginTop: 12 } },
          h('div', { style: { display: 'flex', flex: 1, justifyContent: 'center', padding: '11px', borderRadius: 12, backgroundColor: '#E2B643', color: '#062319', fontSize: 12, fontWeight: 700 } }, 'Appeler'),
          h('div', { style: { display: 'flex', flex: 1, justifyContent: 'center', padding: '11px', borderRadius: 12, backgroundColor: '#166534', color: '#ECFDF3', fontSize: 12, fontWeight: 700 } }, 'WhatsApp')
        )
      ),
      // Secrétaire Général
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', padding: 18, borderRadius: 22, backgroundColor: '#0B3527', border: '1px solid rgba(226,182,67,0.34)' } },
        h(
          'div',
          { style: { display: 'flex', alignItems: 'center', gap: 12 } },
          h('div', { style: { display: 'flex', width: 48, height: 48, borderRadius: 14, backgroundColor: '#062319', border: '1px solid rgba(226,182,67,0.55)', alignItems: 'center', justifyContent: 'center', fontFamily: 'Fraunces', fontSize: 15, fontWeight: 700, color: '#F7EBC6' } }, 'GJM'),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 600, color: '#E2B643' } }, 'SECRÉTAIRE GÉNÉRAL'),
            h('span', { style: { fontFamily: 'Fraunces', fontSize: 18, fontWeight: 700 } }, 'Georgette Jeanne MARGNA')
          )
        ),
        h(
          'div',
          { style: { display: 'flex', justifyContent: 'space-between', marginTop: 12, padding: '10px 12px', borderRadius: 12, backgroundColor: 'rgba(5,27,19,0.82)' } },
          h('span', { style: { fontSize: 11, color: '#A8C4B6' } }, 'Contact'),
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 13, fontWeight: 600 } }, '+233 50 615 5639')
        ),
        h(
          'div',
          { style: { display: 'flex', gap: 10, marginTop: 12 } },
          h('div', { style: { display: 'flex', flex: 1, justifyContent: 'center', padding: '11px', borderRadius: 12, backgroundColor: '#E2B643', color: '#062319', fontSize: 12, fontWeight: 700 } }, 'Appeler'),
          h('div', { style: { display: 'flex', flex: 1, justifyContent: 'center', padding: '11px', borderRadius: 12, backgroundColor: '#166534', color: '#ECFDF3', fontSize: 12, fontWeight: 700 } }, 'WhatsApp')
        )
      )
    ),

    // MOBILE SECTION 6: CARTE COMMUNAUTAIRE + ADHÉSION
    h(
      'div',
      { style: { display: 'flex', flexDirection: 'column', padding: '32px 20px', gap: 14, backgroundColor: '#FAF8F3' } },
      h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 600, color: '#9B7210', letterSpacing: '0.16em' } }, 'ADHÉSION & PASS DIGITAL'),
      h('span', { style: { fontFamily: 'Fraunces', fontSize: 26, fontWeight: 700, color: '#062319' } }, 'Rejoignez la famille AESMG'),
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            padding: 20,
            borderRadius: 22,
            background: 'linear-gradient(135deg, #0A3828 0%, #062319 65%, #041610 100%)',
            border: '1px solid rgba(226,182,67,0.48)',
            color: '#FAF8F3',
          },
        },
        h(
          'div',
          { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
          h(
            'div',
            { style: { display: 'flex', alignItems: 'center', gap: 10 } },
            h('img', { src: aesmgSealSvg, width: 36, height: 36 }),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column' } },
              h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9, color: '#E2B643' } }, 'CARTE COMMUNAUTAIRE'),
              h('span', { style: { fontFamily: 'Fraunces', fontSize: 15, fontWeight: 700 } }, 'AESMG · GHANA')
            )
          ),
          h('span', { style: { padding: '4px 10px', borderRadius: 99, backgroundColor: 'rgba(226,182,67,0.18)', color: '#F7EBC6', fontFamily: 'JetBrains Mono', fontSize: 9 } }, 'ÉTUDIANT')
        ),
        h('span', { style: { marginTop: 18, fontFamily: 'Fraunces', fontSize: 21, fontWeight: 700 } }, 'Aminata TRAORÉ'),
        h('span', { style: { marginTop: 4, fontSize: 11.5, color: '#D5E6DD' } }, 'Accra, Ghana  •  University of Ghana'),
        h('div', { style: { display: 'flex', marginTop: 14 } }, TricoloreBar(3))
      ),
      h(
        'div',
        { style: { display: 'flex', justifyContent: 'center', padding: '14px', borderRadius: 15, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 12.5, fontWeight: 700 } },
        'Envoyer ma demande via WhatsApp'
      )
    ),

    // MOBILE FOOTER + FLOATING BOTTOM DOCK
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          flex: 1,
          backgroundColor: '#051B13',
          color: '#FAF8F3',
          justifyContent: 'space-between',
        },
      },
      TricoloreBar(3),
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', padding: '24px 20px 16px', alignItems: 'center', textAlign: 'center', gap: 6 } },
        h('span', { style: { fontFamily: 'Fraunces', fontSize: 18, fontWeight: 700 } }, 'AESMG'),
        h('span', { style: { fontSize: 11, color: '#C4DAD0' } }, 'Association des Étudiants et Stagiaires Maliens au Ghana'),
        h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, color: '#E2B643' } }, '© 2026 AESMG · MALI · GHANA')
      ),
      // Floating Bottom Dock inside mobile page
      h(
        'div',
        {
          style: {
            display: 'flex',
            margin: '0 14px 18px',
            padding: 6,
            borderRadius: 24,
            backgroundColor: 'rgba(11, 53, 39, 0.96)',
            border: '1px solid rgba(226,182,67,0.35)',
            justifyContent: 'space-between',
          },
        },
        ...['Accueil', 'Objectifs', 'Blog', 'Bureau', 'Adhérer'].map((tab, i) =>
          h(
            'div',
            {
              style: {
                display: 'flex',
                flex: 1,
                justifyContent: 'center',
                padding: '9px 4px',
                borderRadius: 18,
                backgroundColor: i === 0 ? '#E2B643' : 'transparent',
                color: i === 0 ? '#062319' : '#CBE0D5',
                fontSize: 10.5,
                fontWeight: i === 0 ? 700 : 600,
              },
            },
            tab
          )
        )
      )
    )
  )

// =========================================================================
// 3. CLEAN COMBINED FULL-PAGE SHEET: PC COMPLET + MOBILE COMPLET SIDE BY SIDE
//    (ZERO iPhone notch, ZERO browser chrome, pure full-page comparison!)
// =========================================================================
const CombinedCompleteSheet = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 2040,
        height: 4240,
        backgroundColor: '#04160F',
        padding: '44px 56px 56px',
        fontFamily: 'Plus Jakarta Sans',
        gap: 32,
      },
    },
    // Minimal Header Caption
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
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 12, fontWeight: 600, color: '#E2B643', letterSpacing: '0.18em' } }, 'SITE COMPLET · PC (1440PX) & MOBILE (430PX)'),
          h('span', { style: { marginTop: 2, fontFamily: 'Fraunces', fontSize: 28, fontWeight: 700, color: '#FAF8F3' } }, 'Association des Étudiants et Stagiaires Maliens au Ghana (AESMG)')
        )
      ),
      h(
        'div',
        { style: { display: 'flex', gap: 10, shrink: 0 } },
        h('span', { style: { padding: '8px 15px', borderRadius: 99, border: '1px solid rgba(226,182,67,0.45)', color: '#F7EBC6', fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 600 } }, 'PC COMPLET'),
        h('span', { style: { padding: '8px 15px', borderRadius: 99, backgroundColor: '#E2B643', color: '#062319', fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 700 } }, 'MOBILE COMPLET')
      )
    ),
    // Side-by-side Full Pages (Zero Notch, Zero Browser Header!)
    h(
      'div',
      { style: { display: 'flex', gap: 58, alignItems: 'flex-start' } },
      h(
        'div',
        { style: { display: 'flex', borderRadius: 18, overflow: 'hidden', boxShadow: '0 24px 60px rgba(0,0,0,0.45)' } },
        FullPageDesktopSite()
      ),
      h(
        'div',
        { style: { display: 'flex', borderRadius: 18, overflow: 'hidden', boxShadow: '0 24px 60px rgba(0,0,0,0.45)' } },
        FullPageMobileSite()
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
  await renderPng(FullPageDesktopSite(), 1440, 4040, 1440, 'aesmg-site-complet-pc.png')
  await renderPng(FullPageMobileSite(), 430, 4040, 860, 'aesmg-site-complet-mobile.png')
  await renderPng(CombinedCompleteSheet(), 2040, 4240, 2040, 'aesmg-complet-pc-et-mobile.png')
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
