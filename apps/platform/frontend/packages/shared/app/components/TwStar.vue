<script setup lang="ts">
/*
 * Every star the household earns, drawn the way this device is set up:
 * outline or filled, in the text's colour or in gold. Sized by the font, so
 * it sits in a line of text like the emoji it replaced.
 */
const { preferences } = usePreferences()

const filled = computed(() => preferences.value.star_filled === 'on')
const golden = computed(() => preferences.value.star_color === 'on')
</script>

<template>
  <TwIcon
    name="points"
    data-testid="star"
    :data-filled="preferences.star_filled"
    :data-color="preferences.star_color"
    :fill="filled ? 'currentColor' : 'none'"
    class="inline-block size-[1em] align-[-0.125em]"
    :class="golden ? 'text-star' : ''"
  />
</template>
