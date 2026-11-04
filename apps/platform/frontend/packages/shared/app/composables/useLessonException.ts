import type { Lesson } from '../utils/timetable'

/**
 * The timetable's three write paths — without display state.
 *
 * Separate from `useTimetable`, because an exception is created in two
 * places: in the timetable view and in the calendar band. Only the calling
 * view knows what to reload afterwards; errors therefore travel upwards.
 */
export function useLessonException() {
  const { withPin } = usePinGate()

  async function cancel(lesson: Lesson): Promise<void> {
    await enter(lesson, { date: lesson.date, kind: 'cancelled' })
  }

  async function replace(lesson: Lesson, subject: string): Promise<void> {
    await enter(lesson, { date: lesson.date, kind: 'replaced', title: subject })
  }

  async function revert(lesson: Lesson): Promise<void> {
    await withPin(() => $fetch<unknown>(`/api/wall/timetable/${lesson.lesson_id}/change`, {
      baseURL: '/',
      method: 'DELETE',
      credentials: 'same-origin',
      body: { date: lesson.date },
    }))
  }

  async function enter(lesson: Lesson, body: Record<string, unknown>): Promise<void> {
    await withPin(() => $fetch<unknown>(`/api/wall/timetable/${lesson.lesson_id}/change`, {
      baseURL: '/',
      method: 'POST',
      credentials: 'same-origin',
      body: body,
    }))
  }

  return { cancel, replace, revert }
}
