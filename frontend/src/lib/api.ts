import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query'
import { getInitData } from './telegram'

const DEV_TELEGRAM_ID = 111 // dev rejim (Telegram tashqarisida)

function authHeaders(): Record<string, string> {
  const initData = getInitData()
  if (initData) return { 'X-Telegram-Init-Data': initData }
  return { 'X-Dev-Telegram-Id': String(DEV_TELEGRAM_ID) }
}

/** Isbot rasmi proxy (bot token oshkor bo'lmaydi). Dev placeholder'lar uchun null. */
export function photoUrl(fileId?: string | null): string | null {
  if (!fileId || fileId.startsWith('dev-')) return null
  return `/api/photo/${encodeURIComponent(fileId)}`
}

export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
  const isForm = options.body instanceof FormData
  const res = await fetch(`/api${path}`, {
    ...options,
    headers: {
      ...(isForm ? {} : { 'Content-Type': 'application/json' }),
      ...authHeaders(),
      ...(options.headers as Record<string, string>),
    },
  })
  if (!res.ok) {
    throw new Error(`${res.status}: ${await res.text()}`)
  }
  return res.status === 204 ? (undefined as T) : res.json()
}

// ---- Types (backend bilan mos) --------------------------------------------
export type User = {
  id: number
  telegram_id: number
  username: string | null
  first_name: string
  photo_url: string | null
  language: string
}

export type Battle = {
  id: number
  title: string
  status: string
  period_days: number
  start_date: string
  end_date: string
  created_by: number
  winner_id: number | null
  invite_token: string
  /** Jazo chegarasi, % — shu yoki undan kam bajargan jazolanadi (SPEC §17) */
  penalty_threshold?: number
}

export type ProofType = 'camera' | 'screenshot'

export type Challenge = {
  id: number
  battle_id: number
  template_key: string | null
  name: string
  description?: string | null
  icon: string
  cadence: string
  weekdays: number[]
  start_date: string
  pending?: boolean
  proposed_by?: number | null
  proof_type?: ProofType
  /** Odat vaqti 'HH:MM' — shu paytda bot eslatadi (SPEC §18) */
  remind_at?: string | null
}

/** Jazo chizig'iga nisbatan progress — faqat battle detalida (SPEC §17). */
export type HabitProgress = {
  done: number
  /** Butun davr bo'yicha rejadagi odatlar soni */
  expected: number
  /** Jazodan qutulish uchun eng kamida shuncha bajarish kerak */
  safe_at: number
  /** Faqat duel tugagach: true — jazoga tortildi */
  penalized: boolean | null
}

export type PlayerScore = {
  user: User
  score: number
  breakdown?: Record<string, number>
  habits?: HabitProgress
  is_me: boolean
}

// ---- Garov: yutuq va jazo (SPEC §17) ---------------------------------------
export type StakeKind = 'prize' | 'penalty'

export type StakeOption = {
  id: number
  text: string
  proposed_by: number
  is_mine: boolean
}

export type StakeSide = {
  /** O'zingniki doim birinchi */
  options: StakeOption[]
  my_vote: number | null
  rival_vote: number | null
  chosen: {
    id: number
    text: string
    proposed_by: number
    via: 'agreement' | 'coin'
  } | null
}

export type StakesState = {
  threshold: number
  prize: StakeSide
  penalty: StakeSide
}

export type StakeTexts = { prize: string; penalty: string }

/** Yakuniy natija — faqat duel tugagach to'ldiriladi. */
export type BattleResult = {
  winner: User | null
  is_draw: boolean
  you_won: boolean
  prize?: string | null
  penalty?: string | null
}

export type BattleListItem = {
  battle: Battle
  players: PlayerScore[]
  result: BattleResult | null
  /** Kelishilgan yutuq (bo'lsa) */
  prize?: string | null
  days_left: number
  hours_left: number
  late_day?: boolean
}

export type BattleDetail = {
  battle: Battle
  players: PlayerScore[]
  challenges: Challenge[]
  /** Garovsiz (eski) duelda — null */
  stakes: StakesState | null
  result: BattleResult | null
  days_left: number
  hours_left: number
  /** Davr tugagan, lekin kechagi hisobotlar uchun hali ochiq (SPEC §18) */
  late_day?: boolean
}

export type TodayTask = {
  challenge: Challenge
  status: string | null
  completion_id: number | null
}

export type ChatMsg = {
  id: number
  battle_id: number
  sender_id: number | null
  kind: string
  text: string
  created_at: string
}

export type QueueItem = {
  completion: {
    id: number
    day: string
    status: string
    file_id: string | null
    note: string | null
    created_at: string | null
  }
  challenge: Challenge
  rival: User
  /** Isbot qaysi konteynerdan keldi — duel yoki missiya */
  context: { key: 'battle' | 'quest'; id: number; title: string }
}

