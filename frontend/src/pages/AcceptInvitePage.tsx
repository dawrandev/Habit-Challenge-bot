import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import { useAcceptInvite, useInvite, type StakeTexts } from '../lib/api'
import StakeInputs from '../components/StakeInputs'
import { stakesFilled } from '../lib/stakes'

export default function AcceptInvitePage() {
  const { token } = useParams()
  const navigate = useNavigate()
  const { t } = useTranslation()
  const { data, isLoading, isError } = useInvite(token!)
  const accept = useAcceptInvite()
  const [stakes, setStakes] = useState<StakeTexts>({ prize: '', penalty: '' })

  useEffect(() => {
    if (data?.already_participant) {
      navigate(`/battle/${data.battle.id}`, { replace: true })
    }
  }, [data, navigate])

  if (isLoading) {
    return (
      <div className="grid min-h-dvh place-items-center bg-bg text-muted">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-you" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div className="grid min-h-dvh place-items-center bg-bg px-8 text-center">
        <div>
          <p className="mb-2 text-4xl">🤷</p>
          <p className="mb-4 text-muted">{t('invite.notFound')}</p>
          <button
            type="button"
            onClick={() => navigate('/', { replace: true })}
            className="rounded-2xl bg-you px-6 py-3 font-semibold text-bg"
          >
            {t('nav.home')}
          </button>
        </div>
      </div>
    )
  }

  const creator = data.creator
  const name = creator?.first_name ?? '—'
  const initial = (name[0] ?? '?').toUpperCase()

  // Garovli duel: do'st ham o'z yutuq/jazo variantini aytadi (SPEC §17)
  const theirs = data.stakes
  const canAccept = !theirs || stakesFilled(stakes)

  function onAccept() {
    if (!canAccept) return
    accept.mutate(
      {
        token: token!,
        stakes: theirs
          ? { prize: stakes.prize.trim(), penalty: stakes.penalty.trim() }
          : undefined,
      },
      { onSuccess: (r) => navigate(`/battle/${r.battle_id}`, { replace: true }) },
    )
  }

  return (
    <div className="mx-auto flex min-h-dvh max-w-md flex-col justify-center bg-bg px-5 py-8">
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        className="rounded-3xl border border-line bg-surface p-6 text-center shadow-2xl shadow-black/40"
      >
        <p className="mb-4 text-xs tracking-[0.2em] text-muted uppercase">
          ⚔️ {t('invite.title')}
        </p>

        {/* Chaqiruvchi */}
        <div className="mb-4 flex flex-col items-center gap-2">
          <div className="avatar-glow-rival grid h-20 w-20 place-items-center overflow-hidden rounded-full bg-surface-2 font-display text-3xl text-rival ring-2 ring-rival">
            {creator?.photo_url ? (
              <img src={creator.photo_url} alt="" className="h-full w-full object-cover" />
            ) : (
              initial
            )}
          </div>
          <p className="text-sm text-muted">
            <span className="font-display text-base text-rival">{name}</span>{' '}
            {t('invite.by')}
          </p>
        </div>

        <h1 className="mb-1 font-display text-2xl">{data.battle.title}</h1>
        <p className="mb-5 text-sm text-muted">
          {t('create.period')}: {data.battle.period_days} {t('invite.days')}
        </p>

        {/* Challenge'lar */}
        <div className="mb-6 space-y-2 text-left">
          {data.challenges.map((c) => (
            <div
              key={c.id}
              className="flex items-center gap-3 rounded-2xl border border-line bg-surface-2 px-4 py-2.5"
            >
              <span className="text-lg">{c.icon}</span>
              <span className="text-sm">
                {c.template_key ? t(`tpl.${c.template_key}`) : c.name}
              </span>
            </div>
          ))}
        </div>

        {/* Garov: avval uning variantlari, keyin seniki */}
        {theirs && (
          <div className="mb-6 text-left">
            <p className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
              🤝 {t('stakes.section')}
            </p>
            <div className="mb-3 space-y-2">
              {(['prize', 'penalty'] as const).map((kind) => (
                <div
                  key={kind}
                  className="flex items-start gap-3 rounded-2xl border border-rival/30 bg-rival/5 px-4 py-2.5"
                >
                  <span className="text-lg">{kind === 'prize' ? '🏆' : '⚡'}</span>
                  <div className="min-w-0">
                    <p className="text-[11px] text-rival">
                      {kind === 'prize'
                        ? t('stakes.ifRivalWins', { name })
                        : `${t('stakes.penalty')} · ${t('stakes.rivalVariant', { name })}`}
                    </p>
                    <p className="text-sm">{theirs[kind] ?? '—'}</p>
                  </div>
                </div>
              ))}
            </div>
            <p className="mb-3 text-xs text-muted">{t('stakes.inviteHint')}</p>
            <StakeInputs value={stakes} onChange={setStakes} threshold={theirs.threshold} />
          </div>
        )}

        {accept.isError && (
          <p className="mb-3 text-xs text-reject">
            {String(accept.error).startsWith('Error: 409')
              ? t('stakes.inviteClosed')
              : t('stakes.needStakes')}
          </p>
        )}

        {/* Amallar */}
        <div className="flex gap-3">
          <button
            type="button"
            onClick={() => navigate('/', { replace: true })}
            className="flex-1 rounded-2xl border border-line bg-surface py-3.5 font-medium text-muted transition active:scale-[0.98]"
          >
            {t('invite.decline')}
          </button>
          <button
            type="button"
            onClick={onAccept}
            disabled={!canAccept || accept.isPending}
            className="flex-1 rounded-2xl bg-you py-3.5 font-display text-lg text-bg shadow-[0_8px_30px_rgba(246,176,30,0.35)] transition active:scale-[0.98] disabled:opacity-60"
          >
            {accept.isPending ? '…' : t('invite.accept')}
          </button>
        </div>
      </motion.div>
    </div>
  )
}
