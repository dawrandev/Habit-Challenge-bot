import { useState } from 'react'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import VerifyModal from '../components/VerifyModal'
import { useVerify, useVerifyQueue, type QueueItem } from '../lib/api'

export default function ActivityPage() {
  const { t } = useTranslation()
  const { data: queue, isLoading } = useVerifyQueue()
  const verify = useVerify()
  const [selected, setSelected] = useState<QueueItem | null>(null)

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
      <header className="px-5 pt-6 pb-4">
        <h1 className="font-display text-2xl">{t('activity.title')}</h1>
      </header>

      <section className="px-5">
        <h2 className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
          {t('activity.toVerify')}
        </h2>

        {isLoading && (
          <div className="grid place-items-center py-10 text-muted">
            <div className="h-6 w-6 animate-spin rounded-full border-2 border-line border-t-you" />
          </div>
        )}

        {!isLoading && queue && queue.length === 0 && (
          <p className="rounded-2xl border border-line bg-surface px-4 py-4 text-sm text-muted">
            {t('activity.empty')}
          </p>
        )}

        <div className="space-y-2">
          {queue?.map((q, i) => (
            <motion.button
              key={q.completion.id}
              type="button"
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.04 + i * 0.07 }}
              onClick={() => setSelected(q)}
              className="flex w-full items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 text-left transition active:scale-[0.98]"
            >
              <span className="text-lg">{q.challenge.icon}</span>
              <div className="flex-1">
                <p className="text-sm">
                  <span className="text-rival">{q.rival.first_name}</span> ·{' '}
                  {labelOf(q)}
                </p>
              </div>
              <span className="rounded-full bg-you/15 px-2.5 py-1 text-xs font-medium text-you">
                {t('verify.title')} →
              </span>
            </motion.button>
          ))}
        </div>
      </section>

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
              }
            : null
        }
        busy={verify.isPending}
        onClose={() => setSelected(null)}
        onApprove={() => act(true)}
        onReject={() => act(false)}
      />
    </PageTransition>
  )
}
