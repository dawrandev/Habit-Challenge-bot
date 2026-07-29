import { Link, useNavigate } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import { useBattles, useSeed, type PlayerScore } from '../lib/api'

function fmt(n: number) {
  return Number.isInteger(n) ? String(n) : n.toFixed(1)
}

function pick(players: PlayerScore[]) {
  const me = players.find((p) => p.is_me) ?? players[0]
  const rival = players.find((p) => !p.is_me) ?? players[1]
  return { me, rival }
}

export default function HomePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: battles, isLoading } = useBattles()
  const seed = useSeed()

  return (
    <PageTransition>
      <header className="flex items-center justify-between px-5 pt-6 pb-4">
        <h1 className="font-display text-2xl">{t('home.title')}</h1>
        <button
          type="button"
          onClick={() => navigate('/new')}
          className="rounded-full bg-you px-4 py-2 text-sm font-semibold text-bg transition active:scale-95"
        >
          ＋ {t('home.new')}
        </button>
      </header>

      {isLoading && (
        <div className="grid place-items-center py-16 text-muted">
          <div className="h-7 w-7 animate-spin rounded-full border-2 border-line border-t-you" />
        </div>
      )}

      {!isLoading && battles && battles.length === 0 && (
        <div className="px-5 py-10 text-center">
          <p className="mb-4 text-sm text-muted">{t('home.empty')}</p>
          <button
            type="button"
            onClick={() => seed.mutate()}
            disabled={seed.isPending}
            className="rounded-2xl border border-line bg-surface px-5 py-3 text-sm transition active:scale-95 disabled:opacity-50"
          >
            {seed.isPending ? '…' : '🎲 Demo battle yaratish'}
          </button>
        </div>
      )}

      <div className="space-y-3 px-5">
        {battles?.map((item, i) => {
          const { me, rival } = pick(item.players)
          const myScore = me?.score ?? 0
          const rivalScore = rival?.score ?? 0
          const total = Math.max(myScore + rivalScore, 1)
          const youPct = Math.round((myScore / total) * 100)
          const leadingYou = myScore >= rivalScore
          return (
            <motion.div
              key={item.battle.id}
              initial={{ opacity: 0, y: 14 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.05 + i * 0.08, ease: [0.22, 1, 0.36, 1] }}
            >
              <Link
                to={`/battle/${item.battle.id}`}
                className="block rounded-3xl border border-line bg-surface p-4 transition active:scale-[0.98]"
              >
                <div className="mb-3 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span
                      className={`h-2 w-2 rounded-full ${leadingYou ? 'bg-approve' : 'bg-reject'}`}
                    />
                    <span className="font-display text-lg">{item.battle.title}</span>
                  </div>
                  <span className="tabular font-mono text-xs text-muted">
                    {t('battle.timeLeft', {
                      d: item.days_left,
                      h: item.hours_left,
                    })}
                  </span>
                </div>

                <div className="mb-2 flex items-center justify-between">
                  <span className="text-sm text-muted">
                    {t('common.you')} vs {rival?.user.first_name ?? '—'}
                  </span>
                  <span className="font-display text-xl">
                    <span className={leadingYou ? 'text-you' : 'text-muted'}>
                      {fmt(myScore)}
                    </span>
                    <span className="mx-1 text-muted">:</span>
                    <span className={!leadingYou ? 'text-rival' : 'text-muted'}>
                      {fmt(rivalScore)}
                    </span>
                  </span>
                </div>

                <div className="flex h-2 w-full overflow-hidden rounded-full bg-surface-2">
                  <div
                    className="bar-breathe-you h-full bg-you"
                    style={{ width: `${youPct}%` }}
                  />
                  <div className="bar-breathe-rival h-full flex-1 bg-rival" />
                </div>
              </Link>
            </motion.div>
          )
        })}
      </div>

      <div className="h-6" />
    </PageTransition>
  )
}
