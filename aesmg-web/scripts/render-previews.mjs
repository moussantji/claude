import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import satori from 'satori'
import { Resvg } from '@resvg/resvg-js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const rootDir = path.resolve(__dirname, '..')
const exportsDir = path.join(rootDir, 'exports')
fs.mkdirSync(exportsDir, { recursive: true })

// Helper to create satori virtual DOM nodes cleanly without JSX build step
const h = (type, props, ...children) => ({
  type,
  props: {
    ...(props || {}),
    children: children.length === 0 ? undefined : children.length === 1 ? children[0] : children,
  },
})

// Load bundled fonts (.woff)
const readFont = (relPath) => fs.readFileSync(path.join(rootDir, 'node_modules', relPath))

const fonts = [
  {
    name: 'Fraunces',
    data: readFont('@fontsource/fraunces/files/fraunces-latin-600-normal.woff'),
    weight: 600,
    style: 'normal',
  },
  {
    name: 'Fraunces',
    data: readFont('@fontsource/fraunces/files/fraunces-latin-700-normal.woff'),
    weight: 700,
    style: 'normal',
  },
  {
    name: 'Fraunces',
    data: readFont('@fontsource/fraunces/files/fraunces-latin-600-italic.woff'),
    weight: 600,
    style: 'italic',
  },
  {
    name: 'Plus Jakarta Sans',
    data: readFont('@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-400-normal.woff'),
    weight: 400,
    style: 'normal',
  },
  {
    name: 'Plus Jakarta Sans',
    data: readFont('@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-500-normal.woff'),
    weight: 500,
    style: 'normal',
  },
  {
    name: 'Plus Jakarta Sans',
    data: readFont('@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-600-normal.woff'),
    weight: 600,
    style: 'normal',
  },
  {
    name: 'Plus Jakarta Sans',
    data: readFont('@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-700-normal.woff'),
    weight: 700,
    style: 'normal',
  },
  {
    name: 'JetBrains Mono',
    data: readFont('@fontsource/jetbrains-mono/files/jetbrains-mono-latin-500-normal.woff'),
    weight: 500,
    style: 'normal',
  },
  {
    name: 'JetBrains Mono',
    data: readFont('@fontsource/jetbrains-mono/files/jetbrains-mono-latin-600-normal.woff'),
    weight: 600,
    style: 'normal',
  },
]

// Load our bespoke editorial photography as base64 data URIs
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

// Reusable AESMG Emblem SVG as data URI for crisp rendering
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

// Floating Mobile Bottom Dock
const MobileDock = (activeTab) => {
  const tabs = ['Accueil', 'Objectifs', 'Blog', 'Bureau', 'Adhérer']
  return h(
    'div',
    {
      style: {
        display: 'flex',
        position: 'absolute',
        left: 12,
        right: 12,
        bottom: 12,
        padding: '6px',
        borderRadius: 22,
        backgroundColor: 'rgba(6, 35, 25, 0.94)',
        border: '1px solid rgba(226, 182, 67, 0.35)',
        boxShadow: '0 10px 26px rgba(0,0,0,0.38)',
        justifyContent: 'space-between',
        alignItems: 'center',
      },
    },
    ...tabs.map((label) => {
      const active = label === activeTab
      return h(
        'div',
        {
          style: {
            display: 'flex',
            flex: 1,
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '7px 2px',
            borderRadius: 16,
            backgroundColor: active ? '#E2B643' : 'transparent',
            color: active ? '#062319' : '#CBE0D5',
            fontSize: 9.5,
            fontWeight: active ? 700 : 600,
          },
        },
        label
      )
    })
  )
}

// Reusable Titanium Smartphone Shell
const PhoneBezel = ({ width = 340, height = 700, activeTab, topBg = '#062319', children }) =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        width,
        height,
        borderRadius: 46,
        padding: 10,
        backgroundColor: '#0D1512',
        border: '2.5px solid rgba(212, 184, 122, 0.55)',
        boxShadow: '0 32px 72px rgba(0, 0, 0, 0.55)',
      },
    },
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          position: 'relative',
          width: '100%',
          height: '100%',
          borderRadius: 36,
          overflow: 'hidden',
          backgroundColor: '#FAF8F3',
        },
      },
      // Realistic Status Bar + Dynamic Island
      h(
        'div',
        {
          style: {
            display: 'flex',
            height: 36,
            width: '100%',
            backgroundColor: topBg,
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '0 22px',
            position: 'relative',
          },
        },
        h(
          'span',
          {
            style: {
              fontFamily: 'JetBrains Mono',
              fontSize: 10.5,
              fontWeight: 600,
              color: '#FAF8F3',
            },
          },
          '09:41'
        ),
        h('div', {
          style: {
            position: 'absolute',
            left: '50%',
            top: 8,
            transform: 'translateX(-50%)',
            width: 82,
            height: 20,
            borderRadius: 99,
            backgroundColor: '#040807',
          },
        }),
        h(
          'span',
          {
            style: {
              fontFamily: 'JetBrains Mono',
              fontSize: 9.5,
              fontWeight: 600,
              color: '#E2B643',
            },
          },
          '5G'
        )
      ),
      TricoloreBar(3),
      // Screen Content
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            flex: 1,
            width: '100%',
            overflow: 'hidden',
            position: 'relative',
          },
        },
        ...children,
        MobileDock(activeTab)
      )
    )
  )

