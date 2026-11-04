<script setup lang="ts">
import { formatIsoDate } from '../utils/calendar'

/*
 * The hours of one day, from the weather tile or the menu. Today starts at
 * the current hour; the tabs above switch to the coming days.
 */
const { t } = useI18n()
const { weather, openDay, allDays, close } = useWeather()

useEscape(close)

const day = computed(() => allDays.value.find(entry => entry.date === openDay.value) ?? allDays.value[0] ?? null)

const hours = computed(() => day.value?.hours ?? [])
const isToday = computed(() => day.value?.date === weather.value?.today?.date)

/** One column per hour; the curve's points sit in their middles. */
const COLUMN = 64
const CURVE_HEIGHT = 84
/** Room above the highest point for its label, below the lowest for the line. */
const TOP = 30
const BOTTOM = 10

const curve = computed(() => {
  const temperatures = hours.value.map(hour => hour.temperature)
  const high = Math.max(...temperatures)
  const low = Math.min(...temperatures)
  const span = high - low || 1
  const width = hours.value.length * COLUMN

  const points = hours.value.map((hour, index) => ({
    x: index * COLUMN + COLUMN / 2,
    // A flat day stays in the middle instead of glued to the top.
    y: high === low
      ? (TOP + CURVE_HEIGHT - BOTTOM) / 2
      : TOP + ((high - hour.temperature) / span) * (CURVE_HEIGHT - TOP - BOTTOM),
    temperature: hour.temperature,
  }))

  const line = points.map(point => `${point.x},${point.y}`).join(' ')
  const first = points[0]
  const last = points[points.length - 1]
  const area = first && last
    ? `M${first.x},${CURVE_HEIGHT} L${line.replaceAll(' ', ' L')} L${last.x},${CURVE_HEIGHT} Z`
    : ''

  return { width, points, line, area }
})

/*
 * A coming day opens at six in the morning: its night hours are rarely what
 * anyone asks for. Today already starts at the current hour.
 */
const strip = ref<HTMLElement | null>(null)

watch(() => day.value?.date, async () => {
  await nextTick()

  if (strip.value === null) {
    return
  }

  const morning = isToday.value ? -1 : hours.value.findIndex(hour => hour.time >= '06:00')

  strip.value.scrollLeft = Math.max(morning, 0) * COLUMN
}, { immediate: true })

/*
 * A finger swipes the strip by itself; a mouse drags it the same way, since
 * the scrollbar is hidden.
 */
let drag: { x: number, left: number } | null = null

function grab(event: PointerEvent): void {
  if (event.pointerType !== 'mouse' || strip.value === null) {
    return
  }

  drag = { x: event.clientX, left: strip.value.scrollLeft }
  strip.value.setPointerCapture(event.pointerId)
}

function pull(event: PointerEvent): void {
  if (drag !== null && strip.value !== null) {
    strip.value.scrollLeft = drag.left - (event.clientX - drag.x)
  }
}

function release(): void {
  drag = null
}

