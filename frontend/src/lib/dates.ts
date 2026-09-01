/**
 * Sana yordamchilari — 'YYYY-MM-DD' satrlari bilan ishlaydi.
 *
 * ATAYLAB satr: backend ham aynan shu formatni kutadi va `Date` obyektini
 * uzatish vaqt mintaqasi tufayli kunni bir kunga surib yuborishi mumkin.
 */

/** Qurilma mintaqasidagi bugungi sana. */
export function todayISO(): string {
  return toISO(new Date())
}

export function addDaysISO(iso: string, days: number): string {
  const d = new Date(`${iso}T00:00:00`)
  d.setDate(d.getDate() + days)
  return toISO(d)
}

/** Inklyuziv kunlar soni: 01→07 = 7 kun. */
export function daysBetween(start: string, end: string): number {
  const a = new Date(`${start}T00:00:00`).getTime()
  const b = new Date(`${end}T00:00:00`).getTime()
  return Math.floor((b - a) / 86_400_000) + 1
}

/** UTC'ga o'tkazmasdan mahalliy kunni oladi. */
function toISO(d: Date): string {
  const local = new Date(d.getTime() - d.getTimezoneOffset() * 60_000)
  return local.toISOString().slice(0, 10)
}
