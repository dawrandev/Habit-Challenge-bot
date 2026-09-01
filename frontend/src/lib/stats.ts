import { useQuery } from '@tanstack/react-query'
import { apiFetch } from './api'

/** Profil statistikasi — duel va missiya bo'yicha butun tarix. */
export type ProfileStats = {
  battles: {
    total: number
    active: number
    finished: number
    won: number
    draw: number
    lost: number
  }
  quests: {
    total: number
    active: number
    achieved: number
    missed: number
    witnessing: number
  }
  proofs: { approved: number; rejected: number; pending: number }
  /** Tekshiruvchi sifatida: javob berganing / 24s o'tib avtomatik yopilgani */
  verification: { answered: number; expired: number }
  activity: { date: string; done: number }[]
}

export const useStats = () =>
  useQuery({
    queryKey: ['stats'],
    queryFn: () => apiFetch<ProfileStats>('/stats'),
  })
