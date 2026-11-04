<script setup lang="ts">
import { photoIntervalMs, tileStyle } from '~/utils/photoFrame'

/*
 * Pictures on the start page, like a photo widget: the layout follows the
 * tile's size — one picture, two side by side, a mosaic — and the tile
 * draws from its own source (`settings.from`, `settings.albums`, or
 * `settings.memories`), so two tiles may show different albums.
 */
interface Props {
  tileId: number
  w: number
  h: number
  settings: Record<string, unknown>
}

const props: Props = defineProps<Props>()
const { preferences } = usePreferences()

const albums = computed(() => Array.isArray(props.settings.albums)
  ? props.settings.albums.filter((id): id is number => typeof id === 'number')
  : [])

const query = computed(() => ({
  from: props.settings.from === 'favorites' || (props.settings.from === 'albums' && albums.value.length > 0)
    ? String(props.settings.from)
    : 'all',
  albums: props.settings.from === 'albums' ? albums.value : [],
}))

const layout = computed(() => props.settings.memories === true ? 'memories' : tileStyle(props.w, props.h))
</script>

<template>
  <PhotoFrame
    :layout="layout"
    :query="query"
    :interval-ms="photoIntervalMs(preferences.photo_interval)"
    fit="fill"
    :motion="preferences.photo_motion === 'on'"
    :scope="`tile-${props.tileId}`"
    compact
  />
</template>
