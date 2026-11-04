<script setup lang="ts">
import type { PhotoStyle } from '~/utils/photoFrame'

/* A small drawing of a frame layout, for choosing one in the settings. */
interface Props {
  styleKey: PhotoStyle
}

const props: Props = defineProps<Props>()
</script>

<template>
  <span aria-hidden="true" class="relative block aspect-[16/10] overflow-hidden rounded-xl bg-black/80">
    <span v-if="props.styleKey === 'single'" class="absolute inset-0 bg-linear-to-br from-accent/80 to-accent/30">
      <span class="absolute right-2 bottom-1.5 text-[0.7rem] font-light text-white">07:42</span>
    </span>

    <span v-else-if="props.styleKey === 'duo'" class="absolute inset-0 flex gap-0.5">
      <span class="flex-1 bg-linear-to-b from-accent/80 to-accent/40" />
      <span class="flex-1 bg-linear-to-b from-accent/40 to-accent/70" />
    </span>

    <span v-else-if="props.styleKey === 'mosaic'" class="absolute inset-0 grid grid-cols-4 grid-rows-2 gap-0.5">
      <span class="col-span-2 row-span-2 bg-accent/80" />
      <span class="bg-accent/50" />
      <span class="bg-accent/35" />
      <span class="bg-accent/60" />
      <span class="bg-accent/40" />
    </span>

    <span v-else-if="props.styleKey === 'ambient'" class="absolute inset-0 grid grid-cols-[1.6fr_1fr]">
      <span class="bg-linear-to-br from-accent/80 to-accent/40" />
      <span class="flex flex-col justify-center gap-1 px-1.5">
        <span class="text-[0.7rem] leading-none font-light text-white">07:42</span>
        <span class="h-0.5 w-full rounded bg-white/50" />
        <span class="h-0.5 w-3/4 rounded bg-white/35" />
      </span>
    </span>

    <span v-else class="absolute inset-0 flex flex-col items-center justify-center bg-linear-to-br from-accent/70 to-accent/30">
      <span class="absolute inset-x-1.5 top-1.5 flex gap-0.5">
        <span class="h-0.5 flex-1 rounded bg-white" />
        <span class="h-0.5 flex-1 rounded bg-white/40" />
        <span class="h-0.5 flex-1 rounded bg-white/40" />
      </span>
      <span class="h-0.5 w-6 rounded bg-white/70" />
      <span class="mt-1 h-1.5 w-12 rounded bg-white" />
    </span>
  </span>
</template>
