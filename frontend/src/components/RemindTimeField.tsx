import { useTranslation } from 'react-i18next'

/** Odat turiga mos birinchi taklif — bosganda tayyor vaqt chiqsin. */
function suggest(templateKey?: string | null): string {
  if (templateKey === 'earlyRise') return '07:00'
  if (templateKey === 'read') return '21:00'
  if (templateKey === 'sport') return '18:00'
  return '09:00'
}

/**
 * Odat vaqti — ixtiyoriy eslatma (SPEC §18).
 *
 * Bo'sh holatda faqat kichik tugma: ko'pchilik odatga vaqt kerak emas va
 * forma ortiqcha maydon bilan og'irlashmasin.
 */
export default function RemindTimeField({
  value,
  onChange,
  templateKey,
}: {
  value: string | null | undefined
  onChange: (v: string | null) => void
  templateKey?: string | null
}) {
  const { t } = useTranslation()

  if (!value) {
    return (
      <button
        type="button"
        onClick={() => onChange(suggest(templateKey))}
        className="flex w-full items-center gap-2 rounded-xl border border-dashed border-line px-3 py-2 text-xs text-muted transition active:scale-[0.98]"
      >
        <span>⏰</span>
        <span className="flex-1 text-left">{t('remind.add')}</span>
        <span className="text-[10px]">{t('remind.optional')}</span>
      </button>
    )
  }

  return (
    <div className="rounded-xl border border-you/40 bg-you/5 px-3 py-2">
      <div className="flex items-center gap-2">
        <span>⏰</span>
        <span className="flex-1 text-xs">{t('remind.label')}</span>
        <input
          type="time"
          value={value}
          onChange={(e) => onChange(e.target.value || null)}
          className="tabular rounded-lg border border-line bg-surface-2 px-2 py-1 font-mono text-sm text-you outline-none focus:border-you"
        />
        <button
          type="button"
          onClick={() => onChange(null)}
          aria-label={t('remind.remove')}
          className="grid h-7 w-7 place-items-center rounded-full text-muted"
        >
          ✕
        </button>
      </div>
      <p className="mt-1 text-[11px] text-muted">{t('remind.hint', { time: value })}</p>
    </div>
  )
}
