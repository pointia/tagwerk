import type { AgendaCalendar, AgendaPerson } from '~/composables/useAgenda'
import type { AgendaEvent, CalendarDay, PersonColumn } from '~/utils/calendar'

/**
 * Whom an event belongs to.
 *
 * First the explicit assignment on the event. If it is missing, the people of
 * the calendar apply — an event in "Emmas Kalender" is Emma's event, even
 * if nobody tapped her. An empty list means: it belongs to the family.
 */
export function peopleOfEvent(event: AgendaEvent, calendars: AgendaCalendar[]): number[] {
  if (event.people.length > 0) {
    return event.people
  }

  return calendars.find(entry => entry.id === event.calendar_id)?.person_ids ?? []
}

export type { PersonColumn }

/**
 * A day, split into one column per person.
 *
 * "Familie" comes first and only when something is in it — an empty column
 * on the far left would push all others to the right without showing
 * anything. Person columns, on the other hand, stay even when empty: a
 * column that disappears as soon as someone has nothing planned would read
 * like an error. A guest gets a column only on a day with an event of
 * their own.
 */
export function peopleColumns(
  day: CalendarDay,
  people: AgendaPerson[],
  calendars: AgendaCalendar[],
  /** The column for events of nobody in particular, in the interface's language. */
  family: string,
): PersonColumn[] {
  const shared = day.events.filter(event => peopleOfEvent(event, calendars).length === 0)

  const columns: PersonColumn[] = people
    .map(person => ({
      personId: person.id,
      name: person.name,
      color: person.color,
      avatar: person.avatar,
      day: {
        ...day,
        events: day.events.filter(event => peopleOfEvent(event, calendars).includes(person.id)),
      },
    }))
    .filter((column, index) => people[index]?.role !== 'guest' || column.day.events.length > 0)

  if (shared.length === 0) {
    return columns
  }

  return [
    {
      personId: null,
      name: family,
      color: null,
      avatar: null,
      day: { ...day, events: shared },
    },
    ...columns,
  ]
}

/**
 * Events, filtered to the people shown.
 *
 * `null` in the selection stands for "Familie". An empty selection shows
 * everything: whoever accidentally deselects all avatars should face a full
 * calendar and not an empty wall.
 */
export function visibleEvents(
  events: AgendaEvent[],
  calendars: AgendaCalendar[],
  selection: (number | null)[],
): AgendaEvent[] {
  if (selection.length === 0) {
    return events
  }

  return events.filter((event) => {
    const belongsTo = peopleOfEvent(event, calendars)

    return belongsTo.length === 0
      ? selection.includes(null)
      : belongsTo.some(id => selection.includes(id))
  })
}
