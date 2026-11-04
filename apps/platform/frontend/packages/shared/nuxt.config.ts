/*
 * Shared layer of wall and phone: design, theme, realtime.
 *
 * Both interfaces include it via `extends`. What lives here exists exactly
 * once — two versions of the same composable would drift apart sooner or
 * later, and two colour palettes all the more.
 */
export default defineNuxtConfig({
  modules: ['@nuxtjs/i18n'],

  /*
   * Which language applies is the server's call, before the first text:
   * `GET /api/language` (plugins/language.client.ts) answers for a device,
   * an account and nobody (browser languages, matched on the server). The
   * browser decides only without network and without a stored answer. No
   * cookie: a stored choice would compete with the server's. Languages from
   * packs are not listed here — their texts come from the server.
   */
  i18n: {
    strategy: 'no_prefix',
    defaultLocale: 'de',
    detectBrowserLanguage: false,
    vueI18n: './i18n.config.ts',
    locales: [
      { code: 'de', language: 'de-AT', name: 'Deutsch', file: 'de.json' },
      { code: 'en', language: 'en-US', name: 'English', file: 'en.json' },
    ],
  },

  app: {
    head: {
      script: [
        {
          /*
           * The theme before the first paint (public/theme.js). Blocking on
           * purpose. As a file: unhead adds the head's scripts again once the
           * app runs, and inline that would be a violation of the policy.
           */
          src: '/theme.js',
          tagPosition: 'head',
        },
      ],
    },
  },
})
