import React from 'react'
import { motion } from 'framer-motion'
import { Home, Target, BookOpen, Users, IdCard } from 'lucide-react'

interface BottomNavDockProps {
  activeSection: string
  onNavigate: (sectionId: string) => void
}

const TABS = [
  { id: 'accueil', label: 'Accueil', icon: Home },
  { id: 'objectifs', label: 'Objectifs', icon: Target },
  { id: 'blog', label: 'Blog', icon: BookOpen },
  { id: 'bureau', label: 'Bureau', icon: Users },
  { id: 'adhesion', label: 'Adhérer', icon: IdCard },
]

export const BottomNavDock: React.FC<BottomNavDockProps> = ({
  activeSection,
  onNavigate,
}) => {
  return (
    <div className="pointer-events-none fixed inset-x-0 bottom-0 z-40 px-3 pb-safe md:hidden">
      <nav
        aria-label="Navigation mobile"
        className="pointer-events-auto mx-auto mb-2.5 flex max-w-md items-center justify-between rounded-[26px] border border-[#E2B643]/25 bg-[#062319]/92 p-1.5 shadow-[0_18px_45px_rgba(6,35,25,0.42)] backdrop-blur-xl"
      >
        {TABS.map((tab) => {
          const Icon = tab.icon
          const active = activeSection === tab.id
          return (
            <button
              key={tab.id}
              type="button"
              onClick={() => onNavigate(tab.id)}
              aria-current={active ? 'page' : undefined}
              className="relative flex flex-1 flex-col items-center justify-center gap-0.5 rounded-[20px] py-2 text-[10px] font-semibold transition-colors focus:outline-none"
            >
              {active && (
                <motion.span
                  layoutId="mobileNavIndicator"
                  transition={{ type: 'spring', stiffness: 380, damping: 32 }}
                  className="absolute inset-0 rounded-[20px] bg-[#E2B643]"
                />
              )}
              <Icon
                className={`relative z-10 h-[18px] w-[18px] transition-colors ${
                  active ? 'text-[#062319]' : 'text-[#C8DCD2]'
                }`}
              />
              <span
                className={`relative z-10 leading-none transition-colors ${
                  active ? 'font-bold text-[#062319]' : 'text-[#C8DCD2]/90'
                }`}
              >
                {tab.label}
              </span>
            </button>
          )
        })}
      </nav>
    </div>
  )
}
