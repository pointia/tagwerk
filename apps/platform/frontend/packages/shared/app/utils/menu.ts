/*
 * The main menu as a device has arranged it.
 *
 * Stored are the order and the hidden ones, not the shown ones: an area added
 * later then appears by itself, at the end, instead of being swallowed.
 */

export interface Arranged {
  /** Every area the household has, in the chosen order — for the settings. */
  all: string[]
  /** What the menu shows. Never empty. */
  visible: string[]
}

export function arranged(available: string[], order: string[], hidden: string[]): Arranged {
  const chosen = order.filter(key => available.includes(key))
  const all = [...chosen, ...available.filter(key => !chosen.includes(key))]
  const visible = all.filter(key => !hidden.includes(key))

  return { all, visible: visible.length > 0 ? visible : all.slice(0, 1) }
}

/** One step up (-1) or down (+1); at the edge nothing moves. */
export function moved(all: string[], key: string, step: -1 | 1): string[] {
  const from = all.indexOf(key)
  const to = from + step

  if (from === -1 || to < 0 || to >= all.length) {
    return all
  }

  const result = [...all]

  result[from] = all[to]!
  result[to] = key

  return result
}
