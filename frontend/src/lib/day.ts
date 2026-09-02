import { useQuery } from '@tanstack/react-query'
import { apiFetch } from './api'

/**
 * Ilovaning joriy kuni — serverdan.
 *
 * Kun kalendar bo'yicha emas, `day_start_hour` bo'yicha almashadi: 00:00–04:00
 * oralig'ida telefonda yangi sana turadi, lekin isbot hali KECHAGI kunga
 * yoziladi. Buni qurilma soatidan hisoblab bo'lmaydi (mintaqa serverniki),
 * shuning uchun manba — server.
 */
export type ServerDay = {
  /** Isbot aynan shu kunga yoziladi (YYYY-MM-DD) */
  date: string
  /** Shu kun qachon tugaydi (ISO 8601) */
  ends_at: string
  day_start_hour: number
  /** true = yarim tun o'tgan, lekin kun hali almashmagan */
  grace: boolean
}

export const useServerDay = () =>
  useQuery({
    queryKey: ['day'],
    queryFn: () => apiFetch<ServerDay>('/day'),
    // Kun almashishini payqash uchun — lekin har renderda so'ramaslik uchun
    staleTime: 60_000,
    refetchOnWindowFocus: true,
    refetchInterval: 5 * 60_000,
  })
