import { ref } from 'vue'

/**
 * The BCP 47 tag every `Intl` call formats with — German as in Austria,
 * English as in the US.
 *
 * A ref in a plain module rather than a composable: pure utilities like
 * `formatIsoDate` need it too, and computed values that read it format anew
 * when the language changes. `useLanguage` sets it.
 */
export const intlLocale = ref('de-AT')
