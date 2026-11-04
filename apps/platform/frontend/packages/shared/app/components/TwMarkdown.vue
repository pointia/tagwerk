<script setup lang="ts">
/*
 * Draws recipe Markdown as Vue nodes — never `v-html`. Text goes through
 * Vue's escaping, so `<script>` in a recipe stays visible text. See
 * utils/markdown.ts for the subset.
 */
import { h, type VNode } from 'vue'
import { parseMarkdown, type MarkdownBlock, type MarkdownInline } from '../utils/markdown'

interface Props {
  source: string | null
  /** Numbered lists at the top become steps to tick off while cooking. */
  steps?: boolean
}

const props: Props = withDefaults(defineProps<Props>(), { steps: false })

const blocks = computed(() => parseMarkdown(props.source ?? ''))

/* Display only: a step ticked off is nothing the server needs to know. */
const done = ref(new Set<string>())
watch(() => props.source, () => { done.value = new Set() })

function toggle(key: string): void {
  const next = new Set(done.value)

  if (!next.delete(key)) {
    next.add(key)
  }

  done.value = next
}

function inline(nodes: MarkdownInline[]): (VNode | string)[] {
  return nodes.map((node) => {
    switch (node.type) {
      case 'text':
        return node.text
      case 'strong':
        return h('strong', { class: 'font-semibold text-ink' }, inline(node.children))
      case 'em':
        return h('em', inline(node.children))
      case 'link':
        return h('a', {
          href: node.href,
          target: '_blank',
          rel: 'noopener noreferrer nofollow',
          class: 'text-accent underline underline-offset-2',
        }, inline(node.children))
    }
  })
}

function block(node: MarkdownBlock): VNode {
  switch (node.type) {
    case 'heading':
      return h(`h${node.level}`, { class: 'mt-5 text-xl font-semibold text-ink first:mt-0' }, inline(node.children))
    case 'paragraph':
      return h('p', { class: 'mt-3 first:mt-0' }, inline(node.children))
    case 'list':
      return h(node.ordered ? 'ol' : 'ul', {
        class: ['mt-3 space-y-1.5 pl-6 first:mt-0', node.ordered ? 'list-decimal' : 'list-disc'],
        start: node.ordered && node.start !== 1 ? node.start : undefined,
      }, node.items.map(item => h('li', { class: 'pl-1' }, items(item))))
  }
}

/** A list item of one paragraph is drawn without the paragraph's margin. */
function items(content: MarkdownBlock[]): (VNode | string)[] {
  const [first, ...rest] = content

  return first?.type === 'paragraph'
    ? [...inline(first.children), ...rest.map(block)]
    : content.map(block)
}

function step(content: MarkdownBlock[], key: string, number: number): VNode {
  const ticked = done.value.has(key)

  return h('li', { class: 'list-none' }, [h('button', {
    'type': 'button',
    'aria-pressed': ticked,
    'data-testid': 'recipe-step',
    'class': [
      'flex min-h-11 w-full gap-3 rounded-xl px-2 py-2 text-left transition-colors hover:bg-surface-2',
      ticked ? 'text-ink-3 line-through' : '',
    ],
    'onClick': () => toggle(key),
  }, [
    h('span', {
      class: ['flex size-8 shrink-0 items-center justify-center rounded-full text-base font-semibold', ticked ? 'bg-accent text-accent-ink' : 'bg-surface-2 text-ink'],
    }, String(number)),
    h('span', { class: 'min-w-0 flex-1' }, items(content)),
  ])])
}

const Blocks = () => blocks.value.map((node, index) => props.steps && node.type === 'list' && node.ordered
  ? h('ol', { class: 'mt-3 space-y-1 first:mt-0' }, node.items.map((item, position) => step(item, `${index}-${position}`, node.start + position)))
  : block(node))
</script>

<template>
  <div class="text-lg leading-relaxed text-ink-2">
    <Blocks />
  </div>
</template>
