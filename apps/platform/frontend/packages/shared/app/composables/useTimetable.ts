import type { GridRow, TimetableChild, Lesson } from '../utils/timetable'
import { mondayOf, move } from '../utils/timetable'

interface TimetableResponse {
  range: { from: string, to: string }
  people: TimetableChild[]
  periods: GridRow[]
  lessons: Lesson[]
}

/**
 * The timetable for wall and phone.
 *
 * One source for both devices: the wall shows the week, the phone the day,
 * but computing and fetching happens only once.
 */
export function useTimetable() {
  const { t } = useI18n()
  const exception = useLessonException()

  const children = useState<TimetableChild[]>('timetable-children', () => [])
  const raster = useState<GridRow[]>('timetable-grid', () => [])
  const lessons = useState<Lesson[]>('timetable-lessons', () => [])
  const selectedChild = useState<number | null>('timetable-child', () => null)

  // The Monday of the shown week. Timetables are thought of in weeks.
  const monday = useState<string>('timetable-monday', () => mondayOf(new Date().toISOString().slice(0, 10)))

  const error = ref<string | null>(null)
  const loading = ref(false)
  // Whether any answer arrived yet: before it, no children means "not known yet".
  const loaded = useState('timetable-loaded', () => false)

  async function load(): Promise<void> {
    loading.value = true

    try {
      const data = await $fetch<TimetableResponse>('/api/wall/timetable', {
        baseURL: '/',
        credentials: 'same-origin',
        params: { from: monday.value, to: move(monday.value, 6) },
      })

      children.value = data.people
      raster.value = data.periods
      lessons.value = data.lessons
      loaded.value = true
      error.value = null

      /*
       * Select the first child on the first load — and forget a child that no
       * longer has a timetable. Otherwise the view would stay empty without
       * anyone seeing why.
       */
      const known = data.people.some(child => child.id === selectedChild.value)

      if (!known) {
        selectedChild.value = data.people[0]?.id ?? null
      }
    }
    catch {
      error.value = t('timetable.loadFailed')
    }
    finally {
      loading.value = false
    }
  }

  async function page(weeks: number): Promise<void> {
    monday.value = move(monday.value, weeks * 7)

    await load()
  }

  /** Cancel a lesson on one day. */
  async function cancel(lesson: Lesson): Promise<void> {
    await write(() => exception.cancel(lesson), t('timetable.saveFailed'))
  }

  /** Something else takes place instead of the planned lesson. */
  async function replace(lesson: Lesson, subject: string): Promise<void> {
    await write(() => exception.replace(lesson, subject), t('timetable.saveFailed'))
  }

  /** Revert the deviation — the plan applies again. */
  async function revert(lesson: Lesson): Promise<void> {
    await write(() => exception.revert(lesson), t('timetable.revertFailed'))
  }

  async function write(perform: () => Promise<void>, message: string): Promise<void> {
    try {
      await perform()
      await load()
    }
    catch {
      error.value = message
    }
  }

  return {
    children,
    raster,
    lessons,
    selectedChild,
    monday,
    error,
    loading,
    loaded,
    load,
    page,
    cancel,
    replace,
    revert,
  }
}
