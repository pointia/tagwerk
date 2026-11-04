/*
 * Stopwatch and timer as plain numbers. Both keep a start or end point
 * instead of counting ticks: a tablet that sleeps or drops a tick would
 * otherwise run slow.
 */

export interface Stopwatch {
  /** Epoch ms of the running stretch; null while stopped. */
  startedAt: number | null
  /** What ran before the current stretch. */
  banked: number
}

export interface Countdown {
  /** Epoch ms of the end while running; null while paused or unset. */
  endsAt: number | null
  /** What is left while paused. */
  left: number
}

export function elapsedMs(watch: Stopwatch, now: number): number {
  return watch.banked + (watch.startedAt === null ? 0 : now - watch.startedAt)
}

export function remainingMs(timer: Countdown, now: number): number {
  return timer.endsAt === null ? timer.left : Math.max(0, timer.endsAt - now)
}

/**
 * "04:05", from an hour on "1:02:05". A countdown rounds up, so that it
 * shows 00:00 only when it is really over.
 */
export function clockText(ms: number, rounding: 'down' | 'up' = 'down'): string {
  const total = rounding === 'up' ? Math.ceil(ms / 1000) : Math.floor(ms / 1000)
  const hours = Math.floor(total / 3600)
  const minutes = Math.floor((total % 3600) / 60)
  const seconds = total % 60
  const pad = (value: number): string => String(value).padStart(2, '0')

  return hours > 0 ? `${hours}:${pad(minutes)}:${pad(seconds)}` : `${pad(minutes)}:${pad(seconds)}`
}

/** A kitchen timer, not an alarm clock: two digits of minutes are enough. */
export const TIMER_MAX_MS = (99 * 60 + 59) * 1000

/** Minutes and seconds as the timer shows them, rounded up like clockText. */
export function timerParts(ms: number): { minutes: number, seconds: number } {
  const total = Math.ceil(ms / 1000)

  return { minutes: Math.floor(total / 60), seconds: total % 60 }
}

/**
 * The time after swiping one of the two numbers by some steps. Minutes stop
 * at 0 and 99; seconds turn like a wheel and leave the minutes alone, so
 * fine-tuning never jumps a minute.
 */
export function adjustTimer(ms: number, part: 'minutes' | 'seconds', steps: number): number {
  const { minutes, seconds } = timerParts(ms)

  if (part === 'minutes') {
    return (Math.min(99, Math.max(0, minutes + steps)) * 60 + seconds) * 1000
  }

  return (minutes * 60 + (((seconds + steps) % 60) + 60) % 60) * 1000
}
