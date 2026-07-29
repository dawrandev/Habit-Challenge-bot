import { Link } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import { SUPPORTED, LANG_LABELS, setLang, type Lang } from '../i18n'
import { useMe } from '../lib/api'

export default function ProfilePage() {
  const { t, i18n } = useTranslation()
  const current = i18n.language as Lang
  const { data: me } = useMe()

  const name = me?.first_name || t('common.you')
  const initial = (name[0] ?? '?').toUpperCase()

  return (
    <PageTransition>
      <header className="px-5 pt-6 pb-4">
        <h1 className="font-display text-2xl">{t('profile.title')}</h1>
      </header>

      {/* Profil kartochkasi — Telegram profili */}
      <section className="px-5">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center gap-4 rounded-3xl border border-line bg-surface p-5"
        >
          <div className="avatar-glow-you grid h-16 w-16 place-items-center overflow-hidden rounded-full bg-surface-2 font-display text-2xl text-you ring-2 ring-you">
            {me?.photo_url ? (
              <img src={me.photo_url} alt="" className="h-full w-full object-cover" />
            ) : (
              initial
            )}
          </div>
          <div className="flex-1">
            <p className="font-display text-xl">{name}</p>
            {me?.username && (
              <p className="text-sm text-muted">@{me.username}</p>
            )}
          </div>
        </motion.div>
      </section>

      {/* Qanday ishlaydi */}
      <section className="px-5 pt-6">
        <Link
          to="/about"
          className="flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3.5 transition active:scale-[0.98]"
        >
          <span className="text-lg">ℹ️</span>
          <span className="flex-1 text-sm font-medium">{t('about.title')}</span>
          <span className="text-muted">›</span>
        </Link>
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
