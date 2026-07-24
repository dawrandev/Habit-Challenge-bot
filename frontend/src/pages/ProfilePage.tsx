import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import { SUPPORTED, LANG_LABELS, setLang, type Lang } from '../i18n'

const stats = [
  { label: 'W', value: 7, color: 'text-approve' },
  { label: 'L', value: 3, color: 'text-reject' },
  { label: '🔥', value: 12, color: 'text-you' },
]

export default function ProfilePage() {
  const { t, i18n } = useTranslation()
  const current = i18n.language as Lang

  return (
    <PageTransition>
      <header className="px-5 pt-6 pb-4">
        <h1 className="font-display text-2xl">{t('profile.title')}</h1>
      </header>

      {/* Profil kartochkasi */}
      <section className="px-5">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center gap-4 rounded-3xl border border-line bg-surface p-5"
        >
          <div className="grid h-16 w-16 place-items-center rounded-full bg-surface-2 font-display text-2xl text-you ring-2 ring-you">
            S
          </div>
          <div className="flex-1">
            <p className="font-display text-xl">{t('common.you')}</p>
            <div className="mt-1 flex gap-4">
              {stats.map((s) => (
                <span key={s.label} className="text-sm">
                  <span className={`font-display ${s.color}`}>{s.value}</span>{' '}
                  <span className="text-muted">{s.label}</span>
                </span>
              ))}
            </div>
          </div>
        </motion.div>
      </section>

      {/* Til almashtirgich */}
      <section className="px-5 pt-6">
        <h2 className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
          {t('profile.language')}
        </h2>
        <div className="grid grid-cols-2 gap-2">
          {SUPPORTED.map((lang) => {
            const active = current === lang
            return (
              <button
                key={lang}
                type="button"
                onClick={() => setLang(lang)}
                className={`relative rounded-2xl border px-4 py-3 text-left text-sm transition active:scale-[0.97] ${
                  active
                    ? 'border-you bg-you/10 text-you'
                    : 'border-line bg-surface text-text'
                }`}
              >
                {LANG_LABELS[lang]}
                {active && (
                  <motion.span
                    layoutId="lang-check"
                    className="absolute top-3 right-3 text-you"
                  >
                    ✓
                  </motion.span>
                )}
              </button>
            )
          })}
        </div>
      </section>

      {/* Arxiv */}
      <section className="px-5 pt-6">
        <h2 className="mb-2 text-xs tracking-[0.18em] text-muted uppercase">
          {t('profile.archive')}
        </h2>
        <div className="rounded-2xl border border-line bg-surface px-4 py-4 text-sm text-muted">
          {t('activity.empty')}
        </div>
      </section>

      <div className="h-6" />
    </PageTransition>
  )
}
