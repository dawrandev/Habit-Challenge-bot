/**
 * 🎯 MISSIYA (Quest) — yakka odat yo'li, guvoh bilan.
 *
 * Duelda (api.ts) challenge ikkala o'yinchiga tegishli va ular bir-birini
 * tekshiradi. Missiyada odat FAQAT eganiki: guvoh uni bajarmaydi, faqat
 * isbotni tekshiradi. Shuning uchun alohida modul.
 *
 * Isbot yuborish/tekshirish/nizo esa UMUMIY (/completions) — backend'dagi
 * ProofContext kim nima qila olishini hal qiladi.
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  apiFetch,
  type Challenge,
  type ChallengeInput,
  type ChatMsg,
  type TodayTask,
  type User,
} from './api'

export type QuestRole = 'owner' | 'witness'
export type QuestStatusValue = 'active' | 'finished' | 'abandoned'
export type QuestOutcome = 'achieved' | 'missed'

/**
 * Tracker katagi holati.
 *
 * A11Y: rang YOLG'IZ ma'no tashimaydi. `approve` yashil va `reject` qizil
 * deuteranopiyada ΔE ≈ 6.2 — ajratib bo'lmaydi. Har katak glif ham oladi.
 */
export type CellState =
  | 'done'
  | 'partial'
  | 'missed'
  | 'pending'
  | 'rest'
  | 'future'

export type Quest = {
  id: number
  title: string
  status: QuestStatusValue
  owner_id: number
  witness_id: number | null
  period_days: number
  start_date: string
  end_date: string
  goal_percent: number
  outcome: QuestOutcome | null
  invite_token: string
}

export type QuestDay = {
  date: string
  due: number
  done: number
  state: CellState
}

export type QuestChallengeStat = {
  challenge_id: number
  done: number
  missed: number
  pending: number
  rate: number
  current_streak: number
  longest_streak: number
  /** QuestStats.days bilan bir xil uzunlik va tartib — heatmap qatori */
  cells: CellState[]
}

export type QuestStats = {
  done: number
  missed: number
  pending: number
  resolved: number
  planned: number
  rate: number
  ceiling_rate: number
  current_streak: number
  longest_streak: number
  days_elapsed: number
  days_total: number
  goal_percent: number
  goal_reachable: boolean
  outcome: QuestOutcome | null
  days: QuestDay[]
  challenges: QuestChallengeStat[]
}

export type QuestListItem = {
  quest: Quest
  role: QuestRole | null
  owner: User | null
  witness: User | null
  rate: number
  current_streak: number
  done: number
  planned: number
  goal_reachable: boolean
  days_left: number
  hours_left: number
}

export type QuestDetail = {
  quest: Quest
  role: QuestRole | null
  owner: User | null
  witness: User | null
  challenges: Challenge[]
  stats: QuestStats
  days_left: number
  hours_left: number
}

export type QuestInvitePreview = {
  quest: Quest
  challenges: Challenge[]
  owner: User | null
  role: QuestRole | null
  taken: boolean
  is_owner: boolean
}

export type QuestInput = {
  title: string
  period_days: number
  start_tomorrow: boolean
  goal_percent: number
  challenges: ChallengeInput[]
}

// ---- Queries --------------------------------------------------------------

export const useQuests = () =>
  useQuery({
    queryKey: ['quests'],
    queryFn: () => apiFetch<QuestListItem[]>('/quests'),
  })

export const useQuest = (id: number | string) =>
  useQuery({
    queryKey: ['quest', String(id)],
    queryFn: () => apiFetch<QuestDetail>(`/quests/${id}`),
  })

export const useQuestToday = (id: number | string) =>
  useQuery({
    queryKey: ['quest-today', String(id)],
    queryFn: () => apiFetch<TodayTask[]>(`/quests/${id}/today`),
  })

export const useQuestInvite = (token: string) =>
  useQuery({
    queryKey: ['quest-invite', token],
    queryFn: () => apiFetch<QuestInvitePreview>(`/quests/invite/${token}`),
    retry: false,
  })

export const useQuestMessages = (id: number | string, enabled = true) =>
  useQuery({
    queryKey: ['quest-messages', String(id)],
    queryFn: () => apiFetch<ChatMsg[]>(`/quests/${id}/messages`),
    enabled,
  })

// ---- Mutations ------------------------------------------------------------

function invalidateQuest(
  qc: ReturnType<typeof useQueryClient>,
  questId: number | string,
) {
  qc.invalidateQueries({ queryKey: ['quest', String(questId)] })
  qc.invalidateQueries({ queryKey: ['quest-today', String(questId)] })
  qc.invalidateQueries({ queryKey: ['quests'] })
}

export function useCreateQuest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: QuestInput) =>
      apiFetch<{ quest: Quest; invite_token: string }>('/quests', {
        method: 'POST',
        body: JSON.stringify(vars),
      }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['quests'] }),
  })
}

export function useAcceptQuestInvite() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (token: string) =>
      apiFetch<{ ok: boolean; quest_id: number }>(`/quests/${token}/accept`, {
        method: 'POST',
      }),
    onSuccess: () => {
      // Guvoh bo'lgach: missiya ro'yxatga tushadi, tekshiruv navbati ochiladi
      qc.invalidateQueries({ queryKey: ['quests'] })
      qc.invalidateQueries({ queryKey: ['quest'] })
      qc.invalidateQueries({ queryKey: ['quest-invite'] })
      qc.invalidateQueries({ queryKey: ['verify-queue'] })
    },
  })
}

export function useSubmitQuestProof(questId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { challengeId: number; blob: Blob; note?: string }) => {
      const fd = new FormData()
      fd.append('challenge_id', String(vars.challengeId))
      fd.append('file', vars.blob, 'proof.jpg')
      if (vars.note?.trim()) fd.append('note', vars.note.trim())
      return apiFetch('/completions', { method: 'POST', body: fd })
    },
    onSuccess: () => invalidateQuest(qc, questId),
  })
}

export function useUpdateQuest(questId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { title: string; goal_percent?: number }) =>
      apiFetch(`/quests/${questId}`, {
        method: 'PATCH',
        body: JSON.stringify(vars),
      }),
    onSuccess: () => invalidateQuest(qc, questId),
  })
}

export function useDeleteQuest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (questId: number | string) =>
      apiFetch(`/quests/${questId}`, { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['quests'] }),
  })
}

export function useAbandonQuest(questId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => apiFetch(`/quests/${questId}/abandon`, { method: 'POST' }),
    onSuccess: () => invalidateQuest(qc, questId),
  })
}

export function useAddQuestChallenge(questId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (c: ChallengeInput) =>
      apiFetch(`/quests/${questId}/challenges`, {
        method: 'POST',
        body: JSON.stringify(c),
      }),
    onSuccess: () => invalidateQuest(qc, questId),
  })
}

export function useUpdateQuestChallenge(questId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { id: number; data: ChallengeInput }) =>
      apiFetch(`/quests/${questId}/challenges/${vars.id}`, {
        method: 'PATCH',
        body: JSON.stringify(vars.data),
      }),
    onSuccess: () => invalidateQuest(qc, questId),
  })
}

export function useDeleteQuestChallenge(questId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      apiFetch(`/quests/${questId}/challenges/${id}`, { method: 'DELETE' }),
    onSuccess: () => invalidateQuest(qc, questId),
  })
}

export function useSendQuestMessage(questId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (text: string) =>
      apiFetch(`/quests/${questId}/messages`, {
        method: 'POST',
        body: JSON.stringify({ text }),
      }),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ['quest-messages', String(questId)] }),
  })
}
