<script setup lang="ts">
/*
 * A textarea with the few buttons the renderer understands, and a preview
 * drawn by TwMarkdown — what the family sees is what the wall will show.
 */
import type { IconName } from './TwIcon.vue'
import { applyMarkdown, type MarkdownFormat } from '../utils/markdownEdit'

interface Props {
  label: string
  rows?: number
  testid?: string
}

const props: Props = withDefaults(defineProps<Props>(), { rows: 6, testid: undefined })

const model = defineModel<string>({ default: '' })

const { t } = useI18n()

const area = ref<HTMLTextAreaElement | null>(null)
const preview = ref(false)

const buttons: { format: MarkdownFormat, icon: IconName, label: string }[] = [
  { format: 'heading', icon: 'heading', label: 'markdown.heading' },
  { format: 'bold', icon: 'bold', label: 'markdown.bold' },
  { format: 'italic', icon: 'italic', label: 'markdown.italic' },
  { format: 'list', icon: 'lists', label: 'markdown.list' },
  { format: 'numbered', icon: 'listOrdered', label: 'markdown.numbered' },
  { format: 'link', icon: 'link', label: 'markdown.link' },
]

async function format(kind: MarkdownFormat): Promise<void> {
  const element = area.value

  if (!element) {
    return
  }

  const edit = applyMarkdown(
    { value: model.value, start: element.selectionStart, end: element.selectionEnd },
    kind,
    { text: t('markdown.text'), link: t('markdown.linkText') },
  )

  model.value = edit.value
  await nextTick()
  element.focus()
  element.setSelectionRange(edit.start, edit.end)
}
</script>

<template>
  <div class="rounded-2xl border-2 border-line bg-canvas focus-within:border-accent">
    <div class="flex flex-wrap items-center gap-1 border-b border-line p-1">
      <template v-if="!preview">
        <button
          v-for="button in buttons"
          :key="button.format"
          type="button"
          :aria-label="$t(button.label)"
          :title="$t(button.label)"
          class="flex min-h-11 min-w-11 items-center justify-center rounded-xl text-ink-2 transition-colors hover:bg-surface-2"
          @click="format(button.format)"
        >
          <TwIcon :name="button.icon" :size="20" />
        </button>
      </template>

      <button
        type="button"
        :aria-pressed="preview"
        class="ml-auto flex min-h-11 items-center gap-2 rounded-xl px-3 text-base font-medium text-ink-2 transition-colors hover:bg-surface-2"
        @click="preview = !preview"
      >
        <TwIcon :name="preview ? 'pencil' : 'eye'" :size="20" />
        {{ preview ? $t('markdown.write') : $t('markdown.preview') }}
      </button>
    </div>

    <div v-if="preview" class="min-h-32 px-4 py-3">
      <TwMarkdown :source="model" />
    </div>

    <textarea
      v-else
      ref="area"
      v-model="model"
      :rows="props.rows"
      :aria-label="props.label"
      :data-testid="props.testid"
      class="block w-full resize-y rounded-b-2xl bg-transparent px-4 py-3 text-lg text-ink outline-none"
    />
  </div>
</template>
