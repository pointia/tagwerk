<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use App\Models\Calendar;
use App\Support\Preferences;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    private const PHOTO_KEYS = ['photo_style', 'photo_rest_style', 'photo_source', 'photo_interval', 'photo_fit', 'photo_motion', 'photo_caption', 'photo_clock'];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $hidden = array_map('intval', $data['hidden_calendar_ids'] ?? []);
        $data['shown_calendar_ids'] = array_values(array_diff($this->activeCalendarIds(), $hidden));
        $data['preferences'] = Preferences::merged($data['preferences'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Hidden while the defaults apply, and then null.
        $data['permissions'] ??= null;

        $shown = array_map('intval', $data['shown_calendar_ids'] ?? []);
        $data['hidden_calendar_ids'] = array_values(array_diff($this->activeCalendarIds(), $shown));
        unset($data['shown_calendar_ids']);

        // The form only knows resting mode and photo frame; the wall's other choices stay.
        // Strings, because PHP turns the option key '10' into an integer.
        $form = (array) ($data['preferences'] ?? []);
        $data['preferences'] = Preferences::merged([
            ...($this->getRecord()->preferences ?? []),
            'screensaver_areas' => (array) ($form['screensaver_areas'] ?? []),
            'screensaver_idle' => (string) ($form['screensaver_idle'] ?? ''),
            'screensaver_interval' => (string) ($form['screensaver_interval'] ?? ''),
            'screensaver_dim' => (string) ($form['screensaver_dim'] ?? ''),
            'screensaver_dim_night' => (string) ($form['screensaver_dim_night'] ?? ''),
            ...array_map(
                static fn (mixed $value): string => (string) $value,
                array_intersect_key($form, array_flip(self::PHOTO_KEYS)),
            ),
            'photo_albums' => array_map(intval(...), (array) ($form['photo_albums'] ?? [])),
        ]);

        return $data;
    }

    /** @return array<int, int> */
    private function activeCalendarIds(): array
    {
        return Calendar::query()->where('is_active', true)->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
