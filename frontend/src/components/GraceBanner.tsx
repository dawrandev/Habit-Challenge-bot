import { useTranslation } from 'react-i18next'
import { useServerDay } from '../lib/day'

/**
 * "Cho'zilgan kecha" ogohlantirishi.
 *
 * Yarim tun o'tgan, lekin ilova kuni hali almashmagan paytda ko'rinadi.
 * Busiz foydalanuvchi telefonidagi yangi sanani ko'rib, isboti noto'g'ri
 * kunga ketdi deb o'ylaydi — aynan shunday shikoyat bo'lgan.
 *
 * Oddiy soatlarda umuman ko'rinmaydi: kun aniq bo'lganda ogohlantirish
 * shovqin bo'lardi.
 */
export default function GraceBanner() {
  const { t, i18n } = useTranslation()
  const { data } = useServerDay()

  if (!data?.grace) return null

  const date = new Date(`${data.date}T12:00:00`).toLocaleDateString(i18n.language, {
    day: 'numeric',
    month: 'long',
  })

  const until = String(data.day_start_hour).padStart(2, '0') + ':00'

  return (
    <div className="mb-2 flex items-start gap-2.5 rounded-2xl border border-you/30 bg-you/10 px-4 py-2.5">
      <span className="shrink-0 text-base leading-tight">🌙</span>
      <div className="min-w-0">
        <p className="text-sm text-you">{t('grace.title', { date })}</p>
        <p className="text-[11px] text-muted">{t('grace.hint', { time: until })}</p>
      </div>
    </div>
  )
}
