import React, { useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { X, Phone, MessageCircle, Copy, UserPlus } from 'lucide-react'
import {
  EXECUTIVE_BUREAU,
  buildWhatsAppLink,
  downloadVCard,
} from '../data/aesmgData'
import { TricoloreRibbon } from './BrandMarks'

interface QuickContactSheetProps {
  isOpen: boolean
  onClose: () => void
  onShowToast: (message: string) => void
}

export const QuickContactSheet: React.FC<QuickContactSheetProps> = ({
  isOpen,
  onClose,
  onShowToast,
}) => {
  useEffect(() => {
    if (!isOpen) return
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [isOpen, onClose])

  const copyNumber = async (label: string, num: string) => {
    try {
      await navigator.clipboard.writeText(num)
      onShowToast(`${label} copié : ${num}`)
    } catch {
      onShowToast(`Numéro : ${num}`)
    }
  }

  return (
    <AnimatePresence>
      {isOpen && (
        <div
          className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="quick-contact-title"
        >
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="absolute inset-0 bg-[#04140E]/72 backdrop-blur-sm"
          />

          {/* Tactile Bottom Sheet / Modal */}
          <motion.div
            initial={{ y: '100%', opacity: 0.85 }}
            animate={{ y: 0, opacity: 1 }}
            exit={{ y: '100%', opacity: 0 }}
            transition={{ type: 'spring', stiffness: 330, damping: 32 }}
            className="relative z-10 w-full max-w-lg overflow-hidden rounded-t-[30px] bg-[#FAF8F3] shadow-[0_-20px_60px_rgba(0,0,0,0.35)] sm:rounded-[30px]"
          >
            <TricoloreRibbon />

            {/* Drag handle pill on mobile */}
            <div className="flex justify-center pt-3 sm:hidden">
              <span className="h-1.5 w-11 rounded-full bg-[#0B3B2B]/18" />
            </div>

            <div className="p-5 pb-7 sm:p-6">
              {/* Sheet Header */}
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.18em] text-[#9B7210]">
                    Ligne Directe · AESMG
                  </p>
                  <h3
                    id="quick-contact-title"
                    className="mt-1 font-editorial text-2xl font-semibold text-[#062319]"
                  >
                    Contacter le Bureau
                  </h3>
                </div>
                <button
                  type="button"
                  onClick={onClose}
                  aria-label="Fermer"
                  className="flex h-9 w-9 items-center justify-center rounded-full bg-[#0B3B2B]/8 text-[#062319] transition-colors hover:bg-[#0B3B2B]/15"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>

              {/* Contacts List */}
              <div className="mt-5 space-y-3.5">
                {EXECUTIVE_BUREAU.map((member) => {
                  const hasSeparateWa =
                    member.whatsappDisplay !== member.phoneDisplay
                  const waHref = buildWhatsAppLink(
                    member.whatsappNumber,
                    `Bonjour ${member.name}, je contacte le Bureau de l'AESMG.`
                  )

                  return (
                    <div
                      key={member.id}
                      className="rounded-2xl border border-[#0B3B2B]/12 bg-white p-4 shadow-xs"
                    >
                      <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                          <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#062319] font-editorial text-sm font-bold text-[#F5D67B]">
                            {member.initials}
                          </div>
                          <div>
                            <span className="font-mono text-[10px] font-semibold uppercase tracking-wider text-[#9B7210]">
                              {member.role}
                            </span>
                            <p className="font-editorial text-lg leading-tight font-semibold text-[#062319]">
                              {member.name}
                            </p>
                          </div>
                        </div>

                        <button
                          type="button"
                          onClick={() => {
                            downloadVCard(member)
                            onShowToast(`Contact de ${member.name} téléchargé`)
                          }}
                          className="flex h-9 w-9 items-center justify-center rounded-xl border border-[#0B3B2B]/12 bg-[#FAF8F3] text-[#062319] hover:border-[#C9961A]"
                          title="Enregistrer le contact (.vcf)"
                        >
                          <UserPlus className="h-4 w-4" />
                        </button>
                      </div>

                      <div className="mt-3 space-y-1.5 text-xs">
                        <div className="flex items-center justify-between rounded-xl bg-[#FAF8F3] px-3 py-2">
                          <span className="text-[#4E665C]">Contact :</span>
                          <button
                            type="button"
                            onClick={() =>
                              copyNumber(member.name, member.phoneDisplay)
                            }
                            className="inline-flex items-center gap-1.5 font-mono font-semibold text-[#062319] hover:text-[#9B7210]"
                          >
                            <span>{member.phoneDisplay}</span>
                            <Copy className="h-3 w-3 opacity-60" />
                          </button>
                        </div>

                        {hasSeparateWa && (
                          <div className="flex items-center justify-between rounded-xl bg-[#ECFDF3] px-3 py-2">
                            <span className="text-[#166534]">WhatsApp :</span>
                            <button
                              type="button"
                              onClick={() =>
                                copyNumber(
                                  `WhatsApp ${member.name}`,
                                  member.whatsappDisplay
                                )
                              }
                              className="inline-flex items-center gap-1.5 font-mono font-semibold text-[#14532D] hover:underline"
                            >
                              <span>{member.whatsappDisplay}</span>
                              <Copy className="h-3 w-3 opacity-60" />
                            </button>
                          </div>
                        )}
                      </div>

                      <div className="mt-3 grid grid-cols-2 gap-2">
                        <a
                          href={`tel:${member.phoneDial}`}
                          className="inline-flex min-h-[42px] items-center justify-center gap-1.5 rounded-xl bg-[#062319] px-3 py-2 text-xs font-bold text-[#F7EBC6] active:scale-95"
                        >
                          <Phone className="h-3.5 w-3.5 text-[#E2B643]" />
                          <span>Appeler</span>
                        </a>
                        <a
                          href={waHref}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex min-h-[42px] items-center justify-center gap-1.5 rounded-xl bg-[#15803D] px-3 py-2 text-xs font-bold text-white active:scale-95"
                        >
                          <MessageCircle className="h-3.5 w-3.5" />
                          <span>WhatsApp</span>
                        </a>
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}
