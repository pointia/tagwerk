<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeviceKind;
use App\Models\Device;
use App\Models\Household;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Pairs a wall tablet through a short-lived code.
 *
 * Nobody logs in at the tablet; it receives a long-lived token once and
 * stays signed in afterwards.
 */
final class DevicePairingService
{
    private const CODE_LIFETIME_MINUTES = 15;

    /** Characters without 0/O and 1/I, so the code stays readable at the tablet. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function createCode(Household $household, string $name): Device
    {
        return $this->issueCode(new Device([
            'household_id' => $household->id,
            'name' => $name,
            'kind' => DeviceKind::Wall,
        ]));
    }

    /**
     * Gives an already created device a fresh code.
     *
     * Needed for every tablet after the first: the code is deleted on
     * redemption, and a reset or replaced device needs a new one without
     * losing the entry and its name.
     */
    public function issueCode(Device $device): Device
    {
        $device->forceFill([
            'pairing_code' => $this->generateCode(),
            'pairing_expires_at' => Carbon::now()->addMinutes(self::CODE_LIFETIME_MINUTES),
        ])->save();

        return $device;
    }

    /** @return array{device: Device, token: string} */
    public function redeem(string $code): array
    {
        $device = Device::withoutHouseholdScope()
            ->where('pairing_code', mb_strtoupper($code))
            ->first();

        if ($device === null || $device->pairing_expires_at === null || $device->pairing_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => __('The pairing code is invalid or has expired.'),
            ]);
        }

        $token = $device->createToken('device', ['device'])->plainTextToken;

        $device->forceFill([
            'pairing_code' => null,
            'pairing_expires_at' => null,
            'paired_at' => Carbon::now(),
        ])->save();

        return ['device' => $device, 'token' => $token];
    }

    private function generateCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, mb_strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (Device::withoutHouseholdScope()->where('pairing_code', $code)->exists());

        return $code;
    }
}
