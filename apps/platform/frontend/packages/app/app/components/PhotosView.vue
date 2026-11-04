<script setup lang="ts">
import type { FrameEvent } from '~/components/PhotoFrame.vue'
import { photoIntervalMs, restingStyle } from '~/utils/photoFrame'

interface Props {
  /** Resting mode: picture takes the whole screen, the time in a corner. */
  bleed?: boolean
  clock?: string
  date?: string
  /** Today's coming events, for the ambient layout. */
  upcoming?: FrameEvent[]
}

const props: Props = defineProps<Props>()

/*
 * The frame as this device set it up: layout, which pictures, how long, how
 * they sit. At rest the tablet may use a layout of its own — a mosaic by
 * day, the picture beside the time while resting.
 */
const { preferences } = usePreferences()

const layout = computed(() => props.bleed
  ? restingStyle(preferences.value.photo_style, preferences.value.photo_rest_style)
  : restingStyle(preferences.value.photo_style, 'same'))

const query = computed(() => ({
  from: preferences.value.photo_source,
  albums: preferences.value.photo_source === 'albums' ? preferences.value.photo_albums : [],
}))
</script>

<template>
  <div class="flex h-full min-h-0 flex-col">
    <PhotoFrame
      :layout="layout"
      :query="query"
      :interval-ms="photoIntervalMs(preferences.photo_interval)"
      :fit="preferences.photo_fit"
      :motion="preferences.photo_motion === 'on'"
      :caption="preferences.photo_caption === 'on'"
      :clock="props.clock"
      :date="props.date"
      :overlay-clock="props.bleed || preferences.photo_clock === 'on'"
      :bleed="props.bleed"
      :interactive="!props.bleed"
      :upcoming="props.upcoming"
    />
  </div>
</template>
