import { EscapeStack } from '../utils/escape'

const stack = new EscapeStack()
let listening = false

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape' && stack.escape()) {
    event.preventDefault()
  }
}

/**
 * Every popup closes with Escape.
 *
 * Without `open` the popup is the component itself and counts as open while
 * mounted. With it — for popups inside a larger view — it counts while
 * `open()` is true.
 */
export function useEscape(close: () => void, open?: () => boolean): void {
  if (!listening && import.meta.client) {
    window.addEventListener('keydown', onKey)
    listening = true
  }

  // A fresh function per registration: the same `close` may be registered twice.
  const entry = (): void => close()
  let registered = false

  function set(active: boolean): void {
    if (active && !registered) {
      stack.push(entry)
    }
    else if (!active && registered) {
      stack.remove(entry)
    }

    registered = active
  }

  if (open === undefined) {
    onMounted(() => set(true))
  }
  else {
    watch(open, set, { immediate: true })
  }

  onBeforeUnmount(() => set(false))
}
