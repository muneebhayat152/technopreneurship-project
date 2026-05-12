import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined
          if (id.includes('recharts')) return 'recharts'
          if (id.includes('framer-motion')) return 'framer-motion'
          if (id.includes('react-router')) return 'react-router'
          if (id.includes('jspdf') || id.includes('html2canvas')) return 'export'
          if (id.includes('react-dom') || id.includes('node_modules/react/')) return 'react-core'
          return 'vendor'
        },
      },
    },
    chunkSizeWarningLimit: 600,
  },
})
