import React, { useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { ArrowUpRight } from 'lucide-react'
import { COMMUNITY_STORIES, type CommunityStory } from '../data/aesmgData'

type FilterCategory = 'Tous' | CommunityStory['category']
const CATEGORIES: FilterCategory[] = ['Tous', 'Éducatif', 'Culturel', 'Social', 'Sportif']

interface CommunityLifeSectionProps {
  onNavigate: (sectionId: string) => void
}

export const CommunityLifeSection: React.FC<CommunityLifeSectionProps> = ({
  onNavigate,
}) => {
  const [selectedCategory, setSelectedCategory] = useState<FilterCategory>('Tous')

  const visibleStories =
    selectedCategory === 'Tous'
      ? COMMUNITY_STORIES
      : COMMUNITY_STORIES.filter((s) => s.category === selectedCategory)

  return (
    <section
      id="communaute"
      className="relative overflow-hidden bg-[#F3EFE5] py-16 sm:py-20"
    >
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        {/* Header Row */}
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div className="max-w-xl">
            <span className="inline-flex items-center gap-2 rounded-full border border-[#0B3B2B]/15 bg-[#FAF8F3] px-3 py-1 font-mono text-[10px] font-semibold uppercase tracking-[0.18em] text-[#0B3B2B]">
              Vie Communautaire
            </span>
            <h2 className="mt-3 font-editorial text-[1.9rem] leading-[1.12] font-semibold tracking-tight text-[#062319] sm:text-4xl">
              Une communauté vivante,{' '}
              <span className="italic text-[#0F513A]">créative & soudée</span>
            </h2>
            <p className="mt-2.5 text-[14.5px] leading-relaxed text-[#425A50]">
              Activités sociales, culturelles, éducatives et sportives pour
              stimuler la cohésion et le rayonnement des Maliens au Ghana.
            </p>
          </div>

          {/* Interactive Filter Pills (Horizontal scroll on mobile) */}
          <div className="no-scrollbar -mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:px-0">
            {CATEGORIES.map((category) => {
              const active = selectedCategory === category
              return (
                <button
                  key={category}
                  type="button"
                  onClick={() => setSelectedCategory(category)}
                  className={`relative shrink-0 rounded-full px-4 py-2 text-xs font-semibold transition-all active:scale-95 ${
                    active
                      ? 'bg-[#062319] text-[#F7EBC6] shadow-md'
                      : 'border border-[#0B3B2B]/12 bg-[#FAF8F3] text-[#284238] hover:border-[#C9961A]/50'
                  }`}
                >
                  {category}
                </button>
              )
            })}
          </div>
        </div>

        {/* Cards Layout: Smooth Horizontal Snap Carousel on Mobile when "Tous", Responsive Grid on md+ */}
        <motion.div
          layout
          className={`mt-7 ${
            selectedCategory === 'Tous'
              ? 'no-scrollbar -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-3 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 md:pb-0'
              : 'grid grid-cols-1 gap-5 md:grid-cols-2'
          }`}
        >
          <AnimatePresence mode="popLayout">
            {visibleStories.map((story) => (
              <motion.article
                key={story.id}
                layout
                initial={{ opacity: 0, scale: 0.96, y: 14 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, scale: 0.95 }}
                transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
                className={`group flex flex-col overflow-hidden rounded-[26px] border border-[#0B3B2B]/10 bg-[#FAF8F3] shadow-[0_12px_36px_rgba(6,35,25,0.07)] ${
                  selectedCategory === 'Tous'
                    ? 'w-[84vw] max-w-[355px] shrink-0 snap-center md:w-auto md:max-w-none'
                    : 'w-full'
                }`}
              >
                {/* Editorial Photo Frame */}
                <div className="relative aspect-[16/10] w-full overflow-hidden bg-[#062319]">
                  <img
                    src={story.image}
                    alt={story.title}
                    loading="lazy"
                    className="h-full w-full object-cover object-center transition-transform duration-700 group-hover:scale-105"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-[#062319]/75 via-[#062319]/10 to-transparent" />

                  <div className="absolute left-4 top-4">
                    <span className="inline-flex items-center rounded-full border border-[#F7EBC6]/35 bg-[#062319]/75 px-3 py-1 font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-[#F7EBC6] backdrop-blur-md">
                      {story.category}
                    </span>
                  </div>

                  <div className="absolute bottom-3.5 left-4 right-4">
                    <p className="text-[11px] font-semibold uppercase tracking-wider text-[#F5D67B]">
                      {story.subtitle}
                    </p>
                    <h3 className="mt-0.5 font-editorial text-xl font-semibold text-[#FAF8F3]">
                      {story.title}
                    </h3>
                  </div>
                </div>

                {/* Story Body */}
                <div className="flex flex-1 flex-col justify-between p-5">
                  <p className="text-[13.5px] leading-relaxed text-[#3E554B]">
                    {story.description}
                  </p>

                  <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-[#0B3B2B]/8 pt-4">
                    <div className="flex flex-wrap gap-1.5">
                      {story.highlights.map((tag) => (
                        <span
                          key={tag}
                          className="rounded-lg bg-[#0B3B2B]/6 px-2.5 py-1 text-[11px] font-semibold text-[#0B3B2B]"
                        >
                          {tag}
                        </span>
                      ))}
                    </div>

                    <button
                      type="button"
                      onClick={() => onNavigate('adhesion')}
                      className="inline-flex items-center gap-1 text-xs font-bold text-[#062319] transition-colors hover:text-[#C9961A]"
                    >
                      <span>Participer</span>
                      <ArrowUpRight className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </motion.article>
            ))}
          </AnimatePresence>
        </motion.div>

        {/* Mobile swipe hint when viewing all */}
        {selectedCategory === 'Tous' && (
          <p className="mt-2 text-center font-mono text-[10.5px] text-[#5B7268] md:hidden">
            ← Glissez horizontalement pour découvrir les 4 pôles →
          </p>
        )}
      </div>
    </section>
  )
}
