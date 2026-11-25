import React from 'react'
import { Phone, MessageCircle } from 'lucide-react'
import {
  AESMG_INFO,
  EXECUTIVE_BUREAU,
  buildWhatsAppLink,
} from '../data/aesmgData'
import { AesmgSeal, TricoloreRibbon } from './BrandMarks'

interface FooterProps {
  onNavigate: (sectionId: string) => void
  onOpenBlogAdmin: () => void
}

export const Footer: React.FC<FooterProps> = ({
  onNavigate,
  onOpenBlogAdmin,
}) => {
  const president = EXECUTIVE_BUREAU[0]
  const secretary = EXECUTIVE_BUREAU[1]

  return (
    <footer className="relative bg-[#051B13] text-[#FAF8F3] pb-28 md:pb-12">
      <TricoloreRibbon />
      <div className="mx-auto max-w-6xl px-4 pt-12 sm:px-6">
        <div className="grid grid-cols-1 gap-8 border-b border-white/10 pb-10 md:grid-cols-12">
          {/* Brand Column */}
          <div className="md:col-span-5">
            <div className="flex items-center gap-3">
              <AesmgSeal size={46} variant="dark" />
              <div>
                <p className="font-editorial text-xl font-bold tracking-tight text-[#FAF8F3]">
                  AESMG
                </p>
                <p className="font-mono text-[10px] uppercase tracking-[0.16em] text-[#E2B643]">
                  {AESMG_INFO.motto}
                </p>
              </div>
            </div>

            <p className="mt-4 max-w-md text-sm leading-relaxed text-[#C4DAD0]/85">
              {AESMG_INFO.fullName}. Ensemble pour fédérer, accompagner et
              défendre tous les étudiants et stagiaires maliens au Ghana.
            </p>
          </div>

          {/* Navigation Links */}
          <div className="md:col-span-3">
            <p className="font-mono text-[10.5px] font-semibold uppercase tracking-[0.18em] text-[#E2B643]">
              Explorer
            </p>
            <ul className="mt-3.5 space-y-2.5 text-sm text-[#D6E7DF]">
              {[
                { id: 'accueil', label: 'Accueil' },
                { id: 'objectifs', label: 'Buts & Objectifs' },
                { id: 'communaute', label: 'Vie Communautaire' },
                { id: 'blog', label: 'Le Blog AESMG' },
                { id: 'bureau', label: 'Bureau Exécutif' },
                { id: 'adhesion', label: 'Rejoindre l’AESMG' },
              ].map((link) => (
                <li key={link.id}>
                  <button
                    type="button"
                    onClick={() => onNavigate(link.id)}
                    className="transition-colors hover:text-[#F5D67B]"
                  >
                    {link.label}
                  </button>
                </li>
              ))}
              <li>
                <button
                  type="button"
                  onClick={onOpenBlogAdmin}
                  className="font-semibold text-[#E2B643] transition-colors hover:text-[#F7EBC6]"
                >
                  Espace Bureau · Gérer le Blog
                </button>
              </li>
            </ul>
          </div>

          {/* Direct Bureau Coordinates */}
          <div className="md:col-span-4">
            <p className="font-mono text-[10.5px] font-semibold uppercase tracking-[0.18em] text-[#E2B643]">
              Contacts Officiels
            </p>
            <div className="mt-3.5 space-y-3 text-xs text-[#D6E7DF]">
              <div className="rounded-2xl border border-white/8 bg-white/4 p-3.5">
                <p className="font-semibold text-[#FAF8F3]">
                  {president.role} : {president.name}
                </p>
                <div className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 font-mono text-[11px] text-[#C4DAD0]">
                  <a
                    href={`tel:${president.phoneDial}`}
                    className="inline-flex items-center gap-1 hover:text-[#F5D67B]"
                  >
                    <Phone className="h-3 w-3 text-[#E2B643]" />
                    {president.phoneDisplay}
                  </a>
                  <a
                    href={buildWhatsAppLink(
                      president.whatsappNumber,
                      'Bonjour Président Diadié GUITTEYE, je vous contacte via le site AESMG.'
                    )}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 hover:text-[#6EE79D]"
                  >
                    <MessageCircle className="h-3 w-3 text-[#6EE79D]" />
                    {president.whatsappDisplay}
                  </a>
                </div>
              </div>

              <div className="rounded-2xl border border-white/8 bg-white/4 p-3.5">
                <p className="font-semibold text-[#FAF8F3]">
                  {secretary.role} : {secretary.name}
                </p>
                <div className="mt-1.5 font-mono text-[11px] text-[#C4DAD0]">
                  <a
                    href={`tel:${secretary.phoneDial}`}
                    className="inline-flex items-center gap-1 hover:text-[#F5D67B]"
                  >
                    <Phone className="h-3 w-3 text-[#E2B643]" />
                    {secretary.phoneDisplay}
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Bottom Legal Line */}
        <div className="mt-6 flex flex-col items-center justify-between gap-2 text-center text-xs text-[#9BB7AB] sm:flex-row sm:text-left">
          <p>
            © {new Date().getFullYear()} AESMG — Association des Étudiants et
            Stagiaires Maliens au Ghana.
          </p>
          <p className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-[#E2B643]/85">
            Mali · Ghana · Fraternité & Excellence
          </p>
        </div>
      </div>
    </footer>
  )
}