// PHONE SCREEN 1: Hero + Buts & Objectifs
const MobileScreenHome = (opts = {}) =>
  PhoneBezel({
    width: opts.width || 344,
    height: opts.height || 710,
    activeTab: 'Accueil',
    children: [
      // Hero Top Section with real photography
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            position: 'relative',
            width: '100%',
            height: 376,
            backgroundColor: '#062319',
            overflow: 'hidden',
          },
        },
        h('img', {
          src: IMG.hero,
          width: 344,
          height: 376,
          style: {
            position: 'absolute',
            top: 0,
            left: 0,
            width: '100%',
            height: '100%',
            objectFit: 'cover',
          },
        }),
        h('div', {
          style: {
            position: 'absolute',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            background:
              'linear-gradient(180deg, rgba(6,35,25,0.88) 0%, rgba(6,35,25,0.55) 45%, rgba(6,35,25,0.97) 100%)',
          },
        }),
        // Top Navbar inside hero
        h(
          'div',
          {
            style: {
              display: 'flex',
              position: 'relative',
              alignItems: 'center',
              justifyContent: 'space-between',
              padding: '12px 16px 8px',
            },
          },
          h(
            'div',
            { style: { display: 'flex', alignItems: 'center', gap: 8 } },
            h('img', { src: aesmgSealSvg, width: 30, height: 30 }),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column' } },
              h(
                'span',
                {
                  style: {
                    fontFamily: 'Fraunces',
                    fontSize: 14,
                    fontWeight: 700,
                    color: '#FAF8F3',
                  },
                },
                'AESMG'
              ),
              h(
                'span',
                { style: { fontSize: 8.5, color: '#D3E5DC' } },
                'Étudiants & Stagiaires'
              )
            )
          ),
          h(
            'div',
            {
              style: {
                display: 'flex',
                padding: '5px 11px',
                borderRadius: 99,
                backgroundColor: '#E2B643',
                color: '#062319',
                fontSize: 10,
                fontWeight: 700,
              },
            },
            'Contact'
          )
        ),
        // Hero Text Block
        h(
          'div',
          {
            style: {
              display: 'flex',
              flexDirection: 'column',
              position: 'relative',
              padding: '14px 18px 16px',
              marginTop: 'auto',
            },
          },
          h(
            'div',
            {
              style: {
                display: 'flex',
                alignSelf: 'flex-start',
                padding: '4px 10px',
                borderRadius: 99,
                backgroundColor: 'rgba(6,35,25,0.72)',
                border: '1px solid rgba(226,182,67,0.45)',
                color: '#F7EBC6',
                fontFamily: 'JetBrains Mono',
                fontSize: 8.5,
                fontWeight: 600,
                letterSpacing: '0.08em',
              },
            },
            'AW NI CE · AKWAABA · GHANA'
          ),
          h(
            'div',
            {
              style: {
                marginTop: 10,
                fontFamily: 'Fraunces',
                fontSize: 23,
                lineHeight: 1.12,
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
                marginTop: 8,
                fontSize: 10.5,
                lineHeight: 1.45,
                color: '#E2EFE8',
              },
            },
            'Unité · Fraternité · Excellence au service de la communauté malienne.'
          ),
          // Dual Mobile CTAs
          h(
            'div',
            { style: { display: 'flex', gap: 8, marginTop: 14 } },
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  flex: 1,
                  justifyContent: 'center',
                  alignItems: 'center',
                  padding: '10px 10px',
                  borderRadius: 14,
                  backgroundColor: '#E2B643',
                  color: '#062319',
                  fontSize: 10.5,
                  fontWeight: 700,
                },
              },
              'Rejoindre l’AESMG'
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  flex: 1,
                  justifyContent: 'center',
                  alignItems: 'center',
                  padding: '10px 10px',
                  borderRadius: 14,
                  backgroundColor: 'rgba(255,255,255,0.12)',
                  border: '1px solid rgba(255,255,255,0.28)',
                  color: '#FAF8F3',
                  fontSize: 10.5,
                  fontWeight: 600,
                },
              },
              'Appeler le Bureau'
            )
          )
        )
      ),
      // Warm Ivory Objectives Preview Sheet below Hero
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            flex: 1,
            backgroundColor: '#FAF8F3',
            padding: '14px 16px',
            gap: 9,
          },
        },
        h(
          'div',
          { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'Fraunces',
                fontSize: 16,
                fontWeight: 700,
                color: '#062319',
              },
            },
            'Nos Buts & Objectifs'
          ),
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 8.5,
                fontWeight: 600,
                color: '#9B7210',
              },
            },
            '5 PILIERS'
          )
        ),
        // Active Objective 01
        h(
          'div',
          {
            style: {
              display: 'flex',
              flexDirection: 'column',
              padding: '11px 13px',
              borderRadius: 16,
              backgroundColor: '#062319',
              color: '#FAF8F3',
            },
          },
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 8,
                fontWeight: 600,
                color: '#E2B643',
              },
            },
            'OBJECTIF 01 · INCLUSION'
          ),
          h(
            'span',
            {
              style: {
                marginTop: 3,
                fontFamily: 'Fraunces',
                fontSize: 12.5,
                fontWeight: 600,
                color: '#FAF8F3',
              },
            },
            'Fédérer la communauté malienne au Ghana'
          ),
          h(
            'span',
            { style: { marginTop: 4, fontSize: 9.5, lineHeight: 1.38, color: '#D4E5DD' } },
            'Rassembler tous les étudiants et stagiaires maliens vivant au Ghana, sans aucune distinction.'
          )
        ),
        // Objective 02 compact card
        h(
          'div',
          {
            style: {
              display: 'flex',
              flexDirection: 'column',
              padding: '10px 13px',
              borderRadius: 16,
              backgroundColor: '#FFFFFF',
              border: '1px solid rgba(11,59,43,0.12)',
            },
          },
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 8,
                fontWeight: 600,
                color: '#9B7210',
              },
            },
            'OBJECTIF 02 · EXCELLENCE'
          ),
          h(
            'span',
            {
              style: {
                marginTop: 2,
                fontFamily: 'Fraunces',
                fontSize: 12,
                fontWeight: 600,
                color: '#062319',
              },
            },
            'Œuvrer pour l’épanouissement des membres'
          )
        )
      ),
    ],
  })

