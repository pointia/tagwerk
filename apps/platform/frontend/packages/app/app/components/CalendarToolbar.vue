<script setup lang="ts">
import type { CalendarView } from '~/utils/calendarView'

/*
 * Paging, view and "new" for the calendar.
 *
 * `compact` is the phone's: one row like the tasks' — paging, filter, new —
 * with view, school band and the people (default slot) in a sheet behind
 * the filter. Wrapped onto three rows they took half the screen.
 */
interface Props {
  view: CalendarView
  views: CalendarView[]
  backLabel: string
  forwardLabel: string
  bandAvailable: boolean
  bandOn: boolean
  canCreate: boolean
  compact?: boolean
  /** Something in the sheet narrows what is shown: a dot on the button. */
  filtered?: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  page: [-1 | 1]
  today: []
  view: [CalendarView]
  create: []
  band: []
}>()

const { t } = useI18n()

const LABELS = computed((): Record<CalendarView, string> => ({
  day: t('calendar.views.day'),
  three: t('calendar.views.three'),
  week: t('calendar.views.week'),
  month: t('calendar.views.month'),
  list: t('tasks.views.list'),
}))

const filterOpen = ref(false)
useEscape(() => { filterOpen.value = false }, () => filterOpen.value)
</script>

<template>
  <div v-if="compact" class="flex items-center gap-2">
    <div class="flex min-w-0 flex-1 items-center gap-1 rounded-2xl bg-surface-2 p-1">
      <button
        type="button"
        data-testid="calendar-back"
        :aria-label="backLabel"
        class="flex size-11 items-center justify-center rounded-xl text-ink-2"
        @click="emit('page', -1)"
      >
        <TwIcon name="back" :size="20" />
      </button>

      <button
        type="button"
        class="min-h-11 min-w-0 flex-1 truncate rounded-xl text-base font-semibold"
        @click="emit('today')"
      >
        {{ $t('common.today') }}
      </button>

      <button
        type="button"
        data-testid="calendar-forward"
        :aria-label="forwardLabel"
        class="flex size-11 items-center justify-center rounded-xl text-ink-2"
        @click="emit('page', 1)"
      >
        <TwIcon name="forward" :size="20" />
      </button>
    </div>

    <button
      type="button"
      data-testid="calendar-filter"
      :data-active="filtered"
      :aria-label="filtered ? $t('tasks.filters.filterActive') : $t('tasks.filters.filter')"
      class="relative flex size-13 shrink-0 items-center justify-center rounded-2xl bg-surface-2 text-ink-2"
      @click="filterOpen = true"
    >
      <TwIcon name="filter" :size="22" />
      <span v-if="filtered" aria-hidden="true" class="absolute top-2.5 right-2.5 size-2.5 rounded-full bg-accent" />
    </button>

    <button
      v-if="canCreate"
      type="button"
      data-testid="event-new"
      :aria-label="$t('event.new')"
      class="flex size-13 shrink-0 items-center justify-center rounded-2xl bg-accent text-accent-ink"
      @click="emit('create')"
    >
      <TwIcon name="plus" :size="24" />
    </button>

    <div
      v-if="filterOpen"
      class="fixed inset-0 z-30 flex items-end bg-scrim"
      @click.self="filterOpen = false"
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="calendar-filter-title"
        data-testid="calendar-filter-sheet"
        class="max-h-[85svh] w-full space-y-5 overflow-y-auto rounded-t-[2rem] bg-surface px-5 pt-6 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-floating"
      >
        <h2 id="calendar-filter-title" class="font-display text-2xl font-semibold">
          {{ $t('tasks.filters.filter') }}
        </h2>

        <div role="group" :aria-label="$t('calendar.view')" class="flex gap-1 rounded-2xl bg-surface-2 p-1">
          <button
            v-for="value in views"
            :key="value"
            type="button"
            data-testid="calendar-view"
            :data-value="value"
            :aria-pressed="view === value"
            class="min-h-12 flex-1 rounded-xl text-base font-semibold transition-colors"
            :class="view === value ? 'bg-surface text-ink shadow-card' : 'text-ink-2'"
            @click="emit('view', value)"
          >
            {{ LABELS[value] }}
          </button>
        </div>

        <button
          v-if="bandAvailable && view !== 'list'"
          type="button"
          role="switch"
          data-testid="school-band"
          :aria-checked="bandOn"
          class="flex min-h-12 w-full items-center gap-3 rounded-2xl bg-surface-2 px-4 py-3 text-left"
          @click="emit('band')"
        >
          <SwitchKnob :on="bandOn" />
          <span class="text-lg font-semibold">{{ $t('calendar.schoolBand') }}</span>
        </button>

        <slot />

        <button
          type="button"
          data-testid="calendar-filter-close"
          class="min-h-14 w-full rounded-2xl bg-accent text-lg font-semibold text-accent-ink"
          @click="filterOpen = false"
        >
          {{ $t('tasks.filters.done') }}
        </button>
      </div>
    </div>
  </div>

  <div v-else class="flex flex-wrap items-center gap-3">
    <div class="flex items-center gap-1 rounded-2xl bg-surface-2 p-1">
      <button
        type="button"
        data-testid="calendar-back"
        :aria-label="backLabel"
        class="flex size-12 items-center justify-center rounded-xl text-ink-2 transition-colors hover:bg-surface hover:text-ink"
        @click="emit('page', -1)"
      >
        <TwIcon name="back" :size="24" />
      </button>

      <button
        type="button"
        class="min-h-12 rounded-xl px-4 text-lg font-semibold transition-colors hover:bg-surface"
        @click="emit('today')"
      >
        {{ $t('common.today') }}
      </button>

      <button
        type="button"
        data-testid="calendar-forward"
        :aria-label="forwardLabel"
        class="flex size-12 items-center justify-center rounded-xl text-ink-2 transition-colors hover:bg-surface hover:text-ink"
        @click="emit('page', 1)"
      >
        <TwIcon name="forward" :size="24" />
      </button>
    </div>

    <div role="group" :aria-label="$t('calendar.view')" class="flex gap-1 rounded-2xl bg-surface-2 p-1">
      <button
        v-for="value in views"
        :key="value"
        type="button"
        data-testid="calendar-view"
        :data-value="value"
        :aria-pressed="view === value"
        class="min-h-12 rounded-xl px-5 text-lg font-semibold transition-colors"
        :class="view === value ? 'bg-surface text-ink shadow-card' : 'text-ink-2 hover:text-ink'"
        @click="emit('view', value)"
      >
        {{ LABELS[value] }}
      </button>
    </div>

    <!--
      Creating used to work only by tapping a day in the grid.
      Nobody knows that who has not watched someone do it.
    -->
    <button
      v-if="canCreate"
      type="button"
      data-testid="event-new"
      :aria-label="$t('event.new')"
      class="flex size-12 items-center justify-center rounded-2xl bg-accent text-accent-ink transition-opacity hover:opacity-90"
      @click="emit('create')"
    >
      <TwIcon name="plus" :size="24" />
    </button>

    <button
      v-if="bandAvailable && view !== 'list'"
      type="button"
      data-testid="school-band"
      :aria-pressed="bandOn"
      :aria-label="$t('calendar.schoolBand')"
      class="flex size-12 items-center justify-center rounded-2xl transition-colors"
      :class="bandOn ? 'bg-accent text-accent-ink' : 'bg-surface-2 text-ink-2 hover:text-ink'"
      @click="emit('band')"
    >
      <TwIcon name="school" :size="24" />
    </button>
  </div>
</template>
