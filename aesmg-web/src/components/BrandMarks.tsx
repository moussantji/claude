import React from 'react'

export const TricoloreRibbon: React.FC<{ className?: string }> = ({ className = '' }) => (
  <div className={`flex h-[3px] w-full overflow-hidden ${className}`} aria-hidden="true">
    <span className="flex-1 bg-[#007A4D]" />
    <span className="flex-1 bg-[#E2B643]" />
    <span className="flex-1 bg-[#B91C2E]" />
  </div>
)

export const AesmgSeal: React.FC<{
  size?: number
  variant?: 'dark' | 'light'
  className?: string
}> = ({ size = 44, variant = 'dark', className = '' }) => {
  const isDark = variant === 'dark'
  const bg = isDark ? '#062319' : '#FAF8F3'
  const ring = isDark ? '#E2B643' : '#0B3B2B'
  const star = isDark ? '#F7EBC6' : '#C9961A'
  const innerStroke = isDark ? 'rgba(226,182,67,0.32)' : 'rgba(11,59,43,0.22)'

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-label="Emblème AESMG"
    >
      {/* Outer sovereign seal */}
      <rect x="2" y="2" width="60" height="60" rx="18" fill={bg} stroke={ring} strokeWidth="1.6" />
      {/* Inner geometric Bogolan diamond */}
      <path
        d="M32 8.5L55.5 32L32 55.5L8.5 32L32 8.5Z"
        stroke={innerStroke}
        strokeWidth="1.2"
      />
      {/* Sovereign circular halo */}
      <circle cx="32" cy="32" r="19" stroke={ring} strokeWidth="1.15" strokeDasharray="2.5 2.5" />
      {/* Central Star (Ghana Black Star / Sahel Guidance Star) */}
      <path
        d="M32 16.5L35.7 26.2L46 26.6L37.9 33L40.7 43L32 37.2L23.3 43L26.1 33L18 26.6L28.3 26.2L32 16.5Z"
        fill={star}
      />
      {/* Mali-Ghana tricolore dot accents at base */}
      <circle cx="25" cy="49.5" r="2" fill="#007A4D" />
      <circle cx="32" cy="50.5" r="2.2" fill="#E2B643" />
      <circle cx="39" cy="49.5" r="2" fill="#B91C2E" />
    </svg>
  )
}

export const BogolanDivider: React.FC<{ className?: string }> = ({ className = '' }) => (
  <div className={`flex items-center justify-center gap-3 ${className}`} aria-hidden="true">
    <span className="h-[1px] flex-1 bg-gradient-to-r from-transparent via-[#0B3B2B]/20 to-[#C9961A]/45" />
    <svg width="26" height="14" viewBox="0 0 26 14" fill="none">
      <path d="M13 1L20 7L13 13L6 7L13 1Z" stroke="#C9961A" strokeWidth="1.3" />
      <circle cx="13" cy="7" r="2" fill="#0B3B2B" />
      <circle cx="2" cy="7" r="1.4" fill="#007A4D" />
      <circle cx="24" cy="7" r="1.4" fill="#B91C2E" />
    </svg>
    <span className="h-[1px] flex-1 bg-gradient-to-l from-transparent via-[#0B3B2B]/20 to-[#C9961A]/45" />
  </div>
)
