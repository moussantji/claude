import React, { useState } from 'react'
import { motion } from 'framer-motion'
import { Check, Compass, MessageCircle, ArrowRight } from 'lucide-react'
import {
  EXECUTIVE_BUREAU,
  NEWCOMER_GUIDE,
  buildWhatsAppLink,
} from '../data/aesmgData'

export const NewcomerGuideSection: React.FC = () => {
  const [activeStepIndex, setActiveStepIndex] = useState(0)
  const activeStep = NEWCOMER_GUIDE[activeStepIndex]
  const recommendedContact =
    EXECUTIVE_BUREAU.find((m) => m.id === activeStep.recommendedContactId) ??
    EXECUTIVE_BUREAU[0]

  const whatsappHref = buildWhatsAppLink(
    recommendedContact.whatsappNumber,
    `Bonjour ${recommendedContact.name}, je suis étudiant(e)/stagiaire malien(ne) au Ghana et je souhaite entrer en contact avec l'AESMG concernant : ${activeStep.title}.`
  )

  return (
    <section className="bg-[#FAF8F3] px-4 py-14 sm:px-6 md:py-18">
      <div className="mx-auto max-w-6xl">
        <div className="overflow-hidden rounded-[30px] border border-[#0B3B2B]/12 bg-white shadow-[0_18px_50px_rgba(6,35,25,0.06)]">
          <div className="grid grid-cols-1 lg:grid-cols-12">
            {/* Left Intro + Step Switcher */}
            <div className="bg-[#062319] bg-bogolan-dark p-6 text-[#FAF8F3] sm:p-8 lg:col-span-5">
              <div className="inline-flex items-center gap-2 rounded-full border border-[#E2B643]/35 bg-[#E2B643]/12 px-3 py-1 font-mono text-[10px] font-semibold uppercase tracking-[0.18em] text-[#F7EBC6]">
                <Compass className="h-3.5 w-3.5 text-[#E2B643]" />
                Guide Nouvel Arrivant
              </div>

              <h2 className="mt-4 font-editorial text-2xl leading-tight font-semibold sm:text-3xl">
                Vous venez d’arriver{' '}
                <span className="italic text-[#F5D67B]">au Ghana ?</span>
              </h2>
              <p className="mt-2.5 text-[13.5px] leading-relaxed text-[#D7E6DF]/85">
                L’AESMG accompagne vos premiers pas pour une installation
                sereine, réussie et entourée.
              </p>

              {/* Step Selector Pills */}
              <div className="mt-6 space-y-2.5">
                {NEWCOMER_GUIDE.map((item, idx) => {
                  const selected = idx === activeStepIndex
                  return (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setActiveStepIndex(idx)}
                      className={`flex w-full items-center justify-between rounded-2xl border px-4 py-3.5 text-left transition-all ${
                        selected
                          ? 'border-[#E2B643] bg-[#E2B643]/14 text-[#FAF8F3]'
                          : 'border-white/10 bg-white/5 text-[#C7DBD1] hover:bg-white/10'
                      }`}
                    >
                      <div>
                        <span className="block font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-[#E2B643]">
                          {item.step}
                        </span>
                        <span className="mt-0.5 block text-[13.5px] font-semibold">
                          {item.title}
                        </span>
                      </div>
                      <ArrowRight
                        className={`h-4 w-4 shrink-0 transition-transform ${
                          selected ? 'translate-x-0.5 text-[#E2B643]' : 'opacity-40'
                        }`}
                      />
                    </button>
                  )
                })}
              </div>
            </div>

            {/* Right Active Step Checklist Panel */}
            <div className="flex flex-col justify-between p-6 sm:p-8 lg:col-span-7">
              <motion.div
                key={activeStep.id}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3 }}
              >
                <span className="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-[#9B7210]">
                  {activeStep.step} · Feuille de route
                </span>
                <h3 className="mt-2 font-editorial text-2xl font-semibold text-[#062319]">
                  {activeStep.title}
                </h3>
                <p className="mt-2.5 text-[14.5px] leading-relaxed text-[#425A50]">
                  {activeStep.summary}
                </p>

                <div className="mt-6 space-y-3">
                  {activeStep.checklist.map((point) => (
                    <div
                      key={point}
                      className="flex items-start gap-3 rounded-2xl border border-[#0B3B2B]/8 bg-[#FAF8F3] p-3.5"
                    >
                      <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#062319] text-[#E2B643]">
                        <Check className="h-3.5 w-3.5" />
                      </span>
                      <p className="text-[13.5px] font-medium leading-snug text-[#173026]">
                        {point}
                      </p>
                    </div>
                  ))}
                </div>
              </motion.div>

              {/* Recommended Direct Contact CTA */}
              <div className="mt-6 flex flex-col gap-3 rounded-2xl border border-[#C9961A]/28 bg-[#F7EBC6]/35 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p className="font-mono text-[10px] font-semibold uppercase tracking-wider text-[#8C640B]">
                    Point de contact conseillé
                  </p>
                  <p className="text-sm font-bold text-[#062319]">
                    {recommendedContact.name}{' '}
                    <span className="font-normal text-[#425A50]">
                      — {recommendedContact.role}
                    </span>
                  </p>
                </div>

                <a
                  href={whatsappHref}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex min-h-[44px] shrink-0 items-center justify-center gap-2 rounded-xl bg-[#062319] px-4 py-2.5 text-xs font-bold text-[#F7EBC6] shadow-sm transition-all hover:bg-[#0B3B2B] active:scale-95"
                >
                  <MessageCircle className="h-4 w-4 text-[#E2B643]" />
                  <span>Écrire sur WhatsApp</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
