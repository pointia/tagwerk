import { type Countdown, type Stopwatch, TIMER_MAX_MS, elapsedMs, remainingMs } from '~/utils/clockTools'

export type ClockTool = 'stopwatch' | 'timer'

/*
 * Kept outside any component: a kitchen timer has to go on while its window
 * is closed or someone looks at the calendar, and ring wherever the wall is.
 */
let ticker: ReturnType<typeof setInterval> | null = null
let audio: AudioContext | null = null
let alarm: ReturnType<typeof setInterval> | null = null
let alarmEnd: ReturnType<typeof setTimeout> | null = null

/** How long it rings without anyone there. */
const ALARM_MS = 60_000

export function useClockTools() {
  const open = useState<ClockTool | null>('clock-tool', () => null)
  const stopwatch = useState<Stopwatch>('clock-stopwatch', () => ({ startedAt: null, banked: 0 }))
  const timer = useState<Countdown>('clock-timer', () => ({ endsAt: null, left: 0 }))
  const ringing = useState('clock-ringing', () => false)
  const now = useState('clock-now', () => Date.now())

  const elapsed = computed(() => elapsedMs(stopwatch.value, now.value))
  const remaining = computed(() => remainingMs(timer.value, now.value))
  const running = computed(() => stopwatch.value.startedAt !== null || timer.value.endsAt !== null)

  function tick(): void {
    now.value = Date.now()

    if (timer.value.endsAt !== null && timer.value.endsAt <= now.value) {
      timer.value = { endsAt: null, left: 0 }
      ring()
    }

    if (!running.value && ticker !== null) {
      clearInterval(ticker)
      ticker = null
    }
  }

  function keepTicking(): void {
    now.value = Date.now()
    ticker ??= setInterval(tick, 250)
  }

  /*
   * Browsers only allow sound after a tap. Starting is that tap, so the
   * audio context is opened there and ready when the time is up.
   */
  function unlockSound(): void {
    audio ??= new AudioContext()
    void audio.resume()
  }

  function beep(): void {
    if (audio === null) {
      return
    }

    for (const offset of [0, 0.25, 0.5]) {
      const tone = audio.createOscillator()
      const volume = audio.createGain()
      const start = audio.currentTime + offset

      tone.frequency.value = 880
      volume.gain.setValueAtTime(0.25, start)
      volume.gain.exponentialRampToValueAtTime(0.001, start + 0.18)
      tone.connect(volume).connect(audio.destination)
      tone.start(start)
      tone.stop(start + 0.2)
    }
  }

  function ring(): void {
    ringing.value = true
    open.value = 'timer'
    beep()
    alarm = setInterval(beep, 1500)
    alarmEnd = setTimeout(silence, ALARM_MS)
  }

  function silence(): void {
    ringing.value = false

    if (alarm !== null) {
      clearInterval(alarm)
      alarm = null
    }

    if (alarmEnd !== null) {
      clearTimeout(alarmEnd)
      alarmEnd = null
    }
  }

  function startStopwatch(): void {
    stopwatch.value = { startedAt: Date.now(), banked: stopwatch.value.banked }
    keepTicking()
  }

  function stopStopwatch(): void {
    stopwatch.value = { startedAt: null, banked: elapsed.value }
  }

  function resetStopwatch(): void {
    stopwatch.value = { startedAt: null, banked: 0 }
  }

  /** Sets the time while the timer stands; minutes may be added up. */
  function setTimer(ms: number): void {
    timer.value = { endsAt: null, left: Math.min(TIMER_MAX_MS, Math.max(0, ms)) }
  }

  function startTimer(): void {
    if (timer.value.left <= 0) {
      return
    }

    unlockSound()
    silence()
    timer.value = { endsAt: Date.now() + timer.value.left, left: timer.value.left }
    keepTicking()
  }

  function pauseTimer(): void {
    timer.value = { endsAt: null, left: remaining.value }
  }

  function cancelTimer(): void {
    silence()
    timer.value = { endsAt: null, left: 0 }
  }

  /** Closing the window never stops a clock — only a ringing alarm. */
  function close(): void {
    silence()
    open.value = null
  }

  return {
    open,
    stopwatch,
    timer,
    ringing,
    elapsed,
    remaining,
    startStopwatch,
    stopStopwatch,
    resetStopwatch,
    setTimer,
    startTimer,
    pauseTimer,
    cancelTimer,
    silence,
    close,
  }
}
