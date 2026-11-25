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
  hero: loadImg('hero-accra-students.jpg'),
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

const ADMIN_ROWS = [
  {
    title: 'Réussir son installation au Ghana : les 7 repères essentiels',
    category: 'Guides & Ghana',
    status: 'published',
    featured: true,
    author: 'Bureau Exécutif AESMG',
    meta: '5 min · Guide Officiel 2026',
    img: IMG.blogAccra,
  },
  {
    title: 'Bilinguisme français–anglais au Ghana : accélérateur de carrière',
    category: 'Académique',
    status: 'published',
    featured: false,
    author: 'Commission Éducation AESMG',
    meta: '4 min · Mentorat & Stages',
    img: IMG.academic,
  },
  {
    title: 'Entre Bamako et Accra : faire rayonner la culture malienne',
    category: 'Culture & Fraternité',
    status: 'published',
    featured: false,
    author: 'Pôle Culture & Social AESMG',
    meta: '4 min · Vie Culturelle',
    img: IMG.blogCulture,
  },
  {
    title: 'Tournoi fraternel inter-campus Accra–Kumasi : programme officiel',
    category: 'Culture & Fraternité',
    status: 'draft',
    featured: false,
    author: 'Diadié GUITTEYE',
    meta: '3 min · Brouillon en cours',
    img: IMG.sports,
  },
]

