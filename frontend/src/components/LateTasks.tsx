import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useLateTasks, type LateTask } from '../lib/api'

function shortDate(iso: string) {
  const [, m, d] = iso.split('-')
  return `${d}.${m}`
}

/**
 * Kechagi hisobotlar (SPEC §18) — bajarib, yuborishni unutganlar uchun.
 *
 * `context` berilsa — faqat o'sha duel/missiya; berilmasa (Asosiy sahifa) —
 * hammasi, qaysi duel/missiyadan ekani bilan.
 */
export default function LateTasks({
  context,
  className = '',
}: {
  context?: { key: 'battle' | 'quest'; id: number }
  className?: string
}) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data } = useLateTasks()

  const items = (data ?? []).filter(
    (x) => !context || (x.context.key === context.key && x.context.id === context.id),
  )
  if (!items.length) return null

  const label = (x: LateTask) =>
    x.challenge.template_key ? t(`tpl.${x.challenge.template_key}`) : x.challenge.name

  return (
    <section className={`rise-in px-5 ${className}`}>
      <div className="rounded-3xl border border-witness/40 bg-witness/5 p-4">
        <p className="font-display text-base">🕊 {t('late.title')}</p>
        <p className="mt-0.5 mb-3 text-xs text-muted">{t('late.hint')}</p>
        <div className="space-y-2">
          {items.map((x) => (
            <button
              key={`${x.context.key}-${x.challenge.id}-${x.day}`}
              type="button"
              onClick={() =>
                navigate(
                  `/${x.context.key}/${x.context.id}/proof/${x.challenge.id}?type=${
                    x.challenge.proof_type ?? 'camera'
                  }&day=${x.day}`,
                )
              }
              className="flex w-full items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 text-left transition active:scale-[0.98]"
            >
              <span className="text-lg">{x.challenge.icon}</span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm">{label(x)}</span>
                <span className="block truncate text-[11px] text-muted">
                  {t('late.yesterday')} · {shortDate(x.day)}
                  {!context && ` · ${x.context.title}`}
                  {x.status === 'rejected' && ` · ✕ ${t('verify.reject')}`}
                </span>
              </span>
              <span className="shrink-0 rounded-full bg-witness/15 px-2.5 py-1 text-xs font-medium text-witness">
                📸 {t('late.send')}
              </span>
            </button>
          ))}
        </div>
      </div>
    </section>
  )
}
