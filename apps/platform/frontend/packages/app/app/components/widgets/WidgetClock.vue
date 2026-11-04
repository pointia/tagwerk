<script setup lang="ts">
import { intlLocale } from '../../../../shared/app/utils/intlLocale'
import { clockText } from '~/utils/clockTools'

const props = defineProps<{ timeZone: string }>()

// The window lives in app.vue, so a timer rings on whatever area is shown.
const { open, stopwatch, timer, elapsed, remaining } = useClockTools()

const now = ref(new Date())

let clock: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  clock = setInterval(() => (now.value = new Date()), 10_000)
})

onBeforeUnmount(() => {
  if (clock !== null) {
    clearInterval(clock)
  }
})

const time = computed(() => new Intl.DateTimeFormat(intlLocale.value, {
  hour: '2-digit',
  minute: '2-digit',
  timeZone: props.timeZone,
}).format(now.value))

// Short weekday: the long one pushed the month out of a two-row tile.
const date = computed(() => new Intl.DateTimeFormat(intlLocale.value, {
  weekday: 'short',
  day: 'numeric',
  month: 'long',
  timeZone: props.timeZone,
}).format(now.value))
</script>

<template>
  <!--
    Date and buttons share the row below the time: stacked, all three cut off a
    two-row tile. On a phone the stacked tile is lower still, so the buttons
    move beside time and date.
  -->
  <div class="grid h-full grid-cols-[minmax(0,1fr)_auto] content-center items-center gap-x-3 gap-y-1 sm:gap-y-2">
    <p class="col-start-1 row-start-1 font-display text-4xl leading-none font-semibold tabular-nums sm:col-span-2 sm:text-5xl">
      {{ time }}
    </p>

    <p class="col-start-1 row-start-2 truncate text-base text-ink-2">
      {{ date }}
    </p>

    <div class="col-start-2 row-span-2 row-start-1 flex items-center gap-2 sm:row-span-1 sm:row-start-2">

      <!-- A running clock shows its time in its button, so nobody has to open it to look. -->
      <button
        type="button"
        data-testid="clock-open-stopwatch"
        :aria-label="$t('clock.stopwatch')"
        class="flex min-h-11 items-center gap-1.5 rounded-full px-3 text-sm font-semibold tabular-nums transition-colors"
        :class="stopwatch.startedAt !== null ? 'bg-accent-soft text-accent' : 'bg-surface-2 text-ink-2 hover:text-ink'"
        @pointerdown.stop
        @click="open = 'stopwatch'"
      >
        <TwIcon name="stopwatch" :size="20" />
        <span v-if="stopwatch.startedAt !== null">{{ clockText(elapsed) }}</span>
      </button>

      <button
        type="button"
        data-testid="clock-open-timer"
        :aria-label="$t('clock.timer')"
        class="flex min-h-11 items-center gap-1.5 rounded-full px-3 text-sm font-semibold tabular-nums transition-colors"
        :class="timer.endsAt !== null ? 'bg-accent-soft text-accent' : 'bg-surface-2 text-ink-2 hover:text-ink'"
        @pointerdown.stop
        @click="open = 'timer'"
      >
        <TwIcon name="timer" :size="20" />
        <span v-if="timer.endsAt !== null">{{ clockText(remaining, 'up') }}</span>
      </button>
    </div>
  </div>
</template>
