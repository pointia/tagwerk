/*
 * A language pack that lags behind a release shows the missing texts in
 * English — never a raw key. `useLanguage` loads the English messages
 * before it switches to a pack's code.
 */
export default defineI18nConfig(() => ({
  fallbackLocale: 'en',
}))