// PHONE SCREEN 2: Le Journal · Blog AESMG
const MobileScreenBlog = (opts = {}) =>
  PhoneBezel({
    width: opts.width || 356,
    height: opts.height || 734,
    activeTab: 'Blog',
    topBg: '#062319',
    children: [
      // Mini Emerald Header
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            padding: '14px 16px 12px',
            backgroundColor: '#FAF8F3',
            borderBottom: '1px solid rgba(11,59,43,0.08)',
          },
        },
        h(
          'span',
          {
            style: {
              fontFamily: 'JetBrains Mono',
              fontSize: 8.5,
              fontWeight: 600,
              color: '#9B7210',
              letterSpacing: '0.14em',
            },
          },
          'LE JOURNAL · BLOG AESMG'
        ),
        h(
          'span',
          {
            style: {
              marginTop: 3,
              fontFamily: 'Fraunces',
              fontSize: 20,
              fontWeight: 700,
              color: '#062319',
            },
          },
          'Récits, Guides & Vie au Ghana'
        ),
        // Category chips
        h(
          'div',
          { style: { display: 'flex', gap: 6, marginTop: 10 } },
          ...['Tous', 'Guides', 'Académique', 'Culture'].map((cat, idx) =>
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  padding: '5px 10px',
                  borderRadius: 99,
                  backgroundColor: idx === 0 ? '#062319' : '#FFFFFF',
                  border: idx === 0 ? 'none' : '1px solid rgba(11,59,43,0.14)',
                  color: idx === 0 ? '#F7EBC6' : '#264237',
                  fontSize: 9.5,
                  fontWeight: 600,
                },
              },
              cat
            )
          )
        )
      ),
      // Blog Cards Container
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            flex: 1,
            padding: '12px 16px',
            gap: 11,
            backgroundColor: '#FAF8F3',
          },
        },
        // Featured Blog Card ("À la une")
        h(
          'div',
          {
            style: {
              display: 'flex',
              flexDirection: 'column',
              borderRadius: 20,
              overflow: 'hidden',
              backgroundColor: '#062319',
              boxShadow: '0 14px 30px rgba(6,35,25,0.18)',
            },
          },
          h(
            'div',
            {
              style: {
                display: 'flex',
                position: 'relative',
                width: '100%',
                height: 152,
                overflow: 'hidden',
              },
            },
            h('img', {
              src: IMG.blogAccra,
              width: 320,
              height: 152,
              style: { width: '100%', height: '100%', objectFit: 'cover' },
            }),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  position: 'absolute',
                  top: 10,
                  left: 10,
                  padding: '4px 9px',
                  borderRadius: 99,
                  backgroundColor: '#E2B643',
                  color: '#062319',
                  fontFamily: 'JetBrains Mono',
                  fontSize: 8.5,
                  fontWeight: 700,
                },
              },
              'À LA UNE · 5 MIN'
            )
          ),
          h(
            'div',
            {
              style: {
                display: 'flex',
                flexDirection: 'column',
                padding: '12px 14px 14px',
              },
            },
            h(
              'span',
              {
                style: {
                  fontFamily: 'Fraunces',
                  fontSize: 14.5,
                  lineHeight: 1.24,
                  fontWeight: 600,
                  color: '#FAF8F3',
                },
              },
              'Réussir son installation au Ghana : les 7 repères essentiels'
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 5,
                  fontSize: 9.8,
                  lineHeight: 1.4,
                  color: '#CFE2D9',
                },
              },
              'Logement, campus à Accra & Kumasi, intégration et accueil fraternel.'
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  marginTop: 10,
                  justifyContent: 'space-between',
                  alignItems: 'center',
                },
              },
              h(
                'span',
                { style: { fontSize: 9, fontWeight: 600, color: '#E2B643' } },
                'Bureau Exécutif AESMG'
              ),
              h(
                'span',
                {
                  style: {
                    padding: '5px 10px',
                    borderRadius: 10,
                    backgroundColor: '#E2B643',
                    color: '#062319',
                    fontSize: 9.5,
                    fontWeight: 700,
                  },
                },
                'Lire l’article'
              )
            )
          )
        ),
        // Second Blog Article Card (Culture & Bogolan)
        h(
          'div',
          {
            style: {
              display: 'flex',
              borderRadius: 18,
              overflow: 'hidden',
              backgroundColor: '#FFFFFF',
              border: '1px solid rgba(11,59,43,0.12)',
              padding: 10,
              gap: 11,
              alignItems: 'center',
            },
          },
          h('img', {
            src: IMG.blogCulture,
            width: 82,
            height: 82,
            style: { width: 82, height: 82, borderRadius: 12, objectFit: 'cover' },
          }),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column', flex: 1 } },
            h(
              'span',
              {
                style: {
                  fontFamily: 'JetBrains Mono',
                  fontSize: 8,
                  fontWeight: 600,
                  color: '#9B7210',
                },
              },
              'CULTURE & FRATERNITÉ · 4 MIN'
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 3,
                  fontFamily: 'Fraunces',
                  fontSize: 12.5,
                  lineHeight: 1.24,
                  fontWeight: 600,
                  color: '#062319',
                },
              },
              'Entre Bamako et Accra : faire rayonner la culture malienne'
            ),
            h(
              'span',
              { style: { marginTop: 5, fontSize: 9.5, fontWeight: 700, color: '#0F513A' } },
              'Lire l’article ›'
            )
          )
        ),
        // Third Blog Article Card (Academic)
        h(
          'div',
          {
            style: {
              display: 'flex',
              borderRadius: 18,
              overflow: 'hidden',
              backgroundColor: '#FFFFFF',
              border: '1px solid rgba(11,59,43,0.12)',
              padding: 10,
              gap: 11,
              alignItems: 'center',
            },
          },
          h('img', {
            src: IMG.academic,
            width: 82,
            height: 76,
            style: { width: 82, height: 76, borderRadius: 12, objectFit: 'cover' },
          }),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column', flex: 1 } },
            h(
              'span',
              {
                style: {
                  fontFamily: 'JetBrains Mono',
                  fontSize: 8,
                  fontWeight: 600,
                  color: '#9B7210',
                },
              },
              'ACADÉMIQUE · 4 MIN'
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 3,
                  fontFamily: 'Fraunces',
                  fontSize: 12.5,
                  lineHeight: 1.24,
                  fontWeight: 600,
                  color: '#062319',
                },
              },
              'Bilinguisme FR–EN au Ghana : accélérateur de carrière'
            )
          )
        )
      ),
    ],
  })

