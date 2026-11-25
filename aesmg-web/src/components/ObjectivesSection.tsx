import React, { useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  Users,
  Sparkles,
  HeartHandshake,
  Compass,
  ShieldCheck,
  CheckCircle2,
  ChevronDown,
} from 'lucide-react'
import { OBJECTIVES, type ObjectiveItem } from '../data/aesmgData'
import { BogolanDivider } from './BrandMarks'

const ICON_MAP: Record<ObjectiveItem['iconName'], React.ComponentType<{ className?: string }>> = {
  users: Users,
  sparkles: Sparkles,
  heart: HeartHandshake,
  compass: Compass,
  shield: ShieldCheck,
}

export const ObjectivesSection: React.FC = () => {
  const [expandedId, setExpandedId] = useState<string>('federer')

  return (
    <section
      id="objectifs"
      className="relative bg-[#FAF8F3] bg-bogolan-subtle px-4 pb-16 pt-6 sm:px-6 md:py-20"
    >
      <div className="mx-auto max-w-6xl">
        {/* Editorial Section Heading */}
        <div className="mx-auto max-w-2xl text-center">
          <span className="inline-flex items-center gap-2 rounded-full border border-[#0B3B2B]/14 bg-white px-3.5 py-1.5 font-mono text-[10.5px] font-semibold uppercase tracking-[0.18em] text-[#0B3B2B] shadow-xs">
            <span className="h-1.5 w-1.5 rounded-full bg-[#C9961A]" />
            Charte Fondatrice · 5 Piliers
          </span>

          <h2 className="mt-4 font-editorial text-[1.95rem] leading-[1.12] font-semibold tracking-tight text-[#062319] sm:text-4xl">
            Nos Buts &{' '}
            <span className="italic text-[#0F513A]">Objectifs</span>
          </h2>

          <p className="mt-3 text-[14.5px] leading-relaxed text-[#425A50] sm:text-base">
            Chaque engagement de l’AESMG repose sur cinq missions essentielles au
            service de la dignité, de l’unité et de la réussite de la communauté
            malienne au Ghana.
          </p>

          <BogolanDivider className="mx-auto mt-6 max-w-xs" />
        </div>

        {/* Interactive 5 Objectives Stack / Responsive Grid */}
        <div className="mt-8 grid grid-cols-1 gap-3.5 md:mt-12 md:grid-cols-2 lg:gap-5">
          {OBJECTIVES.map((obj, index) => {
            const Icon = ICON_MAP[obj.iconName]
            const isExpanded = expandedId === obj.id
            const isLastOdd = index === OBJECTIVES.length - 1

            return (
              <motion.article
                key={obj.id}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: '-40px' }}
                transition={{ duration: 0.45, delay: index * 0.06 }}
                className={`group relative overflow-hidden rounded-[24px] border transition-all duration-300 ${
                  isLastOdd ? 'md:col-span-2 md:mx-auto md:w-full md:max-w-2xl' : ''
                } ${
                  isExpanded
                    ? 'border-[#0B3B2B]/25 bg-[#062319] text-[#FAF8F3] shadow-[0_20px_50px_rgba(6,35,25,0.18)]'
                    : 'border-[#0B3B2B]/10 bg-white text-[#0E1E18] shadow-[0_4px_20px_rgba(6,35,25,0.04)] hover:border-[#C9961A]/45'
                }`}
              >
                {/* Subtle gold glow on active card */}
                {isExpanded && (
                  <div
                    className="pointer-events-none absolute -right-14 -top-14 h-44 w-44 rounded-full bg-[#E2B643]/15 blur-3xl"
                    aria-hidden="true"
                  />
                )}

                <button
                  type="button"
                  onClick={() => setExpandedId(isExpanded ? '' : obj.id)}
                  aria-expanded={isExpanded}
                  className="relative flex w-full flex-col p-5 text-left sm:p-6 focus:outline-none"
                >
                  {/* Top Row: Number + Pillar Tag + Icon */}
                  <div className="flex w-full items-center justify-between gap-3">
                    <div className="flex items-center gap-2.5">
                      <span
                        className={`flex h-10 w-10 items-center justify-center rounded-xl transition-colors ${
                          isExpanded
                            ? 'bg-[#E2B643] text-[#062319]'
                            : 'bg-[#0B3B2B]/8 text-[#0B3B2B] group-hover:bg-[#0B3B2B] group-hover:text-[#F7EBC6]'
                        }`}
                      >
                        <Icon className="h-5 w-5" />
                      </span>
                      <div>
                        <span
                          className={`block font-mono text-[10px] font-semibold uppercase tracking-[0.18em] ${
                            isExpanded ? 'text-[#E2B643]' : 'text-[#9B7210]'
                          }`}
                        >
                          Objectif {obj.number} · {obj.pillarTag}
                        </span>
                      </div>
                    </div>

                    <span
                      className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-transform duration-300 ${
                        isExpanded
                          ? 'rotate-180 bg-white/10 text-[#F7EBC6]'
                          : 'bg-[#FAF8F3] text-[#3C554B]'
                      }`}
                    >
                      <ChevronDown className="h-4 w-4" />
                    </span>
                  </div>

                  {/* Exact Official Title */}
                  <h3
                    className={`mt-4 font-editorial text-[1.28rem] leading-snug font-semibold sm:text-[1.42rem] ${
                      isExpanded ? 'text-[#FAF8F3]' : 'text-[#062319]'
                    }`}
                  >
                    {obj.title}
                  </h3>

                  {/* Exact Official Description */}
                  <p
                    className={`mt-2.5 text-[14px] leading-relaxed sm:text-[14.5px] ${
                      isExpanded ? 'text-[#DCEBE3]/90' : 'text-[#425A50]'
                    }`}
                  >
                    {obj.description}
                  </p>
                </button>

                {/* Expandable Concrete Actions Drawer */}
                <AnimatePresence initial={false}>
                  {isExpanded && (
                    <motion.div
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      exit={{ height: 0, opacity: 0 }}
                      transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                      className="overflow-hidden"
                    >
                      <div className="border-t border-white/10 px-5 pb-5 pt-4 sm:px-6 sm:pb-6">
                        <p className="mb-2.5 font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-[#E2B643]">
                          Traduction concrète au quotidien
                        </p>
                        <ul className="space-y-2">
                          {obj.concreteActions.map((action) => (
                            <li
                              key={action}
                              className="flex items-start gap-2.5 text-[13px] leading-snug text-[#EEF5F1]"
                            >
                              <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-[#E2B643]" />
                              <span>{action}</span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </motion.article>
            )
          })}
        </div>
      </div>
    </section>
  )
}
