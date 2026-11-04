<script setup lang="ts">
import { formatIsoDate } from '../utils/calendar'
import type { Lesson } from '../utils/timetable'
import {
  gridRows,
  schoolDays,
  clockTime,
  rowFor,
  lessonTint,
  inkOn,
  weekdayName,
} from '../utils/timetable'

const { t } = useI18n()

/**
 * The timetable on both devices.
 *
 * The week as a grid in every width, like a printed timetable: a single
 * day with a strip of weekdays below read like the calendar's paging and
 * hid the week. Narrow, the grid only gets smaller — by the container's
 * width, not by device detection.
 */
const {
  children,
  raster,
  lessons,
  selectedChild,
  monday: mondayOfWeek,
  error,
  loaded,
  load,
  page,
  cancel,
  replace,
  revert,
} = useTimetable()

const tapped = ref<Lesson | null>(null)

const ownLessons = computed(() => lessons.value.filter(
  lesson => selectedChild.value === null || lesson.person_id === selectedChild.value,
))

const days = computed(() => schoolDays(mondayOfWeek.value, ownLessons.value))

const rows = computed(() => gridRows(raster.value, ownLessons.value, selectedChild.value))

function lessonAt(date: string, row: string): Lesson | null {
  return ownLessons.value.find(
    lesson => lesson.date === date && rowFor(lesson) === row,
  ) ?? null
}

function label(lesson: Lesson): string {
  return lesson.status === 'replaced' && lesson.original_title
    ? t('timetable.instead', { subject: lesson.original_title })
    : `${clockTime(lesson.starts_at)}–${clockTime(lesson.ends_at)}`
}

/** Close first, then write — the response reloads the list anyway. */
function exception(perform: (lesson: Lesson) => Promise<void>): void {
  const lesson = tapped.value

  tapped.value = null

  if (lesson) {
    perform(lesson)
  }
}

function replaceException(subject: string): void {
  exception(lesson => replace(lesson, subject))
}

onMounted(load)
</script>

<template>
  <div class="@container flex h-full flex-col">
    <p
      v-if="error"
      role="alert"
      class="mb-4 flex shrink-0 items-center gap-3 rounded-2xl bg-warning px-5 py-3 text-base font-medium text-warning-ink"
    >
      <TwIcon name="warning" :size="20" />
      {{ error }}
    </p>

    <TwLoading v-if="!loaded && !error" />

    <div
      v-else-if="children.length === 0"
      class="flex flex-1 items-center justify-center text-lg text-ink-3"
    >
      {{ $t('timetable.none') }}
    </div>

    <template v-else>
      <!-- Switcher only when there is something to switch. -->
      <div
        v-if="children.length > 1"
        class="mb-4 flex shrink-0 gap-2 overflow-x-auto"
        role="tablist"
        :aria-label="$t('timetable.pickChild')"
      >
        <button
          v-for="child in children"
          :key="child.id"
          type="button"
          role="tab"
          :aria-selected="child.id === selectedChild"
          :data-testid="`timetable-child-${child.id}`"
          class="flex min-h-12 shrink-0 items-center gap-2 rounded-2xl px-4 text-lg font-semibold transition-colors"
          :class="child.id === selectedChild
            ? (child.color ? '' : 'bg-accent text-accent-ink')
            : 'bg-surface text-ink-2 hover:bg-surface-2'"
          :style="child.id === selectedChild && child.color
            ? { backgroundColor: child.color, color: inkOn(child.color) }
            : undefined"
          @click="selectedChild = child.id"
        >
          <!-- The ring keeps the avatar apart from a button in its own colour. -->
          <TwAvatar
            :name="child.name"
            :avatar="child.avatar"
            :color="child.color"
            :size="28"
            :class="child.id === selectedChild ? 'rounded-full ring-2 ring-white/80' : ''"
          />
          {{ child.name }}
        </button>
      </div>

      <div class="mb-3 flex shrink-0 items-center justify-between gap-3">
        <button
          type="button"
          :aria-label="$t('timetable.weekBack')"
          class="min-h-12 min-w-12 rounded-2xl bg-surface text-ink-2 transition-colors hover:bg-surface-2"
          @click="page(-1)"
        >
          ‹
        </button>

        <span data-testid="timetable-week" class="text-lg font-semibold text-ink">
          {{ $t('timetable.weekFrom', { date: formatIsoDate(mondayOfWeek, { day: '2-digit', month: '2-digit', year: 'numeric' }) }) }}
        </span>

        <button
          type="button"
          :aria-label="$t('timetable.weekForward')"
          class="min-h-12 min-w-12 rounded-2xl bg-surface text-ink-2 transition-colors hover:bg-surface-2"
          @click="page(1)"
        >
          ›
        </button>
      </div>

      <!-- The week grid in every width, like a printed timetable: narrow
           only smaller, without the times. `relative` keeps a wide weekend
           scrolling inside instead of widening the page. -->
      <div data-testid="timetable-grid" class="relative min-h-0 flex-1 overflow-auto">
        <table class="w-full table-fixed border-separate border-spacing-0.5 @3xl:border-spacing-1">
          <thead>
            <tr>
              <th class="w-7 @3xl:w-14" />
              <th
                v-for="day in days"
                :key="day.date"
                class="text-sm font-semibold text-ink-2 @3xl:text-base"
              >
                {{ weekdayName(day.weekday, 'short') }}
              </th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="row in rows" :key="row.key">
              <th class="pr-1 text-right align-middle text-sm font-medium text-ink-3 @3xl:pr-0 @3xl:text-base">
                {{ row.label }}
              </th>

              <td v-for="day in days" :key="`${day.date}-${row.key}`" class="align-top">
                <button
                  v-if="lessonAt(day.date, row.key)"
                  type="button"
                  :data-testid="`lesson-${lessonAt(day.date, row.key)!.lesson_id}-${day.date}`"
                  class="flex min-h-12 w-full flex-col justify-center rounded-lg px-1.5 py-1.5 text-left transition-colors @3xl:min-h-16 @3xl:rounded-xl @3xl:px-3 @3xl:py-2"
                  :style="lessonAt(day.date, row.key)!.status === 'cancelled' ? {} : lessonTint(lessonAt(day.date, row.key)!.color)"
                  :class="lessonAt(day.date, row.key)!.status === 'cancelled'
                    ? 'bg-surface-2 text-ink-3'
                    : 'bg-surface text-ink hover:bg-surface-2'"
                  @click="tapped = lessonAt(day.date, row.key)"
                >
                  <span
                    class="text-xs leading-tight font-semibold break-words hyphens-auto @3xl:text-lg"
                    :class="{ 'line-through': lessonAt(day.date, row.key)!.status === 'cancelled' }"
                  >
                    {{ lessonAt(day.date, row.key)!.title }}
                  </span>
                  <span class="hidden text-sm text-ink-3 @3xl:block">
                    {{ label(lessonAt(day.date, row.key)!) }}
                  </span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <LessonExceptionDialog
      v-if="tapped"
      :lesson="tapped"
      @close="tapped = null"
      @cancel="exception(cancel)"
      @replace="replaceException"
      @revert="exception(revert)"
    />
  </div>
</template>
