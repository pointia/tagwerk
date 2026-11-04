<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceKind;
use App\Enums\ThemePreference;
use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasZonedTimestamps;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

/**
 * A paired device.
 *
 * Wall tablets authenticate with a long-lived token created during pairing.
 * Phones are only stored here as carriers of their push subscription — they
 * authenticate through the personal login.
 */
final class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use BelongsToHousehold, HasApiTokens, HasFactory, HasZonedTimestamps;

    protected $fillable = ['household_id', 'user_id', 'name', 'kind', 'theme_preference', 'preferences', 'permissions', 'hidden_calendar_ids', 'locale'];

    // The push secret belongs in a response as little as the pairing code.
    protected $hidden = ['pairing_code', 'push_auth'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => DeviceKind::class,
            'theme_preference' => ThemePreference::class,
            'preferences' => 'array',
            'permissions' => 'array',
            'hidden_calendar_ids' => 'array',
            'pairing_expires_at' => 'immutable_datetime',
            'paired_at' => 'immutable_datetime',
            'push_subscribed_at' => 'immutable_datetime',
        ];
    }

    /**
     * The account this phone belongs to.
     *
     * Only set for phones: a wall tablet belongs to the household, not to a
     * person. This is how a notification can target adults instead of
     * everyone.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasPush(): bool
    {
        return $this->push_endpoint !== null;
    }

    public function isPaired(): bool
    {
        return $this->paired_at !== null;
    }
}
