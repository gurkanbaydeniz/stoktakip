import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      // Geliştirmede API çağrılarını Laravel'e aktar (CORS'suz yerel akış)
      '/api': 'http://127.0.0.1:8000',
    },
  },
})
