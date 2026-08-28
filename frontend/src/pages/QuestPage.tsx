import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import VerifyModal from '../components/VerifyModal'
import ChallengeBars from '../components/charts/ChallengeBars'
import ProgressRing from '../components/charts/ProgressRing'
import StreakChain from '../components/charts/StreakChain'
import TrackerGrid from '../components/charts/TrackerGrid'
import TrendChart from '../components/charts/TrendChart'
import { pct } from '../components/charts/state'
import { useVerify, useVerifyQueue, type QueueItem } from '../lib/api'
import { useQuest, useQuestToday } from '../lib/quests'
import QuestSettingsModal from '../components/QuestSettingsModal'

function Section({
  title,
  delay,
  children,
}: {
  title: string
  delay: string
  children: React.ReactNode
}) {
  return (
    <section className="rise-in px-5 pt-6" style={{ animationDelay: delay }}>
      <h2 className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
        {title}
      </h2>
      {children}
    </section>
  )
}

function Avatar({
  name,
  photo,
  tone,
}: {
  name: string
  photo?: string | null
  tone: 'you' | 'witness'
}) {
  const cls =
    tone === 'you'
      ? 'ring-you text-you avatar-glow-you'
      : 'ring-witness text-witness avatar-glow-witness'
  return (
    <div
      className={`grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-full bg-surface-2 font-display text-lg ring-2 ${cls}`}
    >
      {photo ? (
        <img src={photo} alt="" className="h-full w-full object-cover" />
      ) : (
        (name[0] ?? '?').toUpperCase()
      )}
    </div>
  )
}

