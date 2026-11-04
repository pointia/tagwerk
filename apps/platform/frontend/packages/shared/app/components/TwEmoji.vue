<script setup lang="ts">
import { emojiSrc } from '../utils/emoji'

/**
 * A task's or reward's icon, drawn from Fluent Emoji: coloured or grey, as
 * the device's `colored_emoji` switch says — unless `colored` decides, as in
 * the menu, whose own switch already chose emoji. One em in size, so the
 * text size around it sets it as it did for the plain emoji.
 *
 * Whatever the set lacks — something typed into the admin field — stays the
 * system's emoji: the 404 of the missing file switches back to text.
 */
interface Props {
  icon: string
  colored?: boolean
}

const props: Props = withDefaults(defineProps<Props>(), { colored: undefined })

const { preferences } = usePreferences()
const colored = computed(() => props.colored ?? preferences.value.colored_emoji !== 'off')
const src = computed(() => emojiSrc(props.icon, colored.value))

const missing = ref(false)
watch(src, () => {
  missing.value = false
})
</script>

<template>
  <img
    v-if="src && !missing"
    :src="src"
    alt=""
    aria-hidden="true"
    draggable="false"
    data-testid="emoji"
    loading="lazy"
    class="inline-block size-[1em] shrink-0 align-[-0.125em]"
    :class="colored ? '' : 'dark:invert'"
    @error="missing = true"
  >
  <span v-else aria-hidden="true">{{ props.icon }}</span>
</template>
