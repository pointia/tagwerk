/*
 * The school band on the left of a day column: one lane per child.
 *
 * The lanes come from the whole shown range, not from each day, so a child
 * keeps its place from one day to the next. The band grows with a second child
 * and then stops — beyond a third of the column the events would lose theirs.
 */
const SINGLE = 22
const MAX = 33

export interface BandLayout {
  /** Share of the column in percent. */
  width: number
  /** Person ids in lane order. */
  lanes: number[]
}

export function bandLayout(lessons: { person_id: number }[]): BandLayout {
  const lanes = [...new Set(lessons.map(lesson => lesson.person_id))].sort((a, b) => a - b)

  if (lanes.length === 0) {
    return { width: 0, lanes }
  }

  return { width: lanes.length === 1 ? SINGLE : MAX, lanes }
}
