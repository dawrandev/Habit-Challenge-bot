import { useEffect, useRef, useState } from 'react'
import { AnimatePresence, motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import {
  PROMISE_MAX_BYTES,
  UploadError,
  mediaUrl,
  useFulfillPromise,
  type Obligation,
  type PromiseProof,
} from '../lib/api'

const NOTE_MAX = 280

/** Isbot — rasm yoki video. Dev placeholder'da (fayl yo'q) faqat belgi. */
function ProofMedia({ proof }: { proof: PromiseProof }) {
  const src = mediaUrl(proof.file_id)
  if (!src) {
    return (
      <div className="grid aspect-video place-items-center rounded-2xl bg-surface-2 text-3xl">
        {proof.media_type === 'video' ? '🎬' : '📸'}
      </div>
    )
  }
  return proof.media_type === 'video' ? (
    <video
      src={src}
      controls
      playsInline
      preload="metadata"
      className="max-h-80 w-full rounded-2xl bg-black object-contain"
    />
  ) : (
    <img src={src} alt="" loading="lazy" className="max-h-80 w-full rounded-2xl bg-black object-contain" />
  )
}

/**
 * Fayl tanlangach: oldindan ko'rish + izoh + yuborish (progress bilan).
 * Fayl-picker ataylab galereyaga ruxsat beradi — bu kunlik isbot emas,
 * xotira: kinodagi rasm keyinroq ham yuklanishi mumkin.
 */
function UploadSheet({
  battleId,
  obligation,
  file,
  onClose,
}: {
  battleId: number
  obligation: Obligation
  file: File
  onClose: () => void
}) {
  const { t } = useTranslation()
  const fulfill = useFulfillPromise(battleId)
  const [note, setNote] = useState('')
  const [progress, setProgress] = useState(0)
  const [preview, setPreview] = useState<string | null>(null)
  const isVideo = file.type.startsWith('video/')

  useEffect(() => {
    const url = URL.createObjectURL(file)
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [file])

  const error = fulfill.error instanceof UploadError ? fulfill.error.code : fulfill.error ? 'server' : null

  return (
    <motion.div
      initial={{ height: 0, opacity: 0 }}
      animate={{ height: 'auto', opacity: 1 }}
      exit={{ height: 0, opacity: 0 }}
      className="overflow-hidden"
    >
      <div className="mt-3 space-y-3 rounded-2xl border border-you/40 bg-surface-2 p-3">
        {preview &&
          (isVideo ? (
            <video src={preview} controls playsInline className="max-h-64 w-full rounded-xl bg-black object-contain" />
          ) : (
            <img src={preview} alt="" className="max-h-64 w-full rounded-xl bg-black object-contain" />
          ))}

        <input
          value={note}
          maxLength={NOTE_MAX}
          disabled={fulfill.isPending}
          onChange={(e) => setNote(e.target.value)}
          placeholder={t('promises.notePlaceholder')}
          className="w-full rounded-xl border border-line bg-surface px-3 py-2.5 text-sm outline-none focus:border-you"
        />

        {fulfill.isPending && (
          <div>
            <div className="h-1.5 overflow-hidden rounded-full bg-surface">
              <motion.div className="h-full bg-you" animate={{ width: `${progress}%` }} />
            </div>
            <p className="tabular mt-1 text-center font-mono text-[11px] text-muted">
              {progress < 100 ? `${t('promises.uploading')} ${progress}%` : t('promises.saving')}
            </p>
          </div>
        )}

        {error && <p className="text-center text-xs text-reject">{t(`promises.error.${error}`)}</p>}

        <div className="flex gap-2">
          <button
            type="button"
            disabled={fulfill.isPending}
            onClick={onClose}
            className="flex-1 rounded-xl border border-line py-2.5 text-xs text-muted disabled:opacity-50"
          >
            {t('stakes.cancel')}
          </button>
          <button
            type="button"
            disabled={fulfill.isPending}
            onClick={() =>
              fulfill.mutate(
                { kind: obligation.kind, file, note, onProgress: setProgress },
                { onSuccess: onClose },
              )
            }
            className="flex-[2] rounded-xl bg-you py-2.5 text-xs font-semibold text-bg transition active:scale-[0.98] disabled:opacity-50"
          >
            {fulfill.isPending ? '…' : `✅ ${t('promises.send')}`}
          </button>
        </div>
      </div>
    </motion.div>
  )
}

function PromiseItem({ battleId, o, rivalName }: { battleId: number; o: Obligation; rivalName: string }) {
  const { t, i18n } = useTranslation()
  const input = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [tooBig, setTooBig] = useState(false)
  const proof = o.fulfillment

  const who =
    o.kind === 'prize'
      ? o.is_mine
        ? t('promises.youFor', { name: o.creditor?.first_name ?? rivalName })
        : t('promises.forYou', { name: o.debtor.first_name })
      : o.is_mine
        ? t('promises.penaltyYou')
        : t('promises.penaltyRival', { name: o.debtor.first_name })

  const tone = o.kind === 'prize' ? 'border-you/30' : 'border-reject/30'
  const date = proof
    ? new Date(proof.created_at).toLocaleDateString(i18n.language, { day: 'numeric', month: 'short' })
    : ''

  function pick(f: File | undefined) {
    if (!f) return
    setTooBig(f.size > PROMISE_MAX_BYTES)
    if (f.size <= PROMISE_MAX_BYTES) setFile(f)
  }

  return (
    <div className={`rounded-2xl border bg-surface p-4 ${proof ? 'border-approve/40' : tone}`}>
      <div className="mb-1 flex items-center justify-between gap-2">
        <span className="text-[11px] tracking-wide text-muted">
          {o.kind === 'prize' ? '🎁' : '⚡'} {who}
        </span>
        {proof ? (
          <span className="shrink-0 rounded-full bg-approve/15 px-2.5 py-0.5 text-[11px] font-medium text-approve">
            ✅ {t('promises.done')} · {date}
          </span>
        ) : (
          <span className="shrink-0 rounded-full bg-surface-2 px-2.5 py-0.5 text-[11px] text-muted">
            ⏳ {t('promises.pending')}
          </span>
        )}
      </div>
      <p className="mb-3 font-display text-lg leading-snug">{o.text}</p>

      {proof && (
        <div className="space-y-2">
          <ProofMedia proof={proof} />
          {proof.note && <p className="text-sm text-muted italic">«{proof.note}»</p>}
        </div>
      )}

      {/* Faqat qarzdor yuklaydi; galereya ham mumkin — bu xotira, kunlik isbot emas */}
      {o.is_mine && (
        <>
          <input
            ref={input}
            type="file"
            accept="image/*,video/*"
            className="hidden"
            onChange={(e) => {
              pick(e.target.files?.[0])
              e.target.value = ''
            }}
          />
          {!file &&
            (proof ? (
              <button
                type="button"
                onClick={() => input.current?.click()}
                className="mt-2 w-full rounded-xl border border-line py-2 text-xs text-muted transition active:scale-[0.98]"
              >
                🔄 {t('promises.replace')}
              </button>
            ) : (
              <button
                type="button"
                onClick={() => input.current?.click()}
                className="w-full rounded-2xl bg-you py-3 font-semibold text-bg shadow-[0_8px_30px_rgba(246,176,30,0.25)] transition active:scale-[0.98]"
              >
                📸 {t('promises.upload')}
              </button>
            ))}
          {!proof && !file && <p className="mt-2 text-center text-[11px] text-muted">{t('promises.uploadHint')}</p>}
          {tooBig && <p className="mt-2 text-center text-xs text-reject">{t('promises.error.too_large')}</p>}
          <AnimatePresence>
            {file && <UploadSheet battleId={battleId} obligation={o} file={file} onClose={() => setFile(null)} />}
          </AnimatePresence>
        </>
      )}

      {!o.is_mine && !proof && (
        <p className="text-xs text-muted">
          {t('promises.waitingFor', { name: o.debtor.first_name })}
        </p>
      )}
    </div>
  )
}

/**
 * Duel yakunidagi va'dalar (SPEC §17): kim kimga nima qiladi va bajarilgani
 * foto/video bilan — duelning tarixi. Avval o'zing bajaradiganlar turadi:
 * natija sahifasini ochgan yutqazgan birinchi navbatda shuni ko'rsin.
 */
export default function PromisesCard({
  battleId,
  obligations,
  penalty,
  rivalName,
}: {
  battleId: number
  obligations: Obligation[]
  /** Kelishilgan jazo (garovsiz duelda yo'q) */
  penalty?: string | null
  rivalName: string
}) {
  const { t } = useTranslation()
  if (obligations.length === 0 && !penalty) return null

  const sorted = [...obligations].sort((a, b) => Number(b.is_mine) - Number(a.is_mine))
  const done = obligations.filter((o) => o.fulfillment).length
  const nobodyPenalized = !!penalty && !obligations.some((o) => o.kind === 'penalty')

  return (
    <section className="rise-in px-5 pt-4" style={{ animationDelay: '0.06s' }}>
      <div className="mb-2 flex items-baseline justify-between">
        <h2 className="text-xs tracking-[0.18em] text-muted uppercase">🤝 {t('promises.title')}</h2>
        {obligations.length > 0 && (
          <span className="tabular font-mono text-xs text-muted">
            {t('promises.progress', { done, total: obligations.length })}
          </span>
        )}
      </div>

      <div className="space-y-3">
        {sorted.map((o) => (
          <PromiseItem key={`${o.kind}-${o.debtor.id}`} battleId={battleId} o={o} rivalName={rivalName} />
        ))}

        {nobodyPenalized && (
          <div className="rounded-2xl border border-line bg-surface px-4 py-3 text-sm text-approve">
            🛡 {t('stakes.nobodyPenalized')}
            <span className="mt-0.5 block text-xs text-muted">⚡ {penalty}</span>
          </div>
        )}
      </div>
    </section>
  )
}