// ---- Queries --------------------------------------------------------------
export const useMe = () =>
  useQuery({ queryKey: ['me'], queryFn: () => apiFetch<User>('/me') })

export const useBattles = () =>
  useQuery({
    queryKey: ['battles'],
    queryFn: () => apiFetch<BattleListItem[]>('/battles'),
  })

export const useBattle = (id: number | string) =>
  useQuery({
    queryKey: ['battle', String(id)],
    queryFn: () => apiFetch<BattleDetail>(`/battles/${id}`),
  })

export const useToday = (id: number | string) =>
  useQuery({
    queryKey: ['today', String(id)],
    queryFn: () => apiFetch<TodayTask[]>(`/battles/${id}/today`),
  })

/** Kecha bajarib, yuborishni unutganlar — bugun ham yuborsa bo'ladi (SPEC §18). */
export type LateTask = {
  context: { key: 'battle' | 'quest'; id: number; title: string }
  challenge: Challenge
  day: string
  status: string | null
  completion_id: number | null
}

export const useLateTasks = () =>
  useQuery({
    queryKey: ['late-tasks'],
    queryFn: () => apiFetch<LateTask[]>('/late-tasks'),
  })

export const useVerifyQueue = () =>
  useQuery({
    queryKey: ['verify-queue'],
    queryFn: () => apiFetch<QueueItem[]>('/verify-queue'),
  })

export const useMessages = (id: number | string, enabled = true) =>
  useQuery({
    queryKey: ['messages', String(id)],
    queryFn: () => apiFetch<ChatMsg[]>(`/battles/${id}/messages`),
    enabled,
  })

// ---- Mutations ------------------------------------------------------------
export function useSubmitProof(battleId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { challengeId: number; blob: Blob; note?: string; day?: string }) => {
      const fd = new FormData()
      fd.append('challenge_id', String(vars.challengeId))
      fd.append('file', vars.blob, 'proof.jpg')
      if (vars.note?.trim()) fd.append('note', vars.note.trim())
      // Kechagi kun uchun (SPEC §18); yo'q bo'lsa — bugun
      if (vars.day) fd.append('day', vars.day)
      return apiFetch('/completions', { method: 'POST', body: fd })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['today', String(battleId)] })
      qc.invalidateQueries({ queryKey: ['battle', String(battleId)] })
      qc.invalidateQueries({ queryKey: ['late-tasks'] })
    },
  })
}

export function useVerify() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { completionId: number; approve: boolean }) =>
      apiFetch(`/completions/${vars.completionId}/verify`, {
        method: 'POST',
        body: JSON.stringify({ approve: vars.approve }),
      }),
    onSuccess: () => {
      // Tasdiqlangach ball o'zgaradi — barcha bog'liq ko'rinishlarni yangilash
      qc.invalidateQueries({ queryKey: ['verify-queue'] })
      qc.invalidateQueries({ queryKey: ['battles'] })
      qc.invalidateQueries({ queryKey: ['battle'] })
      qc.invalidateQueries({ queryKey: ['today'] })
    },
  })
}

export function useSendMessage(battleId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (text: string) =>
      apiFetch(`/battles/${battleId}/messages`, {
        method: 'POST',
        body: JSON.stringify({ text }),
      }),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ['messages', String(battleId)] }),
  })
}

export function useSeed() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => apiFetch<{ battle_id: number }>('/dev/seed', { method: 'POST' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['battles'] }),
  })
}

export type ChallengeInput = {
  template_key: string | null
  name: string
  description?: string | null
  icon: string
  cadence: 'daily' | 'weekly_days'
  weekdays: number[]
  proof_type?: ProofType
  start_tomorrow?: boolean
  remind_at?: string | null
}

export function useCreateBattle() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: {
      title: string
      /** Davr aynan sanalar bilan — 'YYYY-MM-DD' (SPEC §3 "erkin sana") */
      start_date: string
      end_date: string
      challenges: ChallengeInput[]
      stakes: StakeTexts
    }) =>
      apiFetch<{ battle: Battle; invite_token: string }>('/battles', {
        method: 'POST',
        body: JSON.stringify(vars),
      }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['battles'] }),
  })
}

export type InvitePreview = {
  battle: Battle
  challenges: Challenge[]
  creator: User | null
  already_participant: boolean
  /** Yaratuvchining variantlari; garovsiz duelda — null */
  stakes: { threshold: number; prize: string | null; penalty: string | null } | null
}

export const useInvite = (token: string) =>
  useQuery({
    queryKey: ['invite', token],
    queryFn: () => apiFetch<InvitePreview>(`/battles/invite/${token}`),
    retry: false,
  })

