/* Telegram Mini App SDK yordamchilari */

type TgWebApp = {
  initData: string
  initDataUnsafe?: {
    user?: { id?: number; language_code?: string }
    start_param?: string
  }
  ready: () => void
  expand: () => void
  colorScheme?: string
}

function tg(): TgWebApp | undefined {
  return (window as unknown as { Telegram?: { WebApp?: TgWebApp } }).Telegram
    ?.WebApp
}

export function initTelegram() {
  const wa = tg()
  if (wa) {
    wa.ready()
    wa.expand()
  }
}

/** Telegram ichida bo'lsa — initData; aks holda dev rejim (null) */
export function getInitData(): string | null {
  return tg()?.initData || null
}

export function getTelegramLang(): string | undefined {
  return tg()?.initDataUnsafe?.user?.language_code
}

/** Deep-link start param (masalan "battle_abc123") — taklif uchun */
export function getStartParam(): string | null {
  const p = tg()?.initDataUnsafe?.start_param
  if (p) return p
  const url = new URLSearchParams(window.location.search)
  return url.get('tgWebAppStartParam') || url.get('startapp') || null
}