// PHONE SCREEN 3: Bureau Exécutif + Carte Digitale
const MobileScreenBureau = (opts = {}) =>
  PhoneBezel({
    width: opts.width || 344,
    height: opts.height || 710,
    activeTab: 'Bureau',
    children: [
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            flex: 1,
            backgroundColor: '#062319',
            padding: '16px 15px',
            gap: 11,
          },
        },
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 8.5,
                fontWeight: 600,
                color: '#E2B643',
                letterSpacing: '0.15em',
              },
            },
            'CONTACTS OFFICIELS · BUREAU'
          ),
          h(
            'span',
            {
              style: {
                marginTop: 3,
                fontFamily: 'Fraunces',
                fontSize: 20,
                fontWeight: 700,
                color: '#FAF8F3',
              },
            },
            'Le Bureau Exécutif'
          )
        ),
        // President Card
        h(
          'div',
          {
            style: {
              display: 'flex',
              flexDirection: 'column',
              padding: 13,
              borderRadius: 20,
              backgroundColor: '#0B3527',
              border: '1px solid rgba(226,182,67,0.38)',
            },
          },
          h(
            'div',
            { style: { display: 'flex', alignItems: 'center', gap: 10 } },
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  width: 42,
                  height: 42,
                  borderRadius: 12,
                  backgroundColor: '#062319',
                  border: '1px solid rgba(226,182,67,0.55)',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontFamily: 'Fraunces',
                  fontSize: 15,
                  fontWeight: 700,
                  color: '#F7EBC6',
                },
              },
              'DG'
            ),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column' } },
              h(
                'span',
                {
                  style: {
                    fontFamily: 'JetBrains Mono',
                    fontSize: 8,
                    fontWeight: 600,
                    color: '#E2B643',
                  },
                },
                'PRÉSIDENT'
              ),
              h(
                'span',
                {
                  style: {
                    fontFamily: 'Fraunces',
                    fontSize: 15.5,
                    fontWeight: 700,
                    color: '#FAF8F3',
                  },
                },
                'Diadié GUITTEYE'
              )
            )
          ),
          h(
            'div',
            {
              style: {
                display: 'flex',
                flexDirection: 'column',
                marginTop: 10,
                gap: 6,
              },
            },
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  justifyContent: 'space-between',
                  padding: '7px 10px',
                  borderRadius: 11,
                  backgroundColor: 'rgba(5,27,19,0.8)',
                },
              },
              h('span', { style: { fontSize: 9, color: '#A9C5B7' } }, 'Contact'),
              h(
                'span',
                {
                  style: {
                    fontFamily: 'JetBrains Mono',
                    fontSize: 10.5,
                    fontWeight: 600,
                    color: '#FAF8F3',
                  },
                },
                '+233 535478560'
              )
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  justifyContent: 'space-between',
                  padding: '7px 10px',
                  borderRadius: 11,
                  backgroundColor: 'rgba(37,211,102,0.12)',
                  border: '1px solid rgba(37,211,102,0.28)',
                },
              },
              h('span', { style: { fontSize: 9, color: '#86EFAC' } }, 'WhatsApp'),
              h(
                'span',
                {
                  style: {
                    fontFamily: 'JetBrains Mono',
                    fontSize: 10.5,
                    fontWeight: 600,
                    color: '#FAF8F3',
                  },
                },
                '+223 91035026'
              )
            )
          ),
          h(
            'div',
            { style: { display: 'flex', gap: 8, marginTop: 10 } },
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  flex: 1,
                  justifyContent: 'center',
                  padding: '8px',
                  borderRadius: 12,
                  backgroundColor: '#E2B643',
                  color: '#062319',
                  fontSize: 10,
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
                  padding: '8px',
                  borderRadius: 12,
                  backgroundColor: '#166534',
                  color: '#ECFDF3',
                  fontSize: 10,
                  fontWeight: 700,
                },
              },
              'WhatsApp'
            )
          )
        ),
        // Secretary General Card
        h(
          'div',
          {
            style: {
              display: 'flex',
              flexDirection: 'column',
              padding: 13,
              borderRadius: 20,
              backgroundColor: '#0B3527',
              border: '1px solid rgba(226,182,67,0.32)',
            },
          },
          h(
            'div',
            { style: { display: 'flex', alignItems: 'center', gap: 10 } },
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  width: 42,
                  height: 42,
                  borderRadius: 12,
                  backgroundColor: '#062319',
                  border: '1px solid rgba(226,182,67,0.55)',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontFamily: 'Fraunces',
                  fontSize: 14,
                  fontWeight: 700,
                  color: '#F7EBC6',
                },
              },
              'GJM'
            ),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column' } },
              h(
                'span',
                {
                  style: {
                    fontFamily: 'JetBrains Mono',
                    fontSize: 8,
                    fontWeight: 600,
                    color: '#E2B643',
                  },
                },
                'SECRÉTAIRE GÉNÉRAL'
              ),
              h(
                'span',
                {
                  style: {
                    fontFamily: 'Fraunces',
                    fontSize: 14.5,
                    fontWeight: 700,
                    color: '#FAF8F3',
                  },
                },
                'Georgette Jeanne MARGNA'
              )
            )
          ),
          h(
            'div',
            {
              style: {
                display: 'flex',
                justifyContent: 'space-between',
                marginTop: 10,
                padding: '7px 10px',
                borderRadius: 11,
                backgroundColor: 'rgba(5,27,19,0.8)',
              },
            },
            h('span', { style: { fontSize: 9, color: '#A9C5B7' } }, 'Contact'),
            h(
              'span',
              {
                style: {
                  fontFamily: 'JetBrains Mono',
                  fontSize: 10.5,
                  fontWeight: 600,
                  color: '#FAF8F3',
                },
              },
              '+233 50 615 5639'
            )
          )
        ),
        // Digital Member Pass Mini Banner
        h(
          'div',
          {
            style: {
              display: 'flex',
              flexDirection: 'column',
              padding: '12px 14px',
              borderRadius: 18,
              background: 'linear-gradient(135deg, #134E3A 0%, #051A12 100%)',
              border: '1px solid rgba(226,182,67,0.5)',
            },
          },
          h(
            'div',
            { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
            h(
              'span',
              {
                style: {
                  fontFamily: 'JetBrains Mono',
                  fontSize: 8.5,
                  fontWeight: 600,
                  color: '#E2B643',
                },
              },
              'CARTE COMMUNAUTAIRE DIGITALE'
            ),
            h('img', { src: aesmgSealSvg, width: 24, height: 24 })
          ),
          h(
            'span',
            {
              style: {
                marginTop: 4,
                fontFamily: 'Fraunces',
                fontSize: 14,
                fontWeight: 600,
                color: '#FAF8F3',
              },
            },
            'Adhésion directe via WhatsApp'
          ),
          h('div', { style: { display: 'flex', marginTop: 8 } }, TricoloreBar(3))
        )
      ),
    ],
  })

