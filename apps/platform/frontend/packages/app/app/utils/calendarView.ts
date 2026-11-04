/** How the calendar shows its days. */
export type CalendarView = 'day' | 'three' | 'week' | 'month' | 'list'

export const WIDE_VIEWS: CalendarView[] = ['day', 'three', 'week', 'month', 'list']

/** A week or month of columns is unreadable at phone width. */
export const NARROW_VIEWS: CalendarView[] = ['day', 'three', 'list']

/** The list covers a week ahead: further nobody reads on a small screen. */
export const LIST_DAYS = 7

/**
 * The view to draw. A stored view the width cannot show falls back to one
 * day without being changed: the same account may want the week on its
 * laptop and still see one day on its phone.
 */
export function shownView(stored: string, narrow: boolean): CalendarView {
  const views = narrow ? NARROW_VIEWS : WIDE_VIEWS

  return views.includes(stored as CalendarView) ? stored as CalendarView : 'day'
}
