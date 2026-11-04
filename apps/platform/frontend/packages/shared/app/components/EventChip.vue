<script setup lang="ts">
import { intlLocale } from '../utils/intlLocale'
import { tint } from '../utils/tint'
import type { AgendaEvent, CalendarPerson } from '../utils/calendar'

/*
 * `wrap`: in the week view the column is narrow but tall — there the title
 * wraps instead of shrinking to "Teambespr…" at 1280 px. In the month the
 * row counts, so it stays truncated there.
 */
interface Props {
  event: AgendaEvent
  timeZone: string
  wrap?: boolean
  people?: CalendarPerson[]
}

/*
 * Annotated on purpose: in the apps' type check the macro's result is `any`
 * for components that live in this layer.
 */
const props: Props = defineProps<Props>()

const color = computed(() => props.event.color ?? '#8a8276')

// Events only carry people ids; the faces come from the agenda's people.
const involved = computed(() => {
  return props.event.people
    .map(id => (props.people ?? []).find(person => person.id === id))
    .filter((person): person is CalendarPerson => person !== undefined)
    // More than three faces are no longer readable in a calendar row.
    .slice(0, 3)
})

const clockTime = computed(() => {
  if (props.event.all_day) {
    return null
  }

  // Without timeZone a device in a foreign time zone would show the shifted
  // time — 09:30 would become 07:30 in UTC.
  return new Date(props.event.starts_at).toLocaleTimeString(intlLocale.value, {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: props.timeZone,
  })
})
</script>

<template>
  <!-- A button, so events can also be reached by keyboard. -->
  <button
    type="button"
    data-testid="event"
    class="flex w-full items-baseline gap-1.5 overflow-hidden rounded-lg py-0.5 pr-1.5 pl-2 text-left text-base leading-snug text-ink transition-[filter] hover:brightness-95"
    :style="{ background: tint(color + '2e', 'solid'), boxShadow: `inset 3px 0 0 ${color}` }"
  >
    <span v-if="clockTime" class="shrink-0 text-ink-2 tabular-nums">{{ clockTime }}</span>
    <span class="font-medium" :class="wrap ? 'min-w-0 break-words' : 'truncate'">{{ event.title }}</span>

    <!-- Overlapping like playing cards: that leaves enough room for the
         title with several participants. -->
    <span v-if="involved.length > 0" class="ml-auto flex shrink-0 self-center -space-x-1.5">
      <TwAvatar
        v-for="person in involved"
        :key="person.id"
        :name="person.name"
        :color="person.color"
        :avatar="person.avatar"
        :size="20"
      />
    </span>
  </button>
</template>
