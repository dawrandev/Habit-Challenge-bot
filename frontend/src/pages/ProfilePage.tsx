import { Link } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import ActivityChart from '../components/charts/ActivityChart'
import { SUPPORTED, setLang, type Lang } from '../i18n'
import { useBattles, useMe, type BattleListItem } from '../lib/api'
import { useQuests, type QuestListItem } from '../lib/quests'
import { useStats } from '../lib/stats'

/**
 * Til chiplari — 4 tasi bitta qatorda (ilgari 2×2 to'r joyni yeb turardi).
 *
 * Bayroq emoji ATAYLAB ishlatilmaydi: Windows'da flag emoji yo'q va 🇬🇧
 * o'rniga "GB" harflari chiqadi — bu ingliz tili uchun chalkash. Matnli kod
 * hamma platformada bir xil o'qiladi.
 */
const LANG_CODE: Record<Lang, string> = {
  uz: 'UZ',
  en: 'EN',
  ru: 'RU',
  tr: 'TR',
}

function Tile({
  value,
  label,
  tone = 'text',
}: {
  value: string | number
  label: string
  tone?: 'text' | 'you' | 'approve' | 'rival' | 'muted'
}) {
  const color = {
    text: 'text-text',
    you: 'text-you',
    approve: 'text-approve',
    rival: 'text-rival',
    muted: 'text-muted',
  }[tone]

  return (
    <div className="rounded-2xl border border-line bg-surface px-3 py-2.5 text-center">
      <p className={`font-display text-xl leading-none ${color}`}>{value}</p>
      <p className="mt-1 text-[10px] leading-tight text-muted">{label}</p>
    </div>
  )
}

/** Arxiv qatori — tugagan duel yoki missiya. */
function ArchiveRow({
  icon,
  to,
  title,
  subtitle,
  badge,
  tone,
}: {
  icon: string
  to: string
  title: string
  subtitle: string
  badge: string
  tone: 'approve' | 'rival' | 'muted'
}) {
  const cls = {
    approve: 'bg-approve/15 text-approve',
    rival: 'bg-rival/15 text-rival',
    muted: 'bg-muted/15 text-muted',
  }[tone]

  return (
    <Link
      to={to}
      className="flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 transition active:scale-[0.98]"
    >
      <span className="shrink-0 text-base">{icon}</span>
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm">{title}</p>
        <p className="truncate text-xs text-muted">{subtitle}</p>
      </div>
      <span className={`shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium ${cls}`}>
        {badge}
      </span>
    </Link>
  )
}

