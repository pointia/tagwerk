<script setup lang="ts">
/*
 * Today's weather in the place of the mark at the top of the menu: symbol,
 * temperature now, low and high. A tap opens the hours.
 */
const { weather, load, show } = useWeather()

onMounted(load)
</script>

<template>
  <button
    type="button"
    data-testid="menu-weather"
    :aria-label="$t('weather.showHours')"
    class="flex w-full flex-col items-center rounded-2xl py-1 leading-tight tabular-nums transition-colors hover:bg-surface-2"
    @click="show()"
  >
    <template v-if="weather">
      <span aria-hidden="true" class="text-4xl leading-none">{{ weather.symbol }}</span>
      <span class="mt-1 font-display text-2xl font-semibold">
        {{ weather.current }}°<span class="text-base text-ink-3">{{ weather.unit ?? 'C' }}</span>
      </span>
      <span class="flex gap-2 text-sm">
        <span class="font-semibold">{{ weather.max }}°</span>
        <span class="text-ink-3">{{ weather.min }}°</span>
      </span>
    </template>

    <!-- Nothing to show yet, or no place set: the mark rather than a gap. -->
    <TwLogo v-else class="size-12" />
  </button>
</template>
