import { useState } from 'react'
import { AnimatePresence, motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import {
  useAbandonQuest,
  useDeleteQuest,
  useUpdateQuest,
  type Quest,
} from '../lib/quests'

const GOALS = [50, 70, 80, 90, 100]

export default function QuestSettingsModal({
  quest,
  isOwner,
  open,
  onClose,
  onDeleted,
}: {
  quest: Quest
  isOwner: boolean
  open: boolean
  onClose: () => void
  onDeleted: () => void
}) {
  const { t } = useTranslation()
  const update = useUpdateQuest(quest.id)
  const abandon = useAbandonQuest(quest.id)
  const remove = useDeleteQuest()

  const [title, setTitle] = useState(quest.title)
  const [goal, setGoal] = useState(quest.goal_percent)
  const [copied, setCopied] = useState(false)

  const botUser = import.meta.env.VITE_BOT_USERNAME ?? 'YourBot'
  const inviteLink = `https://t.me/${botUser}?start=quest_${quest.invite_token}`

  const dirty = title.trim() !== quest.title || goal !== quest.goal_percent

  function save() {
    if (!title.trim() || !dirty) return
    update.mutate({ title: title.trim(), goal_percent: goal })
  }

  function copy() {
    navigator.clipboard?.writeText(inviteLink)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }

  function stop() {
    if (!window.confirm(t('quest.confirmAbandon'))) return
    abandon.mutate(undefined, { onSuccess: onClose })
  }

  function del() {
    if (!window.confirm(t('quest.confirmDelete'))) return
    remove.mutate(quest.id, { onSuccess: onDeleted })
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
            className="fixed inset-x-0 bottom-0 z-50 mx-auto max-h-[92vh] max-w-md space-y-5 overflow-y-auto rounded-t-3xl border border-line bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
          >
            <div className="mx-auto h-1 w-10 rounded-full bg-line" />
            <h2 className="font-display text-xl">⚙️ {t('quest.settings')}</h2>

            {/* Guvoh havolasi — guvoh yo'q bo'lsa asosiy amal */}
            {quest.witness_id === null && (
              <div>
                <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
                  {t('quest.inviteWitness')}
                </label>
                <p className="mb-2 text-xs text-muted">
                  {t('quest.witnessHint')}
                </p>
                <div className="mb-2 truncate rounded-2xl border border-line bg-surface-2 px-4 py-3 text-sm text-witness">
                  {inviteLink}
                </div>
                <button
                  type="button"
                  onClick={copy}
                  className="w-full rounded-2xl bg-witness/15 py-3 text-sm font-semibold text-witness transition active:scale-[0.98]"
                >
                  {copied ? t('create.copied') : t('create.copy')}
                </button>
              </div>
            )}

            {isOwner && (
              <>
                <div>
                  <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
                    {t('crud.editTitle')}
                  </label>
                  <input
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    className="w-full rounded-2xl border border-line bg-surface-2 px-4 py-3 text-text outline-none focus:border-you"
                  />
                </div>

                <div>
                  <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
                    {t('quest.goal')}
                  </label>
                  <div className="flex gap-1.5">
                    {GOALS.map((g) => (
                      <button
                        key={g}
                        type="button"
                        onClick={() => setGoal(g)}
                        className={`tabular flex-1 rounded-xl border py-2.5 font-mono text-sm transition ${
                          goal === g
                            ? 'border-you bg-you/10 text-you'
                            : 'border-line text-muted'
                        }`}
                      >
                        {g}%
                      </button>
                    ))}
                  </div>
                </div>

                <button
                  type="button"
                  onClick={save}
                  disabled={update.isPending || !dirty || !title.trim()}
                  className="w-full rounded-2xl bg-you py-3.5 font-semibold text-bg transition active:scale-[0.98] disabled:opacity-40"
                >
                  {t('crud.save')}
                </button>

                {quest.status === 'active' && (
                  <button
                    type="button"
                    onClick={stop}
                    disabled={abandon.isPending}
                    className="w-full rounded-2xl border border-line py-3 text-sm font-medium text-muted transition active:scale-[0.98] disabled:opacity-50"
                  >
                    ⏸ {t('quest.abandon')}
                  </button>
                )}

                <button
                  type="button"
                  onClick={del}
                  disabled={remove.isPending}
                  className="w-full rounded-2xl bg-reject/15 py-3.5 font-semibold text-reject transition active:scale-[0.98] disabled:opacity-50"
                >
                  🗑 {t('quest.del')}
                </button>
              </>
            )}
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}
