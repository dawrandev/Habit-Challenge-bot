import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import { useCreateBattle, type ChallengeInput, type StakeTexts } from '../lib/api'
import { ICON_SET, TEMPLATES } from '../lib/challenges'
import { useInviteLink } from '../lib/invite'
import DateRangeField, {
  type DateRangeValue,
} from '../components/DateRangeField'
import { addDaysISO, daysBetween, todayISO } from '../lib/dates'
import StakeInputs from '../components/StakeInputs'
import RemindTimeField from '../components/RemindTimeField'
import { stakesFilled } from '../lib/stakes'

/** Standart jazo chegarasi — backend `penalty_threshold` bilan bir xil (SPEC §17). */
const PENALTY_THRESHOLD = 50

/**
 * Davr bo'yicha rejadagi odatlar soni — backend hisobining aksi:
 * har kun × har odat, "muayyan kunlar" bo'lsa faqat o'sha hafta kunlari.
 */
function plannedCount(items: ChallengeInput[], start: string, end: string): number {
  const days = daysBetween(start, end)
  let total = 0
  for (let i = 0; i < days; i++) {
    // 0 = Dushanba (backend bilan bir xil)
    const weekday = (new Date(`${addDaysISO(start, i)}T00:00:00`).getDay() + 6) % 7
    for (const c of items) {
      if (c.cadence === 'daily' || c.weekdays.includes(weekday)) total++
    }
  }
  return total
}

