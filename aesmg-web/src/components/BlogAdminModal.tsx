import React, { useEffect, useMemo, useRef, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  X,
  Plus,
  Pencil,
  Trash2,
  Eye,
  Star,
  CheckCircle2,
  FileText,
  Upload,
  Download,
  RotateCcw,
  ArrowLeft,
  Search,
  Lock,
  Unlock,
  Sparkles,
  Image as ImageIcon,
  Send,
  Globe,
  EyeOff,
} from 'lucide-react'
import {
  BLOG_AUTHOR_PRESETS,
  BLOG_COVER_GALLERY,
  type BlogArticle,
  type BlogCategory,
  type BlogStatus,
} from '../data/blogData'
import { AesmgSeal, TricoloreRibbon } from './BrandMarks'

interface BlogAdminModalProps {
  isOpen: boolean
  articles: BlogArticle[]
  onClose: () => void
  onSaveArticle: (article: BlogArticle, isNew: boolean) => void
  onDeleteArticle: (articleId: string) => void
  onToggleStatus: (articleId: string) => void
  onSetFeatured: (articleId: string) => void
  onReplaceAllArticles: (articles: BlogArticle[]) => void
  onResetDefaults: () => void
  onPreviewArticle: (article: BlogArticle) => void
  onShowToast: (message: string) => void
}

const CATEGORIES: BlogCategory[] = [
  'Guides & Ghana',
  'Académique',
  'Culture & Fraternité',
  'Droits & Vie',
]

const ACCESS_CODE = 'AESMG2026'

interface SectionDraft {
  id: string
  heading: string
  body: string
}

function estimateReadTime(excerpt: string, sections: SectionDraft[]): string {
  const words = [excerpt, ...sections.map((s) => `${s.heading} ${s.body}`)]
    .join(' ')
    .trim()
    .split(/\s+/)
    .filter(Boolean).length
  const minutes = Math.max(1, Math.round(words / 180))
  return `${minutes} min`
}

function slugifyId(title: string): string {
  const base = title
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 42)
  return `${base || 'article'}-${Date.now().toString(36).slice(-4)}`
}

function formatCurrentDateLabel(): string {
  const formatted = new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date())
  return `Publié le ${formatted}`
}

async function compressImageFile(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onerror = () => reject(new Error('Lecture impossible'))
    reader.onload = () => {
      const img = new Image()
      img.onerror = () => reject(new Error('Image invalide'))
      img.onload = () => {
        const maxWidth = 1100
        const scale = img.width > maxWidth ? maxWidth / img.width : 1
        const canvas = document.createElement('canvas')
        canvas.width = Math.round(img.width * scale)
        canvas.height = Math.round(img.height * scale)
        const ctx = canvas.getContext('2d')
        if (!ctx) {
          resolve(reader.result as string)
          return
        }
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
        resolve(canvas.toDataURL('image/jpeg', 0.8))
      }
      img.src = reader.result as string
    }
    reader.readAsDataURL(file)
  })
}

