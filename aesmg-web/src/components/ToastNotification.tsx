import React from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { CheckCircle2 } from 'lucide-react'

interface ToastNotificationProps {
  message: string | null
}

export const ToastNotification: React.FC<ToastNotificationProps> = ({
  message,
}) => {
  return (
    <AnimatePresence>
      {message && (
        <motion.div
          initial={{ opacity: 0, y: -18, scale: 0.96 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          exit={{ opacity: 0, y: -14, scale: 0.96 }}
          transition={{ duration: 0.25 }}
          className="pointer-events-none fixed left-1/2 top-20 z-50 w-[calc(100%-2rem)] max-w-sm -translate-x-1/2"
        >
          <div className="flex items-center gap-2.5 rounded-2xl border border-[#E2B643]/45 bg-[#062319]/95 px-4 py-3 text-xs font-semibold text-[#FAF8F3] shadow-[0_16px_40px_rgba(6,35,25,0.38)] backdrop-blur-md">
            <CheckCircle2 className="h-4 w-4 shrink-0 text-[#E2B643]" />
            <span className="leading-snug">{message}</span>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  )
}
