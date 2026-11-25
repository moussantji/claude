import React, { useEffect, useState } from 'react'
import { motion, useScroll, useSpring } from 'framer-motion'
import { PhoneCall, Smartphone, Maximize2, Share2 } from 'lucide-react'
import { AesmgSeal, TricoloreRibbon } from './BrandMarks'
import { AESMG_INFO } from '../data/aesmgData'

interface TopBarProps {
  activeSection: string
  onNavigate: (sectionId: string) => void
  onOpenContactSheet: () => void
  onShowToast: (message: string) => void
  isInsideFrame: boolean
  isPhoneShowcase: boolean
  onTogglePhoneShowcase: () => void
}

const NAV_LINKS = [
  { id: 'accueil', label: 'Accueil' },
  { id: 'objectifs', label: 'Objectifs' },
  { id: 'communaute', label: 'Vie' },
  { id: 'blog', label: 'Blog' },
  { id: 'bureau', label: 'Bureau' },
  { id: 'adhesion', label: 'Adhérer' },
]

export const TopBar: React.FC<TopBarProps> = ({
  activeSection,
  onNavigate,
  onOpenContactSheet,
  onShowToast,
  isInsideFrame,
  isPhoneShowcase,
  onTogglePhoneShowcase,
}) => {
  const [scrolled, setScrolled] = useState(false)
  const [gmtTime, setGmtTime] = useState('')
  const { scrollYProgress } = useScroll()
  const scaleX = useSpring(scrollYProgress, {
    stiffness: 180,
    damping: 30,
    restDelta: 0.001,
  })

  useEffect(() => {
    const updateClock = () => {
      const now = new Date()
      const formatted = new Intl.DateTimeFormat('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Africa/Accra',
      }).format(now)
      setGmtTime(formatted)
    }
    updateClock()
    const timer = window.setInterval(updateClock, 30000)
    return () => window.clearInterval(timer)
  }, [])

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 24)
    handleScroll()
    window.addEventListener('scroll', handleScroll, { passive: true })
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  const handleShare = async () => {
    const shareText = `${AESMG_INFO.fullName} (AESMG)\nPrésident : Diadié GUITTEYE (+233 535478560 / WhatsApp +223 91035026)\nSecrétaire général : Georgette Jeanne MARGNA (+233 50 615 5639)`
    if (navigator.share) {
      try {
        await navigator.share({
          title: 'AESMG — Association des Étudiants et Stagiaires Maliens au Ghana',
          text: shareText,
          url: window.location.href,
        })
        return
      } catch {
        // User cancelled share sheet
        return
      }
    }
    try {
      await navigator.clipboard.writeText(shareText)
      onShowToast('Fiche officielle AESMG copiée pour partage !')
    } catch {
      onShowToast('Partage disponible depuis votre navigateur.')
    }
  }

  return (
    <header
      className={`fixed top-0 left-0 right-0 z-40 transition-all duration-300 ${
        scrolled
          ? 'glass-ivory border-b border-[#0B3B2B]/10 shadow-[0_8px_30px_rgba(6,35,25,0.06)]'
          : 'bg-gradient-to-b from-[#062319]/82 via-[#062319]/42 to-transparent'
      }`}
    >
      <TricoloreRibbon />
      <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-2.5 sm:px-6">
        {/* Left Brand Identity */}
        <button
          type="button"
          onClick={() => onNavigate('accueil')}
          className="group flex items-center gap-2.5 text-left focus:outline-none"
        >
          <AesmgSeal
            size={38}
            variant="dark"
            className="shrink-0 rounded-[11px] shadow-md transition-transform duration-300 group-hover:scale-105"
          />
          <div className="leading-tight">
            <div className="flex items-center gap-1.5">
              <span
                className={`font-editorial text-base font-bold tracking-tight transition-colors ${
                  scrolled ? 'text-[#062319]' : 'text-[#FAF8F3]'
                }`}
              >
                AESMG
              </span>
              <span
                className={`inline-flex items-center rounded-full px-1.5 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-wider ${
                  scrolled
                    ? 'bg-[#0B3B2B]/10 text-[#0B3B2B]'
                    : 'bg-[#E2B643]/20 text-[#F7EBC6]'
                }`}
              >
                ML · GH
              </span>
            </div>
            <p
              className={`text-[10.5px] font-medium tracking-wide transition-colors ${
                scrolled ? 'text-[#3C554B]' : 'text-[#FAF8F3]/80'
              }`}
            >
              Étudiants & Stagiaires au Ghana
            </p>
          </div>
        </button>

        {/* Desktop Center Navigation */}
        <nav className="hidden items-center gap-1 md:flex" aria-label="Navigation principale">
          {NAV_LINKS.map((link) => {
            const active = activeSection === link.id
            return (
              <button
                key={link.id}
                type="button"
                onClick={() => onNavigate(link.id)}
                className={`relative rounded-full px-3.5 py-1.5 text-xs font-semibold tracking-wide transition-all ${
                  active
                    ? scrolled
                      ? 'bg-[#062319] text-[#F7EBC6]'
                      : 'bg-[#FAF8F3] text-[#062319]'
                    : scrolled
                      ? 'text-[#213B31] hover:bg-[#0B3B2B]/8'
                      : 'text-[#FAF8F3]/88 hover:bg-white/12'
                }`}
              >
                {link.label}
              </button>
            )
          })}
        </nav>

        {/* Right Actions */}
        <div className="flex items-center gap-1.5 sm:gap-2">
          {/* Live Accra / Bamako GMT indicator on xl+ */}
          {gmtTime && (
            <div
              className={`hidden items-center gap-1.5 rounded-full px-2.5 py-1 font-mono text-[10px] font-medium xl:flex ${
                scrolled
                  ? 'border border-[#0B3B2B]/12 bg-white/70 text-[#264237]'
                  : 'border border-white/18 bg-[#062319]/45 text-[#F7EBC6]'
              }`}
              title="Heure locale commune Accra & Bamako (GMT)"
            >
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-[#22C55E]" />
              <span>Accra · Bamako {gmtTime}</span>
            </div>
          )}

          {/* Optional Desktop iPhone Showcase Switch */}
          {!isInsideFrame && (
            <button
              type="button"
              onClick={onTogglePhoneShowcase}
              className={`hidden items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all md:inline-flex ${
                scrolled
                  ? 'border border-[#C9961A]/45 bg-[#F7EBC6]/65 text-[#062319] hover:bg-[#F7EBC6]'
                  : 'border border-[#E2B643]/50 bg-[#E2B643]/20 text-[#FAF8F3] backdrop-blur-md hover:bg-[#E2B643]/30'
              }`}
              title="Basculer entre l'aperçu mobile iPhone et le plein écran"
            >
              {isPhoneShowcase ? (
                <>
                  <Maximize2 className="h-3.5 w-3.5" />
                  <span>Plein écran</span>
                </>
              ) : (
                <>
                  <Smartphone className="h-3.5 w-3.5" />
                  <span>Vue Mobile</span>
                </>
              )}
            </button>
          )}

          {/* Quick Share Button */}
          <button
            type="button"
            onClick={handleShare}
            aria-label="Partager la fiche AESMG"
            title="Partager les contacts officiels AESMG"
            className={`flex h-9 w-9 items-center justify-center rounded-full transition-all active:scale-95 ${
              scrolled
                ? 'border border-[#0B3B2B]/14 bg-white/80 text-[#062319] hover:bg-[#0B3B2B]/10'
                : 'border border-white/20 bg-[#062319]/45 text-[#FAF8F3] backdrop-blur-md hover:bg-white/15'
            }`}
          >
            <Share2 className="h-3.5 w-3.5" />
          </button>

          {/* Direct Quick Contact Pill */}
          <button
            type="button"
            onClick={onOpenContactSheet}
            className={`inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-xs font-semibold shadow-sm transition-all active:scale-95 ${
              scrolled
                ? 'bg-[#062319] text-[#F7EBC6] hover:bg-[#0B3B2B]'
                : 'bg-[#E2B643] text-[#062319] hover:bg-[#ECC45A]'
            }`}
          >
            <PhoneCall className="h-3.5 w-3.5" />
            <span>Contact</span>
          </button>
        </div>
      </div>

      {/* Subtle gold scroll reading progress hairline */}
      <motion.div
        style={{ scaleX }}
        className="h-[2px] origin-left bg-gradient-to-r from-[#007A4D] via-[#E2B643] to-[#F5D67B]"
      />
    </header>
  )
}
