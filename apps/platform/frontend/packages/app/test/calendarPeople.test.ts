import { describe, expect, it } from 'vitest'
import type { AgendaCalendar, AgendaPerson } from '../app/composables/useAgenda'
import type { AgendaEvent, CalendarDay } from '../app/utils/calendar'
import { peopleColumns, peopleOfEvent, visibleEvents } from '../app/utils/calendarPeople'

const EMMA: AgendaPerson = { id: 1, name: 'Emma', color: '#a855f7', avatar: null, role: 'child' }
const JOHN: AgendaPerson = { id: 2, name: 'John', color: '#3b82f6', avatar: null, role: 'adult' }

const CALENDAR_VIEWS: AgendaCalendar[] = [
  { id: 10, summary: 'Familie', color: null, person_ids: [], writable: true },
  { id: 11, summary: 'Emma', color: null, person_ids: [1], writable: true },
  { id: 12, summary: 'Kinder', color: null, person_ids: [1, 2], writable: true },
]

function event(id: number, state: Partial<AgendaEvent> = {}): AgendaEvent {
  return {
    id,
    title: `Termin ${id}`,
    location: null,
    starts_at: '2026-09-21T08:00:00Z',
    ends_at: '2026-09-21T09:00:00Z',
    all_day: false,
    calendar_id: 10,
    people: [],
    color: null,
    ...state,
  }
}

function day(events: AgendaEvent[]): CalendarDay {
  return {
    iso: '2026-09-21',
    day: 21,
    inMonth: true,
    isToday: true,
    isWeekend: false,
    holiday: null,
    events,
  }
}

describe('peopleOfEvent', () => {
  it('takes the explicit assignment', () => {
    expect(peopleOfEvent(event(1, { people: [1, 2] }), CALENDAR_VIEWS)).toEqual([1, 2])
  })

  it('falls back to the calendar owner', () => {
    expect(peopleOfEvent(event(1, { calendar_id: 11 }), CALENDAR_VIEWS)).toEqual([1])
  })

  it('gives an event in a shared calendar to all its people', () => {
    expect(peopleOfEvent(event(1, { calendar_id: 12 }), CALENDAR_VIEWS)).toEqual([1, 2])
  })

  it('leaves an event without either to the family', () => {
    expect(peopleOfEvent(event(1), CALENDAR_VIEWS)).toEqual([])
  })

  it('copes with an unknown calendar', () => {
    expect(peopleOfEvent(event(1, { calendar_id: 99 }), CALENDAR_VIEWS)).toEqual([])
  })
})

describe('peopleColumns', () => {
  it('gives every person a column, even without events', () => {
    const columns = peopleColumns(day([event(1, { people: [1] })]), [EMMA, JOHN], CALENDAR_VIEWS, 'Familie')

    expect(columns.map(column => column.personId)).toEqual([1, 2])
    expect(columns[0]!.day.events).toHaveLength(1)
    expect(columns[1]!.day.events).toHaveLength(0)
  })

  it('gives a guest a column only on a day with an event of their own', () => {
    const OMA: AgendaPerson = { id: 3, name: 'Oma', color: null, avatar: null, role: 'guest' }

    expect(peopleColumns(day([event(1, { people: [1] })]), [EMMA, OMA], CALENDAR_VIEWS, 'Familie')
      .map(column => column.personId)).toEqual([1])
    expect(peopleColumns(day([event(1, { people: [3] })]), [EMMA, OMA], CALENDAR_VIEWS, 'Familie')
      .map(column => column.personId)).toEqual([1, 3])
  })

  it('puts the family column first as soon as it has content', () => {
    const columns = peopleColumns(day([event(1), event(2, { people: [1] })]), [EMMA], CALENDAR_VIEWS, 'Familie')

    expect(columns.map(column => column.personId)).toEqual([null, 1])
    expect(columns[0]!.name).toBe('Familie')
  })

  it('omits the family column when it is empty', () => {
    const columns = peopleColumns(day([event(1, { people: [2] })]), [EMMA, JOHN], CALENDAR_VIEWS, 'Familie')

    expect(columns.map(column => column.personId)).toEqual([1, 2])
  })

  it('shows an event for two people in both columns', () => {
    const columns = peopleColumns(day([event(1, { people: [1, 2] })]), [EMMA, JOHN], CALENDAR_VIEWS, 'Familie')

    expect(columns[0]!.day.events).toHaveLength(1)
    expect(columns[1]!.day.events).toHaveLength(1)
  })
})

describe('visibleEvents', () => {
  const events = [event(1), event(2, { people: [1] }), event(3, { calendar_id: 11 })]

  it('shows everything while nobody is deselected', () => {
    expect(visibleEvents(events, CALENDAR_VIEWS, [])).toHaveLength(3)
  })

  it('shows only the events of the selected person', () => {
    expect(visibleEvents(events, CALENDAR_VIEWS, [1]).map(event => event.id)).toEqual([2, 3])
  })

  it('treats the family as a choice of its own', () => {
    expect(visibleEvents(events, CALENDAR_VIEWS, [null]).map(event => event.id)).toEqual([1])
  })

  it('shows a shared event as soon as one of its people is selected', () => {
    expect(visibleEvents([event(4, { people: [1, 2] })], CALENDAR_VIEWS, [2])).toHaveLength(1)
  })
})
