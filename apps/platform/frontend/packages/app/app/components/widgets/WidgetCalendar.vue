<script setup lang="ts">
import { intlLocale } from '../../../../shared/app/utils/intlLocale'
import type { AgendaEvent } from '~/utils/calendar'
import { dayGrid, formatIsoDate } from '~/utils/calendar'

const { t } = useI18n()

const props = defineProps<{ timeZone: string, today: string, settings?: Record<string, unknown> }>()

// A tapped event opens the same dialog as in the calendar.
const emit = defineEmits<{ 'pick-event': [AgendaEvent] }>()

const { agenda } = useAgenda()

/*
 * Today as an hour grid by default: where something stands says more at a
 * glance than a line of text. The list stays as a tile setting.
 */
const view = computed(() => (props.settings?.view === 'list' ? 'list' : 'day'))

// The now line and the shown hours follow the clock without a reload.
const now = ref(new Date())
let timer: ReturnType<typeof setInterval> | undefined

onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date()
  }, 60_000)
})

onBeforeUnmount(() => clearInterval(timer))

const days = computed(() => dayGrid(now.value, {
  events: agenda.value?.events ?? [],
  holidays: [],
  today: now.value,
  timeZone: props.timeZone,
}))

/** What is on today and later — past helps nobody on the wall. */
const nextIndex = computed(() => (agenda.value?.events ?? [])
  .filter(event => event.ends_at.slice(0, 10) >= props.today)
  .sort((a, b) => a.starts_at.localeCompare(b.starts_at))
  .slice(0, 6))

function when(event: { starts_at: string, all_day: boolean }): string {
  const day = event.starts_at.slice(0, 10)
  const dayText = day === props.today ? t('common.today') : formatIsoDate(day, { weekday: 'short', day: 'numeric', month: 'short' })

  if (event.all_day) {
    return dayText
  }

  const clock = new Intl.DateTimeFormat(intlLocale.value, {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: props.timeZone,
  }).format(new Date(event.starts_at))

  return `${dayText} · ${clock}`
}
</script>

<template>
  <TimeGrid
    v-if="view === 'day'"
    data-testid="widget-calendar-grid"
    class="h-full"
    :days="days"
    :time-zone="timeZone"
    :now="now"
    :people="agenda?.people ?? []"
    :hour-height="44"
    follow
    @pick-event="emit('pick-event', $event)"
  />

  <template v-else>
    <ul v-if="nextIndex.length > 0" class="space-y-1.5 overflow-y-auto">
      <li v-for="event in nextIndex" :key="event.id">
        <button
          type="button"
          data-testid="widget-calendar-event"
          class="flex w-full items-start gap-2.5 rounded-xl px-1 py-0.5 text-left transition-colors hover:bg-surface-2"
          @click="emit('pick-event', event)"
        >
          <span class="mt-1.5 size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: event.color ?? '#8a8276' }" />
          <span class="min-w-0 flex-1">
            <span class="block truncate text-lg font-medium">{{ event.title }}</span>
            <span class="block text-sm text-ink-3">{{ when(event) }}</span>
          </span>
        </button>
      </li>
    </ul>
    <p v-else class="text-base text-ink-3">
      {{ $t('calendar.nothing') }}
    </p>
  </template>
</template>
