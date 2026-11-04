/** What `GET /api/wall/tasks` answers per occurrence and per extra star. */

/** Extra stars a person got on a day, outside of tasks. */
export interface BonusDto {
  person_id: number
  date: string
  points: number
  /** The day bonus, a row apart from extra stars so a device can replace it. */
  day_bonus?: boolean
}

export interface OccurrenceDto {
  task_id: number
  title: string
  /** Emoji for the task; null means the title is enough. */
  icon: string | null
  notes: string | null
  date: string
  /** Person of a task per person; null for an ordinary task. */
  for_person_id: number | null
  /** Time of day (`morning`, `noon`, `evening`); null means any time. */
  daypart: string | null
  daypart_label: string | null
  daypart_icon: string | null
  points: number
  routine: boolean
  /** Readable, e.g. "Jeden Montag"; null for one-off tasks. */
  recurrence: string | null
  person_id: number | null
  person_name: string | null
  /** Whom the task stands for; used when changing it. */
  person_ids: number[]
  skip_holidays: boolean
  /** Start of the rule, for the form. */
  due_on?: string | null
  /** The rule's weekdays; null when only the admin can change the rule. */
  weekdays?: string[] | null
  dayparts?: string[]
  rotation?: number[]
  rotating: boolean
  color: string | null
  /** URL of the picture; null means the initial is enough. */
  avatar: string | null
  done: boolean
  done_by: string | null
  /** Ticked off without stars by a parent; still counts for the day bonus. */
  skipped?: boolean
  /** Who got the stars; for a task "for everyone" whoever did it. */
  done_by_id?: number | null
  /** Stars really booked — none without a person, the old value after a change. */
  earned?: number
  /** Name of the service the task comes from; null for own tasks. */
  origin: string | null
  /** Decided by the server: may the signed-in person or device tick this one? */
  can: { complete: boolean }
}
