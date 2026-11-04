{{--
    Inline styles: Filament's stylesheet only carries the classes it uses
    itself. The scroller is positioned so nothing escapes it on a phone.
--}}
<x-filament-panels::page>
    <x-filament::section>
        @if ($plugins === [])
            <p>{{ __('None.') }}</p>
        @else
            <div style="position: relative; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="text-align: start;">
                            <th style="padding: 0.5rem; text-align: start;">{{ __('Package') }}</th>
                            <th style="padding: 0.5rem; text-align: start;">{{ __('Version') }}</th>
                            <th style="padding: 0.5rem; text-align: start;">{{ __('Licence') }}</th>
                            <th style="padding: 0.5rem; text-align: start;">{{ __('State') }}</th>
                            <th style="padding: 0.5rem; text-align: start;">{{ __('Reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plugins as $plugin)
                            <tr data-plugin="{{ $plugin['name'] }}" style="border-top: 1px solid rgb(128 128 128 / 0.2);">
                                <td style="padding: 0.5rem;">{{ $plugin['name'] }}</td>
                                <td style="padding: 0.5rem;">{{ $plugin['version'] }}</td>
                                <td style="padding: 0.5rem;">{{ $plugin['license'] }}</td>
                                <td style="padding: 0.5rem;">
                                    <x-filament::badge :color="$plugin['reason'] === null ? 'success' : 'danger'">
                                        {{ $plugin['reason'] === null ? __('Loaded') : __('Not loaded') }}
                                    </x-filament::badge>
                                </td>
                                <td style="padding: 0.5rem;">{{ $plugin['reason'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    @if ($languages !== [])
        {{-- What a pack lacks shows in English; the operator sees it here before the families do. --}}
        <x-filament::section :heading="__('Languages')">
            <div style="position: relative; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr>
                            <th style="padding: 0.5rem; text-align: start;">{{ __('Language') }}</th>
                            <th style="padding: 0.5rem; text-align: start;">{{ __('Package') }}</th>
                            <th style="padding: 0.5rem; text-align: start;">{{ __('Translated') }}</th>
                            <th style="padding: 0.5rem; text-align: start;">{{ __('Complete for') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($languages as $language)
                            <tr data-language="{{ $language['code'] }}" style="border-top: 1px solid rgb(128 128 128 / 0.2); vertical-align: top;">
                                <td style="padding: 0.5rem;">{{ $language['name'] }} ({{ $language['code'] }})</td>
                                <td style="padding: 0.5rem;">{{ $language['package'] }}</td>
                                <td style="padding: 0.5rem;">
                                    @if ($language['missing'] === [] && $language['broken'] === [])
                                        <x-filament::badge color="success">{{ __('Complete') }}</x-filament::badge>
                                    @else
                                        <details>
                                            <summary style="cursor: pointer;">
                                                {{ trans_choice(':percent % — :count text shows in English|:percent % — :count texts show in English', count($language['missing']) + count($language['broken']), ['percent' => $language['percent']]) }}
                                            </summary>
                                            @if ($language['broken'] !== [])
                                                <p style="margin-top: 0.5rem; font-weight: 600;">{{ __('Placeholders differ') }}</p>
                                                <ul style="font-family: monospace; font-size: 0.75rem;">
                                                    @foreach ($language['broken'] as $key)
                                                        <li>{{ $key }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            @if ($language['missing'] !== [])
                                                <p style="margin-top: 0.5rem; font-weight: 600;">{{ __('Missing') }}</p>
                                                <ul style="font-family: monospace; font-size: 0.75rem; max-height: 20rem; overflow-y: auto;">
                                                    @foreach ($language['missing'] as $key)
                                                        <li>{{ $key }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </details>
                                    @endif
                                </td>
                                <td style="padding: 0.5rem;">
                                    {{ $language['complete_for'] ?? '—' }}
                                    <span style="opacity: 0.6;">({{ __('running: :version', ['version' => $running]) }})</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
