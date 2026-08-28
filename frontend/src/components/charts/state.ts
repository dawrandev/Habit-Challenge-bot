import type { CellState } from '../../lib/quests'

/**
 * Tracker holatlarining vizual ta'rifi.
 *
 * ⚠️ RANG YOLG'IZ MA'NO TASHIMAYDI. `--color-approve` (#3FD07A) va
 * `--color-reject` (#FF5A5F) deuteranopiyada ΔE ≈ 6.2 — qizil/yashil ko'r
 * odam ularni ajrata olmaydi. Shuning uchun har katak GLIF ham oladi va
 * har chart yonida matnli legenda turadi. Rang — ikkilamchi kanal.
 */
export type StateSpec = {
  /** Ikkilamchi kodlash — rangdan mustaqil o'qiladi */
  glyph: string
  fill: string
  ink: string
  /** i18n kaliti (legenda va tooltip uchun) */
  key: string
}

export const STATE_SPEC: Record<CellState, StateSpec> = {
  done: {
    glyph: '✓',
    fill: 'color-mix(in oklab, var(--color-approve) 22%, transparent)',
    ink: 'var(--color-approve)',
    key: 'done',
  },
  partial: {
    glyph: '◐',
    fill: 'color-mix(in oklab, var(--color-you) 20%, transparent)',
    ink: 'var(--color-you)',
    key: 'partial',
  },
  missed: {
    glyph: '✕',
    fill: 'color-mix(in oklab, var(--color-reject) 20%, transparent)',
    ink: 'var(--color-reject)',
    key: 'missed',
  },
  pending: {
    glyph: '•',
    fill: 'color-mix(in oklab, var(--color-you) 12%, transparent)',
    ink: 'var(--color-you)',
    key: 'pending',
  },
  rest: {
    glyph: '·',
    fill: 'var(--color-surface-2)',
    ink: 'var(--color-muted)',
    key: 'rest',
  },
  future: {
    glyph: '',
    fill: 'transparent',
    ink: 'var(--color-line)',
    key: 'future',
  },
}

/** Legendada ko'rsatiladigan holatlar (kelajak — ma'lumot emas, chiqmaydi). */
export const LEGEND_STATES: CellState[] = [
  'done',
  'partial',
  'missed',
  'pending',
  'rest',
]

/** 12.0 → "12", 66.6 → "66.6" */
export function pct(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(1)
}

/** '2026-08-28' → '28.08' */
export function shortDate(iso: string): string {
  const [, m, d] = iso.split('-')
  return `${d}.${m}`
}
