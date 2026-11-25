import { useCallback, useEffect, useRef, useState } from 'react'
import { TopBar } from './components/TopBar'
import { HeroSection } from './components/HeroSection'
import { ObjectivesSection } from './components/ObjectivesSection'
import { CommunityLifeSection } from './components/CommunityLifeSection'
import { NewcomerGuideSection } from './components/NewcomerGuideSection'
import { BlogSection } from './components/BlogSection'
import { BlogArticleReader } from './components/BlogArticleReader'
import { BlogAdminModal } from './components/BlogAdminModal'
import { LeadershipSection } from './components/LeadershipSection'
import { MembershipSection } from './components/MembershipSection'
import { BottomNavDock } from './components/BottomNavDock'
import { QuickContactSheet } from './components/QuickContactSheet'
import { ToastNotification } from './components/ToastNotification'
import { Footer } from './components/Footer'
import { PhoneShowcaseFrame } from './components/PhoneShowcaseFrame'
import { BLOG_ARTICLES, type BlogArticle } from './data/blogData'

const SECTION_IDS = [
  'accueil',
  'objectifs',
  'communaute',
  'blog',
  'bureau',
  'adhesion',
]

const BOOKMARK_STORAGE_KEY = 'aesmg_saved_articles_v1'
const ARTICLES_STORAGE_KEY = 'aesmg_blog_articles_v1'

