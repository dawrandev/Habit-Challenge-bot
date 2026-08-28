import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import { useAcceptQuestInvite, useQuestInvite } from '../lib/quests'

/**
 * Guvohlik taklifi.
 *
 * Duel taklifidan farqi: bu yerda odam CHALLENGE QABUL QILMAYDI —
 * u faqat guvohlik qilishga rozi bo'ladi. Ekran shuni ochiq aytishi shart,
 * aks holda odam o'ziga odat olganday tushunadi.
 */
export default function AcceptQuestPage() {
  const { token } = useParams()
  const navigate = useNavigate()
  const { t } = useTranslation()
  const { data, isLoading, isError } = useQuestInvite(token!)
  const accept = useAcceptQuestInvite()

  // Allaqachon a'zo bo'lsa (ega yoki guvoh) — to'g'ridan-to'g'ri missiyaga
  useEffect(() => {
    if (data?.role) {
      navigate(`/quest/${data.quest.id}`, { replace: true })
    }
  }, [data, navigate])

  if (isLoading) {
    return (
      <div className="grid min-h-dvh place-items-center bg-bg text-muted">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-witness" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div className="grid min-h-dvh place-items-center bg-bg px-8 text-center">
        <div>
          <p className="mb-2 text-4xl">🤷</p>
          <p className="mb-4 text-muted">{t('questInvite.notFound')}</p>
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

  const owner = data.owner
  const name = owner?.first_name ?? '—'
  const initial = (name[0] ?? '?').toUpperCase()

  function onAccept() {
    accept.mutate(token!, {
      onSuccess: (r) => navigate(`/quest/${r.quest_id}`, { replace: true }),
    })
  }

  return (
    <div className="mx-auto flex min-h-dvh max-w-md flex-col justify-center bg-bg px-5 py-8">
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        className="rounded-3xl border border-line bg-surface p-6 text-center shadow-2xl shadow-black/40"
      >
        <p className="mb-4 text-xs tracking-[0.2em] text-muted uppercase">
          👁 {t('questInvite.title')}
        </p>

        <div className="mb-4 flex flex-col items-center gap-2">
          <div className="avatar-glow-you grid h-20 w-20 place-items-center overflow-hidden rounded-full bg-surface-2 font-display text-3xl text-you ring-2 ring-you">
            {owner?.photo_url ? (
              <img
                src={owner.photo_url}
                alt=""
                className="h-full w-full object-cover"
              />
            ) : (
              initial
            )}
          </div>
          <p className="text-sm text-muted">
            <span className="font-display text-base text-you">{name}</span>{' '}
            {t('questInvite.by')}
          </p>
        </div>

        <h1 className="mb-1 font-display text-2xl">{data.quest.title}</h1>
        <p className="mb-4 text-sm text-muted">
          {data.quest.period_days} {t('invite.days')} ·{' '}
          {t('quest.goalShort', { goal: data.quest.goal_percent })}
        </p>

        {/* Rolni ochiq tushuntirish — eng muhim jumla */}
        <div className="mb-5 rounded-2xl border border-witness/30 bg-witness/10 px-4 py-3 text-sm text-witness">
          {t('questInvite.explain')}
        </div>

        {/* U bajaradigan odatlar */}
        <div className="mb-6 space-y-2 text-left">
          {data.challenges.map((c) => (
            <div
              key={c.id}
              className="flex items-center gap-3 rounded-2xl border border-line bg-surface-2 px-4 py-2.5"
            >
              <span className="text-lg">{c.icon}</span>
              <div className="min-w-0">
                <p className="truncate text-sm">
                  {c.template_key ? t(`tpl.${c.template_key}`) : c.name}
                </p>
                {c.description && (
                  <p className="truncate text-xs text-muted">{c.description}</p>
                )}
              </div>
            </div>
          ))}
        </div>

        {data.taken ? (
          <div className="rounded-2xl border border-line bg-surface-2 px-4 py-3 text-sm text-muted">
            {t('questInvite.taken')}
          </div>
        ) : (
          <div className="flex gap-3">
            <button
              type="button"
              onClick={() => navigate('/', { replace: true })}
              className="flex-1 rounded-2xl border border-line bg-surface py-3.5 font-medium text-muted transition active:scale-[0.98]"
            >
              {t('questInvite.decline')}
            </button>
            <button
              type="button"
              onClick={onAccept}
              disabled={accept.isPending}
              className="flex-1 rounded-2xl bg-witness py-3.5 font-display text-base text-bg shadow-[0_8px_30px_rgba(76,201,240,0.3)] transition active:scale-[0.98] disabled:opacity-60"
            >
              {accept.isPending ? '…' : t('questInvite.accept')}
            </button>
          </div>
        )}
      </motion.div>
    </div>
  )
}
