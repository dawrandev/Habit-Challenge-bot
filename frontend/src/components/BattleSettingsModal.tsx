import { useState } from 'react'
import { AnimatePresence, motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import { useDeleteBattle, useUpdateBattle, type Battle } from '../lib/api'
import { useInviteLink } from '../lib/invite'

export default function BattleSettingsModal({
  battle,
  isCreator,
  open,
  onClose,
  onDeleted,
}: {
  battle: Battle
  isCreator: boolean
  open: boolean
  onClose: () => void
  onDeleted: () => void
}) {
  const { t } = useTranslation()
  const updateBattle = useUpdateBattle(battle.id)
  const deleteBattle = useDeleteBattle()

  const [title, setTitle] = useState(battle.title)
  const [copied, setCopied] = useState(false)

  const { link: inviteLink, ready } = useInviteLink('battle', battle.invite_token)

  function saveTitle() {
    if (!title.trim() || title.trim() === battle.title) return
    updateBattle.mutate(title.trim())
  }

  function copy() {
    if (!ready) return
    navigator.clipboard?.writeText(inviteLink)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }

  function del() {
    if (!window.confirm(t('crud.confirmDeleteBattle'))) return
    deleteBattle.mutate(battle.id, { onSuccess: onDeleted })
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
            className="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-md space-y-5 rounded-t-3xl border border-line bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
          >
            <div className="mx-auto h-1 w-10 rounded-full bg-line" />
            <h2 className="font-display text-xl">⚙️ {t('crud.settings')}</h2>

            {/* Taklif havolasi */}
            <div>
              <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
                {t('crud.shareInvite')}
              </label>
              <div className="mb-2 truncate rounded-2xl border border-line bg-surface-2 px-4 py-3 text-sm text-you">
                {ready ? inviteLink : '…'}
              </div>
              <button
                type="button"
                onClick={copy}
                disabled={!ready}
                className="w-full rounded-2xl bg-you/15 py-3 text-sm font-semibold text-you transition active:scale-[0.98] disabled:opacity-50"
              >
                {copied ? t('create.copied') : t('create.copy')}
              </button>
            </div>

            {/* Nom tahrirlash (yaratuvchi) */}
            {isCreator && (
              <div>
                <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
                  {t('crud.editTitle')}
                </label>
                <div className="flex gap-2">
                  <input
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    className="flex-1 rounded-2xl border border-line bg-surface-2 px-4 py-3 text-text outline-none focus:border-you"
                  />
                  <button
                    type="button"
                    onClick={saveTitle}
                    disabled={updateBattle.isPending || !title.trim()}
                    className="rounded-2xl bg-you px-5 font-semibold text-bg disabled:opacity-50"
                  >
                    {t('crud.save')}
                  </button>
                </div>
              </div>
            )}

            {/* O'chirish (yaratuvchi) */}
            {isCreator && (
              <button
                type="button"
                onClick={del}
                disabled={deleteBattle.isPending}
                className="w-full rounded-2xl bg-reject/15 py-3.5 font-semibold text-reject transition active:scale-[0.98] disabled:opacity-50"
              >
                🗑 {t('crud.deleteBattle')}
              </button>
            )}
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}
