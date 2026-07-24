import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { AnimatePresence, motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import { useSubmitProof } from '../lib/api'

type Phase = 'loading' | 'denied' | 'nocamera' | 'ready' | 'captured' | 'sending' | 'sent'

export default function ProofCapturePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id, challengeId } = useParams()
  const submitMut = useSubmitProof(id!)
  const videoRef = useRef<HTMLVideoElement>(null)
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const [phase, setPhase] = useState<Phase>('loading')
  const [shot, setShot] = useState<string | null>(null)

  async function startCamera() {
    setPhase('loading')
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
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

  function stopCamera() {
    streamRef.current?.getTracks().forEach((tr) => tr.stop())
    streamRef.current = null
  }

  useEffect(() => {
    startCamera()
    return stopCamera
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function capture() {
    const video = videoRef.current
    const canvas = canvasRef.current
    if (!video || !canvas) return
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    const ctx = canvas.getContext('2d')
    if (!ctx) return
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height)
    setShot(canvas.toDataURL('image/jpeg', 0.9))
    setPhase('captured')
  }

  function retake() {
    setShot(null)
    setPhase('ready')
  }

  async function submit() {
    const canvas = canvasRef.current
    if (!canvas || !challengeId) return
    setPhase('sending')
    const blob: Blob = await new Promise((res) =>
      canvas.toBlob((b) => res(b!), 'image/jpeg', 0.9),
    )
    try {
      await submitMut.mutateAsync({ challengeId: Number(challengeId), blob })
      stopCamera()
      setPhase('sent')
      setTimeout(() => navigate(-1), 900)
    } catch {
      setPhase('captured') // xatolik — qayta urinish mumkin
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-black">
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
        <span className="w-9" />
      </div>

      {/* Kamera / preview maydoni */}
      <div className="relative flex-1 overflow-hidden">
        <video
          ref={videoRef}
          playsInline
          muted
          className={`h-full w-full object-cover ${phase === 'captured' || phase === 'sending' || phase === 'sent' ? 'hidden' : ''}`}
        />
        {shot && phase !== 'ready' && (
          <img src={shot} alt="" className="h-full w-full object-cover" />
        )}
        <canvas ref={canvasRef} className="hidden" />

        {/* Yo'riqnoma */}
        {phase === 'ready' && (
          <div className="pointer-events-none absolute inset-x-0 bottom-4 text-center">
            <span className="rounded-full bg-black/50 px-3 py-1.5 text-sm text-white/90 backdrop-blur">
              {t('proof.hint')}
            </span>
          </div>
        )}

        {/* Yuklanmoqda */}
        {phase === 'loading' && (
          <div className="absolute inset-0 grid place-items-center text-muted">
            <div className="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-you" />
          </div>
        )}

        {/* Ruxsat yo'q / kamera yo'q */}
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

        {/* Yuborildi overlay */}
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

      {/* Boshqaruv paneli */}
      <div className="px-8 pt-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
        {phase === 'ready' && (
          <div className="flex justify-center">
            <button
              type="button"
              onClick={capture}
              aria-label={t('proof.capture')}
              className="grid h-18 w-18 place-items-center rounded-full ring-4 ring-white/80 transition active:scale-90"
              style={{ width: 72, height: 72 }}
            >
              <span className="h-14 w-14 rounded-full bg-white" />
            </button>
          </div>
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
