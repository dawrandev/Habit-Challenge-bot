import { useState } from 'react'
import { createPortal } from 'react-dom'
import { AnimatePresence, motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import {
  useEditStake,
  useFlipStake,
  useVoteStake,
  type Battle,
  type StakeKind,
  type StakeSide,
  type StakesState,
} from '../lib/api'
import { useInviteLink } from '../lib/invite'
import { STAKE_MAX } from '../lib/stakes'

const KINDS: StakeKind[] = ['prize', 'penalty']
const ICON: Record<StakeKind, string> = { prize: '🏆', penalty: '⚡' }

/** Tanga kamida shuncha aylansin — javob tez kelsa ham natija "tashlangan" bo'lib ko'rinsin. */
const FLIP_MS = 1300

function VoteTag({ tone, label }: { tone: 'you' | 'rival'; label: string }) {
  return (
    <motion.span
      layout
      initial={{ scale: 0.6, opacity: 0 }}
      animate={{ scale: 1, opacity: 1 }}
      className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
        tone === 'you' ? 'bg-you text-bg' : 'bg-rival text-text'
      }`}
    >
      ✓ {label}
    </motion.span>
  )
}

function StakeBlock({
  battleId,
  kind,
  side,
  threshold,
  rivalName,
  onChat,
  onFlip,
}: {
  battleId: number
  kind: StakeKind
  side: StakeSide
  threshold: number
  rivalName: string
  onChat: () => void
  onFlip: (kind: StakeKind) => void
}) {
  const { t } = useTranslation()
  const vote = useVoteStake(battleId)
  const edit = useEditStake(battleId)
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState('')

  const title = kind === 'prize' ? t('stakes.prizeQ') : t('stakes.penaltyQ', { n: threshold })

  if (side.chosen) {
    return (
      <motion.div
        initial={{ scale: 0.96, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        className={`rounded-2xl border p-4 ${
          kind === 'prize' ? 'border-you/40 bg-you/5' : 'border-reject/40 bg-reject/5'
        }`}
      >
        <div className="mb-1 flex items-center justify-between">
          <span className="text-xs tracking-[0.18em] text-muted uppercase">
            {ICON[kind]} {t(`stakes.${kind}`)}
          </span>
          <span className="rounded-full bg-approve/15 px-2.5 py-0.5 text-[11px] font-medium text-approve">
            {side.chosen.via === 'coin' ? `🎲 ${t('stakes.byCoin')}` : `🤝 ${t('stakes.agreed')}`}
          </span>
        </div>
        <p className="font-display text-lg leading-snug">{side.chosen.text}</p>
      </motion.div>
    )
  }

  const { my_vote: mine, rival_vote: theirs } = side
  const split = mine !== null && theirs !== null && mine !== theirs

  let hint = t('stakes.pickFirst')
  if (mine === null && theirs !== null) hint = t('stakes.yourTurn', { name: rivalName })
  if (mine !== null && theirs === null) hint = t('stakes.waitRival', { name: rivalName })

  return (
    <div className="rounded-2xl border border-line bg-surface p-4">
      <p className="mb-3 flex items-center gap-2 text-sm font-medium">
        <span className="text-lg">{ICON[kind]}</span>
        {title}
      </p>

      <div className="space-y-2">
        {side.options.map((opt) => {
          const picked = mine === opt.id
          const isEditing = editing && opt.is_mine

          if (isEditing) {
            return (
              <div key={opt.id} className="rounded-2xl border border-you/60 bg-surface-2 p-3">
                <input
                  autoFocus
                  value={draft}
                  maxLength={STAKE_MAX}
                  onChange={(e) => setDraft(e.target.value)}
                  className="mb-2 w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm outline-none focus:border-you"
                />
                <p className="mb-2 text-[11px] text-muted">{t('stakes.editHint')}</p>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setEditing(false)}
                    className="flex-1 rounded-xl border border-line py-2 text-xs text-muted"
                  >
                    {t('stakes.cancel')}
                  </button>
                  <button
                    type="button"
                    disabled={draft.trim().length < 2 || edit.isPending}
                    onClick={() =>
                      edit.mutate(
                        { kind, text: draft.trim() },
                        { onSuccess: () => setEditing(false) },
                      )
                    }
                    className="flex-1 rounded-xl bg-you py-2 text-xs font-semibold text-bg disabled:opacity-50"
                  >
                    {edit.isPending ? '…' : t('stakes.save')}
                  </button>
                </div>
              </div>
            )
          }

          return (
            <motion.button
              key={opt.id}
              type="button"
              layout
              whileTap={{ scale: 0.98 }}
              disabled={vote.isPending}
              onClick={() => !picked && vote.mutate(opt.id)}
              className={`relative w-full rounded-2xl border px-4 py-3 text-left transition-colors ${
                picked
                  ? 'border-you bg-you/10 shadow-[0_0_24px_rgba(246,176,30,0.18)]'
                  : 'border-line bg-surface-2'
              }`}
            >
              <div className="mb-1 flex items-center justify-between gap-2">
                <span className={`text-[11px] ${opt.is_mine ? 'text-you' : 'text-rival'}`}>
                  {opt.is_mine ? t('stakes.yourVariant') : t('stakes.rivalVariant', { name: rivalName })}
                </span>
                <span className="flex items-center gap-1">
                  {picked && <VoteTag tone="you" label={t('stakes.you')} />}
                  {theirs === opt.id && <VoteTag tone="rival" label={rivalName} />}
                  {opt.is_mine && (
                    <span
                      role="button"
                      tabIndex={0}
                      aria-label={t('stakes.edit')}
                      onClick={(e) => {
                        e.stopPropagation()
                        setDraft(opt.text)
                        setEditing(true)
                      }}
                      className="ml-1 grid h-6 w-6 place-items-center rounded-full bg-surface text-xs text-muted"
                    >
                      ✎
                    </span>
                  )}
                </span>
              </div>
              <p className="text-sm leading-snug">{opt.text}</p>
            </motion.button>
          )
        })}
      </div>

      {split ? (
        <div className="mt-3 rounded-xl bg-surface-2 p-3">
          <p className="mb-2.5 text-center text-xs text-muted">{t('stakes.split')}</p>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={onChat}
              className="flex-1 rounded-xl border border-line py-2.5 text-xs font-medium text-text transition active:scale-95"
            >
              💬 {t('stakes.toChat')}
            </button>
            <button
              type="button"
              onClick={() => onFlip(kind)}
              className="flex-1 rounded-xl bg-you py-2.5 text-xs font-semibold text-bg transition active:scale-95"
            >
              🎲 {t('stakes.flip')}
            </button>
          </div>
        </div>
      ) : (
        <p className="mt-2.5 text-center text-xs text-muted">{hint}</p>
      )}
    </div>
  )
}

/**
 * Tanga — aylanadi, keyin natijani ko'rsatadi.
 * Portal orqali: sahifa animatsiyasi (transform) ichida `fixed` butun
 * ekranni emas, faqat bo'limni qoplab qolardi.
 */
function CoinOverlay({
  state,
  onClose,
}: {
  state: { kind: StakeKind; result: string | null } | null
  onClose: () => void
}) {
  const { t } = useTranslation()
  return createPortal(
    <AnimatePresence>
      {state && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          onClick={() => state.result && onClose()}
          className="fixed inset-0 z-50 grid place-items-center bg-black/70 px-8 backdrop-blur-sm"
        >
          <div className="text-center">
            <div className={`coin mx-auto mb-6 ${state.result ? 'coin-land' : 'coin-spin'}`}>
              <span>{ICON[state.kind]}</span>
            </div>
            {state.result ? (
              <motion.div initial={{ y: 12, opacity: 0 }} animate={{ y: 0, opacity: 1 }}>
                <p className="mb-1 text-xs tracking-[0.2em] text-muted uppercase">
                  {t(`stakes.${state.kind}`)}
                </p>
                <p className="font-display text-2xl text-you">{state.result}</p>
                <p className="mt-6 text-xs text-muted">{t('stakes.tapToClose')}</p>
              </motion.div>
            ) : (
              <p className="text-sm text-muted">{t('stakes.flipping')}</p>
            )}
          </div>
        </motion.div>
      )}
    </AnimatePresence>,
    document.body,
  )
}

/**
 * Garov kelishuvi (SPEC §17) — duel boshlanishidan oldingi bosqich.
 *
 * `pending`: do'st hali qabul qilmagan — o'z variantlaring + taklif havolasi.
 * `negotiating`: har garov bo'yicha ikki variant, ovoz, kerak bo'lsa tanga.
 * `active`: hozirgina kelishildi — sahifa panelni yopmasdan oldin bayram
 *           ekrani (aks holda tanga natijasi ko'rinmay qolardi).
 */
export default function StakesNegotiation({
  battle,
  stakes,
  rivalName,
  onChat,
  onDone,
}: {
  battle: Battle
  stakes: StakesState
  rivalName: string
  onChat: () => void
  onDone: () => void
}) {
  const { t } = useTranslation()
  const flip = useFlipStake(battle.id)
  const [coin, setCoin] = useState<{ kind: StakeKind; result: string | null } | null>(null)
  const [copied, setCopied] = useState(false)
  const invite = useInviteLink('battle', battle.invite_token)

  function onFlip(kind: StakeKind) {
    setCoin({ kind, result: null })
    const started = Date.now()
    flip.mutate(kind, {
      onSuccess: (r) => {
        const text = r.stakes?.[kind].chosen?.text ?? ''
        const wait = Math.max(0, FLIP_MS - (Date.now() - started))
        setTimeout(() => setCoin({ kind, result: text }), wait)
      },
      onError: () => setCoin(null),
    })
  }

  if (battle.status === 'pending') {
    return (
      <section className="rise-in px-5">
        <div className="rounded-3xl border border-line bg-surface p-5 text-center shadow-2xl shadow-black/40">
          <p className="mb-2 text-4xl">⏳</p>
          <h2 className="font-display text-xl">{t('stakes.waitingAccept')}</h2>
          <p className="mt-1 mb-4 text-sm text-muted">{t('stakes.waitingAcceptHint')}</p>

          <div className="mb-4 space-y-2 text-left">
            {KINDS.map((kind) => (
              <div key={kind} className="flex items-start gap-3 rounded-2xl bg-surface-2 px-4 py-3">
                <span className="text-lg">{ICON[kind]}</span>
                <div className="min-w-0">
                  <p className="text-[11px] text-muted">
                    {t(`stakes.${kind}`)} · {t('stakes.yourVariant')}
                  </p>
                  <p className="text-sm">{stakes[kind].options[0]?.text ?? '—'}</p>
                </div>
              </div>
            ))}
          </div>

          <button
            type="button"
            disabled={!invite.ready}
            onClick={() => {
              navigator.clipboard?.writeText(invite.link)
              setCopied(true)
              setTimeout(() => setCopied(false), 1500)
            }}
            className="w-full rounded-2xl bg-you py-3 font-semibold text-bg transition active:scale-[0.98] disabled:opacity-50"
          >
            {copied ? t('stakes.copied') : `🔗 ${t('stakes.copyInvite')}`}
          </button>
        </div>
      </section>
    )
  }

  const settled = KINDS.filter((k) => stakes[k].chosen).length

  return (
    <section className="rise-in px-5">
      {battle.status === 'active' ? (
        <motion.div
          initial={{ scale: 0.95, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          className="mb-3 rounded-3xl border border-you/40 p-5 text-center shadow-2xl shadow-black/40"
          style={{
            background:
              'radial-gradient(120% 90% at 50% 0%, color-mix(in oklab, var(--color-you) 18%, transparent) 0%, var(--color-surface) 65%)',
          }}
        >
          <p className="trophy-pop mb-1 text-5xl">🔥</p>
          <h2 className="font-display text-2xl text-you">{t('stakes.started')}</h2>
          <p className="mt-1 mb-4 text-sm text-muted">{t('stakes.startedHint')}</p>
          <button
            type="button"
            onClick={onDone}
            className="w-full rounded-2xl bg-you py-3.5 font-display text-lg text-bg shadow-[0_8px_30px_rgba(246,176,30,0.35)] transition active:scale-[0.98]"
          >
            {t('stakes.go')}
          </button>
        </motion.div>
      ) : (
      <div className="mb-3 rounded-3xl border border-line bg-surface p-5 shadow-2xl shadow-black/40">
        <div className="mb-1 flex items-center justify-between">
          <h2 className="font-display text-xl">🤝 {t('stakes.negotiation')}</h2>
          <span className="tabular rounded-full bg-surface-2 px-2.5 py-1 font-mono text-xs text-muted">
            {settled}/2
          </span>
        </div>
        <p className="text-sm text-muted">{t('stakes.negotiationHint')}</p>
        <div className="mt-3 flex h-1.5 overflow-hidden rounded-full bg-surface-2">
          <motion.div
            className="h-full bg-approve"
            initial={false}
            animate={{ width: `${(settled / 2) * 100}%` }}
          />
        </div>
        <p className="mt-2 text-[11px] text-muted">
          {t('stakes.startsAfter')}. {t('stakes.datesShift')}.
        </p>
      </div>
      )}

      <div className="space-y-3">
        {KINDS.map((kind) => (
          <StakeBlock
            key={kind}
            battleId={battle.id}
            kind={kind}
            side={stakes[kind]}
            threshold={stakes.threshold}
            rivalName={rivalName}
            onChat={onChat}
            onFlip={onFlip}
          />
        ))}
      </div>

      <CoinOverlay state={coin} onClose={() => setCoin(null)} />
    </section>
  )
}