// =========================================================================
// IMAGE 1: EXMPLAIRE TÉLÉPHONE (3-Phone Luxury Mobile Studio PNG - 1440x1020)
// =========================================================================
const TelephoneShowcase = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 1440,
        height: 1020,
        background: 'linear-gradient(145deg, #062319 0%, #041710 55%, #08291E 100%)',
        position: 'relative',
        overflow: 'hidden',
        fontFamily: 'Plus Jakarta Sans',
      },
    },
    TricoloreBar(5),
    // Soft ambient studio gold glow
    h('div', {
      style: {
        position: 'absolute',
        top: -120,
        left: 420,
        width: 600,
        height: 600,
        borderRadius: 999,
        background: 'radial-gradient(circle, rgba(226,182,67,0.18) 0%, rgba(6,35,25,0) 70%)',
      },
    }),
    // Top Studio Header
    h(
      'div',
      {
        style: {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '34px 64px 18px',
          position: 'relative',
        },
      },
      h(
        'div',
        { style: { display: 'flex', alignItems: 'center', gap: 16 } },
        h('img', { src: aesmgSealSvg, width: 54, height: 54 }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 11.5,
                fontWeight: 600,
                color: '#E2B643',
                letterSpacing: '0.18em',
              },
            },
            'EXEMPLAIRE MOBILE · SITE WEB REACT'
          ),
          h(
            'span',
            {
              style: {
                marginTop: 2,
                fontFamily: 'Fraunces',
                fontSize: 28,
                fontWeight: 700,
                color: '#FAF8F3',
              },
            },
            'Association des Étudiants et Stagiaires Maliens au Ghana (AESMG)'
          )
        )
      ),
      h(
        'div',
        {
          style: {
            display: 'flex',
            padding: '9px 18px',
            borderRadius: 99,
            border: '1px solid rgba(226,182,67,0.45)',
            backgroundColor: 'rgba(226,182,67,0.14)',
            color: '#F7EBC6',
            fontFamily: 'JetBrains Mono',
            fontSize: 12,
            fontWeight: 600,
          },
        },
        'VERSION TÉLÉPHONE'
      )
    ),
    // 3 Phones Row
    h(
      'div',
      {
        style: {
          display: 'flex',
          flex: 1,
          alignItems: 'center',
          justifyContent: 'center',
          gap: 38,
          padding: '0 48px 30px',
          position: 'relative',
        },
      },
      MobileScreenHome({ width: 356, height: 730 }),
      MobileScreenBlog({ width: 374, height: 766 }),
      MobileScreenBureau({ width: 356, height: 730 })
    ),
    // Bottom Studio Caption Bar
    h(
      'div',
      {
        style: {
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: '0 64px 28px',
          color: '#B6D0C4',
          fontSize: 12.5,
        },
      },
      h(
        'span',
        { style: { fontFamily: 'JetBrains Mono', color: '#E2B643', fontWeight: 600 } },
        'UNITÉ · FRATERNITÉ · EXCELLENCE'
      ),
      h(
        'span',
        {},
        'Accueil & 5 Objectifs   •   Le Journal · Blog AESMG   •   Bureau Exécutif & Adhésion WhatsApp'
      )
    )
  )

