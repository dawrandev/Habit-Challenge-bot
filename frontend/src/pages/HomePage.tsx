import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AnimatePresence, motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import { QuestCard } from './QuestsPage'
import { useBattles, type PlayerScore } from '../lib/api'
import { useQuests } from '../lib/quests'

function fmt(n: number) {
  return Number.isInteger(n) ? String(n) : n.toFixed(1)
}

function pick(players: PlayerScore[]) {
  const me = players.find((p) => p.is_me) ?? players[0]
  const rival = players.find((p) => !p.is_me) ?? players[1]
  return { me, rival }
}

/** Nima yaratish kerakligini tanlash — ikki rejim ikki xil munosabat. */
function CreateSheet({
  open,
  onClose,
}: {
  open: boolean
  onClose: () => void
}) {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const options = [
    {
      to: '/new',
      icon: '⚔️',
      title: t('home.createBattle'),
      hint: t('home.createBattleHint'),
      tone: 'rival' as const,
    },
    {
      to: '/quest/new',
      icon: '🎯',
      title: t('home.createQuest'),
      hint: t('home.createQuestHint'),
      tone: 'witness' as const,
    },
  ]

  return (
    <AnimatePresence>
      {open && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm"
          />
          <motion.div
            initial={{ y: '100%' }}
            animate={{ y: 0 }}
            exit={{ y: '100%' }}
            transition={{ type: 'spring', stiffness: 320, damping: 32 }}
            className="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-md space-y-3 rounded-t-3xl border border-line bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
          >
            <div className="mx-auto h-1 w-10 rounded-full bg-line" />
            <h2 className="font-display text-xl">{t('home.createWhat')}</h2>

            {options.map((o) => (
              <button
                key={o.to}
                type="button"
                onClick={() => {
                  onClose()
                  navigate(o.to)
                }}
                className={`flex w-full items-center gap-4 rounded-2xl border bg-surface-2 px-4 py-4 text-left transition active:scale-[0.98] ${
                  o.tone === 'rival' ? 'border-rival/40' : 'border-witness/40'
                }`}
              >
                <span className="text-3xl">{o.icon}</span>
                <span className="min-w-0 flex-1">
                  <span className="block font-display text-lg">{o.title}</span>
                  <span className="block text-xs text-muted">{o.hint}</span>
                </span>
                <span className="text-muted">›</span>
              </button>
            ))}
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}

export default function HomePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: battles, isLoading } = useBattles()
  const { data: quests, isLoading: questsLoading } = useQuests()
  const [createOpen, setCreateOpen] = useState(false)

  const loading = isLoading || questsLoading
  const hasBattles = (battles?.length ?? 0) > 0
  const hasQuests = (quests?.length ?? 0) > 0
  const empty = !loading && !hasBattles && !hasQuests

  return (
    <PageTransition>
      <header className="flex items-center justify-between px-5 pt-6 pb-4">
        <h1 className="font-display text-2xl">{t('home.title')}</h1>
        <button
          type="button"
          onClick={() => setCreateOpen(true)}
          className="rounded-full bg-you px-4 py-2 text-sm font-semibold text-bg transition active:scale-95"
        >
          ＋ {t('home.new')}
        </button>
      </header>

      {loading && (
        <div className="grid place-items-center py-16 text-muted">
          <div className="h-7 w-7 animate-spin rounded-full border-2 border-line border-t-you" />
        </div>
      )}

      {empty && (
        <div className="px-5 py-10 text-center">
          <p className="mb-4 text-sm text-muted">{t('home.empty')}</p>
          <div className="flex flex-col items-center gap-3">
            <button
              type="button"
              onClick={() => setCreateOpen(true)}
              className="rounded-2xl bg-you px-6 py-3 font-semibold text-bg transition active:scale-95"
            >
              ＋ {t('home.new')}
            </button>
            <Link to="/about" className="text-sm text-you underline">
              ℹ️ {t('about.title')}
            </Link>
          </div>
        </div>
      )}

      {/* ⚔️ Duellar */}
      {hasBattles && (
        <section className="px-5">
          <h2 className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
            ⚔️ {t('home.battles')}
          </h2>
          <div className="space-y-3">
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
                        <span className="font-display text-lg">
                          {item.battle.title}
                        </span>
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
        </section>
      )}

      {/* 🎯 Missiyalar */}
      {hasQuests && (
        <section className={`px-5 ${hasBattles ? 'pt-6' : ''}`}>
          <div className="mb-2 flex items-center justify-between">
            <h2 className="text-xs tracking-[0.18em] text-muted uppercase">
              🎯 {t('home.quests')}
            </h2>
            <button
              type="button"
              onClick={() => navigate('/quests')}
              className="text-xs text-muted"
            >
              {t('quest.title')} ›
            </button>
          </div>
          <div className="space-y-3">
            {quests?.slice(0, 3).map((item, i) => (
              <QuestCard key={item.quest.id} item={item} index={i} />
            ))}
          </div>
        </section>
      )}

      <div className="h-6" />

      <CreateSheet open={createOpen} onClose={() => setCreateOpen(false)} />
    </PageTransition>
  )
}
