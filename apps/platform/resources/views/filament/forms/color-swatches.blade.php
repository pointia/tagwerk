{{--
    A handful of ready colours plus a free one. Inline styles: Filament's
    stylesheet only carries the classes it uses itself.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{ state: $wire.$entangle(@js($getStatePath())) }"
        style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;"
    >
        @foreach ($swatches as $hex => $name)
            <button
                type="button"
                data-swatch="{{ $hex }}"
                title="{{ $name }}"
                aria-label="{{ $name }}"
                x-on:click="state = @js($hex)"
                x-bind:aria-pressed="state === @js($hex)"
                x-bind:style="state === @js($hex)
                    ? { outline: '3px solid {{ $hex }}', outlineOffset: '2px' }
                    : { outline: 'none' }"
                style="width: 2.25rem; height: 2.25rem; border-radius: 9999px; border: 2px solid rgba(255,255,255,0.8); box-shadow: 0 0 0 1px rgba(0,0,0,0.15); background: {{ $hex }}; cursor: pointer;"
            ></button>
        @endforeach

        <label title="{{ __('Own colour') }}" style="display: inline-flex; align-items: center; gap: 0.375rem; cursor: pointer; font-size: 0.875rem; opacity: 0.8;">
            <input
                type="color"
                x-bind:value="state ?? '#888888'"
                x-on:input="state = $event.target.value"
                style="width: 2.25rem; height: 2.25rem; padding: 0; border: none; background: none; cursor: pointer;"
            >
            {{ __('own') }}
        </label>

        <button
            type="button"
            x-show="state"
            x-on:click="state = null"
            style="font-size: 0.875rem; text-decoration: underline; opacity: 0.7; background: none; border: none; cursor: pointer;"
        >{{ __('no colour') }}</button>
    </div>
</x-dynamic-component>
