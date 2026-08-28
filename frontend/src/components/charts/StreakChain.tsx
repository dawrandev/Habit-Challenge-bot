import { useTranslation } from 'react-i18next'

/**
 * Seriya zanjiri — SPEC §13 "streak zanjiri" signature elementi.
 *
 * Ketma-ket mukammal kunlar bo'g'in bo'lib turadi; oxirgi bo'g'in "tirik"
 * (nafas oladi), ya'ni zanjir davom etayotgani ko'rinadi. Seriya uzilgan
 * bo'lsa zanjir so'nadi va matn buni ochiq aytadi — faqat rangga tayanmaymiz.
 */
const MAX_LINKS = 14

export default function StreakChain({
  current,
  longest,
}: {
  current: number
  longest: number
}) {
  const { t } = useTranslation()

  const shown = Math.min(current, MAX_LINKS)
  const overflow = current - shown
  const broken = current === 0

  return (
    <div className="flex items-center gap-3">
      <div className="min-w-0 flex-1">
        <div className="mb-1.5 flex items-center gap-1.5">
          {broken ? (
            // Uzilgan zanjir — bo'sh bo'g'inlar
            <>
              {Array.from({ length: 5 }, (_, i) => (
                <span
                  key={i}
                  className="h-2.5 w-2.5 rounded-full border border-line"
                />
              ))}
              <span className="ml-1 text-xs text-muted">
                {t('quest.streakBroken')}
              </span>
            </>
          ) : (
            <>
              {Array.from({ length: shown }, (_, i) => (
                <span
                  key={i}
                  className={`h-2.5 w-2.5 shrink-0 rounded-full bg-you ${
                    i === shown - 1 ? 'streak-tip' : ''
                  }`}
                  style={{ opacity: 0.45 + (0.55 * (i + 1)) / shown }}
                />
              ))}
              {overflow > 0 && (
                <span className="tabular ml-1 font-mono text-xs text-you">
                  +{overflow}
                </span>
              )}
            </>
          )}
        </div>
        <p className="text-[11px] text-muted">
          {t('quest.longestStreak', { n: longest })}
        </p>
      </div>

      {/* Raqam — tablo shriftida (Arena konventsiyasi) */}
      <div className="flex shrink-0 items-baseline gap-1">
        <span
          className={`font-display text-3xl leading-none ${
            broken ? 'text-muted' : 'text-you'
          }`}
        >
          {current}
        </span>
        <span className="text-xs text-muted">{t('quest.daysUnit')}</span>
      </div>
    </div>
  )
}
