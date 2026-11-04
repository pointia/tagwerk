<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Enums\DeviceKind;
use App\Models\Device;
use App\Services\DevicePairingService;
use App\Support\HouseholdTimezone;
use App\Support\QrCode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Device'))
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kind')
                    ->label(__('Kind'))
                    ->badge()
                    ->color('gray')
                    ->icon(fn (DeviceKind $state): Heroicon => $state === DeviceKind::Wall
                        ? Heroicon::OutlinedDeviceTablet
                        : Heroicon::OutlinedDevicePhoneMobile)
                    ->formatStateUsing(fn (DeviceKind $state): string => $state->label()),
                TextColumn::make('paired_at')
                    ->label(__('Paired'))
                    ->timezone(fn (): string => HouseholdTimezone::current())
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder(__('Not yet'))
                    ->sortable(),
                TextColumn::make('pairing_code')
                    ->label(__('Open code'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage(__('Code copied'))
                    ->description(fn (Device $record): ?string => $record->pairing_expires_at
                        ? __('valid until :time', ['time' => $record->pairing_expires_at->format('H:i')])
                        : null)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('Created on'))
                    ->timezone(fn (): string => HouseholdTimezone::current())
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateIcon(Heroicon::OutlinedDeviceTablet)
            ->emptyStateHeading(__('No devices yet'))
            ->emptyStateDescription(__('Create the wall tablet, then generate a pairing code.'))
            ->recordActions([
                Action::make('pair')
                    ->label(__('Pairing code'))
                    ->icon(Heroicon::OutlinedQrCode)
                    // Mobile devices never pair with a code but through the
                    // personal sign-in.
                    ->visible(fn (Device $record): bool => $record->kind === DeviceKind::Wall)
                    ->modalHeading(__('Pair device'))
                    // A modal instead of a toast: the code is read off or
                    // scanned, and both take longer than a toast stays up by
                    // default.
                    ->mountUsing(fn (Device $record) => app(DevicePairingService::class)->issueCode($record))
                    ->modalContent(function (Device $record): View {
                        $code = (string) $record->refresh()->pairing_code;

                        return view('filament.pairing-code', [
                            'code' => $code,
                            // The wall lives at the root; it takes the code
                            // from the URL.
                            'qr' => QrCode::svg(url('/?pair='.$code)),
                            'validUntil' => $record->pairing_expires_at?->format('H:i'),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close')),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
