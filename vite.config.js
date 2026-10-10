import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    // Dev uniquement : autorise le tunnel public pour voir sur telephone.
    // Sans impact sur le build de production (dist/).
    allowedHosts: ['tap-fails-forest-journals.trycloudflare.com'],
  },
})
