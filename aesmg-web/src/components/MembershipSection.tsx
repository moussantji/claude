import React, { useState } from 'react'
import { motion } from 'framer-motion'
import {
  Send,
  Sparkles,
  GraduationCap,
  Briefcase,
  MapPin,
  Building2,
  CheckCircle2,
} from 'lucide-react'
import {
  AESMG_INFO,
  EXECUTIVE_BUREAU,
  buildWhatsAppLink,
} from '../data/aesmgData'
import { AesmgSeal, TricoloreRibbon } from './BrandMarks'

interface MembershipSectionProps {
  onShowToast: (message: string) => void
}

export const MembershipSection: React.FC<MembershipSectionProps> = ({
  onShowToast,
}) => {
  const [fullName, setFullName] = useState('')
  const [status, setStatus] = useState<'Étudiant' | 'Stagiaire'>('Étudiant')
  const [city, setCity] = useState<string>('Accra')
  const [institution, setInstitution] = useState('')
  const [recipientId, setRecipientId] = useState<'president' | 'secretary'>('president')
  const [submitted, setSubmitted] = useState(false)

  const displayName = fullName.trim() || 'Votre Nom & Prénom'
  const displayInstitution = institution.trim() || 'Université / Structure d’accueil'
  const selectedRecipient =
    EXECUTIVE_BUREAU.find((m) => m.id === recipientId) ?? EXECUTIVE_BUREAU[0]

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    const cleanName = fullName.trim()
    if (!cleanName) {
      onShowToast('Veuillez saisir votre nom complet pour générer votre fiche.')
      return
    }

    const message = [
      `Bonjour ${selectedRecipient.name} (${selectedRecipient.role} AESMG),`,
      `Je souhaite rejoindre l'Association des Étudiants et Stagiaires Maliens au Ghana (AESMG).`,
      ``,
      `• Nom complet : ${cleanName}`,
      `• Statut : ${status}`,
      `• Ville au Ghana : ${city}`,
      institution.trim() ? `• Établissement / Filière : ${institution.trim()}` : '',
      ``,
      `Merci pour votre accueil fraternel !`,
    ]
      .filter(Boolean)
      .join('\n')

    setSubmitted(true)
    onShowToast(`Demande prête pour ${selectedRecipient.name} sur WhatsApp !`)
    const url = buildWhatsAppLink(selectedRecipient.whatsappNumber, message)
    window.open(url, '_blank', 'noopener,noreferrer')
  }

  return (
    <section
      id="adhesion"
      className="relative bg-[#FAF8F3] bg-bogolan-subtle px-4 py-16 sm:px-6 md:py-22"
    >
      <div className="mx-auto max-w-6xl">
        {/* Heading */}
        <div className="mx-auto max-w-2xl text-center">
          <span className="inline-flex items-center gap-2 rounded-full border border-[#C9961A]/35 bg-[#F7EBC6]/55 px-3.5 py-1.5 font-mono text-[10.5px] font-semibold uppercase tracking-[0.18em] text-[#7D5708]">
            <Sparkles className="h-3.5 w-3.5 text-[#B58211]" />
            Adhésion & Pass Communautaire
          </span>

          <h2 className="mt-4 font-editorial text-[1.95rem] leading-[1.12] font-semibold tracking-tight text-[#062319] sm:text-4xl">
            Rejoignez la famille{' '}
            <span className="italic text-[#0F513A]">AESMG</span>
          </h2>

          <p className="mt-3 text-[14.5px] leading-relaxed text-[#425A50]">
            Prévisualisez votre carte communautaire digitale et transmettez
            votre inscription en un clic directement au Bureau Exécutif sur
            WhatsApp.
          </p>
        </div>

        {/* Two-column Luxury Split: Live Digital Pass + Interactive Form */}
        <div className="mt-10 grid grid-cols-1 items-start gap-7 lg:grid-cols-12">
          {/* Live Interactive Digital Member Pass */}
          <div className="lg:col-span-5 lg:sticky lg:top-24">
            <motion.div
              whileHover={{ y: -3 }}
              transition={{ type: 'spring', stiffness: 260, damping: 20 }}
              className="card-shimmer relative overflow-hidden rounded-[28px] border border-[#E2B643]/45 bg-gradient-to-br from-[#0A3828] via-[#062319] to-[#041610] p-6 text-[#FAF8F3] shadow-[0_26px_65px_rgba(6,35,25,0.28)]"
            >
              {/* Subtle Bogolan geometric watermark */}
              <div
                className="pointer-events-none absolute inset-0 bg-bogolan-dark opacity-70"
                aria-hidden="true"
              />
              {/* Radial champagne glow */}
              <div
                className="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-[#E2B643]/22 blur-3xl"
                aria-hidden="true"
              />

              <div className="relative flex flex-col justify-between min-h-[224px]">
                {/* Pass Header */}
                <div className="flex items-start justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <AesmgSeal size={42} variant="dark" className="shrink-0" />
                    <div>
                      <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-[#E2B643]">
                        Carte Communautaire
                      </p>
                      <p className="font-editorial text-base font-bold tracking-tight text-[#FAF8F3]">
                        AESMG · GHANA
                      </p>
                    </div>
                  </div>

                  <span className="inline-flex items-center gap-1 rounded-full border border-[#E2B643]/40 bg-[#E2B643]/14 px-2.5 py-1 font-mono text-[10px] font-semibold uppercase text-[#F7EBC6]">
                    {status === 'Étudiant' ? (
                      <GraduationCap className="h-3 w-3 text-[#E2B643]" />
                    ) : (
                      <Briefcase className="h-3 w-3 text-[#E2B643]" />
                    )}
                    {status}
                  </span>
                </div>

                {/* Live Member Name & Details */}
                <div className="mt-7">
                  <p className="font-mono text-[9.5px] uppercase tracking-[0.18em] text-[#A8C6B8]">
                    Membre de la Communauté
                  </p>
                  <p className="mt-1 truncate font-editorial text-2xl font-semibold text-[#FAF8F3]">
                    {displayName}
                  </p>

                  <div className="mt-3.5 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-[#D5E6DD]">
                    <span className="inline-flex items-center gap-1.5">
                      <MapPin className="h-3.5 w-3.5 text-[#E2B643]" />
                      {city}, Ghana
                    </span>
                    <span className="inline-flex min-w-0 items-center gap-1.5">
                      <Building2 className="h-3.5 w-3.5 shrink-0 text-[#E2B643]" />
                      <span className="truncate max-w-[200px]">
                        {displayInstitution}
                      </span>
                    </span>
                  </div>
                </div>

                {/* Pass Footer */}
                <div className="mt-6">
                  <TricoloreRibbon className="rounded-full opacity-90" />
                  <div className="mt-3 flex items-center justify-between font-mono text-[10px] text-[#A8C6B8]">
                    <span>{AESMG_INFO.motto}</span>
                    <span className="text-[#E2B643]">MALI · GHANA</span>
                  </div>
                </div>
              </div>
            </motion.div>

            <p className="mt-3 text-center text-xs text-[#546C62]">
              Aperçu dynamique mis à jour en temps réel selon vos informations.
            </p>
          </div>

          {/* Interactive Mobile-Friendly Registration Form */}
          <form
            onSubmit={handleSubmit}
            className="rounded-[28px] border border-[#0B3B2B]/12 bg-white p-5 shadow-[0_14px_40px_rgba(6,35,25,0.06)] sm:p-7 lg:col-span-7"
          >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {/* Full Name */}
              <div className="sm:col-span-2">
                <label
                  htmlFor="aesmg-name"
                  className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#264237]"
                >
                  Nom & Prénom *
                </label>
                <input
                  id="aesmg-name"
                  type="text"
                  required
                  value={fullName}
                  onChange={(e) => {
                    setFullName(e.target.value)
                    setSubmitted(false)
                  }}
                  placeholder="Ex. Aminata TRAORÉ"
                  className="min-h-[48px] w-full rounded-2xl border border-[#0B3B2B]/15 bg-[#FAF8F3] px-4 py-3 text-sm font-medium text-[#062319] placeholder:text-[#7E948B] focus:border-[#0B3B2B] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#E2B643]/40"
                />
              </div>

              {/* Status Toggle */}
              <div className="sm:col-span-2">
                <span className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#264237]">
                  Votre Statut au Ghana
                </span>
                <div className="grid grid-cols-2 gap-2.5">
                  {(['Étudiant', 'Stagiaire'] as const).map((option) => {
                    const active = status === option
                    return (
                      <button
                        key={option}
                        type="button"
                        onClick={() => setStatus(option)}
                        className={`flex min-h-[48px] items-center justify-center gap-2 rounded-2xl border text-sm font-semibold transition-all ${
                          active
                            ? 'border-[#062319] bg-[#062319] text-[#F7EBC6] shadow-sm'
                            : 'border-[#0B3B2B]/14 bg-[#FAF8F3] text-[#284238] hover:border-[#C9961A]/50'
                        }`}
                      >
                        {option === 'Étudiant' ? (
                          <GraduationCap className="h-4 w-4" />
                        ) : (
                          <Briefcase className="h-4 w-4" />
                        )}
                        <span>{option}</span>
                      </button>
                    )
                  })}
                </div>
              </div>

              {/* City */}
              <div>
                <label
                  htmlFor="aesmg-city"
                  className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#264237]"
                >
                  Ville au Ghana
                </label>
                <select
                  id="aesmg-city"
                  value={city}
                  onChange={(e) => setCity(e.target.value)}
                  className="min-h-[48px] w-full rounded-2xl border border-[#0B3B2B]/15 bg-[#FAF8F3] px-4 py-3 text-sm font-medium text-[#062319] focus:border-[#0B3B2B] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#E2B643]/40"
                >
                  {AESMG_INFO.cities.map((c) => (
                    <option key={c} value={c}>
                      {c}
                    </option>
                  ))}
                </select>
              </div>

              {/* Institution / Field */}
              <div>
                <label
                  htmlFor="aesmg-institution"
                  className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#264237]"
                >
                  Université / Structure
                </label>
                <input
                  id="aesmg-institution"
                  type="text"
                  value={institution}
                  onChange={(e) => setInstitution(e.target.value)}
                  placeholder="Ex. University of Ghana / Stage"
                  className="min-h-[48px] w-full rounded-2xl border border-[#0B3B2B]/15 bg-[#FAF8F3] px-4 py-3 text-sm font-medium text-[#062319] placeholder:text-[#7E948B] focus:border-[#0B3B2B] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#E2B643]/40"
                />
              </div>

              {/* Choose Bureau Recipient */}
              <div className="sm:col-span-2">
                <span className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#264237]">
                  Transmettre ma fiche sur WhatsApp à
                </span>
                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                  {EXECUTIVE_BUREAU.map((member) => {
                    const active = recipientId === member.id
                    return (
                      <button
                        key={member.id}
                        type="button"
                        onClick={() =>
                          setRecipientId(member.id as 'president' | 'secretary')
                        }
                        className={`flex items-center justify-between rounded-2xl border px-3.5 py-3 text-left transition-all ${
                          active
                            ? 'border-[#C9961A] bg-[#F7EBC6]/45 text-[#062319]'
                            : 'border-[#0B3B2B]/12 bg-[#FAF8F3] text-[#355046] hover:border-[#0B3B2B]/30'
                        }`}
                      >
                        <div className="min-w-0">
                          <p className="font-mono text-[10px] font-semibold uppercase tracking-wider text-[#8C640B]">
                            {member.role}
                          </p>
                          <p className="truncate text-xs font-bold text-[#062319]">
                            {member.name}
                          </p>
                          <p className="font-mono text-[10.5px] text-[#4C655B]">
                            {member.whatsappDisplay}
                          </p>
                        </div>
                        <span
                          className={`h-4 w-4 shrink-0 rounded-full border-2 ${
                            active
                              ? 'border-[#062319] bg-[#E2B643]'
                              : 'border-[#0B3B2B]/25'
                          }`}
                        />
                      </button>
                    )
                  })}
                </div>
              </div>
            </div>

            <button
              type="submit"
              className="mt-6 inline-flex min-h-[54px] w-full items-center justify-center gap-2.5 rounded-2xl bg-[#062319] px-6 py-3.5 text-sm font-bold text-[#F7EBC6] shadow-[0_14px_34px_rgba(6,35,25,0.22)] transition-all hover:bg-[#0B3B2B] active:scale-[0.98]"
            >
              <Send className="h-4 w-4 text-[#E2B643]" />
              <span>Envoyer ma demande d’adhésion via WhatsApp</span>
            </button>

            {submitted && (
              <div className="mt-3.5 flex items-center gap-2 rounded-xl bg-[#0B3B2B]/8 px-3.5 py-2.5 text-xs font-medium text-[#0B3B2B]">
                <CheckCircle2 className="h-4 w-4 shrink-0 text-[#007A4D]" />
                <span>
                  Votre message pré-rempli a été préparé pour{' '}
                  <strong>{selectedRecipient.name}</strong>. Bienvenue à l’AESMG !
                </span>
              </div>
            )}
          </form>
        </div>
      </div>
    </section>
  )
}
