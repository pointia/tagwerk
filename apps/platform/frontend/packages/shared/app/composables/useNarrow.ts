/**
 * Below Tailwind's `sm`: the phone's layout. A component that needs a
 * different structure there, not just other classes, asks this — hidden
 * duplicates would leave two of every control in the page.
 */
export function useNarrow() {
  const narrow = ref(false)
  let query: MediaQueryList | null = null
  const measure = (): void => { narrow.value = query?.matches ?? false }

  onMounted(() => {
    query = window.matchMedia('(max-width: 639px)')
    measure()
    query.addEventListener('change', measure)
  })
  onBeforeUnmount(() => query?.removeEventListener('change', measure))

  return narrow
}
