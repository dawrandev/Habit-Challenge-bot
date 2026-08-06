import { useEffect, useRef, useState, type ChangeEvent } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { AnimatePresence, motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import { useSubmitProof } from '../lib/api'

type Phase =
  | 'loading'
  | 'pick' // skrinshot: fayl tanlash
  | 'denied'
  | 'nocamera'
  | 'ready'
  | 'captured'
  | 'sending'
  | 'sent'

export default function ProofCapturePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id, challengeId } = useParams()
  const [params] = useSearchParams()
  const isScreenshot = params.get('type') === 'screenshot'
  const submitMut = useSubmitProof(id!)

  const videoRef = useRef<HTMLVideoElement>(null)
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const blobRef = useRef<Blob | null>(null)

  const [phase, setPhase] = useState<Phase>('loading')
  const [shot, setShot] = useState<string | null>(null)
  const [note, setNote] = useState('')
  const [facing, setFacing] = useState<'environment' | 'user'>('environment')

  async function startCamera(mode: 'environment' | 'user' = facing) {
    setPhase('loading')
    stopCamera()
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: mode },
        audio: false,
      })
      streamRef.current = stream
      if (videoRef.current) {
        videoRef.current.srcObject = stream
        await videoRef.current.play().catch(() => {})
      }
      setPhase('ready')
    } catch (err) {
      const name = (err as DOMException)?.name
      setPhase(name === 'NotFoundError' || name === 'OverconstrainedError' ? 'nocamera' : 'denied')
    }
  }

  function flipCamera() {
    const next = facing === 'environment' ? 'user' : 'environment'
    setFacing(next)
    startCamera(next)
  }

  function stopCamera() {
    streamRef.current?.getTracks().forEach((tr) => tr.stop())
    streamRef.current = null
  }

  useEffect(() => {
    if (isScreenshot) {
      setPhase('pick')
    } else {
      startCamera()
    }
    return stopCamera
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // --- Kamera ---
  function capture() {
    const video = videoRef.current
    const canvas = canvasRef.current
    if (!video || !canvas) return
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)
    canvas.toBlob(
      (b) => {
        blobRef.current = b
      },
      'image/jpeg',
      0.9,
    )
    setShot(canvas.toDataURL('image/jpeg', 0.9))
    setPhase('captured')
  }

  // --- Skrinshot (galereya) ---
  function onFile(e: ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    blobRef.current = file
    setShot(URL.createObjectURL(file))
    setPhase('captured')
  }

  function retake() {
    setShot(null)
    blobRef.current = null
    setPhase(isScreenshot ? 'pick' : 'ready')
  }

  async function submit() {
    if (!blobRef.current || !challengeId) return
    setPhase('sending')
    try {
      await submitMut.mutateAsync({
        challengeId: Number(challengeId),
        blob: blobRef.current,
        note: note.trim() || undefined,
      })
      stopCamera()
      setPhase('sent')
      setTimeout(() => navigate(-1), 900)
    } catch {
      setPhase('captured')
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-black">
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={onFile}
      />

      {/* Header */}
      <div className="flex items-center justify-between px-5 pt-[max(1rem,env(safe-area-inset-top))] pb-3">
        <button
          type="button"
          onClick={() => navigate(-1)}
          className="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-text"
          aria-label={t('proof.cancel')}
        >
          ✕
        </button>
        <span className="font-display text-lg text-text">{t('proof.title')}</span>
        {!isScreenshot && (phase === 'ready' || phase === 'loading') ? (
          <button
            type="button"
            onClick={flipCamera}
            className="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-lg text-text transition active:scale-90"
            aria-label={t('proof.flip')}
          >
            🔄
          </button>
        ) : (
          <span className="w-9" />
        )}
      </div>

      {/* Kamera / skrinshot / preview */}
      <div className="relative flex-1 overflow-hidden">
        {!isScreenshot && (
          <video
            ref={videoRef}
            playsInline
            muted
            className={`h-full w-full object-cover ${facing === 'user' ? '-scale-x-100' : ''} ${phase === 'captured' || phase === 'sending' || phase === 'sent' ? 'hidden' : ''}`}
          />
        )}
        {shot && (phase === 'captured' || phase === 'sending' || phase === 'sent') && (
          <img src={shot} alt="" className="h-full w-full object-contain" />
        )}
        <canvas ref={canvasRef} className="hidden" />

        {/* Kamera yo'riqnomasi */}
        {phase === 'ready' && (
          <div className="pointer-events-none absolute inset-x-0 bottom-4 text-center">
            <span className="rounded-full bg-black/50 px-3 py-1.5 text-sm text-white/90 backdrop-blur">
              {t('proof.hint')}
            </span>
          </div>
        )}

        {/* Skrinshot tanlash ekrani */}
        {phase === 'pick' && (
          <div className="absolute inset-0 grid place-items-center px-8 text-center">
            <div>
              <p className="mb-3 text-5xl">🖼</p>
              <p className="mb-5 text-sm text-muted">{t('crud.proofScreenshot')}</p>
              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                className="rounded-2xl bg-you px-6 py-3 font-semibold text-bg transition active:scale-95"
              >
                🖼 {t('crud.proofScreenshot')}
              </button>
            </div>
          </div>
        )}

        {phase === 'loading' && (
          <div className="absolute inset-0 grid place-items-center text-muted">
            <div className="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-you" />
          </div>
        )}

        {(phase === 'denied' || phase === 'nocamera') && (
          <div className="absolute inset-0 grid place-items-center px-8 text-center">
            <div>
              <p className="mb-2 text-4xl">📷</p>
              <p className="mb-1 font-display text-lg text-text">
                {phase === 'denied' ? t('proof.permissionTitle') : t('proof.noCamera')}
              </p>
              {phase === 'denied' && (
                <p className="text-sm text-muted">{t('proof.permissionBody')}</p>
              )}
              <button
                type="button"
                onClick={phase === 'denied' ? startCamera : () => navigate(-1)}
                className="mt-5 rounded-2xl bg-you px-6 py-3 font-semibold text-bg transition active:scale-95"
              >
                {phase === 'denied' ? t('proof.capture') : t('proof.cancel')}
              </button>
            </div>
          </div>
        )}

        <AnimatePresence>
          {phase === 'sent' && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="absolute inset-0 grid place-items-center bg-black/60 backdrop-blur"
            >
              <motion.div
                initial={{ scale: 0.5, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ type: 'spring', stiffness: 300, damping: 18 }}
                className="grid h-24 w-24 place-items-center rounded-full bg-approve text-5xl text-black"
              >
                ✓
              </motion.div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      {/* Boshqaruv */}
      <div className="px-8 pt-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
        {phase === 'ready' && (
          <div className="flex justify-center">
            <button
              type="button"
              onClick={capture}
              aria-label={t('proof.capture')}
              className="grid place-items-center rounded-full ring-4 ring-white/80 transition active:scale-90"
              style={{ width: 72, height: 72 }}
            >
              <span className="h-14 w-14 rounded-full bg-white" />
            </button>
          </div>
        )}

        {(phase === 'captured' || phase === 'sending') && (
          <input
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder={t('proof.notePlaceholder')}
            maxLength={280}
            disabled={phase === 'sending'}
            className="mb-3 w-full rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder:text-white/50 outline-none focus:border-you disabled:opacity-50"
          />
        )}

        {(phase === 'captured' || phase === 'sending') && (
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={retake}
              disabled={phase === 'sending'}
              className="flex-1 rounded-2xl border border-line bg-surface py-3.5 font-medium text-text transition active:scale-[0.98] disabled:opacity-50"
            >
              {t('proof.retake')}
            </button>
            <button
              type="button"
              onClick={submit}
              disabled={phase === 'sending'}
              className="flex-1 rounded-2xl bg-you py-3.5 font-display text-lg text-bg shadow-[0_8px_30px_rgba(246,176,30,0.35)] transition active:scale-[0.98] disabled:opacity-70"
            >
              {phase === 'sending' ? t('proof.sending') : t('proof.submit')}
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
