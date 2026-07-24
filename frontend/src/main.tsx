import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

// Shriftlar. Archivo = width o'qi (Expanded tablo). Inter/JetBrains index = Kirill (rus) ham.
import '@fontsource-variable/archivo/wdth.css'
import '@fontsource-variable/inter'
import '@fontsource-variable/jetbrains-mono'

import './i18n'
import './index.css'
import App from './App.tsx'
import { initTelegram } from './lib/telegram'

initTelegram()

const queryClient = new QueryClient({
  defaultOptions: { queries: { staleTime: 15_000, retry: 1 } },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <App />
    </QueryClientProvider>
  </StrictMode>,
)
