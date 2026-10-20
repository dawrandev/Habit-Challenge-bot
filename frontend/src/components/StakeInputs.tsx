import { useTranslation } from 'react-i18next'
import type { StakeKind, StakeTexts } from '../lib/api'
import { STAKE_MAX } from '../lib/stakes'

const TONE: Record<StakeKind, { icon: string; ring: string; chip: string }> = {
  prize: {
    icon: '🏆',
    ring: 'focus:border-you',
    chip: 'border-you bg-you/10 text-you',
  },
  penalty: {
    icon: '⚡',
    ring: 'focus:border-reject',
    chip: 'border-reject bg-reject/10 text-reject',
  },
}

/**
 * Garov kiritish — yutuq va jazo varianti (SPEC §17).
 *
 * Tezkor chiplar maydonni to'ldiradi: ko'pchilik shablondan boshlaydi va
 * keyin o'zgartiradi — bo'sh maydon oldida o'ylanib qolish kerak emas.
 */
export default function StakeInputs({
  value,
  onChange,
  threshold = 50,
}: {
  value: StakeTexts
  onChange: (v: StakeTexts) => void
  threshold?: number
}) {
  const { t } = useTranslation()

  const fields: { kind: StakeKind; q: string; placeholder: string }[] = [
    {
      kind: 'prize',
      q: t('stakes.prizeQ'),
      placeholder: t('stakes.prizePlaceholder'),
    },
    {
      kind: 'penalty',
      q: t('stakes.penaltyQ', { n: threshold }),
      placeholder: t('stakes.penaltyPlaceholder'),
    },
  ]

  return (
    <div className="space-y-3">
      {fields.map(({ kind, q, placeholder }) => {
        const tone = TONE[kind]
        const ideas = t(`stakes.${kind}Ideas`, { returnObjects: true }) as string[]
        const current = value[kind]
        return (
          <div key={kind} className="rounded-2xl border border-line bg-surface p-4">
            <p className="mb-2 flex items-center gap-2 text-sm font-medium">
              <span className="text-lg">{tone.icon}</span>
              {q}
            </p>
            <input
              value={current}
              maxLength={STAKE_MAX}
              onChange={(e) => onChange({ ...value, [kind]: e.target.value })}
              placeholder={placeholder}
              className={`mb-2.5 w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm outline-none ${tone.ring}`}
            />
            <div className="no-scrollbar -mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5">
              {ideas.map((idea) => {
                const on = current === idea
                return (
                  <button
                    key={idea}
                    type="button"
                    onClick={() => onChange({ ...value, [kind]: on ? '' : idea })}
                    className={`shrink-0 rounded-full border px-3 py-1.5 text-xs whitespace-nowrap transition active:scale-95 ${
                      on ? tone.chip : 'border-line bg-surface-2 text-muted'
                    }`}
                  >
                    {idea}
                  </button>
                )
              })}
            </div>
          </div>
        )
      })}
    </div>
  )
}
