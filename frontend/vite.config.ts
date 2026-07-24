import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: true, // Telegram webview / tunnel (ngrok, cloudflared) uchun
    proxy: {
      // /api → backend (FastAPI, 8000)
      '/api': 'http://localhost:8000',
    },
  },
})
