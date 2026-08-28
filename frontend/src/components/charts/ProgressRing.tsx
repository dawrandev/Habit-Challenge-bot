import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { pct } from './state'

/**
 * Maqsad halqasi — "meter" formasi (pie EMAS: bitta qiymat, qismlar emas).
 *
 * Trek — o'sha rampaning ochroq pog'onasi (oltin ustiga oltin), shuning uchun
 * holat butun halqa bo'ylab o'qiladi. Maqsad chizig'i alohida belgi bo'lib
 * turadi, ya'ni "yetdimi?" savoli raqamni o'qimasdan ham ko'rinadi.
 *
 * Maqsadga matematik yetib bo'lmay qolganda to'ldirish xavf rangiga o'tadi —
 * lekin bu YOLG'IZ rang emas: yonida matnli izoh chiqadi.
 */
export default function ProgressRing({
  rate,
  goal,
  reachable,
  size = 168,
}: {
  rate: number
  goal: number
  reachable: boolean
  size?: number
}) {
  const { t } = useTranslation()
  const [mounted, setMounted] = useState(false)

  useEffect(() => setMounted(true), [])

  const stroke = 12
  const r = (size - stroke) / 2 - 6
  const c = size / 2
  const len = 2 * Math.PI * r

  const clamped = Math.max(0, Math.min(100, rate))
  const filled = (clamped / 100) * len

  const danger = !reachable
  const accent = danger ? 'var(--color-reject)' : 'var(--color-you)'

  // Maqsad belgisi — halqadagi kichik nasechka (12 soatdan boshlab)
  const goalAngle = (Math.min(100, Math.max(0, goal)) / 100) * 2 * Math.PI - Math.PI / 2
  const tickInner = r - stroke / 2 - 2
  const tickOuter = r + stroke / 2 + 2

  return (
    <div className="relative grid place-items-center" style={{ width: size, height: size }}>
      <svg
        width={size}
        height={size}
        viewBox={`0 0 ${size} ${size}`}
        role="img"
        aria-label={t('quest.a11yRing', { rate: pct(clamped), goal })}
      >
        {/* Trek — bir rampaning ochroq pog'onasi */}
        <circle
          cx={c}
          cy={c}
          r={r}
          fill="none"
          stroke="color-mix(in oklab, var(--color-you) 14%, transparent)"
          strokeWidth={stroke}
        />

        {/* To'ldirish */}
        <circle
          cx={c}
          cy={c}
          r={r}
          fill="none"
          stroke={accent}
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={`${filled} ${len - filled}`}
          strokeDashoffset={0}
          transform={`rotate(-90 ${c} ${c})`}
          className={mounted ? 'ring-draw' : undefined}
          style={{ ['--ring-len' as string]: `${len}px` }}
        />

        {/* Maqsad nasechkasi — matn tokenida, seriya rangida emas */}
        <line
          x1={c + Math.cos(goalAngle) * tickInner}
          y1={c + Math.sin(goalAngle) * tickInner}
          x2={c + Math.cos(goalAngle) * tickOuter}
          y2={c + Math.sin(goalAngle) * tickOuter}
          stroke="var(--color-text)"
          strokeWidth={2}
          strokeLinecap="round"
        />
      </svg>

      {/* Hero raqam — halqa ichida. Arena konventsiyasi: tablo raqamlari display shriftda */}
      <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
        <span
          className="font-display text-5xl leading-none"
          style={{ color: accent }}
        >
          {pct(clamped)}
          <span className="text-2xl">%</span>
        </span>
        <span className="mt-1.5 text-[11px] tracking-[0.14em] text-muted uppercase">
          {t('quest.goalShort', { goal })}
        </span>
      </div>
    </div>
  )
}
