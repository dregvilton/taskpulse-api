import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig(({ command }) => ({
  base: command === 'serve' ? '/' : '/app/',
  plugins: [vue()],
  server: {
    proxy: {
      '^/(auth|users|tasks|analytics|health)(/|\\?|$)': {
        target: process.env.DEV_API_TARGET || 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/*.test.js'],
  },
}))
