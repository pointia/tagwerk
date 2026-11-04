/**
 * The cheer after a tick: stars from where the finger was, two rockets and
 * a "Yay!". One at a time — a new tick replaces the running one.
 *
 * The day bonus gets the big one: it replaces the tick's cheer with
 * fireworks, confetti and the gift's stars.
 */
export interface Cheer {
  id: number
  points: number
  /** Stars of a day bonus just earned; 0 for an ordinary tick. */
  bonus: number
  x: number
  y: number
}

/* Where the last touch landed, so the stars burst from the check mark. */
let lastX = -1
let lastY = -1

if (import.meta.client) {
  window.addEventListener('pointerdown', (event) => {
    lastX = event.clientX
    lastY = event.clientY
  }, { capture: true, passive: true })
}

export function useCheer() {
  const current = useState<Cheer | null>('cheer', () => null)

  function cheer(points: number, bonus = 0): void {
    if (!import.meta.client) {
      return
    }

    current.value = {
      id: Date.now(),
      points,
      bonus,
      x: lastX >= 0 ? lastX : window.innerWidth / 2,
      y: lastY >= 0 ? lastY : window.innerHeight / 2,
    }
  }

  function done(id: number): void {
    if (current.value?.id === id) {
      current.value = null
    }
  }

  function dayBonus(points: number): void {
    cheer(0, points)
  }

  return { current, cheer, dayBonus, done }
}
