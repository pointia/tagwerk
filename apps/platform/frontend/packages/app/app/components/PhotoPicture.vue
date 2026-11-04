<script setup lang="ts">
interface Props {
  url: string
  alt: string
  /** `fit` keeps the whole picture, `fill` crops it to the frame. */
  fit?: string
  /** The slow zoom of a slideshow. */
  motion?: boolean
}

const props: Props = defineProps<Props>()
</script>

<template>
  <div class="absolute inset-0 overflow-hidden">
    <!-- The same picture, enlarged and blurred, fills what the uncropped one
         leaves free — no black bars beside a portrait. -->
    <img
      v-if="props.fit !== 'fill'"
      :src="props.url"
      alt=""
      aria-hidden="true"
      class="absolute inset-0 size-full scale-110 object-cover opacity-70 blur-2xl"
    >
    <img
      :src="props.url"
      :alt="props.alt"
      draggable="false"
      class="absolute inset-0 size-full select-none"
      :class="[props.fit === 'fill' ? 'object-cover' : 'object-contain', props.motion ? 'tw-ken-burns' : '']"
    >
  </div>
</template>
