import { Link } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import { useBattles, type PlayerScore } from '../lib/api'

function rivalOf(players: PlayerScore[]) {
  return players.find((p) => !p.is_me) ?? players[1]
}

export default function ChatPage() {
  const { t } = useTranslation()
  const { data: battles, isLoading } = useBattles()

  return (
    <PageTransition>
      <header className="px-5 pt-6 pb-4">
        <h1 className="font-display text-2xl">{t('chat.title')}</h1>
      </header>

      {isLoading && (
        <div className="grid place-items-center py-10 text-muted">
          <div className="h-6 w-6 animate-spin rounded-full border-2 border-line border-t-you" />
        </div>
      )}

      {!isLoading && battles && battles.length === 0 && (
        <p className="mx-5 rounded-2xl border border-line bg-surface px-4 py-4 text-sm text-muted">
          {t('chat.empty')}
        </p>
      )}

      <div className="space-y-2 px-5">
        {battles?.map((b, i) => {
          const rival = rivalOf(b.players)
          const name = rival?.user.first_name ?? '—'
          return (
            <motion.div
              key={b.battle.id}
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.04 + i * 0.07 }}
            >
              <Link
                to={`/battle/${b.battle.id}/chat`}
                className="flex w-full items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 transition active:scale-[0.98]"
              >
                <div className="grid h-11 w-11 place-items-center rounded-full bg-surface-2 font-display text-rival ring-2 ring-rival">
                  {name[0]}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="font-medium">{name}</p>
                  <p className="truncate text-sm text-muted">{b.battle.title}</p>
                </div>
                <span className="text-muted">›</span>
              </Link>
            </motion.div>
          )
        })}
      </div>

      <div className="h-6" />
    </PageTransition>
  )
}
