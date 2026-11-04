<script setup lang="ts">
import { isMotionStill } from '../utils/motion'

/**
 * Stars, fireworks and a "Yay!" when something gets ticked.
 *
 * Drawn on a canvas like the confetti: a few dozen particles, no package.
 * With motion off (`isMotionStill`) only the word stays — the
 * praise is the point, the sparks are the extra.
 *
 * A day bonus is the big one: longer, a sky full of rockets, a rain of
 * stars and the confetti cannons on top.
 */
const { t } = useI18n()
const { current, done } = useCheer()

const canvas = ref<HTMLCanvasElement | null>(null)

const DURATION_MS = 1600
const BONUS_MS = 4200
const COLORS = ['#fbbf24', '#f59e0b', '#ef4444', '#10b981', '#3b82f6', '#a855f7', '#ec4899']

const words = computed(() => [t('cheer.yay'), t('cheer.super'), t('cheer.great'), t('cheer.wow'), t('cheer.done')])
const word = ref('')

interface Spark {
  x: number
  y: number
  vx: number
  vy: number
  size: number
  color: string
  star: boolean
  delay: number
}

function pick<T>(list: T[]): T {
  return list[Math.floor(Math.random() * list.length)]!
}

/** A burst of sparks around a point, starting after `delay` milliseconds. */
function burst(x: number, y: number, count: number, speed: number, star: boolean, delay: number): Spark[] {
  return Array.from({ length: count }, (_, index) => {
    const angle = (index / count) * Math.PI * 2 + Math.random() * 0.4
    const velocity = speed * (0.55 + Math.random() * 0.6)

    return {
      x,
      y,
      vx: Math.cos(angle) * velocity,
      vy: Math.sin(angle) * velocity,
      size: star ? 10 + Math.random() * 9 : 3 + Math.random() * 3,
      color: pick(COLORS),
      star,
      delay,
    }
  })
}

function drawStar(pencil: CanvasRenderingContext2D, x: number, y: number, radius: number): void {
  pencil.beginPath()

  for (let point = 0; point < 10; point++) {
    const angle = (point * Math.PI) / 5 - Math.PI / 2
    const length = point % 2 === 0 ? radius : radius * 0.45

    pencil.lineTo(x + Math.cos(angle) * length, y + Math.sin(angle) * length)
  }

  pencil.closePath()
  pencil.fill()
}

let frame = 0
let timer: ReturnType<typeof setTimeout> | undefined

/** Rockets across the sky one after another, then stars raining down. */
function bonusSparks(width: number, height: number): Spark[] {
  const rockets = Array.from({ length: 9 }, (_, index) => burst(
    width * (0.1 + Math.random() * 0.8),
    height * (0.1 + Math.random() * 0.35),
    44,
    6.5,
    index % 3 === 0,
    index * 330,
  )).flat()

  const rain = Array.from({ length: 40 }, () => ({
    x: Math.random() * width,
    y: -20 - Math.random() * height * 0.3,
    vx: -0.5 + Math.random(),
    vy: 2 + Math.random() * 3,
    size: 9 + Math.random() * 10,
    color: pick(COLORS),
    star: true,
    delay: 600 + Math.random() * 1800,
  }))

  return [...rockets, ...rain]
}

function play(id: number, x: number, y: number, bonus: boolean): void {
  cancelAnimationFrame(frame)
  clearTimeout(timer)
  word.value = bonus ? t('cheer.dayBonus') : pick(words.value)

  const duration = bonus ? BONUS_MS : DURATION_MS

  const element = canvas.value
  const pencil = element?.getContext('2d')

  if (!element || !pencil || isMotionStill()) {
    timer = setTimeout(() => done(id), duration)

    return
  }

  const ratio = window.devicePixelRatio || 1

  element.width = window.innerWidth * ratio
  element.height = window.innerHeight * ratio
  pencil.setTransform(ratio, 0, 0, ratio, 0, 0)

  const width = window.innerWidth
  const height = window.innerHeight

  // Stars from the check mark, then two rockets bursting up top.
  const sparks = bonus
    ? bonusSparks(width, height)
    : [
        ...burst(x, y, 26, 8, true, 0),
        ...burst(width * (0.2 + Math.random() * 0.25), height * (0.18 + Math.random() * 0.15), 36, 5, false, 180),
        ...burst(width * (0.55 + Math.random() * 0.25), height * (0.15 + Math.random() * 0.15), 36, 5, false, 420),
      ]

  const start = performance.now()

  const step = (now: number): void => {
    const elapsed = now - start

    pencil.clearRect(0, 0, width, height)

    for (const spark of sparks) {
      const age = elapsed - spark.delay

      if (age < 0) {
        continue
      }

      const life = age / (duration - spark.delay)
      const seconds = age / 16

      pencil.globalAlpha = Math.max(0, 1 - life * life)
      pencil.fillStyle = spark.color

      // Slowing down, then falling a little: sparks, not bullets.
      const drag = 1 - Math.min(0.9, life * 0.9)
      const px = spark.x + spark.vx * seconds * drag
      const py = spark.y + spark.vy * seconds * drag + 0.04 * seconds * seconds

      if (spark.star) {
        drawStar(pencil, px, py, spark.size)
      }
      else {
        pencil.beginPath()
        pencil.arc(px, py, spark.size, 0, Math.PI * 2)
        pencil.fill()
      }
    }

    pencil.globalAlpha = 1

    if (elapsed < duration) {
      frame = requestAnimationFrame(step)
    }
    else {
      pencil.clearRect(0, 0, width, height)
      done(id)
    }
  }

  frame = requestAnimationFrame(step)
}

watch(current, (cheer) => {
  if (cheer) {
    nextTick(() => play(cheer.id, cheer.x, cheer.y, cheer.bonus > 0))
  }
})

onBeforeUnmount(() => {
  cancelAnimationFrame(frame)
  clearTimeout(timer)
})
</script>

<template>
  <div v-if="current" aria-hidden="true" class="pointer-events-none fixed inset-0 z-[60]">
    <TwConfetti v-if="current.bonus > 0" :key="`confetti-${current.id}`" :count="260" :duration-ms="BONUS_MS" />
    <canvas ref="canvas" class="relative size-full" />

    <!-- On a card of its own: over coloured columns bare text would drown. -->
    <div :key="current.id" class="absolute inset-x-0 top-[26%] z-[70] flex justify-center px-4">
      <div
        v-if="current.bonus > 0"
        data-testid="cheer"
        data-bonus
        class="tw-tada flex flex-col items-center rounded-[2rem] bg-surface/95 px-10 py-6 shadow-floating ring-4 ring-points"
      >
        <span class="text-8xl leading-none"><TwEmoji icon="🎁" /></span>
        <span class="mt-3 text-center font-display text-4xl font-bold text-accent sm:text-6xl">{{ word }}</span>
        <span class="tw-pop mt-3 flex items-center gap-2 rounded-full bg-points px-7 py-2 font-display text-5xl font-bold text-points-ink tabular-nums [animation-delay:500ms]">
          +{{ current.bonus }} <TwStar />
        </span>
      </div>

      <div v-else data-testid="cheer" class="tw-pop flex flex-col items-center rounded-3xl bg-surface/95 px-8 py-4 shadow-floating">
        <span class="font-display text-5xl font-bold text-accent sm:text-6xl">{{ word }}</span>
        <span
          v-if="current.points > 0"
          class="mt-2 flex items-center gap-1.5 rounded-full bg-points px-5 py-1 font-display text-3xl font-bold text-points-ink tabular-nums"
        >
          +{{ current.points }} <TwStar />
        </span>
      </div>
    </div>
  </div>
</template>
