<?php

declare(strict_types=1);

namespace App\Filament\Schemas;

use App\Enums\Permission;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

/**
 * __('Permissions') for a person or a wall device: the defaults, or a list of its own.
 *
 * The list is hidden while the defaults apply and is then not submitted —
 * the edit pages store null for it, so a later change of role takes effect.
 */
final class PermissionsSection
{
    /** @param  Closure(Get): list<Permission>  $defaults */
    public static function make(string $description, Closure $defaults): Section
    {
        return Section::make(__('Permissions'))
            ->description($description)
            ->collapsible()
            ->schema([
                Toggle::make('custom_permissions')
                    ->label(__('Own permissions instead of the default'))
                    ->dehydrated(false)
                    ->live()
                    ->afterStateHydrated(fn (Toggle $component, ?Model $record) => $component->state($record?->getAttribute('permissions') !== null))
                    ->afterStateUpdated(function (bool $state, Set $set, Get $get) use ($defaults): void {
                        if ($state) {
                            $set('permissions', self::values($defaults($get)));
                        }
                    }),

                TextEntry::make('default_permissions')
                    ->label(__('Default'))
                    ->state(fn (Get $get): array => array_map(fn (Permission $permission): string => $permission->label(), $defaults($get)))
                    ->placeholder(__('None'))
                    ->bulleted()
                    ->visible(fn (Get $get): bool => ! $get('custom_permissions')),

                CheckboxList::make('permissions')
                    ->label(__('May'))
                    ->options(collect(Permission::cases())
                        ->mapWithKeys(fn (Permission $permission): array => [
                            $permission->value => $permission->group().': '.$permission->label(),
                        ])
                        ->all())
                    ->columns(2)
                    ->bulkToggleable()
                    ->visible(fn (Get $get): bool => (bool) $get('custom_permissions')),
            ]);
    }

    /**
     * @param  list<Permission>  $permissions
     * @return list<string>
     */
    private static function values(array $permissions): array
    {
        return array_map(fn (Permission $permission): string => $permission->value, $permissions);
    }
}
