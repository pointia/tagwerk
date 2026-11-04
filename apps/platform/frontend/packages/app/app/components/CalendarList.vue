<script setup lang="ts">
import { intlLocale } from '../../../shared/app/utils/intlLocale'
import { type AgendaEvent, type CalendarDay, formatIsoDate } from '~/utils/calendar'
import type { AgendaPerson } from '~/composables/useAgenda'

/*
 * The calendar as a list: one block per day, a week ahead. It scrolls to
 * the first event not over yet — on a phone that is what one looks for.
 */
interface Props {
  days: CalendarDay[]
  people: AgendaPerson[]
  timeZone: string
  now: Date
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{ 'pick-event': [AgendaEvent] }>()

function clockTime(moment: string): string {
  return new Intl.DateTimeFormat(intlLocale.value, { hour: '2-digit', minute: '2-digit', timeZone: props.timeZone }).format(new Date(moment))
}

function past(event: AgendaEvent): boolean {
  return new Date(event.ends_at).getTime() <= props.now.getTime()
}

function names(event: AgendaEvent): string {
  return event.people
    .map(id => props.people.find(person => person.id === id)?.name)
    .filter(Boolean)
    .join(', ')
}

const nextId = computed(() => props.days.flatMap(day => day.events).find(event => !past(event))?.id ?? null)

const list = useTemplateRef<HTMLElement>('list')

watch(nextId, async (id) => {
  if (id === null) {
    return
  }

  await nextTick()
  list.value?.querySelector(`[data-event="${id}"]`)?.scrollIntoView({ block: 'center' })
}, { immediate: true })
</script>

<template>
  <div ref="list" data-testid="calendar-list" class="h-full space-y-5 overflow-y-auto p-4">
    <section
      v-for="day in props.days"
      :key="day.iso"
      :aria-label="formatIsoDate(day.iso, { weekday: 'long', day: 'numeric', month: 'long' })"
    >
      <h3 class="mb-1.5 px-1 text-base font-bold" :class="day.isToday ? 'text-accent' : 'text-ink-2'">
        {{ day.isToday ? $t('common.today') : formatIsoDate(day.iso, { weekday: 'long', day: 'numeric', month: 'long' }) }}
        <span v-if="day.holiday" class="font-medium text-ink-3"> · {{ day.holiday.name }}</span>
      </h3>

      <p v-if="day.events.length === 0" class="px-1 text-base text-ink-3">
        {{ $t('calendar.nothing') }}
      </p>

      <ul v-else class="divide-y divide-line overflow-hidden rounded-3xl bg-canvas">
        <li v-for="event in day.events" :key="event.id" :data-event="event.id">
          <button
            type="button"
            data-testid="calendar-event"
            class="flex min-h-12 w-full items-start gap-3 px-4 py-2.5 text-left"
            :class="past(event) ? 'tw-past' : ''"
            @click="emit('pick-event', event)"
          >
            <span class="mt-2 size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: event.color ?? '#8a8276' }" />
            <span class="w-20 shrink-0 pt-0.5 text-base text-ink-2 tabular-nums">
              {{ event.all_day ? $t('event.allDayShort') : clockTime(event.starts_at) }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-lg font-medium break-words">{{ event.title }}</span>
              <span v-if="event.location || names(event)" class="block truncate text-sm text-ink-3">
                {{ [names(event), event.location].filter(Boolean).join(' · ') }}
              </span>
            </span>
          </button>
        </li>
      </ul>
    </section>
  </div>
</template>
