import React from 'react'
import { motion } from 'framer-motion'
import { ArrowUpRight, PhoneCall, ShieldCheck, Sparkles, Users, MapPin } from 'lucide-react'
import { AESMG_INFO } from '../data/aesmgData'

interface HeroSectionProps {
  onNavigate: (sectionId: string) => void
  onOpenContactSheet: () => void
}

const HIGHLIGHTS = [
  {
    icon: Users,
    value: 'Étudiants & Stagiaires',
    label: 'Tous campus du Ghana',
  },
  {
    icon: Sparkles,
    value: '5 Piliers',
    label: 'Unité · Entraide · Culture',
  },
  {
    icon: ShieldCheck,
    value: 'Accompagnement',
    label: 'Droits moraux & académiques',
  },
]

export const HeroSection: React.FC<HeroSectionProps> = ({
  onNavigate,
  onOpenContactSheet,
}) => {
  return (
    <section
      id="accueil"
      className="relative overflow-hidden bg-[#062319] text-[#FAF8F3] pt-[66px]"
    >
      {/* Ambient Editorial Photo Backdrop */}
      <div className="absolute inset-0">
        <img
          src="/images/hero-accra-students.jpg"
          alt="Étudiants et stagiaires maliens sur un campus universitaire au Ghana"
          className="h-full w-full object-cover object-[center_22%] scale-[1.02]"
        />
        {/* Sculpted luxury emerald-gold veil for pristine readability on every phone */}
        <div className="absolute inset-0 bg-gradient-to-b from-[#062319]/88 via-[#062319]/58 to-[#062319]" />
        <div className="absolute inset-0 bg-gradient-to-tr from-[#062319]/92 via-transparent to-[#C9961A]/18" />
      </div>

      {/* Content Container */}
      <div className="relative mx-auto flex min-h-[86svh] max-w-6xl flex-col justify-between px-4 pb-10 pt-7 sm:px-6 md:min-h-[82svh] md:pt-12">
        {/* Top Institutional Greeting Pill */}
        <motion.div
          initial={{ opacity: 0, y: 14 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.55, ease: [0.22, 1, 0.36, 1] }}
          className="flex flex-wrap items-center gap-2"
        >
          <span className="inline-flex items-center gap-2 rounded-full border border-[#E2B643]/40 bg-[#062319]/65 px-3.5 py-1.5 text-[11px] font-semibold tracking-wider text-[#F7EBC6] backdrop-blur-md">
            <span className="flex h-2 w-2 items-center justify-center">
              <span className="h-2 w-2 rounded-full bg-[#E2B643]" />
            </span>
            {AESMG_INFO.greeting.toUpperCase()}
          </span>
          <span className="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[11px] font-medium text-[#FAF8F3]/90 backdrop-blur-md">
            <MapPin className="h-3 w-3 text-[#E2B643]" />
            Accra · Kumasi · Cape Coast · Ghana
          </span>
        </motion.div>

        {/* Main Editorial Typography Block */}
        <div className="my-auto py-8 sm:py-10 md:max-w-2xl">
          <motion.p
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.55, delay: 0.08 }}
            className="mb-3 font-mono text-[11px] font-semibold uppercase tracking-[0.22em] text-[#E2B643]"
          >
            {AESMG_INFO.motto}
          </motion.p>

          <motion.h1
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.65, delay: 0.14, ease: [0.22, 1, 0.36, 1] }}
            className="font-editorial text-[2.15rem] leading-[1.08] font-semibold tracking-tight text-[#FAF8F3] sm:text-5xl md:text-[3.55rem]"
          >
            Association des{' '}
            <span className="italic font-semibold text-[#F5D67B]">
              Étudiants et Stagiaires
            </span>{' '}
            Maliens au Ghana
          </motion.h1>

          <motion.p
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.22 }}
            className="mt-4 max-w-xl text-[14.5px] leading-relaxed text-[#E7EFEA]/90 sm:text-base"
          >
            Une maison commune de fraternité, d’excellence académique et de
            solidarité active pour chaque étudiant et stagiaire malien vivant au
            Ghana — sans aucune distinction.
          </motion.p>

          {/* Primary Mobile-First Action Buttons */}
          <motion.div
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="mt-7 flex flex-col gap-3 sm:flex-row sm:items-center"
          >
            <button
              type="button"
              onClick={() => onNavigate('adhesion')}
              className="group inline-flex min-h-[52px] items-center justify-center gap-2 rounded-2xl bg-[#E2B643] px-6 py-3.5 text-sm font-bold text-[#062319] shadow-[0_12px_32px_rgba(226,182,67,0.34)] transition-all hover:bg-[#ECC45A] active:scale-[0.98]"
            >
              <span>Rejoindre l’AESMG</span>
              <ArrowUpRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
            </button>

            <button
              type="button"
              onClick={onOpenContactSheet}
              className="inline-flex min-h-[52px] items-center justify-center gap-2 rounded-2xl border border-[#FAF8F3]/25 bg-[#062319]/55 px-6 py-3.5 text-sm font-semibold text-[#FAF8F3] backdrop-blur-md transition-all hover:bg-[#062319]/80 active:scale-[0.98]"
            >
              <PhoneCall className="h-4 w-4 text-[#E2B643]" />
              <span>Contacter le Bureau</span>
            </button>
          </motion.div>
        </div>

        {/* Bottom Glassmorphic Institutional Strip — horizontal glide on mobile to keep Hero compact above the fold */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.65, delay: 0.38 }}
          className="no-scrollbar -mx-4 flex snap-x snap-mandatory gap-2.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 sm:pb-0"
        >
          {HIGHLIGHTS.map((item) => {
            const Icon = item.icon
            return (
              <div
                key={item.value}
                className="flex w-[78vw] max-w-[270px] shrink-0 snap-start items-center gap-3 rounded-2xl border border-white/14 bg-[#062319]/72 px-4 py-3 backdrop-blur-md sm:w-auto sm:max-w-none"
              >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E2B643]/18 text-[#E2B643]">
                  <Icon className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <p className="truncate text-[13px] font-bold text-[#FAF8F3]">
                    {item.value}
                  </p>
                  <p className="truncate text-[11.5px] text-[#CDE0D6]/85">
                    {item.label}
                  </p>
                </div>
              </div>
            )
          })}
        </motion.div>
      </div>

      {/* Sculpted curved transition into warm ivory canvas */}
      <div className="relative h-6 w-full rounded-t-[28px] bg-[#FAF8F3]" />
    </section>
  )
}
