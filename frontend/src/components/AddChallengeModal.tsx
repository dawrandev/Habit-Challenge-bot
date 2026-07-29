import { useEffect, useState } from 'react'
import { AnimatePresence, motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import { ICON_SET } from '../lib/challenges'
import {
  useAddChallenge,
  useDeleteChallenge,
  useUpdateChallenge,
  type Challenge,
  type ChallengeInput,
} from '../lib/api'

export default function AddChallengeModal({
  battleId,
  open,
  onClose,
  edit = null,
}: {
  battleId: number | string
  open: boolean
  onClose: () => void
  edit?: Challenge | null
}) {
  const { t } = useTranslation()
  const add = useAddChallenge(battleId)
  const update = useUpdateChallenge(battleId)
  const remove = useDeleteChallenge(battleId)
  const weekdays = t('weekdays', { returnObjects: true }) as string[]

  const [name, setName] = useState('')
  const [icon, setIcon] = useState<string>('📖')
  const [cadence, setCadence] = useState<'daily' | 'weekly_days'>('daily')
  const [days, setDays] = useState<number[]>([])

  // edit ochilganda mavjud qiymatlar bilan to'ldirish
  useEffect(() => {
    if (open && edit) {
      setName(edit.template_key ? t(`tpl.${edit.template_key}`) : edit.name)
      setIcon(edit.icon)
      setCadence(edit.cadence === 'weekly_days' ? 'weekly_days' : 'daily')
      setDays(edit.weekdays ?? [])
    } else if (open && !edit) {
      setName('')
      setIcon('📖')
      setCadence('daily')
      setDays([])
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, edit?.id])

  function toggleDay(d: number) {
    setDays((cur) => (cur.includes(d) ? cur.filter((x) => x !== d) : [...cur, d].sort()))
  }

  const busy = add.isPending || update.isPending || remove.isPending

  function submit() {
    if (!name.trim()) return
    const payload: ChallengeInput = {
      template_key: edit?.template_key ?? null,
      name: name.trim(),
      icon,
      cadence,
      weekdays: cadence === 'weekly_days' ? days : [],
    }
    if (edit) {
      update.mutate({ id: edit.id, data: payload }, { onSuccess: onClose })
    } else {
      add.mutate(payload, { onSuccess: onClose })
    }
  }

  function del() {
    if (!edit) return
    if (!window.confirm(t('crud.confirmDeleteChallenge'))) return
    remove.mutate(edit.id, { onSuccess: onClose })
  }

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
            className="fixed inset-x-0 bottom-0 z-50 mx-auto max-h-[90vh] max-w-md overflow-y-auto rounded-t-3xl border border-line bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
          >
            <div className="mx-auto mb-4 h-1 w-10 rounded-full bg-line" />
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-display text-xl">
                {icon} {edit ? t('crud.editChallenge') : t('create.chooseChallenges')}
              </h2>
              {edit && (
                <button
                  type="button"
                  onClick={del}
                  disabled={busy}
                  className="rounded-full bg-reject/15 px-3 py-1 text-xs font-semibold text-reject transition active:scale-95"
                >
                  🗑
                </button>
              )}
            </div>

            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={t('create.customPlaceholder')}
              disabled={!!edit?.template_key}
              className="mb-3 w-full rounded-2xl border border-line bg-surface-2 px-4 py-3 text-text outline-none focus:border-you disabled:opacity-60"
            />

            <div className="mb-4 grid grid-cols-8 gap-1.5">
              {ICON_SET.map((em) => (
                <button
                  key={em}
                  type="button"
                  onClick={() => setIcon(em)}
                  className={`grid aspect-square place-items-center rounded-xl text-lg transition ${
                    icon === em ? 'bg-you/20 ring-2 ring-you' : 'bg-surface-2'
                  }`}
                >
                  {em}
                </button>
              ))}
            </div>

            <div className="mb-2 flex gap-2">
              <button
                type="button"
                onClick={() => setCadence('daily')}
                className={`flex-1 rounded-xl border py-2 text-sm transition ${cadence === 'daily' ? 'border-you bg-you/10 text-you' : 'border-line text-muted'}`}
              >
                {t('create.everyDay')}
              </button>
              <button
                type="button"
                onClick={() => setCadence('weekly_days')}
                className={`flex-1 rounded-xl border py-2 text-sm transition ${cadence === 'weekly_days' ? 'border-you bg-you/10 text-you' : 'border-line text-muted'}`}
              >
                {t('create.certainDays')}
              </button>
            </div>
            {cadence === 'weekly_days' && (
              <div className="mb-2 flex justify-between gap-1">
                {weekdays.map((wd, d) => (
                  <button
                    key={d}
                    type="button"
                    onClick={() => toggleDay(d)}
                    className={`flex-1 rounded-lg py-1.5 text-[11px] transition ${days.includes(d) ? 'bg-you text-bg' : 'bg-surface-2 text-muted'}`}
                  >
                    {wd}
                  </button>
                ))}
              </div>
            )}

            <button
              type="button"
              onClick={submit}
              disabled={!name.trim() || busy}
              className="mt-3 w-full rounded-2xl bg-you py-3.5 font-display text-lg text-bg transition active:scale-[0.98] disabled:opacity-50"
            >
              {busy ? '…' : edit ? t('crud.save') : `＋ ${t('create.create')}`}
            </button>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}
