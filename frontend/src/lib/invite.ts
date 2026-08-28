import { useQuery } from '@tanstack/react-query'
import { apiFetch } from './api'

/**
 * Taklif havolasi uchun bot username.
 *
 * ILGARI build vaqtida `VITE_BOT_USERNAME` orqali qotirilardi — `npm run build`
 * ni flagsiz bajarish barcha havolalarni jimgina `t.me/YourBot` ga yuborardi.
 * Endi qiymat backend .env dan ish vaqtida keladi.
 */
export type AppConfig = { bot_username: string }

export const useAppConfig = () =>
  useQuery({
    queryKey: ['config'],
    queryFn: () => apiFetch<AppConfig>('/config'),
    staleTime: Infinity,
    gcTime: Infinity,
    retry: 1,
  })

/** Taklif turi: duelga raqib yoki missiyaga guvoh. */
export type InviteKind = 'battle' | 'quest'

export function buildInviteLink(
  botUsername: string | undefined,
  kind: InviteKind,
  token: string,
): string {
  const bot = (botUsername || '').replace(/^@/, '').trim()
  if (!bot) return ''
  return `https://t.me/${bot}?start=${kind}_${token}`
}

/**
 * Tayyor havola + hali yuklanmagan holat.
 * Havola bo'sh bo'lsa UI nusxalash tugmasini o'chirib qo'yishi kerak —
 * ishlamaydigan havolani nusxalagandan ko'ra kutgan yaxshi.
 */
export function useInviteLink(kind: InviteKind, token: string | undefined) {
  const { data, isLoading, isError } = useAppConfig()
  const link = token ? buildInviteLink(data?.bot_username, kind, token) : ''
  return { link, ready: link !== '', isLoading, isError }
}
