<script setup lang="ts">
/*
 * Inside a button whose action waits for the server. Appears only after a
 * moment: on a fast network nothing flickers, on a slow one the tap is
 * visibly on its way. The label stays, so the button keeps its size.
 */
const props = defineProps<{ busy: boolean }>()

const DELAY_MS = 300

const shown = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

watch(() => props.busy, (busy) => {
  clearTimeout(timer)
  shown.value = false

  if (busy) {
    timer = setTimeout(() => {
      shown.value = true
    }, DELAY_MS)
  }
}, { immediate: true })

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <TwSpinner v-if="shown" data-testid="pending" :size="18" />
</template>
