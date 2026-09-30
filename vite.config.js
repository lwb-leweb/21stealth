import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'path'
import { readFileSync } from 'fs'

const { version } = JSON.parse(readFileSync('./package.json', 'utf-8'))

export default defineConfig({
  define: {
    __APP_VERSION__: JSON.stringify(version),
  },
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['icon-*.png'],
      manifest: {
        name: '21stealth',
        short_name: '21stealth',
        description: 'Your crypto portfolio — private by default.',
        theme_color: '#0d110a',
        background_color: '#0d110a',
        display: 'standalone',
        orientation: 'portrait',
        scope: '/',
        start_url: '/dashboard',
        icons: [
          { src: 'icon-96.png',  sizes: '96x96',   type: 'image/png' },
          { src: 'icon-144.png', sizes: '144x144',  type: 'image/png' },
          { src: 'icon-152.png', sizes: '152x152',  type: 'image/png' },
          { src: 'icon-167.png', sizes: '167x167',  type: 'image/png' },
          { src: 'icon-180.png', sizes: '180x180',  type: 'image/png' },
          { src: 'icon-192.png', sizes: '192x192',  type: 'image/png' },
          { src: 'icon-512.png', sizes: '512x512',  type: 'image/png' },
          { src: 'icon-512.png', sizes: '512x512',  type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
      },
    }),
  ],
  // Dev only: forward API calls to the live backend (no local PHP needed)
  server: {
    proxy: {
      '/backend': { target: 'https://21stealth.com', changeOrigin: true },
    },
  },
  resolve: {
    alias: {
      '@':        path.resolve(__dirname, './src'),
      '@ui':      path.resolve(__dirname, './src/components/ui'),
      '@layout':  path.resolve(__dirname, './src/components/layout'),
      '@styles':  path.resolve(__dirname, './src/styles'),
      '@config':  path.resolve(__dirname, './src/config'),
      '@hooks':   path.resolve(__dirname, './src/hooks'),
      '@store':   path.resolve(__dirname, './src/store'),
      '@lib':     path.resolve(__dirname, './src/lib'),
      '@utils':   path.resolve(__dirname, './src/utils'),
    },
  },
})
