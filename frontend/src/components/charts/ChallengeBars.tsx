import { useTranslation } from 'react-i18next'
import type { Challenge } from '../../lib/api'
import type { QuestChallengeStat } from '../../lib/quests'
import { pct } from './state'

/**
 * Challenge bo'yicha bajarish foizi — gorizontal barlar.
 *
 * BITTA o'lchov, shuning uchun BITTA rang: har barni "kattaroq = to'qroq"
 * qilib bo'yash uzunlikni ikki marta kodlashi va yagona bo'sh kanalni
 * behuda sarflashi bo'lardi. Qiymat barning uchida — har nuqtada emas.
 *
 * Bar qalinligi 10px (24px shiftidan ancha past), ma'lumot uchi 4px yumaloq,
 * asosi to'g'ri burchakli.
 */
const BAR_H = 10

export default function ChallengeBars({
  stats,
  challenges,
}: {
  stats: QuestChallengeStat[]
  challenges: Challenge[]
}) {
  const { t } = useTranslation()

  const byId = new Map(challenges.map((c) => [c.id, c]))
  const nameOf = (c?: Challenge) =>
    !c ? '—' : c.template_key ? t(`tpl.${c.template_key}`) : c.name || '—'

  const rows = stats
    .map((s) => ({ stat: s, challenge: byId.get(s.challenge_id) }))
    .filter((r) => r.challenge !== undefined)

  if (rows.length === 0) return null

  return (
    <div className="space-y-3">
      {rows.map(({ stat, challenge }) => {
        const width = Math.max(0, Math.min(100, stat.rate))
        return (
          <div key={stat.challenge_id}>
            <div className="mb-1.5 flex items-center gap-2">
              <span className="shrink-0 text-sm">{challenge?.icon}</span>
              <span className="min-w-0 flex-1 truncate text-sm">
                {nameOf(challenge)}
              </span>
              {/* Qiymat barning uchida — matn tokenida, seriya rangida emas */}
              <span className="tabular shrink-0 font-mono text-xs text-muted">
                {stat.done}/{stat.done + stat.missed} · {pct(stat.rate)}%
              </span>
            </div>

            <div
              className="w-full overflow-hidden rounded-full bg-surface-2"
              style={{ height: BAR_H }}
              role="img"
              aria-label={`${nameOf(challenge)}: ${pct(stat.rate)}%`}
            >
              <div
                className="h-full bg-you transition-[width] duration-700 ease-out"
                style={{
                  width: `${width}%`,
                  // Ma'lumot uchi yumaloq, asosi (chap) to'g'ri burchakli
                  borderTopRightRadius: 4,
                  borderBottomRightRadius: 4,
                }}
              />
            </div>

            {stat.current_streak > 0 && (
              <p className="mt-1 text-[11px] text-muted">
                🔥 {t('quest.streakNow', { n: stat.current_streak })}
              </p>
            )}
          </div>
        )
      })}
    </div>
  )
}
