{{--
    An emoji field with a searchable grid of suggestions underneath. Any
    character can still be typed or pasted into the field itself.

    The suggestions are the same Fluent Emoji catalog the wall and the phone
    use (public/emoji/catalog.json), fetched on first view and shown all at
    once. Drawn as text in the system's emoji font, not as pictures: 1,600
    image requests made the page slow. Icons are compared by their code
    points, never by the text: stored icons come with and without the
    variation selector FE0F.

    Inline styles: Filament's stylesheet only carries the classes it uses
    itself. Dynamic styles as objects: Alpine merges those, a string would
    replace the whole style attribute.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            query: '',
            icons: [],
            hex(icon) {
                return [...(icon ?? '').trim()]
                    .map((character) => character.codePointAt(0))
                    .filter((code) => code !== 0xfe0f)
                    .map((code) => code.toString(16))
                    .join('-')
            },
            get shown() {
                const words = this.query.trim().toLowerCase().split(/\s+/).filter(Boolean)
                return words.length === 0
                    ? this.icons
                    : this.icons.filter((entry) => words.every((word) => entry.words.includes(word) || entry.hex === this.hex(word)))
            },
            async init() {
                const response = await fetch('/emoji/catalog.json')
                if (response.ok) {
                    this.icons = (await response.json()).icons.map(([emoji, hex, words]) => ({ emoji, hex, words }))
                }
            },
        }"
        style="display: flex; flex-direction: column; gap: 0.5rem;"
    >
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <input
                type="text"
                x-model="state"
                maxlength="16"
                aria-label="Zeichen"
                placeholder="{{ $placeholder }}"
                data-icon-value
                style="width: 4.5rem; height: 2.75rem; font-size: 1.5rem; text-align: center; border-radius: 0.5rem; border: 1px solid rgba(127,127,127,0.35); background: transparent;"
            >
            <input
                type="search"
                x-model="query"
                placeholder="{{ __('Search, e.g. teeth, ice cream, cinema …') }}"
                aria-label="Zeichen suchen"
                data-icon-search
                style="flex: 1; height: 2.75rem; padding: 0 0.75rem; border-radius: 0.5rem; border: 1px solid rgba(127,127,127,0.35); background: transparent;"
            >
            <button
                type="button"
                x-show="state"
                x-on:click="state = null"
                style="font-size: 0.875rem; text-decoration: underline; opacity: 0.7; background: none; border: none; cursor: pointer;"
            >ohne</button>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem; max-height: 20rem; overflow-y: auto;">
            <template x-for="entry in shown" :key="entry.hex">
                <button
                    type="button"
                    x-on:click="state = entry.emoji"
                    x-bind:title="entry.words"
                    x-bind:aria-label="entry.words"
                    x-bind:aria-pressed="hex(state) === entry.hex"
                    x-bind:style="hex(state) === entry.hex ? { background: 'var(--primary-500)', borderColor: 'var(--primary-500)' } : {}"
                    x-text="entry.emoji"
                    data-icon-option
                    style="width: 2.75rem; height: 2.75rem; font-size: 1.5rem; border-radius: 0.5rem; border: 1px solid rgba(127,127,127,0.25); background: transparent; cursor: pointer;"
                ></button>
            </template>
            <p x-show="icons.length > 0 && shown.length === 0" style="opacity: 0.7; font-size: 0.875rem;">
                {{ __('Nothing found — any emoji can also be typed straight into the field on the left.') }}
            </p>
        </div>
    </div>
</x-dynamic-component>
