import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  // relative paths in the built files: the app works from public/bookshelf-spa/dist/
  base: './',
  plugins: [
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    // npm run dev: requests to /api/... are forwarded to Caligrafy, so the browser sees one single site
    proxy: {
      '/api': 'http://localhost:8080'
    }
  }
})
