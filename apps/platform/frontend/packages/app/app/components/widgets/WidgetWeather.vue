<script setup lang="ts">
import { formatIsoDate } from '~/utils/calendar'

const { weather, hint, load, show } = useWeather()

onMounted(load)
</script>

<template>
  <!-- Now on top, the coming days in a row below: a tile three columns wide has no room beside it. -->
  <div class="flex h-full flex-col justify-center gap-2">
    <!-- A tap opens the hours: the tile is where people look for them. -->
    <button
      v-if="weather"
      type="button"
      data-testid="weather-open"
      :aria-label="$t('weather.showHours')"
      class="flex min-w-0 items-center gap-3 rounded-xl text-left"
      @click="show()"
    >
      <span aria-hidden="true" class="text-4xl leading-none">{{ weather.symbol }}</span>
      <span class="font-display text-4xl leading-none font-semibold tabular-nums">
        {{ weather.current }}°
      </span>
      <span class="min-w-0 text-sm leading-tight text-ink-2">
        <span class="block truncate">{{ weather.label }}</span>
        <span class="block tabular-nums">{{ weather.min }}° / {{ weather.max }}°</span>
      </span>
    </button>

    <ul
      v-if="weather && (weather.days ?? []).length > 0"
      data-testid="weather-days"
      class="grid grid-cols-3 gap-1 border-t border-line pt-2"
    >
      <li v-for="day in weather.days" :key="day.date">
        <button
          type="button"
          data-testid="weather-day"
          class="flex w-full flex-col items-center rounded-lg text-sm leading-tight tabular-nums"
          @click="show(day.date)"
        >
          <span class="font-semibold text-ink-2">
            {{ formatIsoDate(day.date, { weekday: 'short' }).replace('.', '') }}
            <span class="text-base" role="img" :aria-label="day.label">{{ day.symbol }}</span>
          </span>
          <span><span class="font-semibold">{{ day.max }}°</span> <span class="text-ink-3">{{ day.min }}°</span></span>
        </button>
      </li>
    </ul>

    <!-- No "error": on a wall in the hallway nobody can do anything about it. -->
    <p v-if="!weather" class="text-base text-ink-3">
      {{ hint ?? $t('weather.loading') }}
    </p>
  </div>
</template>
