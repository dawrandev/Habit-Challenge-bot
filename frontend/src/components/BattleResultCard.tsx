import { useTranslation } from 'react-i18next'
import type { BattleResult, PlayerScore } from '../lib/api'

function fmt(n: number) {
  return Number.isInteger(n) ? String(n) : n.toFixed(1)
}

function Face({
  name,
  photo,
  tone,
  crowned,
}: {
  name: string
  photo?: string | null
  tone: 'you' | 'rival'
  crowned: boolean
}) {
  const ring = tone === 'you' ? 'ring-you' : 'ring-rival'
  const text = tone === 'you' ? 'text-you' : 'text-rival'
  return (
    <div className="relative flex flex-col items-center gap-1.5">
      {crowned && <span className="crown-drop absolute -top-5 text-2xl">👑</span>}
      <div
        className={`grid h-16 w-16 place-items-center overflow-hidden rounded-full bg-surface-2 font-display text-2xl ring-2 ${ring} ${text} ${
          crowned ? '' : 'opacity-45 grayscale'
        }`}
      >
        {photo ? (
          <img src={photo} alt="" className="h-full w-full object-cover" />
        ) : (
          (name[0] ?? '?').toUpperCase()
        )}
      </div>
      <span
        className={`max-w-[84px] truncate text-xs ${crowned ? 'text-text' : 'text-muted'}`}
      >
        {name}
      </span>
    </div>
  )
}

/**
 * Duel yakuni — g'olib e'loni (SPEC §6).
 *
 * Yutqazgan tomon o'chirilmaydi, faqat so'ndiriladi: yakuniy hisob ikkala
 * tomon uchun ham ko'rinib turishi kerak, aks holda natija tekshirib
 * bo'lmaydigan da'voga aylanadi.
 *
 * Animatsiyalar ATAYLAB CSS'da (motion emas): natija ekrani sahifaning
 * butun mazmuni — u JS animatsiyasi tugashini kutib `opacity:0` da turmasligi
 * kerak. CSS variant `prefers-reduced-motion` ni ham o'zi hurmat qiladi.
 */
export default function BattleResultCard({
  result,
  me,
  rival,
  title,
}: {
  result: BattleResult
  me?: PlayerScore
  rival?: PlayerScore
  title: string
}) {
  const { t } = useTranslation()

  const myScore = me?.score ?? 0
  const rivalScore = rival?.score ?? 0
  const draw = result.is_draw
  const won = result.you_won

  const headline = draw
    ? t('result.draw')
    : won
      ? t('result.youWon')
      : t('result.youLost')

  const accent = draw
    ? 'var(--color-muted)'
    : won
      ? 'var(--color-you)'
      : 'var(--color-rival)'

  return (
    <section className="rise-in px-5">
      <div
        className="relative overflow-hidden rounded-3xl border p-5 text-center shadow-2xl shadow-black/40"
        style={{
          borderColor: `color-mix(in oklab, ${accent} 40%, transparent)`,
          background: `radial-gradient(120% 90% at 50% 0%, color-mix(in oklab, ${accent} 16%, transparent) 0%, var(--color-surface) 62%)`,
        }}
      >
        <p className="text-[10px] tracking-[0.22em] text-muted uppercase">
          {t('result.finished')}
        </p>

        <p className="trophy-pop my-1 text-5xl">
          {draw ? '🤝' : won ? '🏆' : '🏁'}
        </p>

        <h2
          className="font-display text-2xl"
          style={{ color: draw ? 'var(--color-text)' : accent }}
        >
          {headline}
        </h2>
        <p className="mt-0.5 mb-4 truncate text-xs text-muted">{title}</p>

        {/* Yakuniy tablo */}
        <div className="flex items-center justify-center gap-5">
          <Face
            name={t('common.you')}
            photo={me?.user.photo_url}
            tone="you"
            crowned={draw || won}
          />

          <div className="flex flex-col items-center">
            <span className="tabular font-display text-3xl leading-none">
              <span className={won || draw ? 'text-you' : 'text-muted'}>
                {fmt(myScore)}
              </span>
              <span className="mx-1.5 text-muted">:</span>
              <span className={!won || draw ? 'text-rival' : 'text-muted'}>
                {fmt(rivalScore)}
              </span>
            </span>
            <span className="mt-1 text-[10px] tracking-[0.18em] text-muted uppercase">
              {t('result.finalScore')}
            </span>
          </div>

          <Face
            name={rival?.user.first_name ?? '—'}
            photo={rival?.user.photo_url}
            tone="rival"
            crowned={draw || !won}
          />
        </div>

        {/* Kim yutganini faqat YUTQAZGANGA aytamiz — o'zing yutganingda
            sarlavha allaqachon shuni aytdi, ismni takrorlash ortiqcha. */}
        {!draw && !won && (
          <p className="mt-4 text-sm text-muted">
            {t('result.winnerIs')}{' '}
            <span className="font-display" style={{ color: accent }}>
              {result.winner?.first_name ?? '—'}
            </span>
          </p>
        )}
      </div>
    </section>
  )
}
