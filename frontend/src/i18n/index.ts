import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'

import en from './locales/en.json'
import ru from './locales/ru.json'
import tr from './locales/tr.json'
import uz from './locales/uz.json'

export const SUPPORTED = ['uz', 'en', 'ru', 'tr'] as const
export type Lang = (typeof SUPPORTED)[number]

export const LANG_LABELS: Record<Lang, string> = {
  uz: "O'zbekcha",
  en: 'English',
  ru: 'Русский',
  tr: 'Türkçe',
}

/** Telegram tili → localStorage → brauzer → 'uz' */
function detectLang(): Lang {
  const tg = (window as unknown as {
    Telegram?: { WebApp?: { initDataUnsafe?: { user?: { language_code?: string } } } }
  }).Telegram?.WebApp?.initDataUnsafe?.user?.language_code
  const saved = localStorage.getItem('lang') ?? undefined
  const nav = navigator.language?.split('-')[0]
  const pick = [saved, tg, nav].find(
    (l): l is Lang => !!l && (SUPPORTED as readonly string[]).includes(l),
  )
  return pick ?? 'uz'
}

i18n.use(initReactI18next).init({
  resources: {
    en: { translation: en },
    ru: { translation: ru },
    tr: { translation: tr },
    uz: { translation: uz },
  },
  lng: detectLang(),
  fallbackLng: 'en',
  supportedLngs: SUPPORTED as unknown as string[],
  interpolation: { escapeValue: false },
})

export function setLang(lang: Lang) {
  localStorage.setItem('lang', lang)
  i18n.changeLanguage(lang)
}

export default i18n