function tabLabel(date: string, index: number): string {
  return index === 0 && weather.value?.today?.date === date
    ? t('common.today')
    : formatIsoDate(date, { weekday: 'short' }).replace('.', '')
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim p-6" @click.self="close">
    <div
      role="dialog"
      aria-modal="true"
      :aria-label="$t('weather.title')"
      data-testid="weather-dialog"
      class="flex max-h-full w-full max-w-xl flex-col rounded-3xl bg-surface p-6 shadow-floating"
    >
      <div class="flex items-center gap-3">
        <div role="tablist" :aria-label="$t('weather.days')" class="flex flex-1 gap-1 rounded-2xl bg-surface-2 p-1">
          <button
            v-for="(entry, index) in allDays"
            :key="entry.date"
            type="button"
            role="tab"
            data-testid="weather-tab"
            :aria-selected="day?.date === entry.date"
            class="flex min-h-12 flex-1 items-center justify-center gap-1.5 rounded-xl text-base font-semibold transition-colors"
            :class="day?.date === entry.date ? 'bg-surface text-ink shadow-card' : 'text-ink-2 hover:text-ink'"
            @click="openDay = entry.date"
          >
            <span aria-hidden="true">{{ entry.symbol }}</span>
            {{ tabLabel(entry.date, index) }}
          </button>
        </div>

        <button
          type="button"
          :aria-label="$t('common.close')"
          class="flex size-12 shrink-0 items-center justify-center rounded-full text-ink-2 transition-colors hover:bg-surface-2"
          @click="close"
        >
          <TwIcon name="close" :size="22" />
        </button>
      </div>

      <template v-if="day">
        <div class="mt-5 flex items-center gap-4">
          <span aria-hidden="true" class="text-5xl leading-none">{{ day.symbol }}</span>
          <div class="min-w-0 flex-1">
            <p class="font-display text-2xl font-semibold">
              {{ formatIsoDate(day.date, { weekday: 'long', day: 'numeric', month: 'long' }) }}
            </p>
            <p class="text-base text-ink-2">
              {{ day.label }}
            </p>
          </div>
          <p class="text-right text-base leading-tight tabular-nums">
            <span class="block font-display text-2xl font-semibold">{{ day.max }}°</span>
            <span class="block text-ink-3">{{ day.min }}°</span>
          </p>
        </div>

        <p
          v-if="day.sunshine != null || day.rain != null"
          class="mt-3 flex gap-5 text-base text-ink-2"
        >
          <span v-if="day.sunshine != null">☀️ {{ $t('weather.sunshine', { hours: day.sunshine }) }}</span>
          <span v-if="day.rain != null">💧 {{ $t('weather.rain', { percent: day.rain }) }}</span>
        </p>

        <!-- A strip to swipe through, as weather apps do: the temperature
             reads as a curve instead of a column of numbers. -->
        <div
          v-if="hours.length > 0"
          ref="strip"
          data-testid="weather-hours"
          class="strip mt-4 cursor-grab touch-pan-x overflow-x-auto overscroll-x-contain border-t border-line pt-4 pb-2 select-none active:cursor-grabbing"
          @pointerdown="grab"
          @pointermove="pull"
          @pointerup="release"
          @pointercancel="release"
        >
          <div class="w-max px-8">
            <ol class="flex">
              <li
                v-for="(hour, index) in hours"
                :key="hour.time"
                data-testid="weather-hour"
                class="flex w-16 flex-col items-center gap-1 tabular-nums"
              >
                <span class="text-sm" :class="index === 0 && isToday ? 'font-bold text-ink' : 'font-medium text-ink-2'">
                  {{ hour.time }}
                </span>
                <span class="text-3xl leading-none" role="img" :aria-label="hour.label">{{ hour.symbol }}</span>
              </li>
            </ol>

            <svg
              aria-hidden="true"
              :width="curve.width"
              :height="CURVE_HEIGHT"
              :viewBox="`0 0 ${curve.width} ${CURVE_HEIGHT}`"
              class="mt-1 block"
            >
              <path :d="curve.area" class="fill-accent/10" />
              <polyline
                :points="curve.line"
                fill="none"
                stroke-width="2.5"
                stroke-linejoin="round"
                stroke-linecap="round"
                class="stroke-accent"
              />
              <g v-for="point in curve.points" :key="point.x">
                <circle :cx="point.x" :cy="point.y" r="3.5" class="fill-accent" />
                <text
                  :x="point.x"
                  :y="point.y - 10"
                  text-anchor="middle"
                  class="fill-ink text-base font-semibold"
                >{{ point.temperature }}°</text>
              </g>
            </svg>

            <ol class="flex" aria-hidden="true">
              <li
                v-for="hour in hours"
                :key="hour.time"
                class="flex h-6 w-16 items-center justify-center text-xs font-medium text-ink-3 tabular-nums"
              >
                <template v-if="hour.rain">💧{{ hour.rain }} %</template>
              </li>
            </ol>
          </div>
        </div>

        <p v-else class="mt-4 text-base text-ink-3">
          {{ $t('weather.noHours') }}
        </p>
      </template>
    </div>
  </div>
</template>

<style scoped>
/*
 * No scrollbar under the hours, and the edges fade out instead of cutting
 * an hour in half: what runs out of sight reads as "more to swipe".
 */
.strip {
  scrollbar-width: none;
  mask-image: linear-gradient(to right, transparent, black 2rem, black calc(100% - 2rem), transparent);
}

.strip::-webkit-scrollbar {
  display: none;
}
</style>