// =========================================================================
// REUSABLE PC BROWSER WINDOW COMPONENT
// =========================================================================
const DesktopBrowserMockup = ({ width = 1520, height = 910 }) =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width,
        height,
        borderRadius: 24,
        overflow: 'hidden',
        backgroundColor: '#FAF8F3',
        border: '2px solid rgba(226,182,67,0.42)',
        boxShadow: '0 36px 85px rgba(0,0,0,0.52)',
      },
    },
    // Browser Chrome Bar
    h(
      'div',
      {
        style: {
          display: 'flex',
          height: 44,
          backgroundColor: '#051B13',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '0 20px',
          borderBottom: '1px solid rgba(255,255,255,0.08)',
        },
      },
      h(
        'div',
        { style: { display: 'flex', gap: 8 } },
        h('div', { style: { width: 12, height: 12, borderRadius: 99, backgroundColor: '#EF4444' } }),
        h('div', { style: { width: 12, height: 12, borderRadius: 99, backgroundColor: '#E2B643' } }),
        h('div', { style: { width: 12, height: 12, borderRadius: 99, backgroundColor: '#22C55E' } })
      ),
      h(
        'div',
        {
          style: {
            display: 'flex',
            padding: '5px 28px',
            borderRadius: 99,
            backgroundColor: 'rgba(255,255,255,0.08)',
            color: '#D5E6DD',
            fontFamily: 'JetBrains Mono',
            fontSize: 11.5,
          },
        },
        'https://aesmg-ghana.org  ·  Association des Étudiants et Stagiaires Maliens au Ghana'
      ),
      h(
        'div',
        {
          style: {
            display: 'flex',
            fontFamily: 'JetBrains Mono',
            fontSize: 10.5,
            color: '#E2B643',
            fontWeight: 600,
          },
        },
        'ACCRA · BAMAKO GMT'
      )
    ),
    TricoloreBar(3),
    // Website Top Hero Section
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          position: 'relative',
          width: '100%',
          height: 405,
          backgroundColor: '#062319',
          overflow: 'hidden',
        },
      },
      h('img', {
        src: IMG.hero,
        width,
        height: 405,
        style: {
          position: 'absolute',
          top: 0,
          left: 0,
          width: '100%',
          height: '100%',
          objectFit: 'cover',
        },
      }),
      h('div', {
        style: {
          position: 'absolute',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background:
            'linear-gradient(95deg, rgba(6,35,25,0.95) 0%, rgba(6,35,25,0.78) 52%, rgba(6,35,25,0.32) 100%)',
        },
      }),
      // Website Desktop Navbar
      h(
        'div',
        {
          style: {
            display: 'flex',
            position: 'relative',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '16px 42px',
          },
        },
        h(
          'div',
          { style: { display: 'flex', alignItems: 'center', gap: 11 } },
          h('img', { src: aesmgSealSvg, width: 38, height: 38 }),
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column' } },
            h(
              'span',
              {
                style: {
                  fontFamily: 'Fraunces',
                  fontSize: 17,
                  fontWeight: 700,
                  color: '#FAF8F3',
                },
              },
              'AESMG'
            ),
            h(
              'span',
              { style: { fontSize: 10.5, color: '#D3E5DC' } },
              'Étudiants & Stagiaires au Ghana'
            )
          )
        ),
        h(
          'div',
          { style: { display: 'flex', alignItems: 'center', gap: 10 } },
          ...['Accueil', 'Objectifs', 'Vie', 'Blog', 'Bureau', 'Adhérer'].map((item, idx) =>
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  padding: '6px 14px',
                  borderRadius: 99,
                  backgroundColor: idx === 0 ? '#FAF8F3' : 'transparent',
                  color: idx === 0 ? '#062319' : '#EEF5F1',
                  fontSize: 12,
                  fontWeight: 600,
                },
              },
              item
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
                padding: '7px 14px',
                borderRadius: 99,
                border: '1px solid rgba(226,182,67,0.55)',
                backgroundColor: 'rgba(226,182,67,0.18)',
                color: '#FAF8F3',
                fontSize: 11.5,
                fontWeight: 600,
              },
            },
            'Vue Mobile'
          ),
          h(
            'div',
            {
              style: {
                display: 'flex',
                padding: '7px 16px',
                borderRadius: 99,
                backgroundColor: '#E2B643',
                color: '#062319',
                fontSize: 11.5,
                fontWeight: 700,
              },
            },
            'Contact'
          )
        )
      ),
      // Hero Headline + Actions
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            position: 'relative',
            padding: '16px 42px 26px',
            maxWidth: 790,
            marginTop: 'auto',
          },
        },
        h(
          'div',
          { style: { display: 'flex', gap: 10, alignItems: 'center' } },
          h(
            'span',
            {
              style: {
                padding: '5px 13px',
                borderRadius: 99,
                border: '1px solid rgba(226,182,67,0.45)',
                backgroundColor: 'rgba(6,35,25,0.72)',
                color: '#F7EBC6',
                fontFamily: 'JetBrains Mono',
                fontSize: 10.5,
                fontWeight: 600,
              },
            },
            'AW NI CE · AKWAABA'
          ),
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 11,
                fontWeight: 600,
                color: '#E2B643',
                letterSpacing: '0.16em',
              },
            },
            'UNITÉ · FRATERNITÉ · EXCELLENCE'
          )
        ),
        h(
          'div',
          {
            style: {
              marginTop: 12,
              fontFamily: 'Fraunces',
              fontSize: 42,
              lineHeight: 1.08,
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
              marginTop: 10,
              fontSize: 14,
              lineHeight: 1.5,
              color: '#E4EFEA',
              maxWidth: 630,
            },
          },
          'Une maison commune de fraternité, d’excellence académique et de solidarité active pour chaque étudiant et stagiaire malien vivant au Ghana.'
        ),
        h(
          'div',
          { style: { display: 'flex', gap: 12, marginTop: 18 } },
          h(
            'div',
            {
              style: {
                display: 'flex',
                padding: '12px 22px',
                borderRadius: 14,
                backgroundColor: '#E2B643',
                color: '#062319',
                fontSize: 13,
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
                padding: '12px 22px',
                borderRadius: 14,
                border: '1px solid rgba(255,255,255,0.28)',
                backgroundColor: 'rgba(6,35,25,0.62)',
                color: '#FAF8F3',
                fontSize: 13,
                fontWeight: 600,
              },
            },
            'Contacter le Bureau'
          )
        )
      )
    ),
    // Lower Desktop Editorial Grid: Objectives + Blog AESMG + Bureau Exécutif
    h(
      'div',
      {
        style: {
          display: 'flex',
          flex: 1,
          backgroundColor: '#FAF8F3',
          padding: '22px 38px',
          gap: 24,
        },
      },
      // Column 1: 5 Buts & Objectifs
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', width: 385, gap: 9 } },
        h(
          'div',
          { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'Fraunces',
                fontSize: 20,
                fontWeight: 700,
                color: '#062319',
              },
            },
            'Nos Buts & Objectifs'
          ),
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 9.5,
                fontWeight: 600,
                color: '#9B7210',
              },
            },
            'CHARTE · 5 PILIERS'
          )
        ),
        ...[
          ['01', 'Fédérer la communauté malienne au Ghana', true],
          ['02', 'Œuvrer pour l’épanouissement des membres', false],
          ['03', 'Promouvoir l’unité et la fraternité', false],
          ['04', 'Animer la vie communautaire', false],
          ['05', 'Défendre les droits et intérêts des membres', false],
        ].map(([num, title, dark]) =>
          h(
            'div',
            {
              style: {
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                padding: '11px 14px',
                borderRadius: 14,
                backgroundColor: dark ? '#062319' : '#FFFFFF',
                border: dark ? 'none' : '1px solid rgba(11,59,43,0.11)',
              },
            },
            h(
              'span',
              {
                style: {
                  display: 'flex',
                  width: 30,
                  height: 30,
                  borderRadius: 9,
                  alignItems: 'center',
                  justifyContent: 'center',
                  backgroundColor: dark ? '#E2B643' : 'rgba(11,59,43,0.08)',
                  color: '#062319',
                  fontFamily: 'JetBrains Mono',
                  fontSize: 11,
                  fontWeight: 700,
                },
              },
              num
            ),
            h(
              'span',
              {
                style: {
                  fontSize: 12.5,
                  fontWeight: 600,
                  color: dark ? '#FAF8F3' : '#062319',
                },
              },
              title
            )
          )
        )
      ),
      // Column 2: Le Journal · Blog AESMG
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', flex: 1, gap: 11 } },
        h(
          'div',
          { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'Fraunces',
                fontSize: 20,
                fontWeight: 700,
                color: '#062319',
              },
            },
            'Le Journal · Blog AESMG'
          ),
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 9.5,
                fontWeight: 600,
                color: '#9B7210',
              },
            },
            'GUIDES · ACADÉMIQUE · CULTURE'
          )
        ),
        // Featured Blog Row
        h(
          'div',
          {
            style: {
              display: 'flex',
              borderRadius: 18,
              overflow: 'hidden',
              backgroundColor: '#062319',
              height: 158,
            },
          },
          h('img', {
            src: IMG.blogAccra,
            width: 240,
            height: 158,
            style: { width: 240, height: 158, objectFit: 'cover' },
          }),
          h(
            'div',
            {
              style: {
                display: 'flex',
                flexDirection: 'column',
                flex: 1,
                padding: '14px 16px',
                justifyContent: 'space-between',
              },
            },
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column' } },
              h(
                'span',
                {
                  style: {
                    fontFamily: 'JetBrains Mono',
                    fontSize: 9,
                    fontWeight: 700,
                    color: '#E2B643',
                  },
                },
                'À LA UNE · GUIDES & GHANA · 5 MIN'
              ),
              h(
                'span',
                {
                  style: {
                    marginTop: 5,
                    fontFamily: 'Fraunces',
                    fontSize: 16,
                    lineHeight: 1.22,
                    fontWeight: 600,
                    color: '#FAF8F3',
                  },
                },
                'Réussir son installation au Ghana : les 7 repères essentiels'
              ),
              h(
                'span',
                { style: { marginTop: 6, fontSize: 10.5, color: '#D2E4DB', lineHeight: 1.38 } },
                'Logement, campus à Accra & Kumasi, intégration linguistique et réseau AESMG.'
              )
            ),
            h(
              'span',
              { style: { fontSize: 10.5, fontWeight: 700, color: '#E2B643' } },
              'Lire l’article ›'
            )
          )
        ),
        // 2 Mini Blog Cards (rich editorial layout)
        h(
          'div',
          { style: { display: 'flex', gap: 12, flex: 1 } },
          h(
            'div',
            {
              style: {
                display: 'flex',
                flex: 1,
                flexDirection: 'column',
                justifyContent: 'space-between',
                borderRadius: 16,
                backgroundColor: '#FFFFFF',
                border: '1px solid rgba(11,59,43,0.12)',
                padding: 13,
              },
            },
            h(
              'div',
              { style: { display: 'flex', gap: 11, alignItems: 'center' } },
              h('img', {
                src: IMG.academic,
                width: 84,
                height: 84,
                style: { width: 84, height: 84, borderRadius: 12, objectFit: 'cover' },
              }),
              h(
                'div',
                { style: { display: 'flex', flexDirection: 'column', flex: 1 } },
                h(
                  'span',
                  {
                    style: {
                      fontFamily: 'JetBrains Mono',
                      fontSize: 8.5,
                      fontWeight: 600,
                      color: '#9B7210',
                    },
                  },
                  'ACADÉMIQUE · 4 MIN'
                ),
                h(
                  'span',
                  {
                    style: {
                      marginTop: 4,
                      fontFamily: 'Fraunces',
                      fontSize: 13.5,
                      lineHeight: 1.24,
                      fontWeight: 600,
                      color: '#062319',
                    },
                  },
                  'Bilinguisme FR–EN : accélérateur de carrière'
                )
              )
            ),
            h(
              'span',
              { style: { marginTop: 10, fontSize: 10, lineHeight: 1.42, color: '#465E54' } },
              'Immersion anglophone, clubs universitaires et stages locaux au Ghana.'
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  marginTop: 10,
                  paddingTop: 8,
                  borderTop: '1px solid rgba(11,59,43,0.08)',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                },
              },
              h('span', { style: { fontSize: 9.5, color: '#536B61', fontWeight: 600 } }, 'Pôle Éducation'),
              h('span', { style: { fontSize: 10, color: '#062319', fontWeight: 700 } }, 'Lire ›')
            )
          ),
          h(
            'div',
            {
              style: {
                display: 'flex',
                flex: 1,
                flexDirection: 'column',
                justifyContent: 'space-between',
                borderRadius: 16,
                backgroundColor: '#FFFFFF',
                border: '1px solid rgba(11,59,43,0.12)',
                padding: 13,
              },
            },
            h(
              'div',
              { style: { display: 'flex', gap: 11, alignItems: 'center' } },
              h('img', {
                src: IMG.blogCulture,
                width: 84,
                height: 84,
                style: { width: 84, height: 84, borderRadius: 12, objectFit: 'cover' },
              }),
              h(
                'div',
                { style: { display: 'flex', flexDirection: 'column', flex: 1 } },
                h(
                  'span',
                  {
                    style: {
                      fontFamily: 'JetBrains Mono',
                      fontSize: 8.5,
                      fontWeight: 600,
                      color: '#9B7210',
                    },
                  },
                  'CULTURE · 4 MIN'
                ),
                h(
                  'span',
                  {
                    style: {
                      marginTop: 4,
                      fontFamily: 'Fraunces',
                      fontSize: 13.5,
                      lineHeight: 1.24,
                      fontWeight: 600,
                      color: '#062319',
                    },
                  },
                  'Rayonnement malien & fraternité au Ghana'
                )
              )
            ),
            h(
              'span',
              { style: { marginTop: 10, fontSize: 10, lineHeight: 1.42, color: '#465E54' } },
              'Bogolan, rencontres culturelles et solidarité active entre Bamako et Accra.'
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  marginTop: 10,
                  paddingTop: 8,
                  borderTop: '1px solid rgba(11,59,43,0.08)',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                },
              },
              h('span', { style: { fontSize: 9.5, color: '#536B61', fontWeight: 600 } }, 'Pôle Culture'),
              h('span', { style: { fontSize: 10, color: '#062319', fontWeight: 700 } }, 'Lire ›')
            )
          )
        )
      ),
      // Column 3: Bureau Exécutif Official Contacts
      h(
        'div',
        {
          style: {
            display: 'flex',
            flexDirection: 'column',
            width: 325,
            padding: 16,
            borderRadius: 20,
            backgroundColor: '#062319',
            color: '#FAF8F3',
            justifyContent: 'space-between',
          },
        },
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 9,
                fontWeight: 600,
                color: '#E2B643',
                letterSpacing: '0.15em',
              },
            },
            'BUREAU EXÉCUTIF'
          ),
          h(
            'span',
            {
              style: {
                marginTop: 2,
                fontFamily: 'Fraunces',
                fontSize: 18,
                fontWeight: 700,
                color: '#FAF8F3',
              },
            },
            'Contacts Officiels'
          ),
          // Diadié GUITTEYE
          h(
            'div',
            {
              style: {
                display: 'flex',
                flexDirection: 'column',
                marginTop: 12,
                padding: 12,
                borderRadius: 14,
                backgroundColor: '#0B3527',
                border: '1px solid rgba(226,182,67,0.34)',
              },
            },
            h(
              'span',
              {
                style: {
                  fontFamily: 'JetBrains Mono',
                  fontSize: 8.5,
                  fontWeight: 600,
                  color: '#E2B643',
                },
              },
              'PRÉSIDENT'
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 2,
                  fontFamily: 'Fraunces',
                  fontSize: 15,
                  fontWeight: 700,
                },
              },
              'Diadié GUITTEYE'
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 6,
                  fontFamily: 'JetBrains Mono',
                  fontSize: 10.5,
                  color: '#E2EFE8',
                },
              },
              'Tél : +233 535478560'
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 2,
                  fontFamily: 'JetBrains Mono',
                  fontSize: 10.5,
                  color: '#86EFAC',
                },
              },
              'WhatsApp : +223 91035026'
            )
          ),
          // Georgette Jeanne MARGNA
          h(
            'div',
            {
              style: {
                display: 'flex',
                flexDirection: 'column',
                marginTop: 10,
                padding: 12,
                borderRadius: 14,
                backgroundColor: '#0B3527',
                border: '1px solid rgba(226,182,67,0.28)',
              },
            },
            h(
              'span',
              {
                style: {
                  fontFamily: 'JetBrains Mono',
                  fontSize: 8.5,
                  fontWeight: 600,
                  color: '#E2B643',
                },
              },
              'SECRÉTAIRE GÉNÉRAL'
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 2,
                  fontFamily: 'Fraunces',
                  fontSize: 14.5,
                  fontWeight: 700,
                },
              },
              'Georgette Jeanne MARGNA'
            ),
            h(
              'span',
              {
                style: {
                  marginTop: 6,
                  fontFamily: 'JetBrains Mono',
                  fontSize: 10.5,
                  color: '#E2EFE8',
                },
              },
              'Tél : +233 50 615 5639'
            )
          )
        ),
        h(
          'div',
          {
            style: {
              display: 'flex',
              justifyContent: 'center',
              padding: '9px',
              borderRadius: 12,
              backgroundColor: '#E2B643',
              color: '#062319',
              fontSize: 11,
              fontWeight: 700,
            },
          },
          'Appeler · WhatsApp · Carte .vcf'
        )
      )
    )
  )

