import { useTranslation } from 'react-i18next'
import type { HabitProgress, StakesState } from '../lib/api'

function PenaltyBar({
  label,
  habits,
  threshold,
  tone,
}: {
  label: string
  habits: HabitProgress
  threshold: number
  tone: 'you' | 'rival'
}) {
  const { t } = useTranslation()
  const { done, expected, safe_at } = habits
  const pct = expected > 0 ? Math.min(100, (done / expected) * 100) : 0
  const safe = done >= safe_at
  const left = Math.max(0, safe_at - done)
  const fill = tone === 'you' ? 'bg-you' : 'bg-rival'

  return (
    <div>
      <div className="mb-1 flex items-baseline justify-between text-xs">
        <span className={tone === 'you' ? 'text-you' : 'text-rival'}>{label}</span>
        <span className="tabular font-mono">
          <span className="text-text">{done}</span>
          <span className="text-muted">/{expected}</span>
          <span className={`ml-2 ${safe ? 'text-approve' : 'text-reject'}`}>
            {safe ? `🛡 ${t('stakes.safe')}` : t('stakes.needMore', { n: left })}
          </span>
        </span>
      </div>
      <div className="relative h-2.5 overflow-hidden rounded-full bg-surface-2">
        {/* Chiziqdan pastki qism — xavf zonasi */}
        <div
          className="absolute inset-y-0 left-0 bg-reject/15"
          style={{ width: `${threshold}%` }}
        />
        <div
          className={`relative h-full rounded-full transition-[width] duration-700 ease-out ${fill}`}
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  )
}

/**
 * Faol duelning garovi (SPEC §17): nima uchun o'ynayapmiz va kim jazo
 * chizig'iga yaqin. Chiziq har ikki o'yinchi uchun ko'rinadi — bu raqibni
 * ham "tortib chiqarish" uchun motivatsiya.
 */
export default function StakesCard({
  stakes,
  me,
  rival,
  rivalName,
}: {
  stakes: StakesState
  me?: HabitProgress
  rival?: HabitProgress
  rivalName: string
}) {
  const { t } = useTranslation()
  const prize = stakes.prize.chosen?.text
  const penalty = stakes.penalty.chosen?.text
  if (!prize || !penalty) return null

  return (
    <section className="rise-in px-5 pt-4" style={{ animationDelay: '0.05s' }}>
      <div className="rounded-3xl border border-line bg-surface p-4">
        <div className="mb-4 grid grid-cols-2 gap-2">
          <div className="rounded-2xl border border-you/30 bg-you/5 px-3 py-2.5">
            <p className="mb-0.5 text-[10px] tracking-[0.16em] text-muted uppercase">
              🏆 {t('stakes.prize')}
            </p>
            <p className="text-sm leading-snug">{prize}</p>
          </div>
          <div className="rounded-2xl border border-reject/30 bg-reject/5 px-3 py-2.5">
            <p className="mb-0.5 text-[10px] tracking-[0.16em] text-muted uppercase">
              ⚡ {t('stakes.penalty')}
            </p>
            <p className="text-sm leading-snug">{penalty}</p>
          </div>
        </div>

        <p className="mb-2 text-[11px] text-muted">
          {t('stakes.penaltyLine', { n: stakes.threshold })}
        </p>
        <div className="space-y-3">
          {me && (
            <PenaltyBar label={t('common.you')} habits={me} threshold={stakes.threshold} tone="you" />
          )}
          {rival && (
            <PenaltyBar label={rivalName} habits={rival} threshold={stakes.threshold} tone="rival" />
          )}
        </div>
      </div>
    </section>
  )
}
