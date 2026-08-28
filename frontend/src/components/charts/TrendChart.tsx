import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { QuestDay } from '../../lib/quests'
import { pct, shortDate } from './state'

/**
 * Kümülativ bajarish foizi — vaqt bo'yicha o'zgarish.
 *
 * BITTA o'q (0–100%), bitta seriya. Maqsad — seriya emas, mos yozuvlar
 * chizig'i. Ikkinchi o'q YO'Q: ikki xil o'lchovni bitta ramkaga tiqish
 * soxta bog'liqlik yaratadi. Bitta seriya bo'lgani uchun legenda qutisi
 * ham yo'q — sarlavha nima chizilganini aytadi.
 *
 * Faqat oxirgi nuqta doimiy belgilanadi; qolgani bosish orqali ochiladi.
 */
type Point = { x: number; y: number; date: string; rate: number }

const W = 320
const H = 132
const PAD_L = 26
const PAD_R = 12
const PAD_T = 10
const PAD_B = 22 // x-o'qi yozuvlari uchun joy — konteynerdan chiqmasin

export default function TrendChart({
  days,
  goal,
}: {
  days: QuestDay[]
  goal: number
}) {
  const { t } = useTranslation()
  const [picked, setPicked] = useState<number | null>(null)

  const series = useMemo<Point[]>(() => {
    let done = 0
    let decided = 0
    const out: Omit<Point, 'x' | 'y'>[] = []

    for (const day of days) {
      if (day.due === 0 || day.state === 'future') continue

      // Bugungi tugallanmagan slot hali hal bo'lmagan — maxrajga kirmaydi.
      // Shu qoida sarlavhadagi foiz bilan aynan mos tushadi.
      const decidedToday = day.state === 'pending' ? day.done : day.due
      if (decidedToday === 0) continue

      done += day.done
      decided += decidedToday
      out.push({ date: day.date, rate: (done / decided) * 100 })
    }

    const n = out.length
    return out.map((p, i) => ({
      ...p,
      x: PAD_L + (n <= 1 ? 0 : (i / (n - 1)) * (W - PAD_L - PAD_R)),
      y: PAD_T + (1 - p.rate / 100) * (H - PAD_T - PAD_B),
    }))
  }, [days])

  if (series.length < 2) {
    return (
      <p className="py-6 text-center text-xs text-muted">
        {t('quest.notEnoughData')}
      </p>
    )
  }

  const yTick = (v: number) => PAD_T + (1 - v / 100) * (H - PAD_T - PAD_B)
  const baseY = H - PAD_B

  const line = series.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x} ${p.y}`).join(' ')
  const area = `${line} L${series[series.length - 1].x} ${baseY} L${series[0].x} ${baseY} Z`
  const last = series[series.length - 1]
  const shown = picked !== null ? series[picked] : null

  const approxLen = series.reduce((sum, p, i) => {
    if (i === 0) return 0
    const prev = series[i - 1]
    return sum + Math.hypot(p.x - prev.x, p.y - prev.y)
  }, 0)

  return (
    <div>
      <svg
        viewBox={`0 0 ${W} ${H}`}
        className="w-full"
        role="img"
        aria-label={t('quest.a11yTrend', { rate: pct(Math.round(last.rate * 10) / 10) })}
      >
        {/* To'r — bir pog'ona sirtdan farqli, ingichka va TUTASH (punktir emas) */}
        {[0, 50, 100].map((v) => (
          <line
            key={v}
            x1={PAD_L}
            y1={yTick(v)}
            x2={W - PAD_R}
            y2={yTick(v)}
            stroke="var(--color-line)"
            strokeWidth={1}
          />
        ))}
        {[0, 50, 100].map((v) => (
          <text
            key={`t${v}`}
            x={PAD_L - 5}
            y={yTick(v) + 3}
            textAnchor="end"
            fontSize={8}
            fill="var(--color-muted)"
            fontFamily="var(--font-mono)"
          >
            {v}
          </text>
        ))}

        {/* Maydon — ~10% shabada, to'q blok emas */}
        <path d={area} fill="var(--color-you)" opacity={0.1} />

        {/* Maqsad — mos yozuvlar chizig'i (matn tokenida, seriya rangida emas) */}
        <line
          x1={PAD_L}
          y1={yTick(goal)}
          x2={W - PAD_R}
          y2={yTick(goal)}
          stroke="var(--color-text)"
          strokeWidth={1}
          opacity={0.55}
        />
        {/* Yozuv CHAPDA: o'ng chekkada oxirgi nuqta belgisi turadi va
            ustma-ust tushardi. Yorliq chizig'idan uzilib qolmasin uchun
            uning ustiga, boshiga qo'yiladi. */}
        <text
          x={PAD_L + 3}
          y={yTick(goal) - 4}
          fontSize={8}
          fill="var(--color-muted)"
          fontFamily="var(--font-mono)"
        >
          {t('quest.goalShort', { goal })}
        </text>

        {/* Seriya — 2px, yumaloq uchlar */}
        <path
          d={line}
          fill="none"
          stroke="var(--color-you)"
          strokeWidth={2}
          strokeLinecap="round"
          strokeLinejoin="round"
          className="trend-draw"
          style={{
            ['--trend-len' as string]: `${approxLen}px`,
            strokeDasharray: approxLen,
          }}
        />

        {/* Bosish maydonlari — belgidan kattaroq (mobil uchun) */}
        {series.map((p, i) => (
          <rect
            key={p.date}
            x={p.x - 6}
            y={PAD_T}
            width={12}
            height={H - PAD_T - PAD_B}
            fill="transparent"
            onClick={() => setPicked(picked === i ? null : i)}
            style={{ cursor: 'pointer' }}
          />
        ))}

        {shown && (
          <>
            <line
              x1={shown.x}
              y1={PAD_T}
              x2={shown.x}
              y2={baseY}
              stroke="var(--color-muted)"
              strokeWidth={1}
            />
            <circle
              cx={shown.x}
              cy={shown.y}
              r={4}
              fill="var(--color-you)"
              stroke="var(--color-surface)"
              strokeWidth={2}
            />
          </>
        )}

        {/* Oxirgi nuqta — sirt halqasi bilan */}
        <circle
          cx={last.x}
          cy={last.y}
          r={4}
          fill="var(--color-you)"
          stroke="var(--color-surface)"
          strokeWidth={2}
        />

        {/* x-o'qi: faqat chekka sanalar — har kunga yozuv xaos bo'lardi */}
        <text
          x={PAD_L}
          y={H - 5}
          fontSize={8}
          fill="var(--color-muted)"
          fontFamily="var(--font-mono)"
        >
          {shortDate(series[0].date)}
        </text>
        <text
          x={W - PAD_R}
          y={H - 5}
          textAnchor="end"
          fontSize={8}
          fill="var(--color-muted)"
          fontFamily="var(--font-mono)"
        >
          {shortDate(last.date)}
        </text>
      </svg>

      <div className="mt-1 flex items-center justify-between gap-2 px-1 text-[11px]">
        <span className="truncate text-muted">
          {shown
            ? `${shown.date} · ${pct(Math.round(shown.rate * 10) / 10)}%`
            : t('quest.tapPoint')}
        </span>
        <span className="tabular shrink-0 font-mono text-muted">
          {t('quest.nowRate', { rate: pct(Math.round(last.rate * 10) / 10) })}
        </span>
      </div>
    </div>
  )
}
