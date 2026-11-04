<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Tables;

use App\Enums\PersonRole;
use App\Models\Membership;
use App\Services\Memberships;
use App\Support\OutgoingMail;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('person.name')
                    ->label(__('Person'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable(),
                TextColumn::make('person.role')
                    ->label(__('Role'))
                    ->badge()
                    ->formatStateUsing(fn (PersonRole $state): string => $state->label()),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->state(fn (Membership $record): string => Memberships::isPending($record) ? 'invited' : 'active')
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? __('Active') : __('Invited'))
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'warning'),
            ])
            ->defaultSort('id')
            ->recordActions([
                Action::make('resendInvitation')
                    ->label(__('Send invitation again'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->visible(fn (Membership $record): bool => OutgoingMail::enabled() && Memberships::isPending($record))
                    ->requiresConfirmation()
                    ->modalDescription(__('The previous link stops working.'))
                    ->action(fn (Membership $record, Memberships $memberships) => $memberships->resend($record))
                    ->successNotificationTitle(__('Invitation sent')),
                EditAction::make(),
            ]);
    }
}