export const BlogAdminModal: React.FC<BlogAdminModalProps> = ({
  isOpen,
  articles,
  onClose,
  onSaveArticle,
  onDeleteArticle,
  onToggleStatus,
  onSetFeatured,
  onReplaceAllArticles,
  onResetDefaults,
  onPreviewArticle,
  onShowToast,
}) => {
  const [isUnlocked, setIsUnlocked] = useState(false)
  const [pinInput, setPinInput] = useState('')
  const [pinError, setPinError] = useState(false)

  const [view, setView] = useState<'list' | 'editor'>('list')
  const [filterStatus, setFilterStatus] = useState<'all' | BlogStatus>('all')
  const [searchQuery, setSearchQuery] = useState('')
  const [confirmDeleteId, setConfirmDeleteId] = useState<string | null>(null)
  const [confirmReset, setConfirmReset] = useState(false)

  // Editor form state
  const [editingId, setEditingId] = useState<string | null>(null)
  const [title, setTitle] = useState('')
  const [category, setCategory] = useState<BlogCategory>('Guides & Ghana')
  const [status, setStatus] = useState<BlogStatus>('published')
  const [featured, setFeatured] = useState(false)
  const [author, setAuthor] = useState<string>(BLOG_AUTHOR_PRESETS[0].author)
  const [authorRole, setAuthorRole] = useState<string>(BLOG_AUTHOR_PRESETS[0].role)
  const [image, setImage] = useState<string>(BLOG_COVER_GALLERY[0].url)
  const [excerpt, setExcerpt] = useState('')
  const [pullQuote, setPullQuote] = useState('')
  const [tagsText, setTagsText] = useState('AESMG, Ghana, Étudiants')
  const [takeawaysText, setTakeawaysText] = useState('')
  const [sections, setSections] = useState<SectionDraft[]>([
    { id: 'sec-1', heading: 'Introduction', body: '' },
  ])

  const fileInputRef = useRef<HTMLInputElement | null>(null)
  const importJsonRef = useRef<HTMLInputElement | null>(null)

  useEffect(() => {
    if (!isOpen) return
    const original = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => {
      document.body.style.overflow = original
      window.removeEventListener('keydown', handleKey)
    }
  }, [isOpen, onClose])

  const stats = useMemo(() => {
    const published = articles.filter((a) => a.status === 'published').length
    const drafts = articles.filter((a) => a.status === 'draft').length
    const featuredArticle = articles.find((a) => a.featured && a.status === 'published')
    return {
      total: articles.length,
      published,
      drafts,
      featuredTitle: featuredArticle?.title ?? 'Aucun',
    }
  }, [articles])

  const filteredList = useMemo(() => {
    const q = searchQuery.trim().toLowerCase()
    return articles.filter((a) => {
      if (filterStatus !== 'all' && a.status !== filterStatus) return false
      if (!q) return true
      return (
        a.title.toLowerCase().includes(q) ||
        a.category.toLowerCase().includes(q) ||
        a.author.toLowerCase().includes(q)
      )
    })
  }, [articles, filterStatus, searchQuery])

  const openNewEditor = () => {
    setEditingId(null)
    setTitle('')
    setCategory('Guides & Ghana')
    setStatus('published')
    setFeatured(false)
    setAuthor(BLOG_AUTHOR_PRESETS[0].author)
    setAuthorRole(BLOG_AUTHOR_PRESETS[0].role)
    setImage(BLOG_COVER_GALLERY[0].url)
    setExcerpt('')
    setPullQuote('')
    setTagsText('AESMG, Ghana, Communauté')
    setTakeawaysText('')
    setSections([
      {
        id: `sec-${Date.now()}`,
        heading: 'L’essentiel à savoir',
        body: '',
      },
    ])
    setView('editor')
  }

  const openEditEditor = (article: BlogArticle) => {
    setEditingId(article.id)
    setTitle(article.title)
    setCategory(article.category)
    setStatus(article.status)
    setFeatured(Boolean(article.featured))
    setAuthor(article.author)
    setAuthorRole(article.authorRole)
    setImage(article.image)
    setExcerpt(article.excerpt)
    setPullQuote(article.pullQuote)
    setTagsText(article.tags.join(', '))
    setTakeawaysText(article.keyTakeaways.join('\n'))
    setSections(
      article.sections.map((s, idx) => ({
        id: `sec-${idx}-${Date.now()}`,
        heading: s.heading,
        body: s.paragraphs.join('\n\n'),
      }))
    )
    setView('editor')
  }

  const handleUnlockSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (pinInput.trim().toUpperCase() === ACCESS_CODE) {
      setIsUnlocked(true)
      setPinError(false)
      onShowToast('Espace Gestion du Blog déverrouillé !')
    } else {
      setPinError(true)
    }
  }

  const handleCoverUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    try {
      const dataUrl = await compressImageFile(file)
      setImage(dataUrl)
      onShowToast('Photo de couverture importée avec succès !')
    } catch {
      onShowToast('Impossible de charger cette image.')
    }
    e.target.value = ''
  }

  const handleSave = (targetStatus: BlogStatus) => {
    const cleanTitle = title.trim()
    const cleanExcerpt = excerpt.trim()
    if (!cleanTitle) {
      onShowToast('Veuillez saisir un titre pour votre article.')
      return
    }
    if (!cleanExcerpt) {
      onShowToast('Veuillez saisir un court résumé (extrait).')
      return
    }

    const parsedSections = sections
      .map((s) => ({
        heading: s.heading.trim() || 'Développement',
        paragraphs: s.body
          .split(/\n+/)
          .map((p) => p.trim())
          .filter(Boolean),
      }))
      .filter((s) => s.paragraphs.length > 0)

    const finalSections =
      parsedSections.length > 0
        ? parsedSections
        : [{ heading: 'À lire', paragraphs: [cleanExcerpt] }]

    const parsedTakeaways = takeawaysText
      .split('\n')
      .map((line) => line.replace(/^[•\-*]\s*/, '').trim())
      .filter(Boolean)

    const finalTakeaways =
      parsedTakeaways.length > 0
        ? parsedTakeaways
        : [
            cleanExcerpt,
            'Retrouvez les contacts officiels du Bureau AESMG en bas de page.',
          ]

    const parsedTags = tagsText
      .split(',')
      .map((t) => t.trim())
      .filter(Boolean)

    const existing = editingId
      ? articles.find((a) => a.id === editingId)
      : undefined

    const articleToSave: BlogArticle = {
      id: editingId ?? slugifyId(cleanTitle),
      status: targetStatus,
      category,
      featured,
      title: cleanTitle,
      excerpt: cleanExcerpt,
      readTime: estimateReadTime(cleanExcerpt, sections),
      publishedLabel: existing?.publishedLabel ?? formatCurrentDateLabel(),
      author: author.trim() || 'Bureau Exécutif AESMG',
      authorRole: authorRole.trim() || 'Rédaction AESMG',
      image: image.trim() || BLOG_COVER_GALLERY[0].url,
      tags: parsedTags.length > 0 ? parsedTags : ['AESMG', 'Ghana'],
      keyTakeaways: finalTakeaways,
      pullQuote:
        pullQuote.trim() ||
        'Unité, fraternité et excellence au service de chaque étudiant et stagiaire malien au Ghana.',
      sections: finalSections,
    }

    onSaveArticle(articleToSave, !editingId)
    setView('list')
  }

  const handleExportJson = () => {
    const payload = JSON.stringify(articles, null, 2)
    const blob = new Blob([payload], { type: 'application/json;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `aesmg-blog-sauvegarde-${new Date().toISOString().slice(0, 10)}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
    onShowToast('Sauvegarde JSON du blog téléchargée !')
  }

  const handleImportJson = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = () => {
      try {
        const parsed = JSON.parse(String(reader.result))
        if (!Array.isArray(parsed) || parsed.length === 0) {
          throw new Error('Format invalide')
        }
        const normalized: BlogArticle[] = parsed.map((item, idx) => ({
          id: String(item.id || `import-${idx}`),
          status: item.status === 'draft' ? 'draft' : 'published',
          category: CATEGORIES.includes(item.category)
            ? item.category
            : 'Guides & Ghana',
          featured: Boolean(item.featured),
          title: String(item.title || 'Article sans titre'),
          excerpt: String(item.excerpt || ''),
          readTime: String(item.readTime || '3 min'),
          publishedLabel: String(item.publishedLabel || formatCurrentDateLabel()),
          author: String(item.author || 'Bureau Exécutif AESMG'),
          authorRole: String(item.authorRole || 'Rédaction AESMG'),
          image: String(item.image || BLOG_COVER_GALLERY[0].url),
          tags: Array.isArray(item.tags) ? item.tags.map(String) : ['AESMG'],
          keyTakeaways: Array.isArray(item.keyTakeaways)
            ? item.keyTakeaways.map(String)
            : [],
          pullQuote: String(item.pullQuote || ''),
          sections: Array.isArray(item.sections) ? item.sections : [],
        }))
        onReplaceAllArticles(normalized)
        onShowToast(`${normalized.length} articles importés avec succès !`)
      } catch {
        onShowToast('Fichier JSON invalide — vérifiez votre sauvegarde.')
      }
    }
    reader.readAsText(file)
    e.target.value = ''
  }

  return (
    <AnimatePresence>
      {isOpen && (
        <div
          className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="blog-admin-title"
        >
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="absolute inset-0 bg-[#04140E]/82 backdrop-blur-sm"
          />

          {/* Main Admin Studio Shell */}
          <motion.div
            initial={{ y: '100%', opacity: 0.9 }}
            animate={{ y: 0, opacity: 1 }}
            exit={{ y: '100%', opacity: 0 }}
            transition={{ type: 'spring', stiffness: 300, damping: 32 }}
            className="relative z-10 flex h-[95svh] w-full max-w-5xl flex-col overflow-hidden rounded-t-[30px] bg-[#FAF8F3] shadow-[0_-24px_80px_rgba(0,0,0,0.48)] sm:h-[92vh] sm:rounded-[30px]"
          >
            <TricoloreRibbon />

            {/* Top Studio Header */}
            <div className="flex shrink-0 items-center justify-between gap-3 border-b border-[#0B3B2B]/12 bg-[#062319] px-4 py-3.5 text-[#FAF8F3] sm:px-6">
              <div className="flex items-center gap-3 min-w-0">
                {isUnlocked && view === 'editor' ? (
                  <button
                    type="button"
                    onClick={() => setView('list')}
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-[#F7EBC6] hover:bg-white/20"
                    aria-label="Retour à la liste des articles"
                  >
                    <ArrowLeft className="h-4 w-4" />
                  </button>
                ) : (
                  <AesmgSeal size={36} variant="dark" className="shrink-0" />
                )}
                <div className="min-w-0">
                  <p className="font-mono text-[9.5px] font-semibold uppercase tracking-[0.18em] text-[#E2B643]">
                    Espace Bureau Exécutif · CMS
                  </p>
                  <h2
                    id="blog-admin-title"
                    className="truncate font-editorial text-lg font-bold tracking-tight sm:text-xl"
                  >
                    {view === 'editor' && isUnlocked
                      ? editingId
                        ? 'Modifier l’article'
                        : 'Rédiger un nouvel article'
                      : 'Studio de Gestion du Blog AESMG'}
                  </h2>
                </div>
              </div>

              <div className="flex items-center gap-2">
                {isUnlocked && view === 'list' && (
                  <button
                    type="button"
                    onClick={openNewEditor}
                    className="inline-flex h-9 items-center gap-1.5 rounded-xl bg-[#E2B643] px-3.5 text-xs font-bold text-[#062319] shadow-sm transition-all hover:bg-[#ECC45A] active:scale-95"
                  >
                    <Plus className="h-4 w-4" />
                    <span className="hidden sm:inline">Nouvel article</span>
                    <span className="sm:hidden">Créer</span>
                  </button>
                )}

                <button
                  type="button"
                  onClick={onClose}
                  aria-label="Fermer le studio"
                  className="flex h-9 w-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-[#FAF8F3] hover:bg-white/20"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            </div>

            {/* BODY CONTENT */}
            {!isUnlocked ? (
              /* Executive Access Gate */
              <div className="flex flex-1 flex-col items-center justify-center overflow-y-auto p-6">
                <div className="w-full max-w-md rounded-[28px] border border-[#0B3B2B]/12 bg-white p-6 text-center shadow-[0_18px_50px_rgba(6,35,25,0.08)] sm:p-8">
                  <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#062319] text-[#E2B643] shadow-md">
                    <Lock className="h-6 w-6" />
                  </div>

                  <p className="mt-4 font-mono text-[10px] font-semibold uppercase tracking-[0.18em] text-[#9B7210]">
                    Accès Rédaction · AESMG
                  </p>
                  <h3 className="mt-1 font-editorial text-2xl font-bold text-[#062319]">
                    Gérer les publications du Blog
                  </h3>
                  <p className="mt-2 text-xs leading-relaxed text-[#465E54]">
                    Publiez, modifiez, passez en brouillon ou mettez « À la une »
                    les articles du journal AESMG depuis votre téléphone ou PC.
                  </p>

                  <form onSubmit={handleUnlockSubmit} className="mt-5 space-y-3">
                    <input
                      type="password"
                      value={pinInput}
                      onChange={(e) => {
                        setPinInput(e.target.value)
                        setPinError(false)
                      }}
                      placeholder="Code Bureau (par défaut : AESMG2026)"
                      className="min-h-[48px] w-full rounded-2xl border border-[#0B3B2B]/16 bg-[#FAF8F3] px-4 text-center font-mono text-sm font-semibold tracking-widest text-[#062319] focus:border-[#0B3B2B] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#E2B643]/40"
                    />

                    {pinError && (
                      <p className="text-xs font-semibold text-[#B91C2E]">
                        Code incorrect. Utilisez <span className="font-mono">AESMG2026</span>.
                      </p>
                    )}

                    <button
                      type="submit"
                      className="inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-2xl bg-[#062319] px-5 text-xs font-bold text-[#F7EBC6] transition-all hover:bg-[#0B3B2B] active:scale-[0.98]"
                    >
                      <Unlock className="h-4 w-4 text-[#E2B643]" />
                      <span>Déverrouiller le Studio</span>
                    </button>
                  </form>

                  <div className="mt-4 border-t border-[#0B3B2B]/10 pt-4">
                    <button
                      type="button"
                      onClick={() => {
                        setIsUnlocked(true)
                        onShowToast('Mode aperçu administrateur activé !')
                      }}
                      className="inline-flex items-center gap-1.5 text-xs font-bold text-[#0F513A] underline-offset-4 hover:underline"
                    >
                      <Sparkles className="h-3.5 w-3.5 text-[#C9961A]" />
                      <span>Accéder directement en mode démo (sans saisie)</span>
                    </button>
                  </div>
                </div>
              </div>
            ) : view === 'list' ? (
              /* DASHBOARD LIST VIEW */
              <div className="flex flex-1 flex-col overflow-y-auto px-4 py-5 sm:px-6">
                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-3.5">
                  <div className="rounded-2xl border border-[#0B3B2B]/10 bg-white p-3.5">
                    <p className="font-mono text-[9.5px] font-semibold uppercase tracking-wider text-[#5A7268]">
                      Total Articles
                    </p>
                    <p className="mt-1 font-editorial text-2xl font-bold text-[#062319]">
                      {stats.total}
                    </p>
                  </div>

                  <div className="rounded-2xl border border-[#007A4D]/20 bg-[#ECFDF5] p-3.5">
                    <p className="font-mono text-[9.5px] font-semibold uppercase tracking-wider text-[#007A4D]">
                      En ligne (Publiés)
                    </p>
                    <p className="mt-1 font-editorial text-2xl font-bold text-[#064E3B]">
                      {stats.published}
                    </p>
                  </div>

                  <div className="rounded-2xl border border-[#C9961A]/25 bg-[#FEF9E7] p-3.5">
                    <p className="font-mono text-[9.5px] font-semibold uppercase tracking-wider text-[#8C640B]">
                      Brouillons
                    </p>
                    <p className="mt-1 font-editorial text-2xl font-bold text-[#785305]">
                      {stats.drafts}
                    </p>
                  </div>

                  <div className="rounded-2xl border border-[#0B3B2B]/12 bg-[#062319] p-3.5 text-[#FAF8F3]">
                    <p className="font-mono text-[9.5px] font-semibold uppercase tracking-wider text-[#E2B643]">
                      À la une
                    </p>
                    <p className="mt-1.5 line-clamp-1 text-xs font-semibold text-[#F7EBC6]">
                      {stats.featuredTitle}
                    </p>
                  </div>
                </div>

                {/* Controls Toolbar */}
                <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex flex-1 items-center gap-2">
                    <div className="relative flex-1">
                      <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#647B71]" />
                      <input
                        type="search"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Filtrer par titre, auteur ou catégorie…"
                        className="min-h-[42px] w-full rounded-xl border border-[#0B3B2B]/14 bg-white pl-9 pr-3 text-xs text-[#062319] focus:border-[#0B3B2B] focus:outline-none"
                      />
                    </div>
                  </div>

                  <div className="flex flex-wrap items-center gap-1.5">
                    {(
                      [
                        ['all', 'Tous'],
                        ['published', 'Publiés'],
                        ['draft', 'Brouillons'],
                      ] as const
                    ).map(([key, label]) => (
                      <button
                        key={key}
                        type="button"
                        onClick={() => setFilterStatus(key)}
                        className={`rounded-xl px-3 py-2 text-[11px] font-semibold transition-all ${
                          filterStatus === key
                            ? 'bg-[#062319] text-[#F7EBC6]'
                            : 'border border-[#0B3B2B]/12 bg-white text-[#284238] hover:border-[#C9961A]'
                        }`}
                      >
                        {label}
                      </button>
                    ))}
                  </div>
                </div>

                {/* Articles Management List */}
                <div className="mt-4 space-y-3">
                  {filteredList.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-[#0B3B2B]/20 bg-white p-8 text-center">
                      <FileText className="mx-auto h-8 w-8 text-[#7E948B]" />
                      <p className="mt-2 font-editorial text-lg font-semibold text-[#062319]">
                        Aucun article trouvé
                      </p>
                      <button
                        type="button"
                        onClick={openNewEditor}
                        className="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-[#062319] px-4 py-2 text-xs font-bold text-[#F7EBC6]"
                      >
                        <Plus className="h-3.5 w-3.5 text-[#E2B643]" />
                        <span>Créer un article</span>
                      </button>
                    </div>
                  ) : (
                    filteredList.map((article) => {
                      const isPublished = article.status === 'published'
                      const isDeleting = confirmDeleteId === article.id

                      return (
                        <div
                          key={article.id}
                          className="flex flex-col gap-3 rounded-2xl border border-[#0B3B2B]/12 bg-white p-3.5 shadow-xs transition-all hover:border-[#C9961A]/45 sm:flex-row sm:items-center sm:p-4"
                        >
                          {/* Thumb + Meta */}
                          <div className="flex flex-1 items-start gap-3.5 min-w-0">
                            <img
                              src={article.image}
                              alt={article.title}
                              className="h-16 w-20 shrink-0 rounded-xl object-cover bg-[#062319]"
                            />
                            <div className="min-w-0 flex-1">
                              <div className="flex flex-wrap items-center gap-1.5">
                                <span
                                  className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-mono text-[9.5px] font-bold uppercase ${
                                    isPublished
                                      ? 'bg-[#DCFCE7] text-[#166534]'
                                      : 'bg-[#FEF3C7] text-[#92400E]'
                                  }`}
                                >
                                  <span
                                    className={`h-1.5 w-1.5 rounded-full ${
                                      isPublished ? 'bg-[#16A34A]' : 'bg-[#D97706]'
                                    }`}
                                  />
                                  {isPublished ? 'Publié' : 'Brouillon'}
                                </span>

                                <span className="rounded-full bg-[#0B3B2B]/7 px-2 py-0.5 font-mono text-[9.5px] font-semibold text-[#0B3B2B]">
                                  {article.category}
                                </span>

                                {article.featured && (
                                  <span className="inline-flex items-center gap-1 rounded-full bg-[#E2B643]/25 px-2 py-0.5 font-mono text-[9.5px] font-bold text-[#7D5708]">
                                    <Star className="h-2.5 w-2.5 fill-[#C9961A] text-[#C9961A]" />
                                    À la une
                                  </span>
                                )}
                              </div>

                              <h4 className="mt-1.5 line-clamp-1 font-editorial text-base font-bold text-[#062319]">
                                {article.title}
                              </h4>

                              <p className="mt-0.5 text-[11.5px] text-[#51695F]">
                                {article.author} · {article.readTime} · {article.publishedLabel}
                              </p>
                            </div>
                          </div>

                          {/* Action Buttons */}
                          <div className="flex flex-wrap items-center justify-end gap-1.5 border-t border-[#0B3B2B]/8 pt-2.5 sm:border-t-0 sm:pt-0">
                            <button
                              type="button"
                              onClick={() => onSetFeatured(article.id)}
                              title="Définir comme article À la une"
                              className={`inline-flex h-9 items-center gap-1 rounded-xl border px-2.5 text-[11px] font-semibold transition-colors ${
                                article.featured
                                  ? 'border-[#C9961A] bg-[#F7EBC6] text-[#062319]'
                                  : 'border-[#0B3B2B]/12 bg-[#FAF8F3] text-[#355046] hover:border-[#C9961A]'
                              }`}
                            >
                              <Star
                                className={`h-3.5 w-3.5 ${
                                  article.featured ? 'fill-[#C9961A] text-[#C9961A]' : ''
                                }`}
                              />
                              <span className="hidden md:inline">Une</span>
                            </button>

                            <button
                              type="button"
                              onClick={() => onToggleStatus(article.id)}
                              title={
                                isPublished
                                  ? 'Repasser en brouillon'
                                  : 'Publier sur le site'
                              }
                              className="inline-flex h-9 items-center gap-1 rounded-xl border border-[#0B3B2B]/12 bg-[#FAF8F3] px-2.5 text-[11px] font-semibold text-[#062319] hover:border-[#0B3B2B]"
                            >
                              {isPublished ? (
                                <>
                                  <EyeOff className="h-3.5 w-3.5 text-[#92400E]" />
                                  <span>Masquer</span>
                                </>
                              ) : (
                                <>
                                  <Globe className="h-3.5 w-3.5 text-[#15803D]" />
                                  <span>Publier</span>
                                </>
                              )}
                            </button>

                            <button
                              type="button"
                              onClick={() => onPreviewArticle(article)}
                              title="Prévisualiser dans le lecteur"
                              className="flex h-9 w-9 items-center justify-center rounded-xl border border-[#0B3B2B]/12 bg-[#FAF8F3] text-[#062319] hover:border-[#C9961A]"
                            >
                              <Eye className="h-3.5 w-3.5" />
                            </button>

                            <button
                              type="button"
                              onClick={() => openEditEditor(article)}
                              title="Modifier l’article"
                              className="inline-flex h-9 items-center gap-1 rounded-xl bg-[#062319] px-3 text-[11px] font-bold text-[#F7EBC6] hover:bg-[#0B3B2B]"
                            >
                              <Pencil className="h-3.5 w-3.5 text-[#E2B643]" />
                              <span>Éditer</span>
                            </button>

                            {isDeleting ? (
                              <button
                                type="button"
                                onClick={() => {
                                  onDeleteArticle(article.id)
                                  setConfirmDeleteId(null)
                                }}
                                className="inline-flex h-9 items-center rounded-xl bg-[#B91C2E] px-3 text-[11px] font-bold text-white"
                              >
                                Confirmer ?
                              </button>
                            ) : (
                              <button
                                type="button"
                                onClick={() => setConfirmDeleteId(article.id)}
                                title="Supprimer l’article"
                                className="flex h-9 w-9 items-center justify-center rounded-xl border border-[#B91C2E]/20 bg-[#FEF2F2] text-[#B91C2E] hover:bg-[#FEE2E2]"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            )}
                          </div>
                        </div>
                      )
                    })
                  )}
                </div>

                {/* Backup & Sync Utilities Footer */}
                <div className="mt-6 flex flex-wrap items-center justify-between gap-2.5 rounded-2xl border border-[#0B3B2B]/10 bg-[#F3EFE5] p-3.5">
                  <p className="text-[11.5px] font-medium text-[#3C554B]">
                    Sauvegarde locale automatique active · Partagez vos articles entre appareils via JSON.
                  </p>
                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      onClick={handleExportJson}
                      className="inline-flex h-9 items-center gap-1.5 rounded-xl border border-[#0B3B2B]/15 bg-white px-3 text-[11px] font-semibold text-[#062319] hover:border-[#C9961A]"
                    >
                      <Download className="h-3.5 w-3.5" />
                      <span>Exporter JSON</span>
                    </button>

                    <button
                      type="button"
                      onClick={() => importJsonRef.current?.click()}
                      className="inline-flex h-9 items-center gap-1.5 rounded-xl border border-[#0B3B2B]/15 bg-white px-3 text-[11px] font-semibold text-[#062319] hover:border-[#C9961A]"
                    >
                      <Upload className="h-3.5 w-3.5" />
                      <span>Importer JSON</span>
                    </button>
                    <input
                      ref={importJsonRef}
                      type="file"
                      accept="application/json"
                      onChange={handleImportJson}
                      className="hidden"
                    />

                    {confirmReset ? (
                      <button
                        type="button"
                        onClick={() => {
                          onResetDefaults()
                          setConfirmReset(false)
                        }}
                        className="inline-flex h-9 items-center gap-1.5 rounded-xl bg-[#B91C2E] px-3 text-[11px] font-bold text-white"
                      >
                        Confirmer réinitialisation
                      </button>
                    ) : (
                      <button
                        type="button"
                        onClick={() => setConfirmReset(true)}
                        className="inline-flex h-9 items-center gap-1.5 rounded-xl border border-[#0B3B2B]/15 bg-white px-3 text-[11px] font-semibold text-[#5A7268] hover:text-[#B91C2E]"
                      >
                        <RotateCcw className="h-3.5 w-3.5" />
                        <span>Réinitialiser</span>
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ) : (
              /* FULL ARTICLE COMPOSER / EDITOR VIEW */
              <div className="flex flex-1 flex-col overflow-hidden">
                <div className="flex-1 overflow-y-auto px-4 py-5 sm:px-6">
                  <div className="mx-auto max-w-3xl space-y-5">
                    {/* Title & Category */}
                    <div className="rounded-2xl border border-[#0B3B2B]/10 bg-white p-4 sm:p-5">
                      <label className="block text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                        Titre de l’article *
                      </label>
                      <input
                        type="text"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder="Ex. Journée culturelle malienne à Accra : retour en images et temps forts"
                        className="mt-1.5 min-h-[48px] w-full rounded-xl border border-[#0B3B2B]/15 bg-[#FAF8F3] px-3.5 text-sm font-semibold text-[#062319] focus:border-[#0B3B2B] focus:bg-white focus:outline-none"
                      />

                      <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                          <label className="block text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                            Catégorie
                          </label>
                          <select
                            value={category}
                            onChange={(e) =>
                              setCategory(e.target.value as BlogCategory)
                            }
                            className="mt-1.5 min-h-[44px] w-full rounded-xl border border-[#0B3B2B]/15 bg-[#FAF8F3] px-3 text-xs font-semibold text-[#062319]"
                          >
                            {CATEGORIES.map((c) => (
                              <option key={c} value={c}>
                                {c}
                              </option>
                            ))}
                          </select>
                        </div>

                        <div>
                          <label className="block text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                            Statut
                          </label>
                          <select
                            value={status}
                            onChange={(e) =>
                              setStatus(e.target.value as BlogStatus)
                            }
                            className="mt-1.5 min-h-[44px] w-full rounded-xl border border-[#0B3B2B]/15 bg-[#FAF8F3] px-3 text-xs font-semibold text-[#062319]"
                          >
                            <option value="published">Publié (Visible)</option>
                            <option value="draft">Brouillon (Masqué)</option>
                          </select>
                        </div>

                        <div className="flex items-end">
                          <button
                            type="button"
                            onClick={() => setFeatured((prev) => !prev)}
                            className={`flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border text-xs font-bold transition-all ${
                              featured
                                ? 'border-[#C9961A] bg-[#F7EBC6] text-[#062319]'
                                : 'border-[#0B3B2B]/15 bg-[#FAF8F3] text-[#3E554B]'
                            }`}
                          >
                            <Star
                              className={`h-4 w-4 ${
                                featured ? 'fill-[#C9961A] text-[#C9961A]' : ''
                              }`}
                            />
                            <span>{featured ? 'À la une activé' : 'Mettre à la une'}</span>
                          </button>
                        </div>
                      </div>
                    </div>

                    {/* Author Presets */}
                    <div className="rounded-2xl border border-[#0B3B2B]/10 bg-white p-4 sm:p-5">
                      <p className="text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                        Signature / Auteur
                      </p>
                      <div className="mt-2 flex flex-wrap gap-1.5">
                        {BLOG_AUTHOR_PRESETS.map((preset) => (
                          <button
                            key={preset.author}
                            type="button"
                            onClick={() => {
                              setAuthor(preset.author)
                              setAuthorRole(preset.role)
                            }}
                            className={`rounded-full px-3 py-1.5 text-[11px] font-semibold transition-all ${
                              author === preset.author
                                ? 'bg-[#062319] text-[#F7EBC6]'
                                : 'border border-[#0B3B2B]/12 bg-[#FAF8F3] text-[#284238] hover:border-[#C9961A]'
                            }`}
                          >
                            {preset.author}
                          </button>
                        ))}
                      </div>
                      <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input
                          type="text"
                          value={author}
                          onChange={(e) => setAuthor(e.target.value)}
                          placeholder="Nom de l’auteur"
                          className="min-h-[42px] rounded-xl border border-[#0B3B2B]/14 bg-[#FAF8F3] px-3 text-xs font-medium text-[#062319]"
                        />
                        <input
                          type="text"
                          value={authorRole}
                          onChange={(e) => setAuthorRole(e.target.value)}
                          placeholder="Fonction / Pôle"
                          className="min-h-[42px] rounded-xl border border-[#0B3B2B]/14 bg-[#FAF8F3] px-3 text-xs font-medium text-[#062319]"
                        />
                      </div>
                    </div>

                    {/* Cover Image Picker & Upload */}
                    <div className="rounded-2xl border border-[#0B3B2B]/10 bg-white p-4 sm:p-5">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                          <ImageIcon className="h-3.5 w-3.5 text-[#C9961A]" />
                          Photo de couverture
                        </p>
                        <button
                          type="button"
                          onClick={() => fileInputRef.current?.click()}
                          className="inline-flex items-center gap-1.5 rounded-xl bg-[#062319] px-3 py-1.5 text-[11px] font-bold text-[#F7EBC6] hover:bg-[#0B3B2B]"
                        >
                          <Upload className="h-3.5 w-3.5 text-[#E2B643]" />
                          <span>Importer depuis tél / PC</span>
                        </button>
                        <input
                          ref={fileInputRef}
                          type="file"
                          accept="image/*"
                          onChange={handleCoverUpload}
                          className="hidden"
                        />
                      </div>

                      <div className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
                        {BLOG_COVER_GALLERY.map((item) => {
                          const selected = image === item.url
                          return (
                            <button
                              key={item.url}
                              type="button"
                              onClick={() => setImage(item.url)}
                              className={`relative overflow-hidden rounded-xl border-2 transition-all ${
                                selected
                                  ? 'border-[#C9961A] ring-2 ring-[#E2B643]/40'
                                  : 'border-transparent opacity-80 hover:opacity-100'
                              }`}
                              title={item.label}
                            >
                              <img
                                src={item.url}
                                alt={item.label}
                                className="h-14 w-full object-cover"
                              />
                              {selected && (
                                <span className="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-[#E2B643] text-[#062319]">
                                  <CheckCircle2 className="h-3 w-3" />
                                </span>
                              )}
                            </button>
                          )
                        })}
                      </div>

                      {image && (
                        <div className="mt-3 overflow-hidden rounded-xl border border-[#0B3B2B]/10">
                          <img
                            src={image}
                            alt="Aperçu couverture"
                            className="h-36 w-full object-cover"
                          />
                        </div>
                      )}
                    </div>

                    {/* Excerpt, Quote, Key Takeaways, Tags */}
                    <div className="space-y-3.5 rounded-2xl border border-[#0B3B2B]/10 bg-white p-4 sm:p-5">
                      <div>
                        <label className="block text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                          Résumé / Extrait *
                        </label>
                        <textarea
                          rows={2}
                          value={excerpt}
                          onChange={(e) => setExcerpt(e.target.value)}
                          placeholder="Un résumé accrocheur affiché sur la carte de l’article…"
                          className="mt-1.5 w-full rounded-xl border border-[#0B3B2B]/14 bg-[#FAF8F3] p-3 text-xs leading-relaxed text-[#062319] focus:border-[#0B3B2B] focus:bg-white focus:outline-none"
                        />
                      </div>

                      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                          <label className="block text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                            Citation mise en avant
                          </label>
                          <textarea
                            rows={2}
                            value={pullQuote}
                            onChange={(e) => setPullQuote(e.target.value)}
                            placeholder="Phrase forte affichée dans l’encadré vert émeraude…"
                            className="mt-1.5 w-full rounded-xl border border-[#0B3B2B]/14 bg-[#FAF8F3] p-3 text-xs leading-relaxed text-[#062319]"
                          />
                        </div>

                        <div>
                          <label className="block text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                            Points « À retenir » (1 par ligne)
                          </label>
                          <textarea
                            rows={2}
                            value={takeawaysText}
                            onChange={(e) => setTakeawaysText(e.target.value)}
                            placeholder={'Premier point essentiel\nDeuxième point essentiel'}
                            className="mt-1.5 w-full rounded-xl border border-[#0B3B2B]/14 bg-[#FAF8F3] p-3 text-xs leading-relaxed text-[#062319]"
                          />
                        </div>
                      </div>

                      <div>
                        <label className="block text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                          Mots-clés (séparés par des virgules)
                        </label>
                        <input
                          type="text"
                          value={tagsText}
                          onChange={(e) => setTagsText(e.target.value)}
                          placeholder="Accra, Bourses, Culture, Stage"
                          className="mt-1.5 min-h-[42px] w-full rounded-xl border border-[#0B3B2B]/14 bg-[#FAF8F3] px-3 text-xs text-[#062319]"
                        />
                      </div>
                    </div>

                    {/* Dynamic Article Sections Editor */}
                    <div className="rounded-2xl border border-[#0B3B2B]/10 bg-white p-4 sm:p-5">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-[11px] font-bold uppercase tracking-wider text-[#264237]">
                            Contenu de l’article · Temps estimé :{' '}
                            <span className="font-mono text-[#9B7210]">
                              {estimateReadTime(excerpt, sections)}
                            </span>
                          </p>
                        </div>
                        <button
                          type="button"
                          onClick={() =>
                            setSections((prev) => [
                              ...prev,
                              {
                                id: `sec-${Date.now()}`,
                                heading: `Partie ${prev.length + 1}`,
                                body: '',
                              },
                            ])
                          }
                          className="inline-flex items-center gap-1 rounded-xl border border-[#0B3B2B]/16 bg-[#FAF8F3] px-3 py-1.5 text-[11px] font-bold text-[#062319] hover:border-[#C9961A]"
                        >
                          <Plus className="h-3.5 w-3.5" />
                          <span>Ajouter une section</span>
                        </button>
                      </div>

                      <div className="mt-3 space-y-3">
                        {sections.map((sec, idx) => (
                          <div
                            key={sec.id}
                            className="rounded-xl border border-[#0B3B2B]/10 bg-[#FAF8F3] p-3"
                          >
                            <div className="flex items-center gap-2">
                              <input
                                type="text"
                                value={sec.heading}
                                onChange={(e) =>
                                  setSections((prev) =>
                                    prev.map((s) =>
                                      s.id === sec.id
                                        ? { ...s, heading: e.target.value }
                                        : s
                                    )
                                  )
                                }
                                placeholder={`Sous-titre ${idx + 1}`}
                                className="min-h-[38px] flex-1 rounded-lg border border-[#0B3B2B]/12 bg-white px-3 text-xs font-bold text-[#062319]"
                              />
                              {sections.length > 1 && (
                                <button
                                  type="button"
                                  onClick={() =>
                                    setSections((prev) =>
                                      prev.filter((s) => s.id !== sec.id)
                                    )
                                  }
                                  className="flex h-9 w-9 items-center justify-center rounded-lg text-[#B91C2E] hover:bg-[#FEE2E2]"
                                  aria-label="Supprimer cette section"
                                >
                                  <Trash2 className="h-3.5 w-3.5" />
                                </button>
                              )}
                            </div>
                            <textarea
                              rows={4}
                              value={sec.body}
                              onChange={(e) =>
                                setSections((prev) =>
                                  prev.map((s) =>
                                    s.id === sec.id
                                      ? { ...s, body: e.target.value }
                                      : s
                                  )
                                )
                              }
                              placeholder="Rédigez vos paragraphes ici (sautez une ligne pour séparer deux paragraphes)…"
                              className="mt-2 w-full rounded-lg border border-[#0B3B2B]/12 bg-white p-3 text-xs leading-relaxed text-[#062319]"
                            />
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Sticky Editor Bottom Save Bar */}
                <div className="flex shrink-0 flex-wrap items-center justify-between gap-2.5 border-t border-[#0B3B2B]/12 bg-white px-4 py-3 pb-safe sm:px-6">
                  <button
                    type="button"
                    onClick={() => setView('list')}
                    className="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-[#0B3B2B]/14 bg-[#FAF8F3] px-4 text-xs font-semibold text-[#3C554B]"
                  >
                    Annuler
                  </button>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => handleSave('draft')}
                      className="inline-flex min-h-[44px] items-center justify-center gap-1.5 rounded-xl border border-[#C9961A]/50 bg-[#FEF9E7] px-4 text-xs font-bold text-[#785305] hover:bg-[#F7EBC6]"
                    >
                      <FileText className="h-3.5 w-3.5" />
                      <span>Enregistrer brouillon</span>
                    </button>

                    <button
                      type="button"
                      onClick={() => handleSave('published')}
                      className="inline-flex min-h-[44px] items-center justify-center gap-1.5 rounded-xl bg-[#062319] px-5 text-xs font-bold text-[#F7EBC6] shadow-sm hover:bg-[#0B3B2B] active:scale-[0.98]"
                    >
                      <Send className="h-3.5 w-3.5 text-[#E2B643]" />
                      <span>Publier maintenant</span>
                    </button>
                  </div>
                </div>
              </div>
            )}
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}
