/*
 * One place for every write's 402, instead of a check in each composable:
 * the server refuses before validation, whatever the call was.
 */
function text(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null
}

export default defineNuxtPlugin(() => {
  const { refusal } = useReadOnly()

  globalThis.$fetch = $fetch.create({
    onResponseError({ response }) {
      if (response.status === 402) {
        const body = (response._data ?? {}) as { message?: unknown, url?: unknown, action?: unknown }

        refusal.value = { message: text(body.message) ?? '', url: text(body.url), action: text(body.action) }
      }
    },
  })
})
