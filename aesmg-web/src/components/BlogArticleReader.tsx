import React, { useEffect, useRef, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  X,
  Clock,
  Bookmark,
  MessageCircle,
  Copy,
  CheckCircle2,
  ArrowRight,
  Quote,
} from 'lucide-react'
import { type BlogArticle } from '../data/blogData'
import { TricoloreRibbon } from './BrandMarks'

interface BlogArticleReaderProps {
  article: BlogArticle | null
  allArticles: BlogArticle[]
  isBookmarked: boolean
  onToggleBookmark: (articleId: string) => void
  onSelectArticle: (article: BlogArticle) => void
  onClose: () => void
  onShowToast: (message: string) => void
}

export const BlogArticleReader: React.FC<BlogArticleReaderProps> = ({
  article,
  allArticles,
  isBookmarked,
  onToggleBookmark,
  onSelectArticle,
  onClose,
  onShowToast,
}) => {
  const scrollContainerRef = useRef<HTMLDivElement | null>(null)
  const [readProgress, setReadProgress] = useState(0)

  useEffect(() => {
    if (!article) return
    const originalOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => {
      document.body.style.overflow = originalOverflow
      window.removeEventListener('keydown', handleKey)
    }
  }, [article, onClose])

  useEffect(() => {
    if (scrollContainerRef.current) {
      scrollContainerRef.current.scrollTo({ top: 0, behavior: 'auto' })
      setReadProgress(0)
    }
  }, [article?.id])

  const handleScroll = () => {
    const el = scrollContainerRef.current
    if (!el) return
    const maxScroll = el.scrollHeight - el.clientHeight
    if (maxScroll <= 0) {
      setReadProgress(100)
      return
    }
    setReadProgress(Math.min(100, Math.round((el.scrollTop / maxScroll) * 100)))
  }

  const handleShareWhatsApp = () => {
    if (!article) return
    const text = `📖 Blog AESMG — ${article.title}\n\n${article.excerpt}\n\nDécouvrir sur le site officiel de l'Association des Étudiants et Stagiaires Maliens au Ghana (AESMG) : ${window.location.href}`
    window.open(
      `https://wa.me/?text=${encodeURIComponent(text)}`,
      '_blank',
      'noopener,noreferrer'
    )
  }

  const handleCopyArticle = async () => {
    if (!article) return
    const summary = `${article.title} — Blog AESMG (${window.location.href})`
    try {
      await navigator.clipboard.writeText(summary)
      onShowToast('Résumé et lien de l’article copiés !')
    } catch {
      onShowToast('Article prêt à être partagé.')
    }
  }

  const relatedArticles = article
    ? allArticles.filter((a) => a.id !== article.id).slice(0, 2)
    : []

  return (
    <AnimatePresence>
      {article && (
        <div
          className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="blog-reader-title"
        >
          {/* Dimmed backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="absolute inset-0 bg-[#04140E]/78 backdrop-blur-sm"
          />

          {/* Mobile-first Editorial Reader Sheet */}
          <motion.div
            initial={{ y: '100%', opacity: 0.9 }}
            animate={{ y: 0, opacity: 1 }}
            exit={{ y: '100%', opacity: 0 }}
            transition={{ type: 'spring', stiffness: 310, damping: 32 }}
            className="relative z-10 flex h-[94svh] w-full max-w-2xl flex-col overflow-hidden rounded-t-[30px] bg-[#FAF8F3] shadow-[0_-24px_70px_rgba(0,0,0,0.45)] sm:h-[90vh] sm:rounded-[30px]"
          >
            <TricoloreRibbon />

            {/* Sticky Top Reader Bar */}
            <div className="relative flex shrink-0 items-center justify-between border-b border-[#0B3B2B]/10 bg-[#FAF8F3]/95 px-4 py-3 backdrop-blur-md sm:px-6">
              <div className="flex items-center gap-2">
                <span className="rounded-full bg-[#062319] px-2.5 py-1 font-mono text-[10px] font-semibold uppercase tracking-wider text-[#F7EBC6]">
                  {article.category}
                </span>
                <span className="inline-flex items-center gap-1 font-mono text-[10.5px] text-[#4C655B]">
                  <Clock className="h-3 w-3 text-[#C9961A]" />
                  {article.readTime}
                </span>
              </div>

              <div className="flex items-center gap-1.5">
                <button
                  type="button"
                  onClick={() => onToggleBookmark(article.id)}
                  aria-label={
                    isBookmarked
                      ? 'Retirer des articles enregistrés'
                      : 'Enregistrer cet article'
                  }
                  className={`flex h-9 w-9 items-center justify-center rounded-full border transition-colors ${
                    isBookmarked
                      ? 'border-[#C9961A] bg-[#F7EBC6] text-[#062319]'
                      : 'border-[#0B3B2B]/12 bg-white text-[#284238] hover:border-[#C9961A]'
                  }`}
                >
                  <Bookmark
                    className={`h-4 w-4 ${isBookmarked ? 'fill-[#C9961A] text-[#062319]' : ''}`}
                  />
                </button>

                <button
                  type="button"
                  onClick={onClose}
                  aria-label="Fermer l’article"
                  className="flex h-9 w-9 items-center justify-center rounded-full bg-[#0B3B2B]/8 text-[#062319] transition-colors hover:bg-[#0B3B2B]/16"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>

              {/* Live Article Reading Progress Bar */}
              <div className="absolute inset-x-0 bottom-0 h-[2px] bg-[#0B3B2B]/8">
                <div
                  className="h-full bg-gradient-to-r from-[#007A4D] via-[#E2B643] to-[#C9961A] transition-[width] duration-150"
                  style={{ width: `${readProgress}%` }}
                />
              </div>
            </div>

            {/* Scrollable Article Content */}
            <div
              ref={scrollContainerRef}
              onScroll={handleScroll}
              className="flex-1 overflow-y-auto px-4 pb-8 pt-5 sm:px-7"
            >
              {/* Cover Image */}
              <div className="relative aspect-[16/9] w-full overflow-hidden rounded-[22px] bg-[#062319] shadow-md">
                <img
                  src={article.image}
                  alt={article.title}
                  className="h-full w-full object-cover"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-[#062319]/60 via-transparent to-transparent" />
                <span className="absolute bottom-3 left-3.5 rounded-full bg-[#062319]/80 px-3 py-1 font-mono text-[10px] font-medium text-[#F7EBC6] backdrop-blur-md">
                  {article.publishedLabel}
                </span>
              </div>

              {/* Title & Author */}
              <h2
                id="blog-reader-title"
                className="mt-5 font-editorial text-2xl leading-[1.18] font-semibold tracking-tight text-[#062319] sm:text-3xl"
              >
                {article.title}
              </h2>

              <div className="mt-3.5 flex items-center justify-between gap-3 border-y border-[#0B3B2B]/10 py-3">
                <div>
                  <p className="text-xs font-bold text-[#062319]">
                    {article.author}
                  </p>
                  <p className="text-[11px] text-[#536B61]">
                    {article.authorRole}
                  </p>
                </div>
                <div className="flex flex-wrap justify-end gap-1.5">
                  {article.tags.slice(0, 3).map((tag) => (
                    <span
                      key={tag}
                      className="rounded-lg bg-[#0B3B2B]/6 px-2 py-0.5 text-[10.5px] font-semibold text-[#0B3B2B]"
                    >
                      #{tag}
                    </span>
                  ))}
                </div>
              </div>

              {/* Key Takeaways Box */}
              <div className="mt-5 rounded-2xl border border-[#C9961A]/35 bg-[#F7EBC6]/35 p-4 sm:p-5">
                <p className="font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-[#8C640B]">
                  À retenir en 3 points
                </p>
                <ul className="mt-2.5 space-y-2">
                  {article.keyTakeaways.map((point) => (
                    <li
                      key={point}
                      className="flex items-start gap-2.5 text-[13px] leading-snug text-[#173026]"
                    >
                      <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-[#0B3B2B]" />
                      <span>{point}</span>
                    </li>
                  ))}
                </ul>
              </div>

              {/* Editorial Pull Quote */}
              <blockquote className="my-6 rounded-2xl bg-[#062319] p-5 text-[#FAF8F3]">
                <Quote className="h-5 w-5 text-[#E2B643]" />
                <p className="mt-2 font-editorial text-lg leading-snug italic text-[#F7EBC6]">
                  « {article.pullQuote} »
                </p>
              </blockquote>

              {/* Structured Sections */}
              <div className="space-y-6">
                {article.sections.map((sec) => (
                  <div key={sec.heading}>
                    <h3 className="font-editorial text-xl font-semibold text-[#062319]">
                      {sec.heading}
                    </h3>
                    <div className="mt-2.5 space-y-3 text-[14.5px] leading-[1.72] text-[#2B4339]">
                      {sec.paragraphs.map((p, idx) => (
                        <p key={idx}>{p}</p>
                      ))}
                    </div>
                  </div>
                ))}
              </div>

              {/* Related Articles */}
              {relatedArticles.length > 0 && (
                <div className="mt-8 border-t border-[#0B3B2B]/12 pt-6">
                  <p className="font-mono text-[10.5px] font-semibold uppercase tracking-[0.18em] text-[#9B7210]">
                    Continuer la lecture
                  </p>
                  <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {relatedArticles.map((rel) => (
                      <button
                        key={rel.id}
                        type="button"
                        onClick={() => onSelectArticle(rel)}
                        className="group flex items-center gap-3 rounded-2xl border border-[#0B3B2B]/10 bg-white p-3 text-left transition-all hover:border-[#C9961A]/60"
                      >
                        <img
                          src={rel.image}
                          alt={rel.title}
                          className="h-14 w-14 shrink-0 rounded-xl object-cover"
                        />
                        <div className="min-w-0 flex-1">
                          <p className="font-mono text-[9.5px] font-semibold uppercase text-[#9B7210]">
                            {rel.category} · {rel.readTime}
                          </p>
                          <p className="mt-0.5 line-clamp-2 text-xs font-bold leading-snug text-[#062319] group-hover:text-[#0F513A]">
                            {rel.title}
                          </p>
                        </div>
                        <ArrowRight className="h-4 w-4 shrink-0 text-[#0B3B2B]/40 group-hover:text-[#062319]" />
                      </button>
                    ))}
                  </div>
                </div>
              )}
            </div>

            {/* Bottom Share Action Bar */}
            <div className="flex shrink-0 items-center justify-between gap-2.5 border-t border-[#0B3B2B]/10 bg-white px-4 py-3 pb-safe sm:px-6">
              <button
                type="button"
                onClick={handleShareWhatsApp}
                className="inline-flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-xl bg-[#062319] px-4 py-2.5 text-xs font-bold text-[#F7EBC6] transition-colors hover:bg-[#0B3B2B] active:scale-[0.98]"
              >
                <MessageCircle className="h-4 w-4 text-[#6EE79D]" />
                <span>Partager sur WhatsApp</span>
              </button>
              <button
                type="button"
                onClick={handleCopyArticle}
                className="inline-flex min-h-[44px] items-center justify-center gap-1.5 rounded-xl border border-[#0B3B2B]/14 bg-[#FAF8F3] px-3.5 py-2.5 text-xs font-semibold text-[#062319] transition-colors hover:border-[#C9961A] active:scale-95"
              >
                <Copy className="h-4 w-4" />
                <span>Copier</span>
              </button>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}
