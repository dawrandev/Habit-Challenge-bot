import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { Challenge } from '../../lib/api'
import type { QuestChallengeStat, QuestDay } from '../../lib/quests'
import { LEGEND_STATES, STATE_SPEC, shortDate } from './state'

/**
 * Tracker to'ri — challenge × kun (SPEC §8).
 *
 * Har katak GLIF oladi (✓ ◐ ✕ • ·), rang faqat kuchaytiruvchi. Qizil/yashil
 * juftlik CVD'da ajralmaydi (ΔE 6.2), shuning uchun rangga tayanmaymiz.
 * Yonida matnli legenda va to'liq jadval ko'rinishi ham bor.
 *
 * Kataklar orasida 2px sirt bo'shlig'i — chegara chizilmaydi.
 */
const CELL = 22
/* Qator yorlig'i chapda YOPISHIB turadi — to'r gorizontal siljiganda ham
   qaysi qator qaysi odat ekani ko'rinib tursin. */
const LABEL_W = 76

export default function TrackerGrid({
  days,
  stats,
  challenges,
}: {
  days: QuestDay[]
  stats: QuestChallengeStat[]
  challenges: Challenge[]
}) {
  const { t } = useTranslation()
  const [tip, setTip] = useState<{ row: number; col: number } | null>(null)
  const [table, setTable] = useState(false)

  const byId = new Map(challenges.map((c) => [c.id, c]))
  const nameOf = (c?: Challenge) =>
    !c ? '—' : c.template_key ? t(`tpl.${c.template_key}`) : c.name || '—'

  const rows = stats
    .map((s) => ({ stat: s, challenge: byId.get(s.challenge_id) }))
    .filter((r) => r.challenge !== undefined)

  if (rows.length === 0 || days.length === 0) return null

  const active = tip
    ? {
        day: days[tip.col],
        row: rows[tip.row],
        state: rows[tip.row].stat.cells[tip.col],
      }
    : null

  return (
    <div>
      {/* To'r — uzun davrda gorizontal siljiydi, sahifa emas */}
      <div className="-mx-1 overflow-x-auto px-1 pb-1">
        <div className="inline-flex flex-col gap-[2px]">
          {/* Sana o'qi — har 7-kunda belgi (har kunga yozuv xaos bo'lardi) */}
          <div className="flex gap-[2px]" style={{ marginBottom: 2 }}>
            <div
              className="sticky left-0 z-10 shrink-0 bg-surface"
              style={{ width: LABEL_W }}
            />
            {days.map((d, i) => (
              <div
                key={d.date}
                className="tabular text-center font-mono text-[8px] text-muted"
                style={{ width: CELL }}
              >
                {i % 7 === 0 ? shortDate(d.date) : ''}
              </div>
            ))}
          </div>

          {rows.map((row, ri) => (
            <div key={row.stat.challenge_id} className="flex items-center gap-[2px]">
              {/* Yopishqoq yorliq — kataklar uning ostidan siljiydi */}
              <div
                className="sticky left-0 z-10 flex shrink-0 items-center gap-1 bg-surface pr-1.5"
                style={{ width: LABEL_W, height: CELL }}
              >
                <span className="shrink-0 text-xs">{row.challenge?.icon}</span>
                <span className="truncate text-[10px] text-muted">
                  {nameOf(row.challenge)}
                </span>
              </div>
              {row.stat.cells.map((cell, ci) => {
                const spec = STATE_SPEC[cell]
                const isTip = tip?.row === ri && tip?.col === ci
                return (
                  <button
                    key={days[ci]?.date ?? ci}
                    type="button"
                    onClick={() => setTip(isTip ? null : { row: ri, col: ci })}
                    aria-label={`${nameOf(row.challenge)} · ${days[ci]?.date} · ${t(`quest.state.${spec.key}`)}`}
                    className="cell-pop grid shrink-0 place-items-center rounded-[6px] font-mono leading-none transition active:scale-90"
                    style={{
                      width: CELL,
                      height: CELL,
                      fontSize: 11,
                      background: spec.fill,
                      color: spec.ink,
                      animationDelay: `${Math.min(ci * 12 + ri * 40, 700)}ms`,
                      outline: isTip ? '2px solid var(--color-text)' : undefined,
                      outlineOffset: isTip ? 1 : undefined,
                      border:
                        cell === 'future'
                          ? '1px solid var(--color-line)'
                          : undefined,
                    }}
                  >
                    {spec.glyph}
                  </button>
                )
              })}
            </div>
          ))}
        </div>
      </div>

      {/* Tanlangan katak tafsiloti — mobil "hover" o'rniga bosish */}
      <div className="mt-2 min-h-[34px]">
        {active ? (
          <div className="flex items-center gap-2 rounded-xl border border-line bg-surface-2 px-3 py-2 text-xs">
            <span style={{ color: STATE_SPEC[active.state].ink }}>
              {STATE_SPEC[active.state].glyph || '—'}
            </span>
            <span className="tabular font-mono text-muted">
              {active.day.date}
            </span>
            <span className="truncate">{nameOf(active.row.challenge)}</span>
            <span className="ml-auto shrink-0 text-muted">
              {t(`quest.state.${STATE_SPEC[active.state].key}`)}
            </span>
          </div>
        ) : (
          <p className="px-1 text-[11px] text-muted">{t('quest.tapCell')}</p>
        )}
      </div>

      {/* Legenda — identifikatsiya hech qachon faqat rangda emas */}
      <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
        {LEGEND_STATES.map((s) => (
          <span key={s} className="flex items-center gap-1.5 text-[11px] text-muted">
            <span
              className="grid h-4 w-4 place-items-center rounded-[4px] font-mono text-[10px] leading-none"
              style={{ background: STATE_SPEC[s].fill, color: STATE_SPEC[s].ink }}
            >
              {STATE_SPEC[s].glyph}
            </span>
            {t(`quest.state.${s}`)}
          </span>
        ))}

        <button
          type="button"
          onClick={() => setTable((v) => !v)}
          className="ml-auto rounded-full border border-line px-2.5 py-1 text-[11px] text-muted transition active:scale-95"
        >
          {table ? '▲' : '▤'} {t('quest.tableView')}
        </button>
      </div>

      {/* Jadval ko'rinishi — a11y: ma'lumot hech qachon chart ortida qulflanmaydi */}
      {table && (
        <div className="mt-2 max-h-64 overflow-auto rounded-xl border border-line">
          <table className="w-full text-left text-xs">
            <thead className="sticky top-0 bg-surface-2 text-muted">
              <tr>
                <th className="px-2 py-1.5 font-medium">{t('quest.thChallenge')}</th>
                <th className="px-2 py-1.5 font-medium">✓</th>
                <th className="px-2 py-1.5 font-medium">✕</th>
                <th className="px-2 py-1.5 font-medium">%</th>
                <th className="px-2 py-1.5 font-medium">🔥</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.stat.challenge_id} className="border-t border-line">
                  <td className="px-2 py-1.5">
                    {row.challenge?.icon} {nameOf(row.challenge)}
                  </td>
                  <td className="tabular px-2 py-1.5 font-mono">{row.stat.done}</td>
                  <td className="tabular px-2 py-1.5 font-mono">{row.stat.missed}</td>
                  <td className="tabular px-2 py-1.5 font-mono">{row.stat.rate}</td>
                  <td className="tabular px-2 py-1.5 font-mono">
                    {row.stat.current_streak}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
