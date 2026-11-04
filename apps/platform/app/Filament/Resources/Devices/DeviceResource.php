<?php

namespace App\Filament\Resources\Devices;

use App\Filament\Concerns\NeedsHousehold;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Devices\Pages\CreateDevice;
use App\Filament\Resources\Devices\Pages\EditDevice;
use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Filament\Resources\Devices\Schemas\DeviceForm;
use App\Filament\Resources\Devices\Tables\DevicesTable;
use App\Models\Device;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DeviceResource extends Resource
{
    use NeedsHousehold;

    protected static ?string $model = Device::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDeviceTablet;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::DeviceTablet;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('Device');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Devices');
    }

    public static function form(Schema $schema): Schema
    {
        return DeviceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DevicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevices::route('/'),
            'create' => CreateDevice::route('/create'),
            'edit' => EditDevice::route('/{record}/edit'),
        ];
    }
}
