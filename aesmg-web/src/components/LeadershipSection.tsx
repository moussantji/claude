import React from 'react'
import { motion } from 'framer-motion'
import {
  Phone,
  MessageCircle,
  Copy,
  UserPlus,
  BadgeCheck,
  Share2,
} from 'lucide-react'
import {
  EXECUTIVE_BUREAU,
  buildWhatsAppLink,
  downloadVCard,
  type ExecutiveMember,
} from '../data/aesmgData'
import { BogolanDivider } from './BrandMarks'

interface LeadershipSectionProps {
  onShowToast: (message: string) => void
}

export const LeadershipSection: React.FC<LeadershipSectionProps> = ({
  onShowToast,
}) => {
  const handleCopy = async (label: string, value: string) => {
    try {
      await navigator.clipboard.writeText(value)
      onShowToast(`${label} copié : ${value}`)
    } catch {
      onShowToast(`Numéro : ${value}`)
    }
  }

  const handleDownloadVCard = (member: ExecutiveMember) => {
    downloadVCard(member)
    onShowToast(`Fiche contact de ${member.name} enregistrée (.vcf)`)
  }

  const handleCopyFullDirectory = async () => {
    const directoryText = [
      '🇲🇱🇬🇭 Association des Étudiants et Stagiaires Maliens au Ghana (AESMG)',
      '',
      '• Président : Diadié GUITTEYE',
      '  Contact : +233 535478560',
      '  WhatsApp : +223 91035026',
      '',
      '• Secrétaire général : Georgette Jeanne MARGNA',
      '  Contact : +233 50 615 5639',
    ].join('\n')

    if (navigator.share) {
      try {
        await navigator.share({
          title: 'Contacts Officiels — AESMG',
          text: directoryText,
        })
        return
      } catch {
        // Fallback to clipboard if user cancels or platform declines
      }
    }

    try {
      await navigator.clipboard.writeText(directoryText)
      onShowToast('Annuaire officiel du Bureau copié — prêt à partager !')
    } catch {
      onShowToast('Impossible de copier automatiquement.')
    }
  }

  return (
    <section
      id="bureau"
      className="relative overflow-hidden bg-[#062319] bg-bogolan-dark px-4 py-16 text-[#FAF8F3] sm:px-6 md:py-22"
    >
      {/* Ambient architectural gold light */}
      <div
        className="pointer-events-none absolute left-1/2 top-0 h-72 w-[520px] -translate-x-1/2 rounded-full bg-[#E2B643]/10 blur-[110px]"
        aria-hidden="true"
      />

      <div className="relative mx-auto max-w-6xl">
        {/* Section Heading */}
        <div className="mx-auto max-w-2xl text-center">
          <span className="inline-flex items-center gap-2 rounded-full border border-[#E2B643]/40 bg-[#E2B643]/12 px-3.5 py-1.5 font-mono text-[10.5px] font-semibold uppercase tracking-[0.2em] text-[#F7EBC6]">
            <BadgeCheck className="h-3.5 w-3.5 text-[#E2B643]" />
            Contacts Officiels · Bureau Exécutif
          </span>

          <h2 className="mt-4 font-editorial text-[1.95rem] leading-[1.12] font-semibold tracking-tight text-[#FAF8F3] sm:text-4xl">
            À l’écoute de{' '}
            <span className="italic text-[#F5D67B]">chaque membre</span>
          </h2>

          <p className="mt-3 text-[14.5px] leading-relaxed text-[#D4E4DC]/85 sm:text-base">
            Besoin d’un renseignement, d’un accompagnement ou d’un accueil au
            Ghana ? Contactez directement les responsables de l’AESMG en un
            geste.
          </p>

          <BogolanDivider className="mx-auto mt-6 max-w-xs opacity-75" />
        </div>

        {/* Sovereign Diplomatic Executive Cards */}
        <div className="mt-10 grid grid-cols-1 gap-5 md:grid-cols-2 lg:gap-7">
          {EXECUTIVE_BUREAU.map((leader, idx) => {
            const hasSeparateWhatsapp =
              leader.whatsappDisplay !== leader.phoneDisplay
            const waLink = buildWhatsAppLink(
              leader.whatsappNumber,
              `Bonjour ${leader.name}, je vous contacte via le site officiel de l'AESMG.`
            )

            return (
              <motion.article
                key={leader.id}
                initial={{ opacity: 0, y: 22 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: '-40px' }}
                transition={{ duration: 0.5, delay: idx * 0.1 }}
                className="relative flex flex-col justify-between overflow-hidden rounded-[28px] border border-[#E2B643]/28 bg-gradient-to-br from-[#0B3527] via-[#082A1E] to-[#051B13] p-6 shadow-[0_24px_60px_rgba(0,0,0,0.34)] sm:p-7"
              >
                {/* Decorative top tricolore bar */}
                <div className="absolute inset-x-0 top-0 flex h-[3px]">
                  <span className="flex-1 bg-[#007A4D]" />
                  <span className="flex-1 bg-[#E2B643]" />
                  <span className="flex-1 bg-[#B91C2E]" />
                </div>

                <div>
                  {/* Leader Monogram + Role Identity */}
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-4">
                      {/* Sovereign Monogram Medallion */}
                      <div className="relative flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-[#E2B643]/55 bg-gradient-to-br from-[#134D39] to-[#062319] shadow-inner">
                        <span className="font-editorial text-xl font-bold tracking-tight text-[#F7EBC6]">
                          {leader.initials}
                        </span>
                        <span
                          className="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-[#E2B643] text-[#062319] shadow"
                          title="Contact officiel vérifié"
                        >
                          <BadgeCheck className="h-3.5 w-3.5" />
                        </span>
                      </div>

                      <div>
                        <span className="inline-block rounded-full bg-[#E2B643]/16 px-2.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-[#F5D67B]">
                          {leader.role}
                        </span>
                        <h3 className="mt-1.5 font-editorial text-2xl leading-tight font-semibold text-[#FAF8F3]">
                          {leader.name}
                        </h3>
                        <p className="mt-0.5 text-xs text-[#B6D0C4]">
                          {leader.roleBadge}
                        </p>
                      </div>
                    </div>

                    {/* Save vCard Action */}
                    <button
                      type="button"
                      onClick={() => handleDownloadVCard(leader)}
                      className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/12 bg-white/6 text-[#F7EBC6] transition-all hover:border-[#E2B643]/60 hover:bg-[#E2B643]/20 active:scale-95"
                      title={`Enregistrer ${leader.name} dans mes contacts (.vcf)`}
                      aria-label={`Enregistrer la fiche contact de ${leader.name}`}
                    >
                      <UserPlus className="h-4 w-4" />
                    </button>
                  </div>

                  <p className="mt-4 text-[13.5px] leading-relaxed text-[#D7E7DF]/85">
                    {leader.mandateNote}
                  </p>

                  {/* Phone & WhatsApp Structured Readouts */}
                  <div className="mt-5 space-y-2.5">
                    {/* Primary Contact Line */}
                    <div className="flex items-center justify-between gap-2 rounded-2xl border border-white/10 bg-[#051B13]/75 px-4 py-3">
                      <div className="min-w-0">
                        <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.15em] text-[#A8C4B6]">
                          Contact (Ghana)
                        </p>
                        <p className="mt-0.5 font-mono text-[15px] font-semibold tracking-wide text-[#FAF8F3]">
                          {leader.phoneDisplay}
                        </p>
                      </div>
                      <button
                        type="button"
                        onClick={() =>
                          handleCopy(
                            `Contact de ${leader.name}`,
                            leader.phoneDisplay
                          )
                        }
                        className="inline-flex h-9 items-center gap-1.5 rounded-xl border border-white/12 bg-white/6 px-3 text-[11px] font-semibold text-[#F7EBC6] transition-colors hover:bg-white/14 active:scale-95"
                      >
                        <Copy className="h-3.5 w-3.5" />
                        <span>Copier</span>
                      </button>
                    </div>

                    {/* Dedicated WhatsApp Line (shown prominently when distinct, or unified badge) */}
                    {hasSeparateWhatsapp ? (
                      <div className="flex items-center justify-between gap-2 rounded-2xl border border-[#25D366]/25 bg-[#25D366]/8 px-4 py-3">
                        <div className="min-w-0">
                          <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.15em] text-[#7EE8A6]">
                            WhatsApp Officiel
                          </p>
                          <p className="mt-0.5 font-mono text-[15px] font-semibold tracking-wide text-[#FAF8F3]">
                            {leader.whatsappDisplay}
                          </p>
                        </div>
                        <button
                          type="button"
                          onClick={() =>
                            handleCopy(
                              `WhatsApp de ${leader.name}`,
                              leader.whatsappDisplay
                            )
                          }
                          className="inline-flex h-9 items-center gap-1.5 rounded-xl border border-[#25D366]/30 bg-[#25D366]/14 px-3 text-[11px] font-semibold text-[#B9F6CE] transition-colors hover:bg-[#25D366]/25 active:scale-95"
                        >
                          <Copy className="h-3.5 w-3.5" />
                          <span>Copier</span>
                        </button>
                      </div>
                    ) : (
                      <div className="flex items-center justify-between gap-2 rounded-2xl border border-white/8 bg-white/4 px-4 py-2.5">
                        <p className="text-[12px] text-[#CBE0D5]">
                          Ligne directe joignable par appel & WhatsApp
                        </p>
                        <span className="h-2 w-2 rounded-full bg-[#25D366]" />
                      </div>
                    )}
                  </div>
                </div>

                {/* One-Tap Mobile Action Buttons */}
                <div className="mt-6 grid grid-cols-2 gap-2.5">
                  <a
                    href={`tel:${leader.phoneDial}`}
                    className="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-2xl bg-[#E2B643] px-4 py-3 text-xs font-bold text-[#062319] shadow-[0_8px_24px_rgba(226,182,67,0.28)] transition-all hover:bg-[#ECC45A] active:scale-[0.98]"
                  >
                    <Phone className="h-4 w-4" />
                    <span>Appeler</span>
                  </a>

                  <a
                    href={waLink}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-2xl border border-[#25D366]/40 bg-[#1B6B3F]/45 px-4 py-3 text-xs font-bold text-[#ECFDF3] transition-all hover:bg-[#1B6B3F]/70 active:scale-[0.98]"
                  >
                    <MessageCircle className="h-4 w-4 text-[#6EE79D]" />
                    <span>WhatsApp</span>
                  </a>
                </div>
              </motion.article>
            )
          })}
        </div>

        {/* One-tap Share Complete Bureau Directory Pill */}
        <div className="mt-7 flex justify-center">
          <button
            type="button"
            onClick={handleCopyFullDirectory}
            className="inline-flex min-h-[46px] items-center gap-2.5 rounded-full border border-[#E2B643]/35 bg-[#0B3527]/85 px-5 py-2.5 text-xs font-semibold text-[#F7EBC6] shadow-md transition-all hover:border-[#E2B643] hover:bg-[#114735] active:scale-95"
          >
            <Share2 className="h-4 w-4 text-[#E2B643]" />
            <span>Partager ou copier l’annuaire complet du Bureau</span>
          </button>
        </div>
      </div>
    </section>
  )
}
