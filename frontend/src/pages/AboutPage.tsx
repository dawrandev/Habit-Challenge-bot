import { useNavigate } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'

const STEPS = [
  { icon: '⚔️', key: 's1' },
  { icon: '📤', key: 's2' },
  { icon: '📸', key: 's3' },
  { icon: '✅', key: 's4' },
  { icon: '🏆', key: 's5' },
] as const

export default function AboutPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  return (
    <PageTransition>
      <header className="flex items-center gap-3 px-5 pt-6 pb-4">
        <button
          type="button"
          onClick={() => navigate(-1)}
          className="grid h-9 w-9 place-items-center rounded-full bg-surface text-muted"
        >
          ←
        </button>
        <h1 className="font-display text-2xl">{t('about.title')}</h1>
      </header>

      <div className="px-5">
        {/* Intro */}
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="mb-5 rounded-3xl border border-line bg-surface p-5 text-center"
        >
          <p className="mb-2 text-4xl">⚔️🔥</p>
          <p className="text-sm text-muted">{t('about.intro')}</p>
        </motion.div>

        {/* Qadamlar */}
        <div className="space-y-3">
          {STEPS.map((step, i) => (
            <motion.div
              key={step.key}
              initial={{ opacity: 0, x: -16 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.08 + i * 0.09, ease: [0.22, 1, 0.36, 1] }}
              className="flex items-center gap-4 rounded-2xl border border-line bg-surface px-4 py-3.5"
            >
              <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-surface-2 text-xl">
                {step.icon}
              </div>
              <div className="flex items-baseline gap-2">
                <span className="font-display text-lg text-you">{i + 1}</span>
                <span className="text-sm">{t(`about.${step.key}`)}</span>
              </div>
            </motion.div>
          ))}
        </div>

        {/* Outro */}
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.6 }}
          className="mt-5 rounded-2xl border border-you/30 bg-you/10 px-4 py-4 text-center text-sm text-you"
        >
          {t('about.outro')}
        </motion.div>
      </div>

      <div className="h-6" />
    </PageTransition>
  )
}
