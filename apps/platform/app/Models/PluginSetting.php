<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Switch and settings of one extension, for the whole server — no
 * household, the operator decides.
 *
 * @property string $key
 * @property bool $enabled
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['key', 'enabled', 'settings'])]
class PluginSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
        ];
    }
}
