/*
 * The example area's layer: one view, registered for the menu by the plugin
 * in app/plugins. Found by the app at build time (plugin-layers.ts).
 */
export default defineNuxtConfig({
  i18n: {
    locales: [
      { code: 'de', file: 'de.json' },
      { code: 'en', file: 'en.json' },
    ],
  },
})
