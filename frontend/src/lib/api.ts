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
}

export type PlayerScore = {
  user: User
  score: number
  breakdown?: Record<string, number>
  is_me: boolean
}

/** Yakuniy natija — faqat duel tugagach to'ldiriladi. */
export type BattleResult = {
  winner: User | null
  is_draw: boolean
  you_won: boolean
}

export type BattleListItem = {
  battle: Battle
  players: PlayerScore[]
  result: BattleResult | null
  days_left: number
  hours_left: number
}

export type BattleDetail = {
  battle: Battle
  players: PlayerScore[]
  challenges: Challenge[]
  result: BattleResult | null
  days_left: number
  hours_left: number
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
    mutationFn: (vars: { challengeId: number; blob: Blob; note?: string }) => {
      const fd = new FormData()
      fd.append('challenge_id', String(vars.challengeId))
      fd.append('file', vars.blob, 'proof.jpg')
      if (vars.note?.trim()) fd.append('note', vars.note.trim())
      return apiFetch('/completions', { method: 'POST', body: fd })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['today', String(battleId)] })
      qc.invalidateQueries({ queryKey: ['battle', String(battleId)] })
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
    mutationFn: (token: string) =>
      apiFetch<{ ok: boolean; battle_id: number }>(`/battles/${token}/accept`, {
        method: 'POST',
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
