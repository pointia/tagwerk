import { coalesce } from './coalesce.ts'

/** How long after this device's last own write announcements wait. */
export const QUIET_MS = 3000

let heldUntil = 0

/**
 * This device is busy writing: announcements wait until it has been quiet
 * for `QUIET_MS`.
 *
 * Ticking off together in the evening, every tick announces itself a moment
 * later, and every announcement reloaded and redrew the view — on a slow
 * tablet the next tap landed in the middle of it and took seconds. The own
 * ticks are on screen already; the reload can wait until nobody taps.
 */
export function holdAnnouncements(now = Date.now()): void {
  heldUntil = now + QUIET_MS
}

/**
 * Announced areas gathered into one reload: while one runs or the device is
 * busy, further areas join the next one instead of starting their own.
 */
export function gatherAnnouncements(
  run: (areas: string[]) => unknown,
  until: () => number = () => heldUntil,
  now: () => number = Date.now,
): (area: string) => void {
  const pending = new Set<string>()
  let timer: ReturnType<typeof setTimeout> | undefined

  const flush = coalesce(() => {
    const areas = [...pending]

    pending.clear()

    return areas.length > 0 ? run(areas) : undefined
  })

  function schedule(): void {
    clearTimeout(timer)
    timer = setTimeout(() => {
      // Another write meanwhile moved the end of the quiet further out.
      if (until() > now()) {
        schedule()

        return
      }

      void flush()
    }, Math.max(until() - now(), 0))
  }

  return (area: string) => {
    pending.add(area)
    schedule()
  }
}
