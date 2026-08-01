import { useEffect, useState } from 'react'
import { AnimatePresence, motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import { photoUrl } from '../lib/api'

export type VerifyItem = {
  rivalName: string
  icon: string
  label: string
  fileId?: string | null
  submittedAt?: string | null
  note?: string | null
}

function formatTime(iso?: string | null): string | null {
  if (!iso) return null
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return null
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

export default function VerifyModal({
  item,
  busy,
  onClose,
  onApprove,
  onReject,
}: {
  item: VerifyItem | null
  busy?: boolean
  onClose: () => void
  onApprove: () => void
  onReject: () => void
}) {
  const { t } = useTranslation()
  const [imgError, setImgError] = useState(false)

  const src = photoUrl(item?.fileId)
  const time = formatTime(item?.submittedAt)

  // Yangi element ochilganda rasm xatosini tozalash
  useEffect(() => {
    setImgError(false)
  }, [item?.fileId])

  return (
    <AnimatePresence>
      {item && (
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
            className="fixed inset-x-0 bottom-0 z-50 mx-auto max-h-[92vh] max-w-md overflow-y-auto rounded-t-3xl border border-line bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
          >
            <div className="mx-auto mb-4 h-1 w-10 rounded-full bg-line" />

            <div className="mb-3 flex items-center gap-2">
              <span className="text-lg">{item.icon}</span>
              <span className="font-display text-lg">{item.label}</span>
              <span className="ml-auto text-sm text-rival">{item.rivalName}</span>
            </div>

            <div className="relative mb-2 w-full overflow-hidden rounded-2xl bg-gradient-to-br from-surface-2 to-bg">
              {src && !imgError ? (
                <img
                  src={src}
                  alt={item.label}
                  onError={() => setImgError(true)}
                  className="mx-auto block max-h-[55vh] w-full object-contain"
                />
              ) : (
                <div className="grid aspect-square w-full place-items-center text-6xl opacity-40">
                  {item.icon}
                </div>
              )}
              {time && (
                <div className="absolute bottom-2 left-2 rounded-full bg-black/50 px-2.5 py-1 text-xs text-white/80 backdrop-blur">
                  {t('verify.submittedAt')} · {time}
                </div>
              )}
            </div>

            {item.note && (
              <div className="mb-3 rounded-2xl border border-line bg-surface-2 px-4 py-2.5 text-sm">
                <span className="mr-1">💬</span>
                {item.note}
              </div>
            )}

            <p className="mb-4 text-center text-sm text-muted">
              {t('verify.question')}
            </p>

            <div className="flex gap-3">
              <button
                type="button"
                disabled={busy}
                onClick={onReject}
                className="flex-1 rounded-2xl bg-reject/15 py-3.5 font-semibold text-reject transition active:scale-[0.98] disabled:opacity-50"
              >
                ✕ {t('verify.reject')}
              </button>
              <button
                type="button"
                disabled={busy}
                onClick={onApprove}
                className="flex-1 rounded-2xl bg-approve/15 py-3.5 font-semibold text-approve transition active:scale-[0.98] disabled:opacity-50"
              >
                ✓ {t('verify.approve')}
              </button>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}
