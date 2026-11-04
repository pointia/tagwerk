<script setup lang="ts">
import { intlLocale } from '../../../shared/app/utils/intlLocale'
import { type AgendaEvent, formatIsoDate, zonedIso } from '~/utils/calendar'
import type { AgendaPerson } from '~/composables/useAgenda'

/*
 * An event to read before changing it. An account sees this first — most
 * taps on a phone only want to know when and where; changing needs the
 * event's own `can.edit`.
 */
interface Props {
  event: AgendaEvent
  people: AgendaPerson[]
  timeZone: string
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{ close: [], edit: [] }>()

useEscape(() => emit('close'))

function clockTime(moment: string): string {
  return new Intl.DateTimeFormat(intlLocale.value, { hour: '2-digit', minute: '2-digit', timeZone: props.timeZone }).format(new Date(moment))
}

const names = computed(() => props.event.people
  .map(id => props.people.find(person => person.id === id)?.name)
  .filter(Boolean)
  .join(', '))
</script>

<template>
  <div class="fixed inset-0 z-30 flex items-end justify-center bg-scrim sm:items-center" @click.self="emit('close')">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="event-title"
      class="w-full rounded-t-[2rem] bg-surface px-6 pt-3 pb-[calc(1.5rem+env(safe-area-inset-bottom))] shadow-floating sm:max-w-md sm:rounded-[2rem] sm:pt-6"
    >
      <div class="mx-auto mb-5 h-1.5 w-12 rounded-full bg-surface-3 sm:hidden" aria-hidden="true" />

      <h2 id="event-title" class="font-display text-2xl font-semibold break-words">
        {{ props.event.title }}
      </h2>

      <p class="mt-1 text-base text-ink-2">
        {{ formatIsoDate(zonedIso(new Date(props.event.starts_at), props.timeZone), { weekday: 'long', day: 'numeric', month: 'long' }) }}
        <template v-if="!props.event.all_day">
          · {{ clockTime(props.event.starts_at) }} – {{ clockTime(props.event.ends_at) }}
        </template>
      </p>

      <p v-if="props.event.location" class="mt-1 text-base text-ink-2">
        {{ props.event.location }}
      </p>

      <p v-if="names" class="mt-1 text-base text-ink-2">
        {{ names }}
      </p>

      <button
        v-if="props.event.can?.edit"
        type="button"
        data-testid="event-edit"
        class="mt-6 min-h-12 w-full rounded-2xl bg-accent text-lg font-semibold text-accent-ink"
        @click="emit('edit')"
      >
        {{ $t('event.edit') }}
      </button>

      <button
        type="button"
        class="min-h-12 w-full rounded-2xl bg-surface-2 text-lg font-semibold"
        :class="props.event.can?.edit ? 'mt-3' : 'mt-6'"
        @click="emit('close')"
      >
        {{ $t('common.close') }}
      </button>
    </div>
  </div>
</template>
