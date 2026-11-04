/**
 * When a day is done — and when it has only just become done.
 *
 * Separate from the display, because the moment confetti falls depends on
 * it. A bug here would otherwise go unnoticed until a child was happy for
 * nothing.
 */
export interface CheckableOccurrence {
  date: string
  done: boolean
}

export function openOnDay(occurrences: CheckableOccurrence[], date: string): number {
  return occurrences.filter(entry => entry.date === date && !entry.done).length
}

/**
 * A day without tasks is not done but empty. Otherwise the wall would
 * congratulate on nothing every Sunday.
 */
export function isDone(occurrences: CheckableOccurrence[], date: string): boolean {
  const onDay = occurrences.filter(entry => entry.date === date)

  return onDay.length > 0 && onDay.every(entry => entry.done)
}

/**
 * Only the transition is celebrated.
 *
 * Without this comparison confetti would fall again on every reload of a
 * finished day — and again every time the same task is reverted and
 * checked off anew.
 */
export function justFinished(
  before: CheckableOccurrence[],
  after: CheckableOccurrence[],
  date: string,
): boolean {
  return isDone(after, date) && !isDone(before, date)
}
