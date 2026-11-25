import React, { useMemo, useState } from 'react'
import { motion } from 'framer-motion'
import {
  BookOpen,
  Clock,
  Bookmark,
  Search,
  ArrowUpRight,
  PenLine,
  Sparkles,
  Settings2,
} from 'lucide-react'
import {
  BLOG_CATEGORIES,
  type BlogArticle,
  type BlogCategory,
} from '../data/blogData'
import { EXECUTIVE_BUREAU, buildWhatsAppLink } from '../data/aesmgData'
import { BogolanDivider } from './BrandMarks'

interface BlogSectionProps {
  articles: BlogArticle[]
  bookmarkedIds: string[]
  onToggleBookmark: (articleId: string) => void
  onOpenArticle: (article: BlogArticle) => void
  onOpenAdmin: () => void
}

export const BlogSection: React.FC<BlogSectionProps> = ({
  articles,
  bookmarkedIds,
  onToggleBookmark,
  onOpenArticle,
  onOpenAdmin,
}) => {
  const [selectedCategory, setSelectedCategory] = useState<'Tous' | BlogCategory>('Tous')
  const [searchQuery, setSearchQuery] = useState('')
  const [showSavedOnly, setShowSavedOnly] = useState(false)

  const publishedArticles = useMemo(
    () => articles.filter((a) => a.status === 'published'),
    [articles]
  )

  const filteredArticles = useMemo(() => {
    const q = searchQuery.trim().toLowerCase()
    return publishedArticles.filter((article) => {
      if (showSavedOnly && !bookmarkedIds.includes(article.id)) return false
      if (selectedCategory !== 'Tous' && article.category !== selectedCategory) {
        return false
      }
      if (!q) return true
      return (
        article.title.toLowerCase().includes(q) ||
        article.excerpt.toLowerCase().includes(q) ||
        article.tags.some((t) => t.toLowerCase().includes(q))
      )
    })
  }, [publishedArticles, selectedCategory, searchQuery, showSavedOnly, bookmarkedIds])

  const featuredArticle =
    selectedCategory === 'Tous' && !searchQuery.trim() && !showSavedOnly
      ? filteredArticles.find((a) => a.featured) ?? filteredArticles[0]
      : null

  const gridArticles = featuredArticle
    ? filteredArticles.filter((a) => a.id !== featuredArticle.id)
    : filteredArticles

  const secretary = EXECUTIVE_BUREAU[1]
  const contributeWhatsAppHref = buildWhatsAppLink(
    secretary.whatsappNumber,
    `Bonjour ${secretary.name}, je souhaite proposer un article ou un témoignage pour le Blog de l'AESMG.`
  )

  return (
    <section
      id="blog"
      className="relative bg-[#FAF8F3] bg-bogolan-subtle px-4 py-16 sm:px-6 md:py-22"
    >
      <div className="mx-auto max-w-6xl">
        {/* Editorial Blog Header */}
        <div className="mx-auto max-w-2xl text-center">
          <span className="inline-flex items-center gap-2 rounded-full border border-[#0B3B2B]/14 bg-white px-3.5 py-1.5 font-mono text-[10.5px] font-semibold uppercase tracking-[0.18em] text-[#0B3B2B] shadow-xs">
            <BookOpen className="h-3.5 w-3.5 text-[#C9961A]" />
            Le Journal · Blog AESMG
          </span>

          <h2 className="mt-4 font-editorial text-[1.95rem] leading-[1.12] font-semibold tracking-tight text-[#062319] sm:text-4xl">
            Récits, Guides &{' '}
            <span className="italic text-[#0F513A]">Vie au Ghana</span>
          </h2>

          <p className="mt-3 text-[14.5px] leading-relaxed text-[#425A50] sm:text-base">
            Conseils d’installation, orientation académique, culture malienne et
            accompagnement des étudiants et stagiaires au Ghana.
          </p>

          <BogolanDivider className="mx-auto mt-6 max-w-xs" />
        </div>

        {/* Mobile-First Search + Saved Toggle + Category Filter Bar */}
        <div className="mt-8 flex flex-col gap-3">
          <div className="flex items-center gap-2">
            <div className="relative flex-1">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#647B71]" />
              <input
                type="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Rechercher un guide, Accra, bilinguisme, droits…"
                aria-label="Rechercher dans le blog AESMG"
                className="min-h-[46px] w-full rounded-2xl border border-[#0B3B2B]/14 bg-white pl-10 pr-4 text-sm text-[#062319] placeholder:text-[#7E948B] shadow-xs focus:border-[#0B3B2B] focus:outline-none focus:ring-2 focus:ring-[#E2B643]/40"
              />
            </div>

            <button
              type="button"
              onClick={() => setShowSavedOnly((prev) => !prev)}
              className={`inline-flex min-h-[46px] shrink-0 items-center gap-1.5 rounded-2xl border px-3.5 text-xs font-semibold transition-all active:scale-95 ${
                showSavedOnly
                  ? 'border-[#C9961A] bg-[#062319] text-[#F7EBC6]'
                  : 'border-[#0B3B2B]/14 bg-white text-[#233D33] hover:border-[#C9961A]/60'
              }`}
              title="Afficher vos articles enregistrés"
            >
              <Bookmark
                className={`h-4 w-4 ${showSavedOnly ? 'fill-[#E2B643] text-[#E2B643]' : ''}`}
              />
              <span className="hidden sm:inline">Favoris</span>
              {bookmarkedIds.length > 0 && (
                <span
                  className={`inline-flex h-5 min-w-[20px] items-center justify-center rounded-full px-1 font-mono text-[10px] font-bold ${
                    showSavedOnly
                      ? 'bg-[#E2B643] text-[#062319]'
                      : 'bg-[#0B3B2B]/10 text-[#062319]'
                  }`}
                >
                  {bookmarkedIds.length}
                </span>
              )}
            </button>

            <button
              type="button"
              onClick={onOpenAdmin}
              className="inline-flex min-h-[46px] shrink-0 items-center gap-1.5 rounded-2xl border border-[#C9961A]/50 bg-[#F7EBC6]/75 px-3.5 text-xs font-bold text-[#062319] transition-all hover:bg-[#F7EBC6] active:scale-95"
              title="Ouvrir le Studio de Gestion du Blog (Espace Bureau)"
            >
              <Settings2 className="h-4 w-4 text-[#8C640B]" />
              <span className="hidden sm:inline">Gérer le Blog</span>
              <span className="sm:hidden">Gérer</span>
            </button>
          </div>

          {/* Horizontal Category Pills */}
          <div className="no-scrollbar -mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
            {BLOG_CATEGORIES.map((category) => {
              const active = selectedCategory === category
              return (
                <button
                  key={category}
                  type="button"
                  onClick={() => {
                    setSelectedCategory(category)
                    setShowSavedOnly(false)
                  }}
                  className={`shrink-0 rounded-full px-4 py-2 text-xs font-semibold transition-all active:scale-95 ${
                    active
                      ? 'bg-[#062319] text-[#F7EBC6] shadow-sm'
                      : 'border border-[#0B3B2B]/12 bg-white text-[#284238] hover:border-[#C9961A]/50'
                  }`}
                >
                  {category}
                </button>
              )
            })}
          </div>
        </div>

        {/* Featured Editorial Story ("À la une") */}
        {featuredArticle && (
          <motion.article
            initial={{ opacity: 0, y: 18 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: '-40px' }}
            transition={{ duration: 0.45 }}
            className="group mt-7 overflow-hidden rounded-[28px] border border-[#0B3B2B]/12 bg-[#062319] text-[#FAF8F3] shadow-[0_22px_55px_rgba(6,35,25,0.16)] lg:grid lg:grid-cols-12"
          >
            {/* Cover Photo */}
            <div className="relative aspect-[16/10] w-full overflow-hidden lg:col-span-6 lg:aspect-auto lg:min-h-[340px]">
              <img
                src={featuredArticle.image}
                alt={featuredArticle.title}
                className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-[#062319]/80 via-[#062319]/15 to-transparent lg:bg-gradient-to-r lg:from-transparent lg:to-[#062319]/75" />

              <div className="absolute left-4 top-4 flex items-center gap-2">
                <span className="inline-flex items-center gap-1 rounded-full bg-[#E2B643] px-3 py-1 font-mono text-[10px] font-bold uppercase tracking-wider text-[#062319]">
                  <Sparkles className="h-3 w-3" />
                  À la une
                </span>
                <span className="rounded-full border border-white/20 bg-[#062319]/75 px-3 py-1 font-mono text-[10px] font-semibold text-[#F7EBC6] backdrop-blur-md">
                  {featuredArticle.category}
                </span>
              </div>
            </div>

            {/* Featured Body */}
            <div className="flex flex-col justify-between p-5 sm:p-7 lg:col-span-6">
              <div>
                <div className="flex items-center justify-between gap-2">
                  <span className="inline-flex items-center gap-1.5 font-mono text-[10.5px] text-[#E2B643]">
                    <Clock className="h-3.5 w-3.5" />
                    {featuredArticle.readTime} de lecture · {featuredArticle.publishedLabel}
                  </span>
                  <button
                    type="button"
                    onClick={() => onToggleBookmark(featuredArticle.id)}
                    aria-label="Enregistrer cet article"
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/15 bg-white/8 text-[#F7EBC6] transition-colors hover:bg-white/16"
                  >
                    <Bookmark
                      className={`h-4 w-4 ${
                        bookmarkedIds.includes(featuredArticle.id)
                          ? 'fill-[#E2B643] text-[#E2B643]'
                          : ''
                      }`}
                    />
                  </button>
                </div>

                <h3 className="mt-3 font-editorial text-2xl leading-snug font-semibold text-[#FAF8F3] sm:text-[1.72rem]">
                  {featuredArticle.title}
                </h3>

                <p className="mt-3 text-[14px] leading-relaxed text-[#D7E6DF]/88">
                  {featuredArticle.excerpt}
                </p>
              </div>

              <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                <div>
                  <p className="text-xs font-bold text-[#FAF8F3]">
                    {featuredArticle.author}
                  </p>
                  <p className="text-[11px] text-[#B7D0C4]">
                    {featuredArticle.authorRole}
                  </p>
                </div>

                <button
                  type="button"
                  onClick={() => onOpenArticle(featuredArticle)}
                  className="inline-flex min-h-[44px] items-center gap-1.5 rounded-xl bg-[#E2B643] px-4 py-2.5 text-xs font-bold text-[#062319] shadow-sm transition-all hover:bg-[#ECC45A] active:scale-95"
                >
                  <span>Lire l’article</span>
                  <ArrowUpRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          </motion.article>
        )}

        {/* Articles Grid / Empty State */}
        {gridArticles.length > 0 ? (
          <div className="mt-6 grid grid-cols-1 gap-5 md:grid-cols-3">
            {gridArticles.map((article, idx) => {
              const saved = bookmarkedIds.includes(article.id)
              return (
                <motion.article
                  key={article.id}
                  initial={{ opacity: 0, y: 18 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true, margin: '-30px' }}
                  transition={{ duration: 0.4, delay: idx * 0.06 }}
                  className="group flex flex-col overflow-hidden rounded-[24px] border border-[#0B3B2B]/10 bg-white shadow-[0_10px_32px_rgba(6,35,25,0.05)] transition-all hover:border-[#C9961A]/45"
                >
                  <div className="relative aspect-[16/10] w-full overflow-hidden bg-[#062319]">
                    <img
                      src={article.image}
                      alt={article.title}
                      loading="lazy"
                      className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-[#062319]/60 via-transparent to-transparent" />

                    <span className="absolute left-3.5 top-3.5 rounded-full border border-[#F7EBC6]/35 bg-[#062319]/78 px-2.5 py-1 font-mono text-[9.5px] font-semibold uppercase tracking-wider text-[#F7EBC6] backdrop-blur-md">
                      {article.category}
                    </span>

                    <button
                      type="button"
                      onClick={() => onToggleBookmark(article.id)}
                      aria-label={
                        saved ? 'Retirer des favoris' : 'Enregistrer cet article'
                      }
                      className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-[#062319]/75 text-[#FAF8F3] backdrop-blur-md transition-colors hover:bg-[#062319]"
                    >
                      <Bookmark
                        className={`h-3.5 w-3.5 ${saved ? 'fill-[#E2B643] text-[#E2B643]' : ''}`}
                      />
                    </button>
                  </div>

                  <div className="flex flex-1 flex-col justify-between p-5">
                    <div>
                      <div className="flex items-center gap-2 font-mono text-[10.5px] text-[#7D5708]">
                        <Clock className="h-3.5 w-3.5 text-[#C9961A]" />
                        <span>{article.readTime}</span>
                        <span>·</span>
                        <span className="truncate">{article.publishedLabel}</span>
                      </div>

                      <h3 className="mt-2.5 font-editorial text-lg leading-snug font-semibold text-[#062319] group-hover:text-[#0F513A]">
                        {article.title}
                      </h3>

                      <p className="mt-2 line-clamp-3 text-[13.5px] leading-relaxed text-[#465E54]">
                        {article.excerpt}
                      </p>
                    </div>

                    <div className="mt-5 flex items-center justify-between border-t border-[#0B3B2B]/8 pt-3.5">
                      <span className="truncate text-[11.5px] font-semibold text-[#3C554B]">
                        {article.author}
                      </span>
                      <button
                        type="button"
                        onClick={() => onOpenArticle(article)}
                        className="inline-flex items-center gap-1 text-xs font-bold text-[#062319] transition-colors hover:text-[#C9961A]"
                      >
                        <span>Lire</span>
                        <ArrowUpRight className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>
                </motion.article>
              )
            })}
          </div>
        ) : (
          !featuredArticle && (
            <div className="mt-8 rounded-[24px] border border-[#0B3B2B]/12 bg-white p-8 text-center">
              <p className="font-editorial text-lg font-semibold text-[#062319]">
                {showSavedOnly
                  ? 'Aucun article enregistré dans vos favoris pour le moment.'
                  : 'Aucun article ne correspond à votre recherche.'}
              </p>
              <p className="mt-1.5 text-sm text-[#4D655B]">
                {showSavedOnly
                  ? 'Appuyez sur l’icône marque-page d’un article pour le retrouver ici.'
                  : 'Essayez un autre mot-clé ou affichez toutes les catégories.'}
              </p>
              <button
                type="button"
                onClick={() => {
                  setSearchQuery('')
                  setSelectedCategory('Tous')
                  setShowSavedOnly(false)
                }}
                className="mt-4 inline-flex items-center rounded-xl bg-[#062319] px-4 py-2 text-xs font-bold text-[#F7EBC6]"
              >
                Voir tous les articles
              </button>
            </div>
          )
        )}

        {/* Community Contribution Banner */}
        <div className="mt-8 flex flex-col items-start justify-between gap-4 rounded-[24px] border border-[#C9961A]/32 bg-gradient-to-r from-[#F7EBC6]/45 via-white to-[#F7EBC6]/30 p-5 sm:flex-row sm:items-center sm:p-6">
          <div className="flex items-start gap-3.5">
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#062319] text-[#E2B643]">
              <PenLine className="h-5 w-5" />
            </div>
            <div>
              <p className="font-mono text-[10px] font-bold uppercase tracking-[0.16em] text-[#8C640B]">
                Tribune Étudiante & Stagiaire
              </p>
              <h3 className="mt-0.5 font-editorial text-lg font-semibold text-[#062319]">
                Partagez votre expérience ou un conseil sur le Blog AESMG
              </h3>
              <p className="mt-0.5 text-xs leading-relaxed text-[#425A50]">
                Récit de campus, guide de filière, bon plan stage : chaque plume
                malienne au Ghana est la bienvenue.
              </p>
            </div>
          </div>

          <a
            href={contributeWhatsAppHref}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex min-h-[46px] shrink-0 items-center justify-center gap-2 rounded-xl bg-[#062319] px-4 py-2.5 text-xs font-bold text-[#F7EBC6] shadow-sm transition-all hover:bg-[#0B3B2B] active:scale-95"
          >
            <span>Proposer un article</span>
            <ArrowUpRight className="h-4 w-4 text-[#E2B643]" />
          </a>
        </div>
      </div>
    </section>
  )
}