export default function ProfilePage() {
  const { t, i18n } = useTranslation()
  const current = i18n.language as Lang
  const { data: me } = useMe()
  const { data: stats } = useStats()
  const { data: battles } = useBattles()
  const { data: quests } = useQuests()

  const name = me?.first_name || t('common.you')
  const initial = (name[0] ?? '?').toUpperCase()

  const finishedBattles = (battles ?? []).filter(
    (b: BattleListItem) => b.battle.status !== 'active' && b.battle.status !== 'pending',
  )
  const finishedQuests = (quests ?? []).filter(
    (q: QuestListItem) => q.quest.status !== 'active',
  )
  const archiveEmpty =
    finishedBattles.length === 0 && finishedQuests.length === 0

  const answered = stats?.verification.answered ?? 0
  const expired = stats?.verification.expired ?? 0
  const reviewTotal = answered + expired
  const reliability =
    reviewTotal > 0 ? Math.round((answered / reviewTotal) * 100) : null

  return (
    <PageTransition>
      {/* Profil + til bitta kartochkada — ikkalasi ham kamdan-kam kerak bo'ladi */}
      <section className="px-5 pt-6">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-3xl border border-line bg-surface p-4"
        >
          <div className="flex items-center gap-3">
            <div className="avatar-glow-you grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-surface-2 font-display text-xl text-you ring-2 ring-you">
              {me?.photo_url ? (
                <img
                  src={me.photo_url}
                  alt=""
                  className="h-full w-full object-cover"
                />
              ) : (
                initial
              )}
            </div>
            <div className="min-w-0 flex-1">
              <p className="truncate font-display text-lg leading-tight">{name}</p>
              {me?.username && (
                <p className="truncate text-xs text-muted">@{me.username}</p>
              )}
            </div>

            {/* Til — 4 ta ixcham chip, bitta qatorda */}
            <div className="flex shrink-0 gap-1">
              {SUPPORTED.map((lang) => {
                const active = current === lang
                return (
                  <button
                    key={lang}
                    type="button"
                    onClick={() => setLang(lang)}
                    aria-label={lang}
                    aria-pressed={active}
                    className={`tabular grid h-8 w-8 place-items-center rounded-lg font-mono text-[11px] font-semibold transition active:scale-90 ${
                      active
                        ? 'bg-you/15 text-you ring-1 ring-you'
                        : 'bg-surface-2 text-muted'
                    }`}
                  >
                    {LANG_CODE[lang]}
                  </button>
                )
              })}
            </div>
          </div>
        </motion.div>
      </section>

      {/* Statistika */}
      <section className="px-5 pt-5">
        <h2 className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
          {t('profile.stats')}
        </h2>

        <div className="mb-2 grid grid-cols-4 gap-2">
          <Tile
            value={stats?.battles.won ?? 0}
            label={t('stats.won')}
            tone="you"
          />
          <Tile
            value={stats?.battles.lost ?? 0}
            label={t('stats.lost')}
            tone="rival"
          />
          <Tile
            value={stats?.quests.achieved ?? 0}
            label={t('stats.achieved')}
            tone="approve"
          />
          <Tile
            value={stats?.proofs.approved ?? 0}
            label={t('stats.proofs')}
            tone="text"
          />
        </div>

        {/* Tekshiruvchi sifatidagi ishonchlilik — o'zaro javobgarlikda eng
            gapiruvchi raqam. Ma'lumot yo'q bo'lsa umuman ko'rsatilmaydi. */}
        {reliability !== null && (
          <div className="mb-2 flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3">
            <span className="text-base">👁</span>
            <div className="min-w-0 flex-1">
              <p className="text-sm">{t('stats.reliability')}</p>
              <p className="text-[11px] text-muted">
                {t('stats.reliabilityHint', { a: answered, n: reviewTotal })}
              </p>
            </div>
            <span
              className={`tabular shrink-0 font-display text-xl ${
                reliability >= 80 ? 'text-approve' : 'text-you'
              }`}
            >
              {reliability}%
            </span>
          </div>
        )}

        <div className="rounded-2xl border border-line bg-surface p-4">
          {stats ? (
            <ActivityChart days={stats.activity} />
          ) : (
            <div className="grid h-24 place-items-center text-muted">
              <div className="h-5 w-5 animate-spin rounded-full border-2 border-line border-t-you" />
            </div>
          )}
        </div>
      </section>

      {/* Arxiv */}
      <section className="px-5 pt-5">
        <h2 className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
          {t('profile.archive')}
        </h2>

        {archiveEmpty ? (
          <p className="rounded-2xl border border-line bg-surface px-4 py-4 text-sm text-muted">
            {t('profile.archiveEmpty')}
          </p>
        ) : (
          <div className="space-y-2">
            {finishedBattles.map((b) => {
              const rival = b.players.find((p) => !p.is_me)
              const draw = b.result?.is_draw ?? false
              const won = b.result?.you_won ?? false
              return (
                <ArchiveRow
                  key={`b-${b.battle.id}`}
                  icon="⚔️"
                  to={`/battle/${b.battle.id}`}
                  title={b.battle.title}
                  subtitle={`${t('common.you')} vs ${rival?.user.first_name ?? '—'}`}
                  badge={
                    draw
                      ? t('result.draw')
                      : won
                        ? t('result.won')
                        : t('result.lost')
                  }
                  tone={draw ? 'muted' : won ? 'approve' : 'rival'}
                />
              )
            })}

            {finishedQuests.map((q) => (
              <ArchiveRow
                key={`q-${q.quest.id}`}
                icon="🎯"
                to={`/quest/${q.quest.id}`}
                title={q.quest.title}
                subtitle={`${q.rate}% · ${t('quest.goalShort', {
                  goal: q.quest.goal_percent,
                })}`}
                badge={
                  q.quest.status === 'abandoned'
                    ? t('quest.abandoned')
                    : q.quest.outcome === 'achieved'
                      ? t('result.won')
                      : t('result.lost')
                }
                tone={
                  q.quest.status === 'abandoned'
                    ? 'muted'
                    : q.quest.outcome === 'achieved'
                      ? 'approve'
                      : 'rival'
                }
              />
            ))}
          </div>
        )}
      </section>

      {/* Qanday ishlaydi */}
      <section className="px-5 pt-5">
        <Link
          to="/about"
          className="flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 transition active:scale-[0.98]"
        >
          <span className="text-base">ℹ️</span>
          <span className="flex-1 text-sm">{t('about.title')}</span>
          <span className="text-muted">›</span>
        </Link>
      </section>

      <div className="h-6" />
    </PageTransition>
  )
}