// =========================================================================
// 1. GESTION BLOG PC (1440 x 1640 — Tableau de Bord + Éditeur d'Article)
// =========================================================================
const AdminDesktopPage = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 1440,
        height: 1640,
        backgroundColor: '#FAF8F3',
        color: '#0E1E18',
        fontFamily: 'Plus Jakarta Sans',
        overflow: 'hidden',
      },
    },
    TricoloreBar(4),
    // Studio Top Bar
    h(
      'div',
      {
        style: {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '18px 64px',
          backgroundColor: '#062319',
          color: '#FAF8F3',
        },
      },
      h(
        'div',
        { style: { display: 'flex', alignItems: 'center', gap: 14 } },
        h('img', { src: aesmgSealSvg, width: 44, height: 44 }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10.5, fontWeight: 600, color: '#E2B643', letterSpacing: '0.18em' } }, 'ESPACE BUREAU EXÉCUTIF · CMS AESMG'),
          h('span', { style: { fontFamily: 'Fraunces', fontSize: 22, fontWeight: 700 } }, 'Studio de Gestion du Blog AESMG')
        )
      ),
      h(
        'div',
        { style: { display: 'flex', gap: 12, alignItems: 'center' } },
        h('span', { style: { padding: '8px 14px', borderRadius: 12, border: '1px solid rgba(255,255,255,0.2)', fontFamily: 'JetBrains Mono', fontSize: 11, color: '#D3E5DC' } }, 'Code Bureau : AESMG2026'),
        h('span', { style: { padding: '10px 18px', borderRadius: 12, backgroundColor: '#E2B643', color: '#062319', fontSize: 13, fontWeight: 700 } }, '+ Nouvel article')
      )
    ),

    // PART 1: DASHBOARD LIST & KPIS
    h(
      'div',
      { style: { display: 'flex', flexDirection: 'column', padding: '36px 64px 32px' } },
      // 4 KPI Cards
      h(
        'div',
        { style: { display: 'flex', gap: 18 } },
        ...[
          ['TOTAL ARTICLES', '5', '#FFFFFF', '#062319', 'rgba(11,59,43,0.12)'],
          ['EN LIGNE (PUBLIÉS)', '4', '#ECFDF5', '#065F46', 'rgba(0,122,77,0.25)'],
          ['BROUILLONS', '1', '#FEF9E7', '#8C640B', 'rgba(201,150,26,0.32)'],
          ['ARTICLE À LA UNE', 'Guide Installation Ghana', '#062319', '#F7EBC6', '#062319'],
        ].map(([label, val, bg, fg, border], idx) =>
          h(
            'div',
            {
              style: {
                display: 'flex',
                flex: 1,
                flexDirection: 'column',
                padding: '18px 22px',
                borderRadius: 18,
                backgroundColor: bg,
                border: `1px solid ${border}`,
              },
            },
            h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10, fontWeight: 700, letterSpacing: '0.14em', color: idx === 3 ? '#E2B643' : fg } }, label),
            h('span', { style: { marginTop: 6, fontFamily: idx === 3 ? 'Plus Jakarta Sans' : 'Fraunces', fontSize: idx === 3 ? 15 : 30, fontWeight: 700, color: fg } }, val)
          )
        )
      ),
      // Filter Bar
      h(
        'div',
        { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 24, gap: 16 } },
        h(
          'div',
          { style: { display: 'flex', flex: 1, padding: '11px 16px', borderRadius: 14, border: '1px solid rgba(11,59,43,0.14)', backgroundColor: '#FFFFFF', fontSize: 13, color: '#7E948B' } },
          'Filtrer par titre, auteur ou catégorie…'
        ),
        h(
          'div',
          { style: { display: 'flex', gap: 8 } },
          ...['Tous (5)', 'Publiés (4)', 'Brouillons (1)'].map((tab, i) =>
            h(
              'span',
              {
                style: {
                  padding: '10px 16px',
                  borderRadius: 12,
                  backgroundColor: i === 0 ? '#062319' : '#FFFFFF',
                  border: i === 0 ? 'none' : '1px solid rgba(11,59,43,0.14)',
                  color: i === 0 ? '#F7EBC6' : '#264237',
                  fontSize: 12,
                  fontWeight: 600,
                },
              },
              tab
            )
          )
        )
      ),
      // Articles Management Rows
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', gap: 12, marginTop: 20 } },
        ...ADMIN_ROWS.map((row) => {
          const isPub = row.status === 'published'
          return h(
            'div',
            {
              style: {
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                padding: '14px 18px',
                borderRadius: 18,
                backgroundColor: '#FFFFFF',
                border: '1px solid rgba(11,59,43,0.12)',
                gap: 16,
              },
            },
            h(
              'div',
              { style: { display: 'flex', alignItems: 'center', gap: 16, flex: 1 } },
              h('img', { src: row.img, width: 88, height: 64, style: { width: 88, height: 64, borderRadius: 12, objectFit: 'cover' } }),
              h(
                'div',
                { style: { display: 'flex', flexDirection: 'column', flex: 1 } },
                h(
                  'div',
                  { style: { display: 'flex', gap: 8, alignItems: 'center' } },
                  h(
                    'span',
                    {
                      style: {
                        padding: '3px 9px',
                        borderRadius: 99,
                        backgroundColor: isPub ? '#DCFCE7' : '#FEF3C7',
                        color: isPub ? '#166534' : '#92400E',
                        fontFamily: 'JetBrains Mono',
                        fontSize: 9.5,
                        fontWeight: 700,
                      },
                    },
                    isPub ? 'PUBLIÉ' : 'BROUILLON'
                  ),
                  h('span', { style: { padding: '3px 9px', borderRadius: 99, backgroundColor: 'rgba(11,59,43,0.07)', color: '#0B3B2B', fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 600 } }, row.category),
                  row.featured
                    ? h('span', { style: { padding: '3px 9px', borderRadius: 99, backgroundColor: 'rgba(226,182,67,0.28)', color: '#7D5708', fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 700 } }, 'À LA UNE')
                    : null
                ),
                h('span', { style: { marginTop: 5, fontFamily: 'Fraunces', fontSize: 17, fontWeight: 700, color: '#062319' } }, row.title),
                h('span', { style: { marginTop: 2, fontSize: 11.5, color: '#536B61' } }, `${row.author}  ·  ${row.meta}`)
              )
            ),
            // Action Buttons
            h(
              'div',
              { style: { display: 'flex', gap: 8, alignItems: 'center' } },
              h('span', { style: { padding: '8px 12px', borderRadius: 10, border: '1px solid rgba(11,59,43,0.14)', backgroundColor: row.featured ? '#F7EBC6' : '#FAF8F3', fontSize: 11.5, fontWeight: 600, color: '#062319' } }, 'Une'),
              h('span', { style: { padding: '8px 12px', borderRadius: 10, border: '1px solid rgba(11,59,43,0.14)', backgroundColor: '#FAF8F3', fontSize: 11.5, fontWeight: 600, color: isPub ? '#92400E' : '#15803D' } }, isPub ? 'Masquer' : 'Publier'),
              h('span', { style: { padding: '8px 12px', borderRadius: 10, border: '1px solid rgba(11,59,43,0.14)', backgroundColor: '#FAF8F3', fontSize: 11.5, fontWeight: 600, color: '#062319' } }, 'Aperçu'),
              h('span', { style: { padding: '8px 14px', borderRadius: 10, backgroundColor: '#062319', fontSize: 11.5, fontWeight: 700, color: '#F7EBC6' } }, 'Éditer'),
              h('span', { style: { padding: '8px 11px', borderRadius: 10, backgroundColor: '#FEF2F2', border: '1px solid rgba(185,28,46,0.2)', fontSize: 11.5, fontWeight: 600, color: '#B91C2E' } }, 'Suppr.')
            )
          )
        })
      ),
      // JSON Backup Bar
      h(
        'div',
        {
          style: {
            display: 'flex',
            marginTop: 18,
            padding: '14px 20px',
            borderRadius: 16,
            backgroundColor: '#F3EFE5',
            border: '1px solid rgba(11,59,43,0.1)',
            justifyContent: 'space-between',
            alignItems: 'center',
          },
        },
        h('span', { style: { fontSize: 12.5, fontWeight: 500, color: '#3C554B' } }, 'Sauvegarde automatique active · Exportez ou importez votre catalogue en JSON entre appareils.'),
        h(
          'div',
          { style: { display: 'flex', gap: 10 } },
          h('span', { style: { padding: '8px 14px', borderRadius: 10, backgroundColor: '#FFFFFF', border: '1px solid rgba(11,59,43,0.15)', fontSize: 11.5, fontWeight: 600, color: '#062319' } }, 'Exporter JSON'),
          h('span', { style: { padding: '8px 14px', borderRadius: 10, backgroundColor: '#FFFFFF', border: '1px solid rgba(11,59,43,0.15)', fontSize: 11.5, fontWeight: 600, color: '#062319' } }, 'Importer JSON')
        )
      )
    ),

    // PART 2: ARTICLE COMPOSER / EDITOR PREVIEW
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          flex: 1,
          padding: '38px 64px 44px',
          backgroundColor: '#F3EFE5',
          borderTop: '1px solid rgba(11,59,43,0.12)',
        },
      },
      h(
        'div',
        { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 } },
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 10.5, fontWeight: 700, color: '#9B7210', letterSpacing: '0.18em' } }, 'ÉDITEUR INTÉGRÉ · RÉDACTION & IMPORT PHOTO'),
          h('span', { style: { marginTop: 3, fontFamily: 'Fraunces', fontSize: 28, fontWeight: 700, color: '#062319' } }, 'Créer ou modifier un article en temps réel')
        ),
        h('span', { style: { padding: '8px 14px', borderRadius: 99, backgroundColor: '#062319', color: '#F7EBC6', fontFamily: 'JetBrains Mono', fontSize: 10.5, fontWeight: 600 } }, 'GALERIE + UPLOAD PHOTO + MULTI-SECTIONS')
      ),
      h(
        'div',
        { style: { display: 'flex', gap: 24, flex: 1 } },
        // Left Editor Column: Metadata + Cover Picker
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', width: 580, gap: 16, padding: 24, borderRadius: 24, backgroundColor: '#FFFFFF', border: '1px solid rgba(11,59,43,0.12)' } },
          h('span', { style: { fontSize: 11, fontWeight: 700, color: '#264237', letterSpacing: '0.08em' } }, 'TITRE DE L’ARTICLE'),
          h('div', { style: { display: 'flex', padding: '12px 14px', borderRadius: 12, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.14)', fontSize: 13.5, fontWeight: 600, color: '#062319' } }, 'Tournoi fraternel inter-campus Accra–Kumasi : programme officiel'),
          h(
            'div',
            { style: { display: 'flex', gap: 12 } },
            h(
              'div',
              { style: { display: 'flex', flex: 1, flexDirection: 'column', gap: 5 } },
              h('span', { style: { fontSize: 10.5, fontWeight: 700, color: '#264237' } }, 'CATÉGORIE'),
              h('div', { style: { display: 'flex', padding: '10px 12px', borderRadius: 11, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.14)', fontSize: 12, fontWeight: 600 } }, 'Culture & Fraternité')
            ),
            h(
              'div',
              { style: { display: 'flex', flex: 1, flexDirection: 'column', gap: 5 } },
              h('span', { style: { fontSize: 10.5, fontWeight: 700, color: '#264237' } }, 'SIGNATURE AUTEUR'),
              h('div', { style: { display: 'flex', padding: '10px 12px', borderRadius: 11, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 12, fontWeight: 600 } }, 'Diadié GUITTEYE · Président')
            )
          ),
          h(
            'div',
            { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 4 } },
            h('span', { style: { fontSize: 10.5, fontWeight: 700, color: '#264237' } }, 'PHOTO DE COUVERTURE'),
            h('span', { style: { padding: '6px 12px', borderRadius: 10, backgroundColor: '#062319', color: '#E2B643', fontSize: 11, fontWeight: 700 } }, 'Importer depuis tél / PC')
          ),
          h(
            'div',
            { style: { display: 'flex', gap: 8 } },
            ...[IMG.sports, IMG.blogAccra, IMG.blogCulture, IMG.academic, IMG.gala, IMG.hero].map((src, i) =>
              h('img', {
                src,
                width: 82,
                height: 54,
                style: {
                  width: 82,
                  height: 54,
                  borderRadius: 10,
                  objectFit: 'cover',
                  border: i === 0 ? '2.5px solid #C9961A' : '1px solid rgba(11,59,43,0.12)',
                },
              })
            )
          ),
          h('img', { src: IMG.sports, width: 530, height: 195, style: { width: '100%', height: 195, borderRadius: 16, objectFit: 'cover' } }),
          h(
            'div',
            { style: { display: 'flex', gap: 8, flexWrap: 'wrap' } },
            ...['Diadié GUITTEYE', 'Georgette Jeanne MARGNA', 'Bureau Exécutif AESMG'].map((p, i) =>
              h('span', { style: { padding: '6px 12px', borderRadius: 99, backgroundColor: i === 0 ? '#062319' : '#FAF8F3', border: '1px solid rgba(11,59,43,0.14)', color: i === 0 ? '#F7EBC6' : '#264237', fontSize: 11, fontWeight: 600 } }, p)
            )
          )
        ),
        // Right Editor Column: Excerpt + Sections + Save Bar
        h(
          'div',
          { style: { display: 'flex', flex: 1, flexDirection: 'column', justifyContent: 'space-between', padding: 24, borderRadius: 24, backgroundColor: '#FFFFFF', border: '1px solid rgba(11,59,43,0.12)' } },
          h(
            'div',
            { style: { display: 'flex', flexDirection: 'column', gap: 14 } },
            h('span', { style: { fontSize: 10.5, fontWeight: 700, color: '#264237' } }, 'RÉSUMÉ / EXTRAIT'),
            h('div', { style: { display: 'flex', padding: 14, borderRadius: 12, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.14)', fontSize: 13, lineHeight: 1.5, color: '#1E362C' } }, 'Rendez-vous sportif et fraternel réunissant les étudiants et stagiaires maliens d’Accra, Kumasi et Cape Coast autour des valeurs d’unité et de fair-play.'),
            h('span', { style: { fontSize: 10.5, fontWeight: 700, color: '#264237' } }, 'CITATION MISE EN AVANT'),
            h('div', { style: { display: 'flex', padding: 14, borderRadius: 12, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 13, lineHeight: 1.45 } }, '« Le sport rassemble nos campus et nourrit l’esprit de fraternité qui fait la force de l’AESMG. »'),
            h(
              'div',
              { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
              h('span', { style: { fontSize: 10.5, fontWeight: 700, color: '#264237' } }, 'SECTIONS DE L’ARTICLE · TEMPS ESTIMÉ : 3 MIN'),
              h('span', { style: { padding: '5px 11px', borderRadius: 9, border: '1px solid rgba(11,59,43,0.16)', backgroundColor: '#FAF8F3', fontSize: 11, fontWeight: 700, color: '#062319' } }, '+ Ajouter section')
            ),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', padding: 14, borderRadius: 14, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.12)', gap: 8 } },
              h('span', { style: { fontSize: 13, fontWeight: 700, color: '#062319' } }, '1. Programme de la journée & inscriptions d’équipes'),
              h('span', { style: { fontSize: 12.5, lineHeight: 1.55, color: '#375046' } }, 'Les rencontres débuteront dès 15h00 avec des équipes mixtes représentant chaque grande ville universitaire du Ghana. Un moment de partage convivial clôturera le tournoi en présence du Bureau Exécutif.')
            )
          ),
          // Sticky Publish Actions
          h(
            'div',
            { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', paddingTop: 18, borderTop: '1px solid rgba(11,59,43,0.1)' } },
            h('span', { style: { padding: '11px 18px', borderRadius: 12, border: '1px solid rgba(11,59,43,0.15)', backgroundColor: '#FAF8F3', fontSize: 12.5, fontWeight: 600, color: '#3C554B' } }, 'Annuler'),
            h(
              'div',
              { style: { display: 'flex', gap: 10 } },
              h('span', { style: { padding: '11px 18px', borderRadius: 12, border: '1px solid rgba(201,150,26,0.5)', backgroundColor: '#FEF9E7', fontSize: 12.5, fontWeight: 700, color: '#785305' } }, 'Enregistrer brouillon'),
              h('span', { style: { padding: '11px 22px', borderRadius: 12, backgroundColor: '#062319', fontSize: 12.5, fontWeight: 700, color: '#F7EBC6' } }, 'Publier maintenant ›')
            )
          )
        )
      )
    )
  )

// =========================================================================
// 2. GESTION BLOG MOBILE (430 x 1640 @ 2x = 860px — ZERO NOTCH!)
// =========================================================================
const AdminMobilePage = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 430,
        height: 1640,
        backgroundColor: '#FAF8F3',
        color: '#0E1E18',
        fontFamily: 'Plus Jakarta Sans',
        overflow: 'hidden',
      },
    },
    TricoloreBar(4),
    // Mobile Studio Bar
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
        { style: { display: 'flex', alignItems: 'center', gap: 10 } },
        h('img', { src: aesmgSealSvg, width: 34, height: 34 }),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column' } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 8.5, color: '#E2B643', fontWeight: 600 } }, 'CMS BUREAU AESMG'),
          h('span', { style: { fontFamily: 'Fraunces', fontSize: 15.5, fontWeight: 700 } }, 'Gestion du Blog')
        )
      ),
      h('span', { style: { padding: '7px 12px', borderRadius: 10, backgroundColor: '#E2B643', color: '#062319', fontSize: 11, fontWeight: 700 } }, '+ Créer')
    ),

    // Mobile KPIs 2x2
    h(
      'div',
      { style: { display: 'flex', flexDirection: 'column', padding: '18px 16px', gap: 12 } },
      h(
        'div',
        { style: { display: 'flex', gap: 10 } },
        h(
          'div',
          { style: { display: 'flex', flex: 1, flexDirection: 'column', padding: 13, borderRadius: 16, backgroundColor: '#ECFDF5', border: '1px solid rgba(0,122,77,0.22)' } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 8.5, fontWeight: 700, color: '#007A4D' } }, 'PUBLIÉS'),
          h('span', { style: { marginTop: 3, fontFamily: 'Fraunces', fontSize: 22, fontWeight: 700, color: '#065F46' } }, '4')
        ),
        h(
          'div',
          { style: { display: 'flex', flex: 1, flexDirection: 'column', padding: 13, borderRadius: 16, backgroundColor: '#FEF9E7', border: '1px solid rgba(201,150,26,0.32)' } },
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 8.5, fontWeight: 700, color: '#8C640B' } }, 'BROUILLONS'),
          h('span', { style: { marginTop: 3, fontFamily: 'Fraunces', fontSize: 22, fontWeight: 700, color: '#785305' } }, '1')
        )
      ),
      // Status Filters
      h(
        'div',
        { style: { display: 'flex', gap: 7 } },
        ...['Tous (5)', 'Publiés (4)', 'Brouillons (1)'].map((t, i) =>
          h('span', { style: { flex: 1, textAlign: 'center', padding: '8px 6px', borderRadius: 11, backgroundColor: i === 0 ? '#062319' : '#FFFFFF', border: i === 0 ? 'none' : '1px solid rgba(11,59,43,0.14)', color: i === 0 ? '#F7EBC6' : '#264237', fontSize: 10.5, fontWeight: 600 } }, t)
        )
      ),
      // Mobile Article Management Cards
      ...ADMIN_ROWS.map((row) => {
        const isPub = row.status === 'published'
        return h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', padding: 13, borderRadius: 18, backgroundColor: '#FFFFFF', border: '1px solid rgba(11,59,43,0.12)', gap: 10 } },
          h(
            'div',
            { style: { display: 'flex', gap: 11, alignItems: 'center' } },
            h('img', { src: row.img, width: 68, height: 56, style: { width: 68, height: 56, borderRadius: 11, objectFit: 'cover' } }),
            h(
              'div',
              { style: { display: 'flex', flexDirection: 'column', flex: 1 } },
              h(
                'div',
                { style: { display: 'flex', gap: 5 } },
                h('span', { style: { padding: '2px 7px', borderRadius: 99, backgroundColor: isPub ? '#DCFCE7' : '#FEF3C7', color: isPub ? '#166534' : '#92400E', fontFamily: 'JetBrains Mono', fontSize: 8.5, fontWeight: 700 } }, isPub ? 'PUBLIÉ' : 'BROUILLON'),
                row.featured ? h('span', { style: { padding: '2px 7px', borderRadius: 99, backgroundColor: 'rgba(226,182,67,0.28)', color: '#7D5708', fontFamily: 'JetBrains Mono', fontSize: 8.5, fontWeight: 700 } }, 'À LA UNE') : null
              ),
              h('span', { style: { marginTop: 4, fontFamily: 'Fraunces', fontSize: 13.5, lineHeight: 1.2, fontWeight: 700, color: '#062319' } }, row.title),
              h('span', { style: { marginTop: 2, fontSize: 10, color: '#536B61' } }, row.author)
            )
          ),
          h(
            'div',
            { style: { display: 'flex', gap: 6, borderTop: '1px solid rgba(11,59,43,0.08)', paddingTop: 9 } },
            h('span', { style: { flex: 1, textAlign: 'center', padding: '7px 4px', borderRadius: 9, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.12)', fontSize: 10, fontWeight: 600, color: isPub ? '#92400E' : '#15803D' } }, isPub ? 'Masquer' : 'Publier'),
            h('span', { style: { flex: 1, textAlign: 'center', padding: '7px 4px', borderRadius: 9, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.12)', fontSize: 10, fontWeight: 600, color: '#062319' } }, 'Aperçu'),
            h('span', { style: { flex: 1, textAlign: 'center', padding: '7px 4px', borderRadius: 9, backgroundColor: '#062319', fontSize: 10, fontWeight: 700, color: '#F7EBC6' } }, 'Éditer'),
            h('span', { style: { padding: '7px 9px', borderRadius: 9, backgroundColor: '#FEF2F2', fontSize: 10, fontWeight: 700, color: '#B91C2E' } }, 'X')
          )
        )
      })
    ),

    // Mobile Article Composer Panel
    h(
      'div',
      {
        style: {
          display: 'flex',
          flexDirection: 'column',
          flex: 1,
          padding: '20px 16px 20px',
          backgroundColor: '#F3EFE5',
          borderTop: '1px solid rgba(11,59,43,0.12)',
          justifyContent: 'space-between',
        },
      },
      h(
        'div',
        { style: { display: 'flex', flexDirection: 'column', gap: 11 } },
        h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 9.5, fontWeight: 700, color: '#9B7210', letterSpacing: '0.16em' } }, 'ÉDITEUR MOBILE · NOUVEL ARTICLE'),
        h(
          'div',
          { style: { display: 'flex', flexDirection: 'column', padding: 15, borderRadius: 20, backgroundColor: '#FFFFFF', border: '1px solid rgba(11,59,43,0.12)', gap: 10 } },
          h('span', { style: { fontSize: 9.5, fontWeight: 700, color: '#264237' } }, 'TITRE DE L’ARTICLE'),
          h('div', { style: { display: 'flex', padding: '10px 12px', borderRadius: 11, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.14)', fontSize: 12, fontWeight: 600, color: '#062319' } }, 'Tournoi fraternel Accra–Kumasi : programme officiel'),
          h(
            'div',
            { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
            h('span', { style: { fontSize: 9.5, fontWeight: 700, color: '#264237' } }, 'PHOTO DE COUVERTURE'),
            h('span', { style: { padding: '5px 10px', borderRadius: 8, backgroundColor: '#062319', color: '#E2B643', fontSize: 9.5, fontWeight: 700 } }, 'Importer photo')
          ),
          h('img', { src: IMG.sports, width: 368, height: 132, style: { width: '100%', height: 132, borderRadius: 12, objectFit: 'cover' } }),
          h('span', { style: { fontSize: 9.5, fontWeight: 700, color: '#264237' } }, 'SIGNATURE'),
          h('div', { style: { display: 'flex', gap: 6 } },
            h('span', { style: { padding: '5px 10px', borderRadius: 99, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 9.5, fontWeight: 600 } }, 'Diadié GUITTEYE'),
            h('span', { style: { padding: '5px 10px', borderRadius: 99, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.14)', color: '#264237', fontSize: 9.5, fontWeight: 600 } }, 'Georgette J. MARGNA')
          ),
          h('span', { style: { fontSize: 9.5, fontWeight: 700, color: '#264237' } }, 'CONTENU DE L’ARTICLE'),
          h('div', { style: { display: 'flex', padding: 11, borderRadius: 11, backgroundColor: '#FAF8F3', border: '1px solid rgba(11,59,43,0.14)', fontSize: 11, lineHeight: 1.48, color: '#2B4339' } }, 'Rendez-vous sportif et fraternel réunissant nos campus autour des valeurs d’unité et de solidarité de l’AESMG.'),
          h(
            'div',
            { style: { display: 'flex', gap: 8, marginTop: 4 } },
            h('div', { style: { display: 'flex', flex: 1, justifyContent: 'center', padding: '11px', borderRadius: 12, border: '1px solid rgba(201,150,26,0.5)', backgroundColor: '#FEF9E7', color: '#785305', fontSize: 11, fontWeight: 700 } }, 'Brouillon'),
            h('div', { style: { display: 'flex', flex: 1, justifyContent: 'center', padding: '11px', borderRadius: 12, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 11, fontWeight: 700 } }, 'Publier ›')
          )
        )
      ),
      // Backup pill footer
      h(
        'div',
        { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 14px', borderRadius: 14, backgroundColor: '#062319', color: '#F7EBC6', fontSize: 10.5, fontWeight: 600 } },
        h('span', { style: { color: '#E2B643' } }, 'Sauvegarde JSON'),
        h('span', {}, 'Exporter · Importer')
      )
    )
  )

// =========================================================================
// 3. COMBINED ADMIN STUDIO SHEET: PC + MOBILE SIDE BY SIDE
// =========================================================================
const CombinedAdminSheet = () =>
  h(
    'div',
    {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 2040,
        height: 1840,
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
          h('span', { style: { fontFamily: 'JetBrains Mono', fontSize: 12, fontWeight: 600, color: '#E2B643', letterSpacing: '0.18em' } }, 'GESTION DU BLOG · ESPACE BUREAU EXÉCUTIF (CMS)'),
          h('span', { style: { marginTop: 2, fontFamily: 'Fraunces', fontSize: 28, fontWeight: 700, color: '#FAF8F3' } }, 'Studio d’Administration du Blog AESMG — PC & Mobile')
        )
      ),
      h(
        'div',
        { style: { display: 'flex', gap: 10 } },
        h('span', { style: { padding: '8px 15px', borderRadius: 99, border: '1px solid rgba(226,182,67,0.45)', color: '#F7EBC6', fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 600 } }, 'ADMIN PC'),
        h('span', { style: { padding: '8px 15px', borderRadius: 99, backgroundColor: '#E2B643', color: '#062319', fontFamily: 'JetBrains Mono', fontSize: 11, fontWeight: 700 } }, 'ADMIN MOBILE')
      )
    ),
    h(
      'div',
      { style: { display: 'flex', gap: 58, alignItems: 'flex-start' } },
      h(
        'div',
        { style: { display: 'flex', borderRadius: 18, overflow: 'hidden', boxShadow: '0 24px 60px rgba(0,0,0,0.45)' } },
        AdminDesktopPage()
      ),
      h(
        'div',
        { style: { display: 'flex', borderRadius: 18, overflow: 'hidden', boxShadow: '0 24px 60px rgba(0,0,0,0.45)' } },
        AdminMobilePage()
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
  await renderPng(AdminDesktopPage(), 1440, 1640, 1440, 'aesmg-gestion-blog-pc.png')
  await renderPng(AdminMobilePage(), 430, 1640, 860, 'aesmg-gestion-blog-mobile.png')
  await renderPng(CombinedAdminSheet(), 2040, 1840, 2040, 'aesmg-gestion-blog-pc-et-mobile.png')
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