export function useAcceptInvite() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { token: string; stakes?: StakeTexts }) =>
      apiFetch<{ ok: boolean; battle_id: number }>(`/battles/${vars.token}/accept`, {
        method: 'POST',
        body: JSON.stringify({ stakes: vars.stakes ?? null }),
      }),
    onSuccess: () => {
      // Qo'shilgach: battle aktiv bo'ladi, ishtirokchi qo'shiladi, taklif holati o'zgaradi
      qc.invalidateQueries({ queryKey: ['battles'] })
      qc.invalidateQueries({ queryKey: ['battle'] })
      qc.invalidateQueries({ queryKey: ['today'] })
      qc.invalidateQueries({ queryKey: ['invite'] })
    },
  })
}

function invalidateBattle(qc: ReturnType<typeof useQueryClient>, battleId: number | string) {
  qc.invalidateQueries({ queryKey: ['battle', String(battleId)] })
  qc.invalidateQueries({ queryKey: ['today', String(battleId)] })
  qc.invalidateQueries({ queryKey: ['battles'] })
}

export function useAddChallenge(battleId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (c: ChallengeInput) =>
      apiFetch(`/battles/${battleId}/challenges`, {
        method: 'POST',
        body: JSON.stringify(c),
      }),
    onSuccess: () => invalidateBattle(qc, battleId),
  })
}

export function useUpdateChallenge(battleId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { id: number; data: ChallengeInput }) =>
      apiFetch(`/battles/${battleId}/challenges/${vars.id}`, {
        method: 'PATCH',
        body: JSON.stringify(vars.data),
      }),
    onSuccess: () => invalidateBattle(qc, battleId),
  })
}

export function useDeleteChallenge(battleId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      apiFetch(`/battles/${battleId}/challenges/${id}`, { method: 'DELETE' }),
    onSuccess: () => invalidateBattle(qc, battleId),
  })
}

export function useRespondChallenge(battleId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { id: number; accept: boolean }) =>
      apiFetch(
        `/battles/${battleId}/challenges/${vars.id}/${vars.accept ? 'accept' : 'reject'}`,
        { method: 'POST' },
      ),
    onSuccess: () => invalidateBattle(qc, battleId),
  })
}

export function useUpdateBattle(battleId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (title: string) =>
      apiFetch(`/battles/${battleId}`, {
        method: 'PATCH',
        body: JSON.stringify({ title }),
      }),
    onSuccess: () => invalidateBattle(qc, battleId),
  })
}

export function useDeleteBattle() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (battleId: number | string) =>
      apiFetch(`/battles/${battleId}`, { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['battles'] }),
  })
}

export function useDisputeMut() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (completionId: number) =>
      apiFetch(`/completions/${completionId}/dispute`, { method: 'POST' }),
    onSuccess: () => {
      // E'tiroz: rad etilgan hisobot qayta pending bo'ladi — o'z chip'i va raqib navbati o'zgaradi
      qc.invalidateQueries({ queryKey: ['verify-queue'] })
      qc.invalidateQueries({ queryKey: ['today'] })
      qc.invalidateQueries({ queryKey: ['battle'] })
    },
  })
}

// ---- Garov mutatsiyalari (SPEC §17) ------------------------------------------
type StakesResponse = { status: string; stakes: StakesState | null; flipped?: number }

function useStakeMutation<V>(
  battleId: number | string,
  request: (vars: V) => Promise<StakesResponse>,
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: request,
    onSuccess: (r) => {
      // Javobni darhol keshga yozamiz — ovoz bosilganda karta kutib turmasin
      qc.setQueryData<BattleDetail>(['battle', String(battleId)], (old) =>
        old
          ? { ...old, stakes: r.stakes, battle: { ...old.battle, status: r.status } }
          : old,
      )
      invalidateBattle(qc, battleId)
    },
  })
}

export const useVoteStake = (battleId: number | string) =>
  useStakeMutation(battleId, (stakeId: number) =>
    apiFetch<StakesResponse>(`/battles/${battleId}/stakes/${stakeId}/vote`, {
      method: 'POST',
    }),
  )

export const useFlipStake = (battleId: number | string) =>
  useStakeMutation(battleId, (kind: StakeKind) =>
    apiFetch<StakesResponse>(`/battles/${battleId}/stakes/${kind}/coin`, {
      method: 'POST',
    }),
  )

export const useEditStake = (battleId: number | string) =>
  useStakeMutation(battleId, (vars: { kind: StakeKind; text: string }) =>
    apiFetch<StakesResponse>(`/battles/${battleId}/stakes/${vars.kind}`, {
      method: 'PATCH',
      body: JSON.stringify({ text: vars.text }),
    }),
  )