// =========================================================================
// IMAGE 2: APERÇU PC (Desktop Studio PNG - 1760x1140)
// =========================================================================
const DesktopShowcase = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 1760,
        height: 1140,
        background: 'linear-gradient(150deg, #062319 0%, #04160F 55%, #092C20 100%)',
        position: 'relative',
        overflow: 'hidden',
        fontFamily: 'Plus Jakarta Sans',
      },
    },
    TricoloreBar(5),
    h('div', {
      style: {
        position: 'absolute',
        top: -100,
        right: 120,
        width: 640,
        height: 640,
        borderRadius: 999,
        background: 'radial-gradient(circle, rgba(226,182,67,0.16) 0%, rgba(6,35,25,0) 70%)',
      },
    }),
    // Header
    h(
      'div',
      {
        style: {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '32px 68px 20px',
          position: 'relative',
        },
      },
      h(
        'div',
        { style: { display: 'flex', alignItems: 'center', gap: 16 } },
        h('img', { src: aesmgSealSvg, width: 52, height: 52 }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 11.5,
                fontWeight: 600,
                color: '#E2B643',
                letterSpacing: '0.18em',
              },
            },
            'APERÇU ORDINATEUR · SITE WEB REACT AESMG'
          ),
          h(
            'span',
            {
              style: {
                marginTop: 2,
                fontFamily: 'Fraunces',
                fontSize: 28,
                fontWeight: 700,
                color: '#FAF8F3',
              },
            },
            'Association des Étudiants et Stagiaires Maliens au Ghana'
          )
        )
      ),
      h(
        'div',
        {
          style: {
            display: 'flex',
            padding: '9px 18px',
            borderRadius: 99,
            border: '1px solid rgba(226,182,67,0.45)',
            backgroundColor: 'rgba(226,182,67,0.14)',
            color: '#F7EBC6',
            fontFamily: 'JetBrains Mono',
            fontSize: 12,
            fontWeight: 600,
          },
        },
        'VERSION PC · DESKTOP'
      )
    ),
    // Centered Laptop / PC Browser Mockup
    h(
      'div',
      {
        style: {
          display: 'flex',
          flex: 1,
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '0 48px 26px',
          position: 'relative',
        },
      },
      DesktopBrowserMockup({ width: 1620, height: 930 }),
      // Subtle Laptop Metallic Chin Base
      h('div', {
        style: {
          width: 1680,
          height: 16,
          marginTop: 6,
          borderRadius: '0 0 18px 18px',
          background: 'linear-gradient(90deg, #1E2E27 0%, #CBB380 50%, #1E2E27 100%)',
          opacity: 0.72,
        },
      })
    )
  )

