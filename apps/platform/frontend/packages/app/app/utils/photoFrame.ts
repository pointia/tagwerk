import { zonedIso } from '../../../shared/app/utils/calendar'

/** Layouts of the photo frame — the server's `Preferences::PHOTO_STYLES`. */
export const PHOTO_STYLES = ['single', 'duo', 'mosaic', 'ambient', 'memories'] as const

export type PhotoStyle = typeof PHOTO_STYLES[number]

export function photoStyle(value: string): PhotoStyle {
  return (PHOTO_STYLES as readonly string[]).includes(value) ? value as PhotoStyle : 'single'
}

/** The frame at rest: its own layout, or `same` as awake. */
export function restingStyle(awake: string, resting: string): PhotoStyle {
  return photoStyle(resting === 'same' ? awake : resting)
}

/** The interval preference in milliseconds; thirty seconds if unreadable. */
export function photoIntervalMs(seconds: string): number {
  const value = Number(seconds)

  return (Number.isFinite(value) && value > 0 ? value : 30) * 1000
}

/** Width over height; a portrait stays below this. */
const PORTRAIT_BELOW = 0.9

export function isPortrait(ratio: number | undefined): boolean {
  return ratio !== undefined && ratio < PORTRAIT_BELOW
}

/**
 * How many pictures the duo shows from `position` on: two portraits side by
 * side, otherwise one. A picture whose size is not known yet counts as
 * landscape — it then stands alone, which never looks wrong.
 */
export function duoCount(urls: string[], position: number, ratios: Record<string, number>): 1 | 2 {
  const first = urls[position]
  const second = urls[position + 1]

  return first !== undefined && second !== undefined && isPortrait(ratios[first]) && isPortrait(ratios[second]) ? 2 : 1
}

/**
 * The tile of the mosaic that changes next: never the one that just
 * changed, so the eye does not stick to one corner.
 */
export function nextTile(tiles: number, last: number | null, random: () => number = Math.random): number {
  if (tiles <= 1) {
    return 0
  }

  const choices = Array.from({ length: tiles }, (_, index) => index).filter(index => index !== last)

  return choices[Math.floor(random() * choices.length)] ?? 0
}

/**
 * The layout a dashboard tile can carry, by its size on the 12-column grid:
 * small shows one picture, wide two, large a mosaic.
 */
export function tileStyle(w: number, h: number): PhotoStyle {
  if (w >= 5 && h >= 4) {
    return 'mosaic'
  }

  return w >= 4 && w >= h ? 'duo' : 'single'
}

export type LibraryZoom = 'years' | 'months' | 'days'

export const LIBRARY_ZOOMS: LibraryZoom[] = ['years', 'months', 'days']

/** One step in (`1`, bigger pictures) or out (`-1`), stopping at the ends. */
export function zoomed(zoom: LibraryZoom, by: 1 | -1): LibraryZoom {
  const index = LIBRARY_ZOOMS.indexOf(zoom) + by

  return LIBRARY_ZOOMS[Math.max(0, Math.min(LIBRARY_ZOOMS.length - 1, index))]!
}

export interface Dated {
  taken_on: string | null
  added_on?: string | null
}

export interface PhotoGroup<T> {
  /** `YYYY`, `YYYY-MM` or `YYYY-MM-DD`; empty for pictures without any date. */
  key: string
  photos: T[]
}

/**
 * The grid's sections: by year, month or day, in the order the photos came
 * (the server sorts by the day taken). The upload day stands in for a
 * picture without a date taken.
 */
export function groupPhotos<T extends Dated>(photos: T[], zoom: LibraryZoom): PhotoGroup<T>[] {
  const length = zoom === 'years' ? 4 : zoom === 'months' ? 7 : 10
  const groups: PhotoGroup<T>[] = []

  for (const photo of photos) {
    const key = (photo.taken_on ?? photo.added_on ?? '').slice(0, length)
    const last = groups[groups.length - 1]

    if (last && last.key === key) {
      last.photos.push(photo)
    }
    else {
      groups.push({ key, photos: [photo] })
    }
  }

  return groups
}

/** Step `by` within a list, wrapping at both ends. */
export function wrapped(position: number, by: number, length: number): number {
  if (length === 0) {
    return 0
  }

  return ((position + by) % length + length) % length
}

interface Timed {
  starts_at: string
  ends_at: string
  all_day: boolean
}

/**
 * What the ambient frame lists beside the picture: today's events in the
 * household's zone, all-day ones first, timed ones until they are over.
 */
export function upcomingToday<T extends Timed>(events: T[], now: Date, timeZone: string, limit = 4): T[] {
  const today = zonedIso(now, timeZone)

  return events
    .filter((event) => {
      const from = zonedIso(new Date(event.starts_at), timeZone)
      const until = zonedIso(new Date(new Date(event.ends_at).getTime() - 1), timeZone)

      return from <= today && today <= until && (event.all_day || new Date(event.ends_at) > now)
    })
    .sort((a, b) => Number(b.all_day) - Number(a.all_day) || a.starts_at.localeCompare(b.starts_at))
    .slice(0, limit)
}

/** A run of days as the memory card names it: "14.–16. Juli 2025". */
export function dayRange(from: string, until: string, locale: string): string {
  const format = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
  const start = new Date(`${from}T00:00:00Z`)

  return from === until ? format.format(start) : format.formatRange(start, new Date(`${until}T00:00:00Z`))
}
