import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import AddChallengeModal from '../components/AddChallengeModal'
import BattleSettingsModal from '../components/BattleSettingsModal'
import {
  useBattle,
  useDeleteChallenge,
  useDisputeMut,
  useRespondChallenge,
  useToday,
  type Challenge,
  type PlayerScore,
} from '../lib/api'

/* Ball 0 dan nishonga sanalib chiqadi */
function useCountUp(target: number, duration = 1000) {
  const [val, setVal] = useState(0)
  const raf = useRef(0)
  useEffect(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setVal(target)
      return
    }
    const start = performance.now()
    const tick = (now: number) => {
      const p = Math.min((now - start) / duration, 1)
      setVal(+(target * (1 - Math.pow(1 - p, 3))).toFixed(1))
      if (p < 1) raf.current = requestAnimationFrame(tick)
    }
    raf.current = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(raf.current)
  }, [target, duration])
  return val
}

function fmt(n: number) {
  return Number.isInteger(n) ? String(n) : n.toFixed(1)
}

function timeLeft(endDate: string) {
  const end = new Date(`${endDate}T23:59:59`)
  const secs = Math.max((end.getTime() - Date.now()) / 1000, 0)
  return { d: Math.floor(secs / 86400), h: Math.floor((secs % 86400) / 3600) }
}

function Avatar({
  initials,
  color,
  photo,
}: {
  initials: string
  color: 'you' | 'rival'
  photo?: string | null
}) {
  const ring = color === 'you' ? 'ring-you' : 'ring-rival'
  const text = color === 'you' ? 'text-you' : 'text-rival'
  const glow = color === 'you' ? 'avatar-glow-you' : 'avatar-glow-rival'
  return (
    <div
      className={`grid h-12 w-12 place-items-center overflow-hidden rounded-full bg-surface-2 ring-2 ${ring} ${text} ${glow} font-display text-xl`}
    >
      {photo ? (
        <img src={photo} alt="" className="h-full w-full object-cover" />
      ) : (
        initials
      )}
    </div>
  )
}

/* Ball qancha uzun bo'lsa, shuncha kichik — kartadan chiqib ketmasligi uchun */
function scoreSizeCls(a: number, b: number) {
  const len = Math.max(fmt(a).length, fmt(b).length)
  if (len <= 2) return 'text-6xl'
  if (len === 3) return 'text-5xl'
  if (len === 4) return 'text-4xl'
  return 'text-3xl'
}

function ScoreNumber({
  value,
  color,
  leading,
  sizeCls,
}: {
  value: number
  color: 'you' | 'rival'
  leading: boolean
  sizeCls: string
}) {
  const display = useCountUp(value)
  const base = `font-display ${sizeCls} leading-none tabular whitespace-nowrap`
  if (leading) {
    return (
      <span className={`${base} ${color === 'you' ? 'flame-you' : 'flame-rival'}`}>
        {fmt(display)}
      </span>
    )
  }
  const dim =
    color === 'you' ? 'text-you glow-pulse-you' : 'text-rival glow-pulse-rival'
  return <span className={`${base} ${dim}`}>{fmt(display)}</span>
}

function TugOfWar({ you, rival }: { you: number; rival: number }) {
  // Ball manfiy bo'lishi mumkin — nisbiy ustunlikni 0..100 oralig'iga solamiz
  const mag = Math.max(Math.abs(you), Math.abs(rival), 1)
  const youPct = Math.min(96, Math.max(4, Math.round(50 + ((you - rival) / (2 * mag)) * 100)))
  return (
    <div className="relative">
      <div className="flex h-4 w-full overflow-hidden rounded-full bg-surface-2">
        <div
          className="bar-breathe-you h-full transition-[width] duration-700 ease-out"
          style={{ width: `${youPct}%`, background: 'linear-gradient(90deg,#b5810f,#f6b01e)' }}
        />
        <div
          className="bar-breathe-rival h-full flex-1 transition-[width] duration-700 ease-out"
          style={{ background: 'linear-gradient(90deg,#7c5cff,#4a37a0)' }}
        />
      </div>
      <div
        className="absolute top-1/2 h-6 w-0.5 -translate-y-1/2 rounded bg-text/70"
        style={{ left: `${youPct}%` }}
      />
    </div>
  )
}

