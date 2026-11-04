{{--
    The week as a grid of bell-schedule periods. Tapping a slot adds or
    removes "weekday-period" in the state; taken slots show their subject.

    Inline styles: Filament's stylesheet only carries the classes it uses
    itself. Dynamic styles as objects: Alpine merges those, a string would
    replace the whole style attribute.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($periods === [])
        <p style="opacity: 0.75;">
            {{ __('Choose a child first. If it has no bell schedule yet, that is set on the person — or pick “Own time” above.') }}
        </p>
    @else
        <div
            x-data="{
                state: $wire.$entangle(@js($getStatePath())),
                toggle(key) {
                    const list = Array.isArray(this.state) ? [...this.state] : []
                    const at = list.indexOf(key)
                    at === -1 ? list.push(key) : list.splice(at, 1)
                    this.state = list
                },
                chosen(key) { return Array.isArray(this.state) && this.state.includes(key) },
                // Chosen slots preview the subject's colour from the field above.
                tint() { return $wire.data?.color || 'var(--primary-500)' },
            }"
            style="overflow-x: auto;"
        >
            <table style="width: 100%; border-collapse: separate; border-spacing: 0.25rem; table-layout: fixed; min-width: 34rem;">
                <thead>
                    <tr>
                        <th style="width: 6.5rem;"></th>
                        @foreach ($weekdays as $day => $name)
                            <th style="font-weight: 600; font-size: 0.875rem; padding-bottom: 0.25rem;">{{ mb_substr($name, 0, 2) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($periods as $period)
                        <tr>
                            <th style="text-align: left; font-weight: 600; font-size: 0.875rem; white-space: nowrap;">
                                {{ $period['number'] }}.
                                <span style="font-weight: 400; opacity: 0.7; font-size: 0.75rem;">{{ $period['time'] }}</span>
                            </th>
                            @foreach ($weekdays as $day => $name)
                                @php($key = "{$day}-{$period['number']}")
                                <td>
                                    @if (isset($taken[$key]))
                                        <div
                                            title="{{ __('Already taken') }}"
                                            data-slot="{{ $key }}"
                                            data-taken
                                            style="height: 2.75rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; padding: 0 0.25rem; font-size: 0.75rem; text-align: center; overflow: hidden; background: {{ $taken[$key]['color'] ? $taken[$key]['color'].'33' : 'rgba(127,127,127,0.15)' }}; box-shadow: inset 4px 0 0 {{ $taken[$key]['color'] ?? 'transparent' }}; opacity: 0.7; cursor: not-allowed;"
                                        >{{ $taken[$key]['title'] }}</div>
                                    @else
                                        <button
                                            type="button"
                                            data-slot="{{ $key }}"
                                            x-on:click="toggle(@js($key))"
                                            x-bind:aria-pressed="chosen(@js($key))"
                                            x-bind:style="chosen(@js($key))
                                                ? { background: tint(), color: '#fff', borderColor: tint(), borderStyle: 'solid' }
                                                : {}"
                                            aria-label="{{ $name }}, {{ __('Period :number', ['number' => $period['number']]) }}"
                                            style="width: 100%; height: 2.75rem; border-radius: 0.5rem; border: 1px dashed rgba(127,127,127,0.45); background: transparent; font-weight: 600; cursor: pointer;"
                                        >
                                            <span x-show="chosen(@js($key))">✓</span>
                                        </button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-dynamic-component>