export default function NewBattlePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateBattle()

  const [title, setTitle] = useState('')
  // Davr endi aynan sanalar bilan (SPEC §3 "erkin sana"); default — 1 hafta
  const [period, setPeriod] = useState<DateRangeValue>(() => {
    const start = todayISO()
    return { start, end: addDaysISO(start, 6) }
  })
  const [items, setItems] = useState<ChallengeInput[]>([])
  const [stakes, setStakes] = useState<StakeTexts>({ prize: '', penalty: '' })
  const [custom, setCustom] = useState('')
  const [customIcon, setCustomIcon] = useState<string>('📖')
  const [token, setToken] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)

  const weekdays = t('weekdays', { returnObjects: true }) as string[]

  function toggleTemplate(key: string, icon: string) {
    setItems((cur) =>
      cur.some((c) => c.template_key === key)
        ? cur.filter((c) => c.template_key !== key)
        : [...cur, { template_key: key, name: '', icon, cadence: 'daily', weekdays: [] }],
    )
  }

  function makeCustom(name: string): ChallengeInput {
    return {
      template_key: null,
      name,
      icon: customIcon,
      cadence: 'daily',
      weekdays: [],
    }
  }

  function addCustom() {
    const name = custom.trim()
    if (!name) return
    setItems((cur) => [...cur, makeCustom(name)])
    setCustom('')
    setCustomIcon('📖')
  }

  /**
   * Yozilgan-u hali qo'shilmagan matn ham hisobga olinadi — aks holda
   * foydalanuvchi nom yozib "Yaratish"ni bosadi va tugma jim o'chiq turadi.
   */
  const allItems = custom.trim() ? [...items, makeCustom(custom.trim())] : items

  function setCadence(idx: number, cadence: 'daily' | 'weekly_days') {
    setItems((cur) =>
      cur.map((c, i) => (i === idx ? { ...c, cadence } : c)),
    )
  }

  function setDescription(idx: number, description: string) {
    setItems((cur) =>
      cur.map((c, i) => (i === idx ? { ...c, description } : c)),
    )
  }

  function setRemind(idx: number, remind_at: string | null) {
    setItems((cur) => cur.map((c, i) => (i === idx ? { ...c, remind_at } : c)))
  }

  function toggleDay(idx: number, day: number) {
    setItems((cur) =>
      cur.map((c, i) => {
        if (i !== idx) return c
        const has = c.weekdays.includes(day)
        return {
          ...c,
          weekdays: has
            ? c.weekdays.filter((d) => d !== day)
            : [...c.weekdays, day].sort(),
        }
      }),
    )
  }

  const ready = allItems.length > 0 && stakesFilled(stakes)
  const days = daysBetween(period.start, period.end)
  const planned = plannedCount(allItems, period.start, period.end)
  const allDaily = allItems.every((c) => c.cadence === 'daily')

  function submit() {
    if (!ready) return
    create.mutate(
      {
        title: title.trim() || t('create.title'),
        start_date: period.start,
        end_date: period.end,
        challenges: allItems,
        stakes: { prize: stakes.prize.trim(), penalty: stakes.penalty.trim() },
      },
      { onSuccess: (r) => setToken(r.invite_token) },
    )
  }

  const invite = useInviteLink('battle', token ?? undefined)
  const inviteLink = invite.link

  // --- Taklif ekrani ---
  if (token) {
    return (
      <PageTransition>
        <div className="grid flex-1 place-items-center px-6 text-center">
          <motion.div
            initial={{ scale: 0.8, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
          >
            <p className="mb-2 text-5xl">🎉</p>
            <h1 className="mb-2 font-display text-2xl">{t('create.inviteTitle')}</h1>
            <p className="mb-4 text-sm text-muted">{t('create.inviteBody')}</p>
            <div className="mb-3 rounded-2xl border border-line bg-surface px-4 py-3 text-sm break-all text-you">
              {inviteLink}
            </div>
            <button
              type="button"
              onClick={() => {
                navigator.clipboard?.writeText(inviteLink)
                setCopied(true)
              }}
              className="rounded-2xl bg-you px-6 py-3 font-semibold text-bg transition active:scale-95"
            >
              {copied ? t('create.copied') : t('create.copy')}
            </button>
            <div>
              <button
                type="button"
                onClick={() => navigate('/')}
                className="mt-4 text-sm text-muted underline"
              >
                {t('nav.home')}
              </button>
            </div>
          </motion.div>
        </div>
      </PageTransition>
    )
  }

  // --- Forma ---
  return (
    <PageTransition>
      <header className="flex items-center gap-3 px-5 pt-6 pb-4">
        <button
          type="button"
          onClick={() => navigate(-1)}
          className="grid h-9 w-9 place-items-center rounded-full bg-surface text-muted"
        >
          ←
        </button>
        <h1 className="font-display text-2xl">{t('create.title')}</h1>
      </header>

      <div className="space-y-6 px-5">
        {/* Nom */}
        <div>
          <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
            {t('create.battleName')}
          </label>
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder={t('create.battleNamePlaceholder')}
            className="w-full rounded-2xl border border-line bg-surface px-4 py-3 text-text outline-none focus:border-you"
          />
        </div>

        {/* Davr — qachon boshlanadi, qachon tugaydi */}
        <div>
          <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
            {t('create.period')}
          </label>
          <DateRangeField value={period} onChange={setPeriod} />
        </div>

        {/* Challenge tanlash */}
        <div>
          <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
            {t('create.chooseChallenges')}
          </label>
          <div className="mb-3 flex flex-wrap gap-2">
            {TEMPLATES.map((tpl) => {
              const on = items.some((c) => c.template_key === tpl.key)
              return (
                <button
                  key={tpl.key}
                  type="button"
                  onClick={() => toggleTemplate(tpl.key, tpl.icon)}
                  className={`rounded-full border px-3 py-2 text-sm transition ${
                    on
                      ? 'border-you bg-you/10 text-you'
                      : 'border-line bg-surface text-text'
                  }`}
                >
                  {tpl.icon} {t(`tpl.${tpl.key}`)}
                </button>
              )
            })}
          </div>

          {/* Custom qo'shish — icon tanlash */}
          <div className="mb-2 grid grid-cols-8 gap-1.5">
            {ICON_SET.map((em) => (
              <button
                key={em}
                type="button"
                onClick={() => setCustomIcon(em)}
                className={`grid aspect-square place-items-center rounded-xl text-lg transition ${
                  customIcon === em ? 'bg-you/20 ring-2 ring-you' : 'bg-surface-2'
                }`}
              >
                {em}
              </button>
            ))}
          </div>
          <div className="flex gap-2">
            <input
              value={custom}
              onChange={(e) => setCustom(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && addCustom()}
              placeholder={t('create.customPlaceholder')}
              className="flex-1 rounded-2xl border border-line bg-surface px-4 py-2.5 text-sm outline-none focus:border-you"
            />
            <button
              type="button"
              onClick={addCustom}
              disabled={!custom.trim()}
              aria-label={t('questCreate.addHabit')}
              className="flex items-center gap-1 rounded-2xl border border-you/40 bg-you/10 px-3 text-you transition active:scale-95 disabled:border-line disabled:bg-surface disabled:opacity-50"
            >
              <span className="text-lg">{customIcon}</span>
              <span className="text-lg leading-none">＋</span>
            </button>
          </div>
        </div>

        {/* Tanlangan challenge'lar + chastota */}
        {items.map((c, idx) => (
          <div
            key={`${c.template_key ?? c.name}-${idx}`}
            className="rounded-2xl border border-line bg-surface p-4"
          >
            <div className="mb-3 flex items-center gap-2">
              <span className="text-lg">{c.icon}</span>
              <span className="flex-1 text-sm font-medium">
                {c.template_key ? t(`tpl.${c.template_key}`) : c.name}
              </span>
              <button
                type="button"
                onClick={() => setItems((cur) => cur.filter((_, i) => i !== idx))}
                className="text-muted"
              >
                ✕
              </button>
            </div>
            <input
              value={c.description ?? ''}
              onChange={(e) => setDescription(idx, e.target.value)}
              placeholder={t('crud.descPlaceholder')}
              maxLength={200}
              className="mb-3 w-full rounded-xl border border-line bg-surface-2 px-3 py-2 text-xs outline-none focus:border-you"
            />
            <div className="mb-2 flex gap-2">
              <button
                type="button"
                onClick={() => setCadence(idx, 'daily')}
                className={`flex-1 rounded-xl border py-2 text-xs transition ${
                  c.cadence === 'daily'
                    ? 'border-you bg-you/10 text-you'
                    : 'border-line text-muted'
                }`}
              >
                {t('create.everyDay')}
              </button>
              <button
                type="button"
                onClick={() => setCadence(idx, 'weekly_days')}
                className={`flex-1 rounded-xl border py-2 text-xs transition ${
                  c.cadence === 'weekly_days'
                    ? 'border-you bg-you/10 text-you'
                    : 'border-line text-muted'
                }`}
              >
                {t('create.certainDays')}
              </button>
            </div>
            {c.cadence === 'weekly_days' && (
              <div className="flex justify-between gap-1">
                {weekdays.map((wd, day) => (
                  <button
                    key={day}
                    type="button"
                    onClick={() => toggleDay(idx, day)}
                    className={`flex-1 rounded-lg py-1.5 text-[11px] transition ${
                      c.weekdays.includes(day)
                        ? 'bg-you text-bg'
                        : 'bg-surface-2 text-muted'
                    }`}
                  >
                    {wd}
                  </button>
                ))}
              </div>
            )}
            <div className="mt-3">
              <RemindTimeField
                value={c.remind_at}
                onChange={(v) => setRemind(idx, v)}
                templateKey={c.template_key}
              />
            </div>
          </div>
        ))}

        {/* Garov — yutuq va jazo (SPEC §17) */}
        <div>
          <label className="mb-1 block text-xs tracking-[0.18em] text-muted uppercase">
            🤝 {t('stakes.section')}
          </label>
          <p className="mb-3 text-xs text-muted">{t('stakes.sectionHint')}</p>
          <StakeInputs value={stakes} onChange={setStakes} threshold={PENALTY_THRESHOLD} />
          {planned > 0 && (
            <p className="mt-2 rounded-xl bg-reject/5 px-3 py-2 text-xs text-muted">
              ⚡{' '}
              {allDaily
                ? t('stakes.planMath', {
                    habits: allItems.length,
                    days,
                    total: planned,
                    limit: Math.floor((planned * PENALTY_THRESHOLD) / 100),
                  })
                : t('stakes.planTotal', {
                    total: planned,
                    limit: Math.floor((planned * PENALTY_THRESHOLD) / 100),
                  })}
            </p>
          )}
        </div>

        {/* Yaratish */}
        <button
          type="button"
          onClick={submit}
          disabled={!ready || create.isPending}
          className="w-full rounded-2xl bg-you py-4 font-display text-lg text-bg shadow-[0_8px_30px_rgba(246,176,30,0.35)] transition active:scale-[0.98] disabled:opacity-50"
        >
          {create.isPending ? '…' : t('create.create')}
        </button>
        {!ready && (
          <p className="text-center text-xs text-muted">
            {!allItems.length ? t('create.needChallenge') : t('stakes.needStakes')}
          </p>
        )}
      </div>

      <div className="h-6" />
    </PageTransition>
  )
}
