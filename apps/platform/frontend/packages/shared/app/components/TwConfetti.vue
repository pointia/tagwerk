<script setup lang="ts">
import { isMotionStill } from '../utils/motion'

/**
 * Confetti for the moment the last check mark is set.
 *
 * Drawn by hand instead of from a package: a rain of rectangles plus two
 * cannons in the lower corners — not worth a dependency that has to be
 * built and maintained along with it.
 *
 * With motion off (`isMotionStill`) none of it shows — for some people
 * this is not fun but nausea.
 */
const props = withDefaults(defineProps<{
  /** Colours of the snippets — usually those of the people. */
  colors?: string[]
  durationMs?: number
  /** How many snippets; a third of them shot up from the lower corners. */
  count?: number
}>(), {
  colors: () => ['#f59e0b', '#ef4444', '#10b981', '#3b82f6', '#a855f7'],
  durationMs: 3600,
  count: 160,
})

const emit = defineEmits<{ finished: [] }>()

const canvas = ref<HTMLCanvasElement | null>(null)

interface Snippet {
  x: number
  y: number
  width: number
  height: number
  color: string
  vx: number
  vy: number
  rotation: number
  spin: number
  /** Phase of the flutter, so they do not all sway in step. */
  flutter: number
}

const GRAVITY = 0.12
const TERMINAL = 4.5

onMounted(() => {
  const element = canvas.value

  if (!element) {
    return
  }

  if (isMotionStill()) {
    emit('finished')

    return
  }

  const pencil = element.getContext('2d')

  if (!pencil) {
    emit('finished')

    return
  }

  const width = element.width = element.offsetWidth
  const height = element.height = element.offsetHeight
  const color = (): string => props.colors[Math.floor(Math.random() * props.colors.length)] ?? '#f59e0b'
  const cannons = Math.round(props.count / 3)

  const snippets: Snippet[] = Array.from({ length: props.count }, (_, index) => {
    const shape = { width: 6 + Math.random() * 7, height: 10 + Math.random() * 9, color: color(), rotation: Math.random() * Math.PI, spin: -0.15 + Math.random() * 0.3, flutter: Math.random() * Math.PI * 2 }

    // Two cannons in the lower corners, aimed up and inwards.
    if (index < cannons) {
      const left = index % 2 === 0
      const speed = 12 + Math.random() * 6
      const tilt = 0.2 + Math.random() * 0.4

      return { ...shape, x: left ? 0 : width, y: height, vx: Math.sin(tilt) * speed * (left ? 1 : -1), vy: -Math.cos(tilt) * speed }
    }

    // The rest rains from above the edge, so it falls in instead of popping up.
    return { ...shape, x: Math.random() * width, y: -Math.random() * height * 0.8, vx: -1 + Math.random() * 2, vy: 2 + Math.random() * 2 }
  })

  const start = performance.now()
  let requested = 0

  function draw(now: number): void {
    if (!pencil) {
      return
    }

    const elapsed = now - start

    pencil.clearRect(0, 0, width, height)

    for (const part of snippets) {
      part.vy = Math.min(TERMINAL, part.vy + GRAVITY)
      part.vx *= 0.99
      part.x += part.vx + Math.sin(elapsed / 220 + part.flutter) * 0.8
      part.y += part.vy
      part.rotation += part.spin

      pencil.save()
      pencil.translate(part.x, part.y)
      pencil.rotate(part.rotation)

      // Fade out over the last third so the snippets do not vanish abruptly.
      pencil.globalAlpha = Math.max(0, Math.min(1, 3 * (1 - elapsed / props.durationMs)))
      pencil.fillStyle = part.color
      // Squashed by the flutter: a snippet turning over, not a flat card.
      pencil.scale(1, Math.cos(elapsed / 160 + part.flutter))
      pencil.fillRect(-part.width / 2, -part.height / 2, part.width, part.height)
      pencil.restore()
    }

    if (elapsed < props.durationMs) {
      requested = requestAnimationFrame(draw)

      return
    }

    pencil.clearRect(0, 0, width, height)
    emit('finished')
  }

  requested = requestAnimationFrame(draw)

  onBeforeUnmount(() => cancelAnimationFrame(requested))
})
</script>

<template>
  <canvas
    ref="canvas"
    aria-hidden="true"
    class="pointer-events-none fixed inset-0 z-50 size-full"
  />
</template>
