import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { shortDate } from './state'

/**
 * Oxirgi 30 kun — kuniga nechta isbot tasdiqlangan.
 *
 * BITTA o'lchov → BITTA rang. Ustunni "kattaroq = to'qroq" qilib bo'yash
 * balandlikni ikki marta kodlash bo'lardi. Har ustunga raqam yozilmaydi —
 * 30 ta yorliq xaos; qiymat bosilganda ochiladi, jami esa sarlavha yonida.
 *
 * Bo'sh kunlar ATAYLAB ko'rsatiladi: uzilishlar odat tarixining eng muhim
 * qismi, ularni tashlab ketish tarixni yolg'on tekis qilib ko'rsatardi.
 */
export type ActivityDay = { date: string; done: number }

export default function ActivityChart({ days }: { days: ActivityDay[] }) {
  const { t } = useTranslation()
  const [picked, setPicked] = useState<number | null>(null)

  if (days.length === 0) return null

  const max = Math.max(...days.map((d) => d.done), 1)
  const total = days.reduce((s, d) => s + d.done, 0)
  const best = Math.max(...days.map((d) => d.done))
  const active = picked !== null ? days[picked] : null

  const H = 64

  return (
    <div>
      <div className="mb-2 flex items-baseline justify-between gap-2">
        <span className="tabular font-mono text-xs text-muted">
          {t('stats.last30')}
        </span>
        <span className="tabular font-mono text-xs text-muted">
          {t('stats.totalProofs', { n: total })}
        </span>
      </div>

      {/* Ustunlar — asosga tayangan, ma'lumot uchi yumaloq */}
      <div className="flex items-end gap-[2px]" style={{ height: H }}>
        {days.map((d, i) => {
          const h = d.done === 0 ? 2 : Math.max(4, (d.done / max) * H)
          const isPicked = picked === i
          return (
            <button
              key={d.date}
              type="button"
              onClick={() => setPicked(isPicked ? null : i)}
              aria-label={`${d.date}: ${d.done}`}
              className="group relative flex flex-1 items-end justify-center"
              style={{ height: H }}
            >
              <span
                className="w-full transition-[height] duration-500 ease-out"
                style={{
                  height: h,
                  maxWidth: 14,
                  background:
                    d.done === 0 ? 'var(--color-line)' : 'var(--color-you)',
                  borderTopLeftRadius: 4,
                  borderTopRightRadius: 4,
                  opacity: isPicked ? 1 : d.done === 0 ? 1 : 0.9,
                  outline: isPicked ? '2px solid var(--color-text)' : undefined,
                  outlineOffset: 1,
                }}
              />
            </button>
          )
        })}
      </div>

      {/* Asos chizig'i — ingichka, tutash, orqaga chekingan */}
      <div className="mt-1 h-px w-full bg-line" />

      <div className="mt-1.5 flex items-center justify-between text-[11px] text-muted">
        <span className="tabular font-mono">{shortDate(days[0].date)}</span>
        <span className="truncate px-2">
          {active
            ? `${active.date} · ${t('stats.nProofs', { n: active.done })}`
            : t('stats.bestDay', { n: best })}
        </span>
        <span className="tabular font-mono">
          {shortDate(days[days.length - 1].date)}
        </span>
      </div>
    </div>
  )
}