export function App() {
  const isInsideFrame =
    typeof window !== 'undefined' &&
    new URLSearchParams(window.location.search).get('frame') === '1'

  const [activeSection, setActiveSection] = useState<string>('accueil')
  const [isContactSheetOpen, setIsContactSheetOpen] = useState(false)
  const [isBlogAdminOpen, setIsBlogAdminOpen] = useState(false)
  const [isPhoneShowcase, setIsPhoneShowcase] = useState(false)
  const [activeArticle, setActiveArticle] = useState<BlogArticle | null>(null)

  const [articles, setArticles] = useState<BlogArticle[]>(() => {
    try {
      const raw = window.localStorage.getItem(ARTICLES_STORAGE_KEY)
      if (!raw) return BLOG_ARTICLES
      const parsed = JSON.parse(raw) as BlogArticle[]
      return Array.isArray(parsed) && parsed.length > 0 ? parsed : BLOG_ARTICLES
    } catch {
      return BLOG_ARTICLES
    }
  })

  const [bookmarkedIds, setBookmarkedIds] = useState<string[]>(() => {
    try {
      const raw = window.localStorage.getItem(BOOKMARK_STORAGE_KEY)
      return raw ? (JSON.parse(raw) as string[]) : []
    } catch {
      return []
    }
  })

  const [toastMessage, setToastMessage] = useState<string | null>(null)
  const toastTimerRef = useRef<number | null>(null)

  const persistArticles = useCallback((next: BlogArticle[]) => {
    setArticles(next)
    try {
      window.localStorage.setItem(ARTICLES_STORAGE_KEY, JSON.stringify(next))
    } catch {
      // Ignore quota exceeded
    }
  }, [])

  const showToast = useCallback((message: string) => {
    setToastMessage(message)
    if (toastTimerRef.current) {
      window.clearTimeout(toastTimerRef.current)
    }
    toastTimerRef.current = window.setTimeout(() => {
      setToastMessage(null)
    }, 3200)
  }, [])

  const handleSaveArticle = useCallback(
    (saved: BlogArticle, isNew: boolean) => {
      setArticles((prev) => {
        const cleaned = saved.featured
          ? prev.map((a) => ({ ...a, featured: false }))
          : prev
        const next = isNew
          ? [saved, ...cleaned]
          : cleaned.map((a) => (a.id === saved.id ? saved : a))
        try {
          window.localStorage.setItem(ARTICLES_STORAGE_KEY, JSON.stringify(next))
        } catch {
          // Ignore quota exceeded
        }
        return next
      })
      showToast(
        saved.status === 'published'
          ? isNew
            ? 'Nouvel article publié sur le Blog AESMG !'
            : 'Article mis à jour et publié !'
          : 'Article enregistré dans les brouillons.'
      )
    },
    [showToast]
  )

  const handleDeleteArticle = useCallback(
    (articleId: string) => {
      setArticles((prev) => {
        const next = prev.filter((a) => a.id !== articleId)
        try {
          window.localStorage.setItem(ARTICLES_STORAGE_KEY, JSON.stringify(next))
        } catch {
          // Ignore quota exceeded
        }
        return next
      })
      setActiveArticle((curr) => (curr?.id === articleId ? null : curr))
      showToast('Article supprimé du Blog.')
    },
    [showToast]
  )

  const handleToggleArticleStatus = useCallback(
    (articleId: string) => {
      let becamePublished = false
      setArticles((prev) => {
        const next = prev.map((a) => {
          if (a.id !== articleId) return a
          const nextStatus: BlogArticle['status'] =
            a.status === 'published' ? 'draft' : 'published'
          becamePublished = nextStatus === 'published'
          return { ...a, status: nextStatus }
        })
        try {
          window.localStorage.setItem(ARTICLES_STORAGE_KEY, JSON.stringify(next))
        } catch {
          // Ignore quota exceeded
        }
        return next
      })
      showToast(
        becamePublished
          ? 'Article publié en ligne !'
          : 'Article passé en brouillon (masqué du public).'
      )
    },
    [showToast]
  )

  const handleSetFeatured = useCallback(
    (articleId: string) => {
      setArticles((prev) => {
        const next = prev.map((a) => ({
          ...a,
          featured: a.id === articleId,
        }))
        try {
          window.localStorage.setItem(ARTICLES_STORAGE_KEY, JSON.stringify(next))
        } catch {
          // Ignore quota exceeded
        }
        return next
      })
      showToast('Article promu « À la une » du Blog !')
    },
    [showToast]
  )

  const handleResetDefaults = useCallback(() => {
    persistArticles(BLOG_ARTICLES)
    showToast('Catalogue du Blog réinitialisé avec les articles officiels.')
  }, [persistArticles, showToast])

  const toggleBookmark = useCallback(
    (articleId: string) => {
      setBookmarkedIds((prev) => {
        const exists = prev.includes(articleId)
        const next = exists
          ? prev.filter((id) => id !== articleId)
          : [...prev, articleId]
        try {
          window.localStorage.setItem(BOOKMARK_STORAGE_KEY, JSON.stringify(next))
        } catch {
          // Ignore storage quota errors
        }
        showToast(
          exists
            ? 'Article retiré de vos favoris.'
            : 'Article enregistré dans vos favoris !'
        )
        return next
      })
    },
    [showToast]
  )

  useEffect(() => {
    return () => {
      if (toastTimerRef.current) window.clearTimeout(toastTimerRef.current)
    }
  }, [])

  useEffect(() => {
    if (isPhoneShowcase) return

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)

        if (visible[0]?.target.id) {
          setActiveSection(visible[0].target.id)
        }
      },
      {
        rootMargin: '-25% 0px -50% 0px',
        threshold: [0.1, 0.25, 0.5],
      }
    )

    SECTION_IDS.forEach((id) => {
      const el = document.getElementById(id)
      if (el) observer.observe(el)
    })

    return () => observer.disconnect()
  }, [isPhoneShowcase])

  const handleNavigate = useCallback((sectionId: string) => {
    setActiveSection(sectionId)
    const target = document.getElementById(sectionId)
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }, [])

  if (isPhoneShowcase && !isInsideFrame) {
    return <PhoneShowcaseFrame onExit={() => setIsPhoneShowcase(false)} />
  }

  return (
    <div className="min-h-screen bg-[#FAF8F3] text-[#0E1E18]">
      <TopBar
        activeSection={activeSection}
        onNavigate={handleNavigate}
        onOpenContactSheet={() => setIsContactSheetOpen(true)}
        onShowToast={showToast}
        isInsideFrame={isInsideFrame}
        isPhoneShowcase={isPhoneShowcase}
        onTogglePhoneShowcase={() => setIsPhoneShowcase((prev) => !prev)}
      />

      <main>
        <HeroSection
          onNavigate={handleNavigate}
          onOpenContactSheet={() => setIsContactSheetOpen(true)}
        />
        <ObjectivesSection />
        <CommunityLifeSection onNavigate={handleNavigate} />
        <NewcomerGuideSection />
        <BlogSection
          articles={articles}
          bookmarkedIds={bookmarkedIds}
          onToggleBookmark={toggleBookmark}
          onOpenArticle={setActiveArticle}
          onOpenAdmin={() => setIsBlogAdminOpen(true)}
        />
        <LeadershipSection onShowToast={showToast} />
        <MembershipSection onShowToast={showToast} />
      </main>

      <Footer
        onNavigate={handleNavigate}
        onOpenBlogAdmin={() => setIsBlogAdminOpen(true)}
      />

      <BottomNavDock
        activeSection={activeSection}
        onNavigate={handleNavigate}
      />

      <BlogArticleReader
        article={activeArticle}
        allArticles={articles.filter((a) => a.status === 'published')}
        isBookmarked={
          activeArticle ? bookmarkedIds.includes(activeArticle.id) : false
        }
        onToggleBookmark={toggleBookmark}
        onSelectArticle={setActiveArticle}
        onClose={() => setActiveArticle(null)}
        onShowToast={showToast}
      />

      <BlogAdminModal
        isOpen={isBlogAdminOpen}
        articles={articles}
        onClose={() => setIsBlogAdminOpen(false)}
        onSaveArticle={handleSaveArticle}
        onDeleteArticle={handleDeleteArticle}
        onToggleStatus={handleToggleArticleStatus}
        onSetFeatured={handleSetFeatured}
        onReplaceAllArticles={persistArticles}
        onResetDefaults={handleResetDefaults}
        onPreviewArticle={(article) => {
          setIsBlogAdminOpen(false)
          setActiveArticle(article)
        }}
        onShowToast={showToast}
      />

      <QuickContactSheet
        isOpen={isContactSheetOpen}
        onClose={() => setIsContactSheetOpen(false)}
        onShowToast={showToast}
      />

      <ToastNotification message={toastMessage} />
    </div>
  )
}

export default App
