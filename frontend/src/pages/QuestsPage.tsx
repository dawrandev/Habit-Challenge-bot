import { Link, useNavigate } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import { pct } from '../components/charts/state'
import { useQuests, type QuestListItem } from '../lib/quests'

/** Ixcham sparkline o'rniga — bajarish shkalasi (bitta o'lchov, bitta rang). */
function MiniBar({ rate, reachable }: { rate: number; reachable: boolean }) {
  const w = Math.max(0, Math.min(100, rate))
  return (
    <div className="h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
      <div
        className="h-full transition-[width] duration-700 ease-out"
        style={{
          width: `${w}%`,
          background: reachable ? 'var(--color-you)' : 'var(--color-reject)',
          borderTopRightRadius: 4,
          borderBottomRightRadius: 4,
        }}
      />
    </div>
  )
}

export function QuestCard({ item, index }: { item: QuestListItem; index: number }) {
  const { t } = useTranslation()
  const { quest } = item
  const isOwner = item.role === 'owner'
  const finished = quest.status !== 'active'

  return (
    <motion.div
      initial={{ opacity: 0, y: 14 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: 0.05 + index * 0.07, ease: [0.22, 1, 0.36, 1] }}
    >
      <Link
        to={`/quest/${quest.id}`}
        className="block rounded-3xl border border-line bg-surface p-4 transition active:scale-[0.98]"
      >
        <div className="mb-2 flex items-start justify-between gap-2">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <span className="shrink-0 text-sm">🎯</span>
              <span className="truncate font-display text-lg">{quest.title}</span>
            </div>
            <p className="mt-0.5 truncate text-xs text-muted">
              {isOwner
                ? item.witness
                  ? `👁 ${item.witness.first_name}`
                  : t('quest.noWitness')
                : `${item.owner?.first_name ?? '—'} · ${t('quest.roleWitness')}`}
            </p>
          </div>

          <div className="shrink-0 text-right">
            <span className="tabular font-display text-xl text-you">
              {pct(item.rate)}
              <span className="text-sm">%</span>
            </span>
            <p className="tabular font-mono text-[10px] text-muted">
              {finished
                ? t(
                    quest.status === 'abandoned'
                      ? 'quest.abandoned'
                      : quest.outcome === 'achieved'
                        ? 'quest.achieved'
                        : 'quest.missedGoal',
                  )
                : t('battle.timeLeft', {
                    d: item.days_left,
                    h: item.hours_left,
                  })}
            </p>
          </div>
        </div>

        <MiniBar rate={item.rate} reachable={item.goal_reachable || finished} />

        <div className="mt-2 flex items-center justify-between text-[11px] text-muted">
          <span className="tabular font-mono">
            ✓ {item.done}/{item.planned} · {t('quest.goalShort', {
              goal: quest.goal_percent,
            })}
          </span>
          {item.current_streak > 0 && (
            <span className="tabular font-mono text-you">
              🔥 {item.current_streak} {t('quest.daysUnit')}
            </span>
          )}
        </div>
      </Link>
    </motion.div>
  )
}

export default function QuestsPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: quests, isLoading } = useQuests()

  return (
    <PageTransition>
      <header className="flex items-center justify-between px-5 pt-6 pb-1">
        <h1 className="font-display text-2xl">{t('quest.title')}</h1>
        <button
          type="button"
          onClick={() => navigate('/quest/new')}
          className="rounded-full bg-you px-4 py-2 text-sm font-semibold text-bg transition active:scale-95"
        >
          ＋ {t('quest.new')}
        </button>
      </header>
      <p className="px-5 pb-4 text-sm text-muted">{t('quest.tagline')}</p>

      {isLoading && (
        <div className="grid place-items-center py-16 text-muted">
          <div className="h-7 w-7 animate-spin rounded-full border-2 border-line border-t-you" />
        </div>
      )}

      {!isLoading && quests && quests.length === 0 && (
        <div className="px-5 py-10 text-center">
          <p className="mb-4 text-4xl">🎯</p>
          <p className="mb-5 text-sm text-muted">{t('quest.empty')}</p>
          <button
            type="button"
            onClick={() => navigate('/quest/new')}
            className="rounded-2xl bg-you px-6 py-3 font-semibold text-bg transition active:scale-95"
          >
            ＋ {t('quest.new')}
          </button>
        </div>
      )}

      <div className="space-y-3 px-5">
        {quests?.map((item, i) => (
          <QuestCard key={item.quest.id} item={item} index={i} />
        ))}
      </div>

      <div className="h-6" />
    </PageTransition>
  )
}
