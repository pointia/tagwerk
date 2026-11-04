/**
 * The smiley of a column with nothing left to do.
 *
 * Chosen by day and person rather than at random on each draw: the wall
 * redraws every few minutes, and a face that changes each time looks like
 * a glitch, not a reward. Tomorrow brings another one.
 */
const FACES = ['🥳', '🤩', '😆', '😎', '🦄', '🌈', '🙌', '🏆', '🎉', '😺', '🐶', '🦖', '🚀', '🍭', '🌟', '💪']

/** How many praises `celebrate.praise` in the locale files holds. */
const PRAISES = 6

export interface Celebration {
  face: string
  /** i18n key of the praise below the face. */
  praise: string
}

/** A small string hash (FNV-1a): stable across devices and reloads. */
function hash(text: string): number {
  let value = 0x811C9DC5

  for (let index = 0; index < text.length; index++) {
    value ^= text.charCodeAt(index)
    value = Math.imul(value, 0x01000193)
  }

  return value >>> 0
}

export function celebration(day: string, personId: number | null): Celebration {
  const value = hash(`${day}:${personId ?? 'all'}`)

  return {
    face: FACES[value % FACES.length] ?? '🌟',
    praise: `celebrate.praise.p${(value >>> 8) % PRAISES}`,
  }
}
