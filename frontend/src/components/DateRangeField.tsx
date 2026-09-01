import { useTranslation } from 'react-i18next'
import { addDaysISO, daysBetween, todayISO } from '../lib/dates'

/**
 * Davr tanlash — boshlanish va tugash sanasi.
 *
 * Ilgari davr tayyor variantlardan tanlanardi (1 hafta / 2 hafta / 1 oy) va
 * boshlanishni tanlab bo'lmasdi. Endi asosiy — aynan sanalar; tayyor
 * variantlar faqat tezkor yorliq bo'lib qoldi (tugash sanasini qo'yadi).
 *
 * Native `<input type="date">` ATAYLAB: Telegram webview'da iOS/Android
 * o'zining kalendarini ochadi, til va hafta boshi tizimdan keladi. O'z
 * kalendarimizni yozish shu tekin narsalarni qo'ldan chiqarardi.
 */
const MAX_DAYS = 365

export type DateRangeValue = { start: string; end: string }

const PRESETS = [
  { days: 7, key: 'week1' },
  { days: 14, key: 'week2' },
  { days: 30, key: 'month1' },
]

export default function DateRangeField({
  value,
  onChange,
}: {
  value: DateRangeValue
  onChange: (next: DateRangeValue) => void
}) {
  const { t } = useTranslation()
  const today = todayISO()

  const span = daysBetween(value.start, value.end)
  const invalid = span < 1
  const tooLong = span > MAX_DAYS

  function setStart(start: string) {
    if (!start) return
    // Tugash sanasi orqada qolib ketmasin — davomiylikni saqlab suramiz
    const keep = Math.max(1, span)
    const end =
      value.end < start ? addDaysISO(start, keep - 1) : value.end
    onChange({ start, end })
  }

  function setEnd(end: string) {
    if (!end) return
    onChange({ ...value, end })
  }

  function applyPreset(days: number) {
    onChange({ start: value.start, end: addDaysISO(value.start, days - 1) })
  }

  const field =
    'w-full rounded-2xl border bg-surface px-4 py-3 text-text outline-none focus:border-you [color-scheme:dark]'

  return (
    <div>
      <div className="mb-2 flex gap-2">
        <label className="flex-1">
          <span className="mb-1.5 block text-[10px] tracking-[0.16em] text-muted uppercase">
            {t('period.start')}
          </span>
          <input
            type="date"
            value={value.start}
            min={today}
            onChange={(e) => setStart(e.target.value)}
            className={`${field} border-line`}
          />
        </label>

        <label className="flex-1">
          <span className="mb-1.5 block text-[10px] tracking-[0.16em] text-muted uppercase">
            {t('period.end')}
          </span>
          <input
            type="date"
            value={value.end}
            min={value.start}
            onChange={(e) => setEnd(e.target.value)}
            className={`${field} ${
              invalid || tooLong ? 'border-reject' : 'border-line'
            }`}
          />
        </label>
      </div>

      {/* Tezkor yorliqlar — tugash sanasini boshlanishdan hisoblab qo'yadi */}
      <div className="mb-2 flex items-center gap-1.5">
        {PRESETS.map((p) => {
          const active = span === p.days
          return (
            <button
              key={p.days}
              type="button"
              onClick={() => applyPreset(p.days)}
              className={`flex-1 rounded-xl border py-1.5 text-xs transition active:scale-95 ${
                active
                  ? 'border-you bg-you/10 text-you'
                  : 'border-line bg-surface text-muted'
              }`}
            >
              {t(`create.${p.key}`)}
            </button>
          )
        })}
      </div>

      {/* Hisoblangan davomiylik — tanlov natijasi doim ko'rinib tursin */}
      <p
        className={`tabular text-center font-mono text-xs ${
          invalid || tooLong ? 'text-reject' : 'text-muted'
        }`}
      >
        {invalid
          ? t('period.invalid')
          : tooLong
            ? t('period.tooLong', { max: MAX_DAYS })
            : t('period.length', { n: span })}
      </p>
    </div>
  )
}
