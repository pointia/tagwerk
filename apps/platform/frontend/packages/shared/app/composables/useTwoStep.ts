/**
 * Remove in two taps: the first arms the button, the second triggers it.
 *
 * On a wall in the hallway people tap in passing, and a deleted entry cannot
 * be brought back there. After a few seconds without a second tap the button
 * disarms again — otherwise a tap minutes later would trigger unexpectedly.
 */
export function useTwoStep(duration = 4000) {
  const armed = ref<string | number | null>(null)

  let clock: ReturnType<typeof setTimeout> | null = null

  function disarm(): void {
    if (clock !== null) {
      clearTimeout(clock)
    }

    clock = null
    armed.value = null
  }

  function tap(key: string | number, action: () => void): void {
    if (armed.value === key) {
      disarm()
      action()

      return
    }

    disarm()
    armed.value = key
    clock = setTimeout(disarm, duration)
  }

  if (getCurrentInstance()) {
    onUnmounted(disarm)
  }

  return { armed, tap }
}