export default function QuestPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { t } = useTranslation()
  const { data, isLoading } = useQuest(id!)
  const { data: today } = useQuestToday(id!)
  const { data: queue } = useVerifyQueue()
  const verify = useVerify()
  const [selected, setSelected] = useState<QueueItem | null>(null)
  const [settingsOpen, setSettingsOpen] = useState(false)

  if (isLoading || !data) {
    return (
      <PageTransition>
        <div className="grid flex-1 place-items-center text-muted">
          <div className="h-7 w-7 animate-spin rounded-full border-2 border-line border-t-you" />
        </div>
      </PageTransition>
    )
  }

  const { quest, stats, challenges, role } = data
  const isOwner = role === 'owner'
  const finished = quest.status !== 'active'

  const nameOf = (key: string | null, name: string) =>
    key ? t(`tpl.${key}`) : name || '—'

  // Shu missiyaga tegishli tekshiruv navbati (guvoh uchun)
  const mine = (queue ?? []).filter(
    (q) => q.context?.key === 'quest' && q.context.id === quest.id,
  )

  const labelOf = (q: QueueItem) =>
    q.challenge.template_key
      ? t(`tpl.${q.challenge.template_key}`)
      : q.challenge.name || '—'

  function act(approve: boolean) {
    if (!selected) return
    verify.mutate(
      { completionId: selected.completion.id, approve },
      { onSuccess: () => setSelected(null) },
    )
  }


  return (
    <PageTransition>
      <header className="flex items-start justify-between gap-2 px-5 pt-6 pb-4">
        <div className="min-w-0">
          <p className="text-xs tracking-[0.2em] text-muted uppercase">
            🎯 {t(isOwner ? 'quest.roleOwner' : 'quest.roleWitness')}
          </p>
          <h1 className="truncate font-display text-2xl">{quest.title}</h1>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          {!finished && (
            <div className="rounded-full border border-line bg-surface px-3 py-1.5 text-right">
              <p className="text-[10px] tracking-wide text-muted uppercase">
                {t('battle.endsIn')}
              </p>
              <p className="tabular font-mono text-sm text-you">
                {t('battle.timeLeft', {
                  d: data.days_left,
                  h: data.hours_left,
                })}
              </p>
            </div>
          )}
          {isOwner && (
            <button
              type="button"
              onClick={() => setSettingsOpen(true)}
              aria-label={t('quest.settings')}
              className="grid h-9 w-9 place-items-center rounded-full border border-line bg-surface text-muted transition active:scale-90"
            >
              ⚙️
            </button>
          )}
        </div>
      </header>

      {/* Yakun bayrog'i */}
      {finished && (
        <div className="px-5 pb-2">
          <div
            className={`rounded-2xl border px-4 py-3 text-center text-sm ${
              quest.outcome === 'achieved'
                ? 'border-approve/40 bg-approve/10 text-approve'
                : 'border-line bg-surface text-muted'
            }`}
          >
            {quest.status === 'abandoned'
              ? t('quest.abandoned')
              : quest.outcome === 'achieved'
                ? t('quest.achieved')
                : t('quest.missedGoal')}
          </div>
        </div>
      )}

      {/* HERO — maqsad halqasi */}
      <section className="rise-in px-5" style={{ animationDelay: '0.02s' }}>
        <div className="rounded-3xl border border-line bg-surface p-5 shadow-2xl shadow-black/40">
          <div className="flex justify-center">
            <ProgressRing
              rate={stats.rate}
              goal={stats.goal_percent}
              reachable={stats.goal_reachable}
            />
          </div>

          {/* Maqsad holati — matn bilan, faqat rang bilan emas */}
          <p
            className={`mt-1 text-center text-xs ${
              stats.goal_reachable ? 'text-muted' : 'text-reject'
            }`}
          >
            {stats.goal_reachable
              ? t('quest.onTrack')
              : t('quest.unreachable')}
          </p>

          {/* Aniq raqamlar — chart yonida har doim son turadi */}
          <div className="mt-4 grid grid-cols-3 gap-2 border-t border-line/60 pt-3 text-center">
            <div>
              <p className="tabular font-mono text-lg text-approve">
                ✓ {stats.done}
              </p>
              <p className="text-[10px] text-muted">{t('quest.statDone')}</p>
            </div>
            <div>
              <p className="tabular font-mono text-lg text-reject">
                ✕ {stats.missed}
              </p>
              <p className="text-[10px] text-muted">{t('quest.statMissed')}</p>
            </div>
            <div>
              <p className="tabular font-mono text-lg text-muted">
                {Math.max(0, stats.planned - stats.done - stats.missed)}
              </p>
              <p className="text-[10px] text-muted">{t('quest.statLeft')}</p>
            </div>
          </div>

          <p className="mt-2 text-center text-[11px] text-muted">
            {t('quest.ceiling', { rate: pct(stats.ceiling_rate) })} ·{' '}
            {stats.days_elapsed}/{stats.days_total} {t('quest.daysUnit')}
          </p>
        </div>
      </section>

      {/* SERIYA */}
      <Section title={t('quest.streak')} delay="0.06s">
        <div className="rounded-2xl border border-line bg-surface px-4 py-3.5">
          <StreakChain
            current={stats.current_streak}
            longest={stats.longest_streak}
          />
        </div>
      </Section>

      {/* GUVOH */}
      <Section title={t('quest.witness')} delay="0.09s">
        {data.witness ? (
          <div className="flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3">
            <Avatar
              name={data.witness.first_name}
              photo={data.witness.photo_url}
              tone="witness"
            />
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium">
                {data.witness.first_name}
              </p>
              <p className="truncate text-xs text-muted">
                {data.witness.username
                  ? `@${data.witness.username}`
                  : t('quest.witness')}
              </p>
            </div>
            <button
              type="button"
              onClick={() => navigate(`/quest/${quest.id}/chat`)}
              className="shrink-0 rounded-full bg-witness/15 px-3 py-1.5 text-xs font-medium text-witness transition active:scale-95"
            >
              💬
            </button>
          </div>
        ) : isOwner ? (
          <div className="rounded-2xl border border-witness/30 bg-witness/5 px-4 py-4">
            <p className="mb-1 text-sm font-medium">{t('quest.noWitness')}</p>
            <p className="mb-3 text-xs text-muted">{t('quest.witnessHint')}</p>
            <button
              type="button"
              onClick={() => setSettingsOpen(true)}
              className="w-full rounded-xl bg-witness py-2.5 text-sm font-semibold text-bg transition active:scale-[0.98]"
            >
              👁 {t('quest.inviteWitness')}
            </button>
          </div>
        ) : (
          <p className="rounded-2xl border border-line bg-surface px-4 py-3 text-sm text-muted">
            {t('quest.noWitness')}
          </p>
        )}
      </Section>

      {/* GUVOH UCHUN — tekshiruv navbati */}
      {!isOwner && mine.length > 0 && (
        <Section title={t('quest.witnessWaiting')} delay="0.12s">
          <div className="space-y-2">
            {mine.map((q) => (
              <button
                key={q.completion.id}
                type="button"
                onClick={() => setSelected(q)}
                className="flex w-full items-center gap-3 rounded-2xl border border-you/30 bg-you/5 px-4 py-3 text-left transition active:scale-[0.98]"
              >
                <span className="text-lg">{q.challenge.icon}</span>
                <span className="flex-1 text-sm">{labelOf(q)}</span>
                <span className="rounded-full bg-you/15 px-2.5 py-1 text-xs font-medium text-you">
                  {t('verify.title')} →
                </span>
              </button>
            ))}
          </div>
        </Section>
      )}

      {/* EGA UCHUN — bugungi vazifalar */}
      {isOwner && !finished && (
        <Section title={t('quest.expectedToday')} delay="0.12s">
          <div className="space-y-2">
            {today?.map((task) => {
              const done =
                task.status === 'approved' || task.status === 'auto_approved'
              const chip = done
                ? { cls: 'bg-approve/15 text-approve', label: `✓ ${t('battle.done')}` }
                : task.status === 'pending'
                  ? { cls: 'bg-muted/15 text-muted', label: '⏳' }
                  : task.status === 'rejected'
                    ? { cls: 'bg-reject/15 text-reject', label: `✕ ${t('verify.reject')}` }
                    : { cls: 'bg-you/15 text-you', label: t('battle.needProof') }
              return (
                <button
                  key={task.challenge.id}
                  type="button"
                  disabled={done}
                  onClick={() =>
                    navigate(
                      `/quest/${quest.id}/proof/${task.challenge.id}?type=${
                        task.challenge.proof_type ?? 'camera'
                      }`,
                    )
                  }
                  className="flex w-full items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 text-left transition active:scale-[0.98] disabled:opacity-60"
                >
                  <span className="text-lg">{task.challenge.icon}</span>
                  <span className="flex-1 text-sm">
                    {nameOf(task.challenge.template_key, task.challenge.name)}
                  </span>
                  <span
                    className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ${chip.cls}`}
                  >
                    {chip.label}
                  </span>
                </button>
              )
            })}
            {today && today.length === 0 && (
              <p className="rounded-2xl border border-line bg-surface px-4 py-4 text-sm text-muted">
                {t('activity.empty')}
              </p>
            )}
          </div>
        </Section>
      )}

      {/* TRACKER — challenge × kun to'ri */}
      <Section title={t('quest.tracker')} delay="0.15s">
        <div className="rounded-2xl border border-line bg-surface p-4">
          <TrackerGrid
            days={stats.days}
            stats={stats.challenges}
            challenges={challenges}
          />
        </div>
      </Section>

      {/* DINAMIKA */}
      <Section title={t('quest.trend')} delay="0.18s">
        <div className="rounded-2xl border border-line bg-surface p-4">
          <TrendChart days={stats.days} goal={stats.goal_percent} />
        </div>
      </Section>

      {/* ODATLAR BO'YICHA */}
      <Section title={t('quest.byChallenge')} delay="0.21s">
        <div className="rounded-2xl border border-line bg-surface p-4">
          <ChallengeBars stats={stats.challenges} challenges={challenges} />
        </div>
      </Section>

      <div className="h-6" />

      <VerifyModal
        item={
          selected
            ? {
                rivalName: selected.rival.first_name,
                icon: selected.challenge.icon,
                label: labelOf(selected),
                fileId: selected.completion.file_id,
                submittedAt: selected.completion.created_at,
                note: selected.completion.note,
              }
            : null
        }
        busy={verify.isPending}
        onClose={() => setSelected(null)}
        onApprove={() => act(true)}
        onReject={() => act(false)}
      />

      <QuestSettingsModal
        quest={quest}
        open={settingsOpen}
        isOwner={isOwner}
        onClose={() => setSettingsOpen(false)}
        onDeleted={() => navigate('/quests', { replace: true })}
      />
    </PageTransition>
  )
}
