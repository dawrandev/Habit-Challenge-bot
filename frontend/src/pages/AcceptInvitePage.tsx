import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import { useAcceptInvite, useInvite } from '../lib/api'

export default function AcceptInvitePage() {
  const { token } = useParams()
  const navigate = useNavigate()
  const { t } = useTranslation()
  const { data, isLoading, isError } = useInvite(token!)
  const accept = useAcceptInvite()

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

  function onAccept() {
    accept.mutate(token!, {
      onSuccess: (r) => navigate(`/battle/${r.battle_id}`, { replace: true }),
    })
  }

  return (
    <div className="mx-auto flex min-h-dvh max-w-md flex-col justify-center bg-bg px-5">
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
            disabled={accept.isPending}
            className="flex-1 rounded-2xl bg-you py-3.5 font-display text-lg text-bg shadow-[0_8px_30px_rgba(246,176,30,0.35)] transition active:scale-[0.98] disabled:opacity-60"
          >
            {accept.isPending ? '…' : t('invite.accept')}
          </button>
        </div>
      </motion.div>
    </div>
  )
}
