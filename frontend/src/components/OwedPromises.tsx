import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useBattles } from '../lib/api'

/**
 * Bajarilmagan va'dalarim (SPEC §17) — tugagan duellardan.
 *
 * Tugagan duel Asosiy sahifada ko'rinmaydi (arxivda), shuning uchun
 * yutqazgan o'z qarzini bu yerda ko'rsin: bot xabarini o'tkazib yuborsa ham.
 */
export default function OwedPromises({ className = '' }: { className?: string }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data } = useBattles()

  const items = (data ?? []).flatMap((b) =>
    (b.result?.obligations ?? [])
      .filter((o) => o.is_mine && !o.fulfillment)
      .map((o) => ({ battle: b.battle, o })),
  )
  if (!items.length) return null

  return (
    <section className={`rise-in px-5 ${className}`}>
      <div className="rounded-3xl border border-you/40 bg-you/5 p-4">
        <p className="font-display text-base">📸 {t('promises.homeTitle')}</p>
        <p className="mt-0.5 mb-3 text-xs text-muted">{t('promises.homeHint')}</p>
        <div className="space-y-2">
          {items.map(({ battle, o }) => (
            <button
              key={`${battle.id}-${o.kind}`}
              type="button"
              onClick={() => navigate(`/battle/${battle.id}`)}
              className="flex w-full items-center gap-3 rounded-2xl bg-surface px-3 py-2.5 text-left transition active:scale-[0.98]"
            >
              <span className="text-lg">{o.kind === 'prize' ? '🎁' : '⚡'}</span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm">{o.text}</p>
                <p className="truncate text-[11px] text-muted">
                  {o.kind === 'prize'
                    ? t('promises.youFor', { name: o.creditor?.first_name ?? '—' })
                    : t('promises.penaltyYou')}{' '}
                  · {battle.title}
                </p>
              </div>
              <span className="shrink-0 rounded-full bg-you px-2.5 py-1 text-[11px] font-semibold text-bg">
                📸 {t('promises.uploadShort')}
              </span>
            </button>
          ))}
        </div>
      </div>
    </section>
  )
}
