import React, { useEffect, useState } from 'react'
import { Maximize2, Smartphone, CheckCircle2, Wifi, BatteryFull, Signal } from 'lucide-react'
import { AesmgSeal, TricoloreRibbon } from './BrandMarks'
import { AESMG_INFO } from '../data/aesmgData'

interface PhoneShowcaseFrameProps {
  onExit: () => void
}

export const PhoneShowcaseFrame: React.FC<PhoneShowcaseFrameProps> = ({
  onExit,
}) => {
  const [statusTime, setStatusTime] = useState('09:41')
  const iframeSrc = `${window.location.pathname}?frame=1`

  useEffect(() => {
    const sync = () => {
      setStatusTime(
        new Intl.DateTimeFormat('fr-FR', {
          hour: '2-digit',
          minute: '2-digit',
          timeZone: 'Africa/Accra',
        }).format(new Date())
      )
    }
    sync()
    const timer = window.setInterval(sync, 30000)
    return () => window.clearInterval(timer)
  }, [])

  return (
    <div className="relative flex min-h-screen w-full flex-col items-center justify-center overflow-hidden bg-[#041710] bg-bogolan-dark px-6 py-8 text-[#FAF8F3]">
      <div className="fixed inset-x-0 top-0 z-20">
        <TricoloreRibbon />
      </div>

      {/* Subtle golden studio radial glow */}
      <div
        className="pointer-events-none absolute left-1/2 top-1/2 h-[640px] w-[640px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#E2B643]/12 blur-[140px]"
        aria-hidden="true"
      />

      <div className="relative z-10 mx-auto flex w-full max-w-6xl flex-col items-center justify-center gap-10 lg:flex-row lg:gap-16">
        {/* Left Studio Info Column */}
        <div className="max-w-md text-center lg:text-left">
          <div className="inline-flex items-center gap-2 rounded-full border border-[#E2B643]/40 bg-[#E2B643]/14 px-3.5 py-1.5 font-mono text-[10.5px] font-semibold uppercase tracking-[0.18em] text-[#F7EBC6]">
            <Smartphone className="h-3.5 w-3.5 text-[#E2B643]" />
            Exemplaire Mobile Interactif · 393×844
          </div>

          <div className="mt-5 flex items-center justify-center gap-3.5 lg:justify-start">
            <AesmgSeal size={50} variant="dark" />
            <div>
              <h1 className="font-editorial text-3xl font-bold tracking-tight text-[#FAF8F3]">
                AESMG Mobile
              </h1>
              <p className="font-mono text-[11px] uppercase tracking-[0.16em] text-[#E2B643]">
                {AESMG_INFO.motto}
              </p>
            </div>
          </div>

          <p className="mt-4 text-sm leading-relaxed text-[#D0E2D9]/85">
            Explorez l’exemplaire mobile natif de l’{AESMG_INFO.fullName} : barre
            de navigation tactile flottante, tiroir d’appel direct vers le
            Bureau, cartes swipeables et pass digital d’adhésion en temps réel.
          </p>

          <ul className="mt-5 space-y-2 text-left text-xs text-[#DCECE4]">
            {[
              'Navigation tactile flottante avec indicateur animé',
              'Blog AESMG avec lecteur mobile, favoris et partage WhatsApp',
              'Appel direct, WhatsApp & fiche contact .vcf en un geste',
              'Carte communautaire digitale interactive en temps réel',
            ].map((feature) => (
              <li key={feature} className="flex items-center gap-2.5">
                <CheckCircle2 className="h-4 w-4 shrink-0 text-[#E2B643]" />
                <span>{feature}</span>
              </li>
            ))}
          </ul>

          <button
            type="button"
            onClick={onExit}
            className="mt-7 inline-flex items-center gap-2 rounded-2xl bg-[#E2B643] px-5 py-3 text-xs font-bold text-[#062319] shadow-[0_12px_30px_rgba(226,182,67,0.3)] transition-all hover:bg-[#ECC45A] active:scale-95"
          >
            <Maximize2 className="h-4 w-4" />
            <span>Revenir à l’affichage plein écran</span>
          </button>
        </div>

        {/* Authentic Titanium Mobile Handset Frame with Clean Non-Overlapping Status Bar */}
        <div className="relative shrink-0">
          <div className="relative rounded-[54px] border-[3px] border-[#C9B387]/45 bg-[#0C1210] p-[11px] shadow-[0_38px_90px_rgba(0,0,0,0.68),inset_0_0_0_1px_rgba(255,255,255,0.12)]">
            <div className="flex h-[min(812px,84vh)] w-[393px] flex-col overflow-hidden rounded-[43px] bg-[#062319]">
              {/* Realistic Top iOS Status Bar + Dynamic Island (zero overlap with app TopBar) */}
              <div className="relative flex h-[44px] shrink-0 items-center justify-between bg-[#062319] px-7 text-[11.5px] font-semibold text-[#FAF8F3]">
                <span className="font-mono tracking-tight">{statusTime}</span>
                <div
                  className="absolute left-1/2 top-1/2 h-[24px] w-[102px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#050807] ring-1 ring-white/8"
                  aria-hidden="true"
                />
                <div className="flex items-center gap-1.5 text-[#FAF8F3]/90">
                  <Signal className="h-3.5 w-3.5" />
                  <Wifi className="h-3.5 w-3.5" />
                  <BatteryFull className="h-4 w-4" />
                </div>
              </div>

              {/* Authentic 393px Mobile Viewport */}
              <iframe
                src={iframeSrc}
                title="Aperçu mobile AESMG"
                className="w-full flex-1 border-0 bg-[#FAF8F3]"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
