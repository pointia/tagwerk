import type { Mode } from './areas'

/** Areas the resting wall may show — the same list as the server's. */
export const RESTING_AREAS: Mode[] = ['calendar', 'tasks', 'timetable', 'photos', 'meals', 'points', 'dashboard']

/** Photos run several pictures before the next area comes. */
const PHOTO_ROUNDS = 3

/**
 * The carousel: the chosen areas in the chosen order, as far as the
 * household still has them. Empty means no resting mode.
 */
export function carousel(chosen: string[], available: Mode[]): Mode[] {
  return chosen.filter((entry): entry is Mode =>
    RESTING_AREAS.includes(entry as Mode) && available.includes(entry as Mode))
}

/** Minutes from the preference, as milliseconds; five if unreadable. */
export function idleMs(minutes: string): number {
  const value = Number(minutes)

  return (Number.isFinite(value) && value > 0 ? value : 5) * 60_000
}

/** The interval from the preference, as milliseconds; thirty seconds if unreadable. */
export function intervalMs(seconds: string): number {
  const value = Number(seconds)

  return (Number.isFinite(value) && value > 0 ? value : 30) * 1000
}

/**
 * How long one area stays. Photos get several rounds of the frame; an area
 * with pages — the timetable, one per child — one interval per page.
 */
export function slideMs(mode: Mode, seconds: string, photoMs: number, pages = 1): number {
  const base = intervalMs(seconds)

  return mode === 'photos' ? Math.max(base, PHOTO_ROUNDS * photoMs) : base * Math.max(1, pages)
}

/** The darkness from the preference, as a share of 0–0.9; half if unreadable. */
function darkness(percent: string): number {
  const value = Number(percent)

  return percent !== '' && Number.isFinite(value) ? Math.min(Math.max(value, 0), 90) / 100 : 0.5
}

/**
 * The darkening that applies now: the night value while the household shows
 * its dark theme, the day value otherwise (also before the theme is known).
 */
export function restingDim(day: string, night: string, theme: string | undefined): string {
  return theme === 'dark' ? night : day
}

/**
 * Opacity of the black layer over the resting wall — photos included, so
 * that the night value darkens the picture frame as the slider promises.
 */
export function veilOpacity(percent: string): number {
  return darkness(percent)
}

/** Brightness while resting, on Fully Kiosk's scale of 0–255. */
export function restingBrightness(before: number, percent: string): number {
  return Math.round(before * (1 - darkness(percent)))
}

/*
 * Fully Kiosk's JavaScript interface ("Enable JavaScript Interface" in its
 * settings). A browser cannot touch the screen's brightness; Fully can. Without
 * it the dark veil over the page does the dimming alone.
 */
interface FullyKiosk {
  getScreenBrightness: () => number
  setScreenBrightness: (value: number) => void
}

function fully(): FullyKiosk | null {
  const candidate: unknown = Reflect.get(globalThis, 'fully')

  return typeof candidate === 'object' && candidate !== null
    && typeof Reflect.get(candidate, 'setScreenBrightness') === 'function'
    && typeof Reflect.get(candidate, 'getScreenBrightness') === 'function'
    ? candidate as FullyKiosk
    : null
}

/** Dims the screen through Fully; returns what to restore, or null. */
export function dimScreen(percent: string): number | null {
  const kiosk = fully()

  if (kiosk === null) {
    return null
  }

  const before = kiosk.getScreenBrightness()

  kiosk.setScreenBrightness(restingBrightness(before, percent))

  return before
}

/**
 * Sets the resting brightness while the slider moves. Every step counts from
 * the brightness before the drag (`before`, read on the first step), so that
 * steps do not dim on top of each other. Returns what to restore, or null.
 */
export function previewScreen(percent: string, before: number | null): number | null {
  const kiosk = fully()

  if (kiosk === null) {
    return null
  }

  const base = before ?? kiosk.getScreenBrightness()
  kiosk.setScreenBrightness(restingBrightness(base, percent))

  return base
}

export function restoreScreen(before: number | null): void {
  if (before !== null) {
    fully()?.setScreenBrightness(before)
  }
}

/** The index after `index`, starting over at the end. */
export function nextSlide(index: number, length: number): number {
  return length === 0 ? 0 : (index + 1) % length
}
