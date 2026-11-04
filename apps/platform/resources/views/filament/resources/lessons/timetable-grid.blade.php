{{--
    One weekly grid per child, built like the grid for creating lessons.

    Inline styles for the same reason as there: Filament's stylesheet only
    carries the classes it uses itself.
--}}
<x-filament-panels::page>
    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: end;">
        <label style="display: grid; gap: 0.25rem; min-width: 12rem;">
            <span style="font-size: 0.875rem; font-weight: 500;">{{ __('Child') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="child" data-filter="child">
                    <option value="">{{ __('All children') }}</option>
                    @foreach ($children as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <label style="display: grid; gap: 0.25rem; min-width: 12rem;">
            <span style="font-size: 0.875rem; font-weight: 500;">{{ __('Rhythm') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="rhythm" data-filter="rhythm">
                    <option value="">{{ __('All') }}</option>
                    <option value="weekly">{{ __('every week') }}</option>
                    <option value="biweekly">{{ __('every other week') }}</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <label style="display: grid; gap: 0.25rem; min-width: 14rem; flex: 1;">
            <span style="font-size: 0.875rem; font-weight: 500;">{{ __('Subject') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input type="search" wire:model.live.debounce.400ms="search" :placeholder="__('Search')" data-filter="search" />
            </x-filament::input.wrapper>
        </label>

        <label style="display: flex; gap: 0.5rem; align-items: center; min-height: 2.25rem;">
            <x-filament::input.checkbox wire:model.live="inactive" data-filter="inactive" />
            <span style="font-size: 0.875rem;">{{ __('Show inactive') }}</span>
        </label>
    </div>

    @forelse ($timetables as $timetable)
        @php($person = $timetable['person'])
        <x-filament::section data-timetable="{{ $person->id }}">
            <x-slot name="heading">
                <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <span style="width: 0.75rem; height: 0.75rem; border-radius: 9999px; background: {{ $person->color ?? '#8a8276' }};"></span>
                    {{ $person->name }}
                </span>
            </x-slot>

            @if ($timetable['periods'] === [])
                <p style="opacity: 0.75;">
                    {{ __('No bell schedule yet — it is set on the person. Lessons with their own time are listed below.') }}
                </p>
            @else
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: separate; border-spacing: 0.25rem; table-layout: fixed; min-width: 34rem;">
                        <thead>
                            <tr>
                                <th style="width: 6.5rem;"></th>
                                @foreach ($timetable['weekdays'] as $day => $name)
                                    <th style="font-weight: 600; font-size: 0.875rem; padding-bottom: 0.25rem;">{{ mb_substr($name, 0, 2) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timetable['periods'] as $period)
                                <tr>
                                    <th style="text-align: left; font-weight: 600; font-size: 0.875rem; white-space: nowrap;">
                                        {{ $period['number'] }}.
                                        <span style="font-weight: 400; opacity: 0.7; font-size: 0.75rem;">{{ $period['time'] }}</span>
                                    </th>
                                    @foreach ($timetable['weekdays'] as $day => $name)
                                        @php($entries = $timetable['cells']["{$day}-{$period['number']}"] ?? [])
                                        <td style="vertical-align: top;">
                                            @if ($entries === [])
                                                {{-- Searching hides the rest; creating from a cell then makes no sense. --}}
                                                @unless ($filtering)
                                                    <a
                                                        href="{{ $this->createUrl($person, $day, $period['number']) }}"
                                                        data-slot="{{ $day }}-{{ $period['number'] }}"
                                                        aria-label="{{ $name }}, {{ __('Period :number', ['number' => $period['number']]) }}: {{ __('New lesson') }}"
                                                        style="display: flex; align-items: center; justify-content: center; height: 2.75rem; border-radius: 0.5rem; border: 1px dashed rgba(127,127,127,0.35); opacity: 0.55; font-weight: 600;"
                                                    >+</a>
                                                @else
                                                    <div style="height: 2.75rem;"></div>
                                                @endunless
                                            @else
                                                <div style="display: grid; gap: 0.25rem;">
                                                    @foreach ($entries as $entry)
                                                        <a
                                                            href="{{ $entry['url'] }}"
                                                            data-lesson
                                                            title="{{ $entry['rhythm'] ?? $entry['title'] }}"
                                                            style="display: flex; align-items: center; justify-content: center; gap: 0.25rem; min-height: {{ count($entries) > 1 ? '1.3rem' : '2.75rem' }}; padding: 0.125rem 0.25rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 600; text-align: center; overflow: hidden; background: {{ $entry['color'] ? $entry['color'].'33' : 'rgba(127,127,127,0.15)' }}; box-shadow: inset 4px 0 0 {{ $entry['color'] ?? 'rgba(127,127,127,0.5)' }}; {{ $entry['active'] ? '' : 'opacity: 0.45; text-decoration: line-through;' }}"
                                                        >
                                                            {{ $entry['title'] }}
                                                            @if ($entry['rhythm'])
                                                                <span aria-label="{{ $entry['rhythm'] }}" style="font-size: 0.65rem; opacity: 0.75;">↻2</span>
                                                            @endif
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($timetable['custom'] !== [])
                <div style="margin-top: 1rem;">
                    <h3 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">{{ __('Own time') }}</h3>
                    <ul style="display: grid; gap: 0.375rem;">
                        @foreach ($timetable['custom'] as $entry)
                            <li>
                                <a
                                    href="{{ $entry['url'] }}"
                                    data-lesson
                                    style="display: flex; gap: 0.75rem; align-items: center; padding: 0.375rem 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; background: {{ $entry['color'] ? $entry['color'].'22' : 'rgba(127,127,127,0.1)' }}; box-shadow: inset 4px 0 0 {{ $entry['color'] ?? 'rgba(127,127,127,0.5)' }}; {{ $entry['active'] ? '' : 'opacity: 0.45;' }}"
                                >
                                    <span style="min-width: 6rem; font-weight: 600;">{{ $entry['day'] }}</span>
                                    <span style="min-width: 7rem; opacity: 0.75;">{{ $entry['time'] }}</span>
                                    <span style="font-weight: 600;">{{ $entry['title'] }}</span>
                                    @if ($entry['rhythm'])
                                        <span style="opacity: 0.75;">· {{ $entry['rhythm'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($timetable['empty'] && $timetable['periods'] !== [] && $filtering)
                <p style="margin-top: 0.75rem; opacity: 0.75;">{{ __('Nothing matches the filter.') }}</p>
            @endif
        </x-filament::section>
    @empty
        <x-filament::empty-state
            :heading="__('No children yet')"
            :description="__('A timetable belongs to a child. Add one under Family → People.')"
            icon="heroicon-o-academic-cap"
        />
    @endforelse
</x-filament-panels::page>