// =========================================================================
// IMAGE 3: COMBINED DUAL-DEVICE SHOWCASE (PC + TÉLÉPHONE - 1920x1120 PNG)
// =========================================================================
const CombinedDualShowcase = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 1920,
        height: 1120,
        background: 'linear-gradient(145deg, #062319 0%, #04160F 55%, #0A2E21 100%)',
        position: 'relative',
        overflow: 'hidden',
        fontFamily: 'Plus Jakarta Sans',
      },
    },
    TricoloreBar(5),
    h('div', {
      style: {
        position: 'absolute',
        top: -80,
        right: 160,
        width: 720,
        height: 720,
        borderRadius: 999,
        background: 'radial-gradient(circle, rgba(226,182,67,0.18) 0%, rgba(6,35,25,0) 68%)',
      },
    }),
    // Header
    h(
      'div',
      {
        style: {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '30px 64px 16px',
          position: 'relative',
        },
      },
      h(
        'div',
        { style: { display: 'flex', alignItems: 'center', gap: 16 } },
        h('img', { src: aesmgSealSvg, width: 52, height: 52 }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h(
            'span',
            {
              style: {
                fontFamily: 'JetBrains Mono',
                fontSize: 12,
                fontWeight: 600,
                color: '#E2B643',
                letterSpacing: '0.18em',
              },
            },
            'PRÉSENTATION OFFICIELLE · PC & TÉLÉPHONE'
          ),
          h(
            'span',
            {
              style: {
                marginTop: 2,
                fontFamily: 'Fraunces',
                fontSize: 30,
                fontWeight: 700,
                color: '#FAF8F3',
              },
            },
            'Site Web AESMG — Étudiants & Stagiaires Maliens au Ghana'
          )
        )
      ),
      h(
        'div',
        {
          style: {
            display: 'flex',
            padding: '10px 20px',
            borderRadius: 99,
            backgroundColor: '#E2B643',
            color: '#062319',
            fontFamily: 'JetBrains Mono',
            fontSize: 12,
            fontWeight: 700,
          },
        },
        'MOBILE + DESKTOP + BLOG'
      )
    ),
    // Dual Device Stage: PC cleanly on Left + Mobile Phone cleanly beside it on Right (zero overlap on text!)
    h(
      'div',
      {
        style: {
          display: 'flex',
          flex: 1,
          position: 'relative',
          padding: '10px 44px 28px',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: 28,
        },
      },
      DesktopBrowserMockup({ width: 1435, height: 900 }),
      MobileScreenBlog({ width: 370, height: 790 })
    )
  )

async function renderPng(element, width, height, outputFilename) {
  const svg = await satori(element, { width, height, fonts })
  const resvg = new Resvg(svg, {
    fitTo: { mode: 'width', value: width },
  })
  const pngData = resvg.render()
  const pngBuffer = pngData.asPng()
  const outPath = path.join(exportsDir, outputFilename)
  fs.writeFileSync(outPath, pngBuffer)
  console.log(`Generated ${outPath} (${Math.round(pngBuffer.length / 1024)} KB)`)
}

async function main() {
  await renderPng(TelephoneShowcase(), 1440, 1020, 'aesmg-telephone.png')
  await renderPng(DesktopShowcase(), 1760, 1140, 'aesmg-pc.png')
  await renderPng(CombinedDualShowcase(), 1920, 1120, 'aesmg-tel-et-pc.png')
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
