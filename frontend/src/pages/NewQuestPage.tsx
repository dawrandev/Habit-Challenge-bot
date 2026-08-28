import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'
import PageTransition from '../components/PageTransition'
import { type ChallengeInput } from '../lib/api'
import { useCreateQuest } from '../lib/quests'
import { ICON_SET, TEMPLATES } from '../lib/challenges'
import { useInviteLink } from '../lib/invite'

const PERIODS = [
  { days: 7, key: 'd7' },
  { days: 14, key: 'd14' },
  { days: 30, key: 'd30' },
  { days: 60, key: 'd60' },
]

const GOALS = [50, 70, 80, 90, 100]

export default function NewQuestPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateQuest()

  const [title, setTitle] = useState('')
  const [period, setPeriod] = useState(30)
  const [goal, setGoal] = useState(80)
  const [startTomorrow, setStartTomorrow] = useState(false)
  const [items, setItems] = useState<ChallengeInput[]>([])
  const [custom, setCustom] = useState('')
  const [customIcon, setCustomIcon] = useState<string>('📖')
  const [created, setCreated] = useState<{ id: number; token: string } | null>(
    null,
  )
  const [copied, setCopied] = useState(false)

  // Bot username backend .env dan keladi (build flagidan emas)
  const { link: inviteLink, ready: linkReady } = useInviteLink(
    'quest',
    created?.token,
  )

  const weekdays = t('weekdays', { returnObjects: true }) as string[]

  function toggleTemplate(key: string, icon: string) {
    setItems((cur) =>
      cur.some((c) => c.template_key === key)
        ? cur.filter((c) => c.template_key !== key)
        : [
            ...cur,
            { template_key: key, name: '', icon, cadence: 'daily', weekdays: [] },
          ],
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
   * Yozilgan-u hali qo'shilmagan matn ham hisobga olinadi.
   * Aks holda foydalanuvchi odat nomini yozib "Boshlash"ni bosadi va tugma
   * jim o'chiq turadi — yozgani hech qayerga bormaydi.
   */
  const allItems = custom.trim() ? [...items, makeCustom(custom.trim())] : items

  function setCadence(idx: number, cadence: 'daily' | 'weekly_days') {
    setItems((cur) => cur.map((c, i) => (i === idx ? { ...c, cadence } : c)))
  }

  function setDescription(idx: number, description: string) {
    setItems((cur) => cur.map((c, i) => (i === idx ? { ...c, description } : c)))
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

  function submit() {
    if (!allItems.length) return
    create.mutate(
      {
        title: title.trim() || t('questCreate.title'),
        period_days: period,
        start_tomorrow: startTomorrow,
        goal_percent: goal,
        challenges: allItems,
      },
      {
        onSuccess: (r) =>
          setCreated({ id: r.quest.id, token: r.invite_token }),
      },
    )
  }

  // --- Guvoh chaqirish ekrani ---
  if (created) {
    const link = inviteLink

    return (
      <PageTransition>
        <div className="grid flex-1 place-items-center px-6 text-center">
          <motion.div
            initial={{ scale: 0.85, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
          >
            <p className="mb-2 text-5xl">🎯</p>
            <h1 className="mb-2 font-display text-2xl">
              {t('questCreate.createdTitle')}
            </h1>
            <p className="mb-1 text-sm text-muted">
              {t('questCreate.createdBody')}
            </p>
            <p className="mb-4 text-xs text-muted">{t('quest.witnessHint')}</p>

            <div className="mb-3 rounded-2xl border border-line bg-surface px-4 py-3 text-sm break-all text-witness">
              {linkReady ? link : '…'}
            </div>
            <button
              type="button"
              disabled={!linkReady}
              onClick={() => {
                navigator.clipboard?.writeText(link)
                setCopied(true)
              }}
              className="rounded-2xl bg-witness px-6 py-3 font-semibold text-bg transition active:scale-95 disabled:opacity-50"
            >
              {copied ? t('create.copied') : t('create.copy')}
            </button>
            <div>
              <button
                type="button"
                onClick={() => navigate(`/quest/${created.id}`, { replace: true })}
                className="mt-4 text-sm text-muted underline"
              >
                {t('questCreate.later')}
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
      <header className="flex items-center gap-3 px-5 pt-6 pb-2">
        <button
          type="button"
          onClick={() => navigate(-1)}
          className="grid h-9 w-9 place-items-center rounded-full bg-surface text-muted"
        >
          ←
        </button>
        <h1 className="font-display text-2xl">{t('questCreate.title')}</h1>
      </header>
      <p className="px-5 pb-4 text-sm text-muted">🎯 {t('quest.tagline')}</p>

      <div className="space-y-6 px-5">
        {/* Nom */}
        <div>
          <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
            {t('questCreate.name')}
          </label>
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder={t('questCreate.namePlaceholder')}
            className="w-full rounded-2xl border border-line bg-surface px-4 py-3 text-text outline-none focus:border-you"
          />
        </div>

        {/* Davr */}
        <div>
          <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
            {t('questCreate.period')}
          </label>
          <div className="flex gap-2">
            {PERIODS.map((p) => (
              <button
                key={p.days}
                type="button"
                onClick={() => setPeriod(p.days)}
                className={`flex-1 rounded-2xl border py-3 text-sm transition ${
                  period === p.days
                    ? 'border-you bg-you/10 text-you'
                    : 'border-line bg-surface text-text'
                }`}
              >
                {t(`questCreate.${p.key}`)}
              </button>
            ))}
          </div>
        </div>

        {/* Maqsad foizi */}
        <div>
          <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
            {t('questCreate.goal')}
          </label>
          <div className="mb-2 flex gap-1.5">
            {GOALS.map((g) => (
              <button
                key={g}
                type="button"
                onClick={() => setGoal(g)}
                className={`tabular flex-1 rounded-xl border py-2.5 font-mono text-sm transition ${
                  goal === g
                    ? 'border-you bg-you/10 text-you'
                    : 'border-line bg-surface text-muted'
                }`}
              >
                {g}%
              </button>
            ))}
          </div>
          <p className="text-xs text-muted">{t('questCreate.goalHint')}</p>
        </div>

        {/* Ertadan boshlash */}
        <button
          type="button"
          onClick={() => setStartTomorrow((v) => !v)}
          className="flex w-full items-center justify-between rounded-2xl border border-line bg-surface px-4 py-3"
        >
          <span className="text-sm">{t('create.startTomorrow')}</span>
          <span
            className={`relative h-6 w-11 rounded-full transition ${
              startTomorrow ? 'bg-you' : 'bg-surface-2'
            }`}
          >
            <span
              className={`absolute top-0.5 h-5 w-5 rounded-full bg-white transition-all ${
                startTomorrow ? 'left-[22px]' : 'left-0.5'
              }`}
            />
          </span>
        </button>

        {/* Odatlar */}
        <div>
          <label className="mb-2 block text-xs tracking-[0.18em] text-muted uppercase">
            {t('questCreate.chooseHabits')}
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

        {/* Tanlangan odatlar */}
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
          </div>
        ))}

        <button
          type="button"
          onClick={submit}
          disabled={!allItems.length || create.isPending}
          className="w-full rounded-2xl bg-you py-4 font-display text-lg text-bg shadow-[0_8px_30px_rgba(246,176,30,0.35)] transition active:scale-[0.98] disabled:opacity-50"
        >
          {create.isPending ? '…' : t('questCreate.create')}
        </button>
        {!allItems.length && (
          <p className="text-center text-xs text-muted">
            {t('questCreate.needHabit')}
          </p>
        )}
      </div>

      <div className="h-6" />
    </PageTransition>
  )
}