function statusChip(status: string | null, t: (k: string) => string) {
  if (status === 'approved' || status === 'auto_approved')
    return { cls: 'bg-approve/15 text-approve', label: `✓ ${t('battle.done')}` }
  if (status === 'pending')
    return { cls: 'bg-muted/15 text-muted', label: '⏳' }
  if (status === 'rejected')
    return { cls: 'bg-reject/15 text-reject', label: `✕ ${t('verify.reject')}` }
  return { cls: 'bg-you/15 text-you', label: t('battle.needProof') }
}

export default function BattlePage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { t } = useTranslation()
  const { data, isLoading } = useBattle(id!)
  const { data: today } = useToday(id!)
  const [chOpen, setChOpen] = useState(false)
  const [editCh, setEditCh] = useState<Challenge | null>(null)
  const [settingsOpen, setSettingsOpen] = useState(false)
  const respond = useRespondChallenge(id!)
  const removeCh = useDeleteChallenge(id!)
  const dispute = useDisputeMut()

  if (isLoading || !data) {
    return (
      <PageTransition>
        <div className="grid flex-1 place-items-center text-muted">
          <div className="h-7 w-7 animate-spin rounded-full border-2 border-line border-t-you" />
        </div>
      </PageTransition>
    )
  }

  const me: PlayerScore | undefined =
    data.players.find((p) => p.is_me) ?? data.players[0]
  const rival: PlayerScore | undefined =
    data.players.find((p) => !p.is_me) ?? data.players[1]
  const myScore = me?.score ?? 0
  const rivalScore = rival?.score ?? 0
  const diff = myScore - rivalScore
  const scoreSize = scoreSizeCls(myScore, rivalScore)
  const tl = timeLeft(data.battle.end_date)

  const statusText =
    diff > 0
      ? t('battle.ahead', { count: fmt(Math.abs(diff)) })
      : diff < 0
        ? t('battle.behind', { count: fmt(Math.abs(diff)) })
        : t('battle.tied')

  const nameOf = (key: string | null, name: string) =>
    key ? t(`tpl.${key}`) : name || '—'

  return (
    <PageTransition>
      <header className="flex items-center justify-between px-5 pt-6 pb-4">
        <div>
          <p className="text-xs tracking-[0.2em] text-muted uppercase">
            {t('battle.duel')}
          </p>
          <h1 className="font-display text-2xl">{data.battle.title}</h1>
        </div>
        <div className="flex items-center gap-2">
          <div className="rounded-full border border-line bg-surface px-3 py-1.5 text-right">
            <p className="text-[10px] tracking-wide text-muted uppercase">
              {t('battle.endsIn')}
            </p>
            <p className="tabular font-mono text-sm text-you">
              {t('battle.timeLeft', { d: tl.d, h: tl.h })}
            </p>
          </div>
          <button
            type="button"
            onClick={() => setSettingsOpen(true)}
            className="grid h-9 w-9 place-items-center rounded-full border border-line bg-surface text-muted transition active:scale-90"
            aria-label={t('crud.settings')}
          >
            ⚙️
          </button>
        </div>
      </header>

      <section className="rise-in px-5" style={{ animationDelay: '0.02s' }}>
        <div className="rounded-3xl border border-line bg-surface p-5 shadow-2xl shadow-black/40">
          <div className="flex items-center justify-between gap-2">
            <div className="flex w-14 shrink-0 flex-col items-center gap-2">
              <Avatar
                initials={t('common.you')[0]}
                color="you"
                photo={me?.user.photo_url}
              />
              <span className="max-w-full truncate text-xs text-muted">
                {t('common.you')}
              </span>
            </div>
            <div className="flex min-w-0 flex-1 items-end justify-center gap-2">
              <ScoreNumber
                value={myScore}
                color="you"
                leading={diff >= 0}
                sizeCls={scoreSize}
              />
              <span className="vs-pulse mb-1.5 font-display text-base text-muted">
                vs
              </span>
              <ScoreNumber
                value={rivalScore}
                color="rival"
                leading={diff < 0}
                sizeCls={scoreSize}
              />
            </div>
            <div className="flex w-14 shrink-0 flex-col items-center gap-2">
              <Avatar
                initials={(rival?.user.first_name ?? '?')[0]}
                color="rival"
                photo={rival?.user.photo_url}
              />
              <span className="max-w-full truncate text-xs text-muted">
                {rival?.user.username ? `@${rival.user.username}` : rival?.user.first_name ?? '—'}
              </span>
            </div>
          </div>
          <div className="mt-5">
            <TugOfWar you={myScore} rival={rivalScore} />
            <p className="mt-2 text-center text-xs text-muted">{statusText}</p>
          </div>
        </div>
      </section>

      {/* Takliflar (pending challenge'lar) */}
      {data.challenges.some((c) => c.pending) && (
        <section className="rise-in px-5 pt-6" style={{ animationDelay: '0.08s' }}>
          <h2 className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
            🆕 {t('crud.proposals')}
          </h2>
          <div className="space-y-2">
            {data.challenges
              .filter((c) => c.pending)
              .map((ch) => {
                const mine = ch.proposed_by === me?.user.id
                const label = ch.template_key ? t(`tpl.${ch.template_key}`) : ch.name
                return (
                  <div
                    key={ch.id}
                    className="flex items-center gap-3 rounded-2xl border border-you/30 bg-you/5 px-4 py-3"
                  >
                    <span className="text-lg">{ch.icon}</span>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm">{label}</p>
                      {ch.description && (
                        <p className="truncate text-xs text-muted">{ch.description}</p>
                      )}
                    </div>
                    {mine ? (
                      <div className="flex items-center gap-2">
                        <span className="text-xs text-muted">⏳ {t('crud.waiting')}</span>
                        <button
                          type="button"
                          onClick={() => removeCh.mutate(ch.id)}
                          className="grid h-8 w-8 place-items-center rounded-full bg-reject/15 text-reject"
                        >
                          ✕
                        </button>
                      </div>
                    ) : (
                      <div className="flex gap-2">
                        <button
                          type="button"
                          onClick={() => respond.mutate({ id: ch.id, accept: false })}
                          className="grid h-9 w-9 place-items-center rounded-full bg-reject/15 text-reject transition active:scale-90"
                        >
                          ✕
                        </button>
                        <button
                          type="button"
                          onClick={() => respond.mutate({ id: ch.id, accept: true })}
                          className="grid h-9 w-9 place-items-center rounded-full bg-approve/15 text-approve transition active:scale-90"
                        >
                          ✓
                        </button>
                      </div>
                    )}
                  </div>
                )
              })}
          </div>
        </section>
      )}

      {/* Batafsil */}
      <section className="rise-in px-5 pt-6" style={{ animationDelay: '0.12s' }}>
        <div className="mb-1 flex items-center justify-between">
          <h2 className="text-xs tracking-[0.18em] text-muted uppercase">
            {t('battle.byChallenge')}
          </h2>
          <button
            type="button"
            onClick={() => {
              setEditCh(null)
              setChOpen(true)
            }}
            className="rounded-full bg-you/15 px-3 py-1 text-xs font-semibold text-you transition active:scale-95"
          >
            ＋ Challenge
          </button>
        </div>
        <div className="divide-y divide-line rounded-2xl border border-line bg-surface px-4">
          {data.challenges.filter((c) => !c.pending).map((ch) => {
            const y = me?.breakdown?.[ch.id] ?? 0
            const r = rival?.breakdown?.[ch.id] ?? 0
            const total = Math.max(y + r, 1)
            const youLead = y >= r
            return (
              <button
                key={ch.id}
                type="button"
                onClick={() => {
                  setEditCh(ch)
                  setChOpen(true)
                }}
                className="flex w-full items-center gap-3 py-2.5 text-left transition active:opacity-70"
              >
                <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-surface-2 text-lg">
                  {ch.icon}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="mb-1 flex items-center justify-between">
                    <span className="truncate text-sm font-medium">
                      {nameOf(ch.template_key, ch.name)}
                    </span>
                    <span className="tabular font-mono text-sm">
                      <span className={youLead ? 'text-you' : 'text-muted'}>{y}</span>
                      <span className="mx-1 text-muted">—</span>
                      <span className={!youLead ? 'text-rival' : 'text-muted'}>{r}</span>
                    </span>
                  </div>
                  {ch.description && (
                    <p className="mb-1.5 truncate text-xs text-muted">{ch.description}</p>
                  )}
                  <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                    <div
                      className="h-full bg-you"
                      style={{ width: `${(y / total) * 100}%` }}
                    />
                    <div className="h-full flex-1 bg-rival" />
                  </div>
                </div>
              </button>
            )
          })}
        </div>
      </section>

      {/* Bugun kutilyapti */}
      <section className="rise-in px-5 pt-6" style={{ animationDelay: '0.22s' }}>
        <h2 className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
          {t('battle.expectedToday')}
        </h2>
        <div className="space-y-2">
          {today?.map((task) => {
            const chip = statusChip(task.status, t)
            const done =
              task.status === 'approved' || task.status === 'auto_approved'
            const rejected = task.status === 'rejected'
            return (
              <div
                key={task.challenge.id}
                className="flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 transition"
              >
                <button
                  type="button"
                  disabled={done}
                  onClick={() =>
                    navigate(
                      `/battle/${data.battle.id}/proof/${task.challenge.id}?type=${task.challenge.proof_type ?? 'camera'}`,
                    )
                  }
                  className="flex flex-1 items-center gap-3 text-left transition active:scale-[0.98] disabled:opacity-60"
                >
                  <span className="text-lg">{task.challenge.icon}</span>
                  <span className="flex-1 text-sm">
                    {nameOf(task.challenge.template_key, task.challenge.name)}
                  </span>
                </button>
                {rejected && task.completion_id ? (
                  <button
                    type="button"
                    disabled={dispute.isPending}
                    onClick={() => {
                      if (!window.confirm(t('crud.disputeConfirm'))) return
                      dispute.mutate(task.completion_id!)
                    }}
                    className="rounded-full bg-you/15 px-2.5 py-1 text-xs font-medium text-you transition active:scale-95 disabled:opacity-50"
                  >
                    ⚑ {t('verify.dispute')}
                  </button>
                ) : (
                  <span
                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${chip.cls}`}
                  >
                    {chip.label}
                  </span>
                )}
              </div>
            )
          })}
          {today && today.length === 0 && (
            <p className="rounded-2xl border border-line bg-surface px-4 py-4 text-sm text-muted">
              {t('activity.empty')}
            </p>
          )}
        </div>
      </section>

      <div className="h-6" />

      <AddChallengeModal
        battleId={data.battle.id}
        open={chOpen}
        edit={editCh}
        onClose={() => setChOpen(false)}
      />

      <BattleSettingsModal
        battle={data.battle}
        isCreator={me?.user.id === data.battle.created_by}
        open={settingsOpen}
        onClose={() => setSettingsOpen(false)}
        onDeleted={() => navigate('/', { replace: true })}
      />
    </PageTransition>
  )
}
