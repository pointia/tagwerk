import { defineConfig } from 'vite'

/*
 * In development the browser talks to Vite only; Vite passes the server's
 * paths on. The sign-in cookie then belongs to this origin, as it will when
 * Tagwerk serves the built files itself. TAGWERK_URL is your server — a DDEV
 * checkout is https://tagwerk.test.
 */
const server = process.env.TAGWERK_URL ?? 'https://tagwerk.test'
const proxy = { target: server, changeOrigin: true, secure: false }

export default defineConfig({
  server: {
    proxy: { '/api': proxy, '/broadcasting': proxy, '/emoji': proxy },
  },
  build: {
    // Tagwerk serves index.html at / and everything else from /assets/.
    outDir: 'dist',
    assetsDir: 'assets',
    emptyOutDir: true,
  },
})
