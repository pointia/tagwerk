<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Enums\ThemePreference;
use App\Notifications\PasswordChanged;
use App\Notifications\PasswordResetLink;
use App\Support\Abilities;
use App\Support\HouseholdContext;
use App\Support\Locale;
use App\Support\OutgoingMail;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'theme_preference', 'preferences', 'hidden_calendar_ids', 'locale'])]
#[Hidden(['password', 'remember_token', 'pin_hash'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /*
     * A second sign-in factor (authenticator app, recovery codes), used only
     * while an extension offers it — see App\Auth\SecondFactors.
     */
    use InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery;

    /**
     * A new password or a deleted account logs out every phone — otherwise a
     * lost phone would stay signed in after the reset. The owner hears about
     * a new password by mail, except when accepting an invitation: there the
     * first real password is no news.
     */
    protected static function booted(): void
    {
        self::updated(function (self $user): void {
            // A newly set up second factor applies to phones too: their
            // tokens were issued without it.
            if ($user->wasChanged('app_authentication_secret') && $user->app_authentication_secret !== null) {
                $user->tokens()->delete();
            }

            if ($user->wasChanged('password')) {
                $user->tokens()->delete();

                if ($user->getOriginal('email_verified_at') !== null && OutgoingMail::enabled()) {
                    $user->notify(new PasswordChanged);
                }
            }
        });

        self::deleting(function (self $user): void {
            $user->tokens()->delete();
            // A morph relation has no foreign key to cascade.
            Notification::query()->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->getKey())->delete();
        });
    }

    /**
     * The account's messages about the household in view, plus those about
     * none. Replaces Laravel's relation, so Filament's bell and the app's
     * list read the same.
     *
     * @return MorphMany<Notification, $this>
     */
    public function notifications(): MorphMany
    {
        $household = app(HouseholdContext::class)->id();

        return $this->morphMany(Notification::class, 'notifiable')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('household_id')
                ->when($household !== null, fn (Builder $query): Builder => $query->orWhere('household_id', $household)))
            ->latest();
    }

    /** Called by the password broker; the link leads into the app. */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new PasswordResetLink($token));
    }

    /** Mails go out in the recipient's language, also from the queue. */
    public function preferredLocale(): string
    {
        return Locale::for($this);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'theme_preference' => ThemePreference::class,
            'preferences' => 'array',
            'hidden_calendar_ids' => 'array',
            'password' => 'hashed',
            'is_operator' => 'boolean',
        ];
    }

    /**
     * The admin's user menu: the person's face, otherwise the initial. Without
     * this Filament asks ui-avatars.com, which would learn who opens the admin.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->membership()?->person?->face() ?? Person::initialFace((string) $this->name, '#57534e');
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * The accepted membership in this household; without a household the one
     * a request would land in (see `defaultMembership()`).
     */
    public function membershipIn(?int $householdId): ?Membership
    {
        if ($householdId === null) {
            return $this->defaultMembership();
        }

        return $this->memberships()->accepted()->where('household_id', $householdId)->first();
    }

    /** The membership in the household of the request. */
    public function membership(): ?Membership
    {
        return $this->membershipIn(app(HouseholdContext::class)->id());
    }

    /** Replaces `person_id`: the account's person in the household of the request. */
    public function currentPersonId(): ?int
    {
        $id = $this->membership()?->person_id;

        return $id === null ? null : (int) $id;
    }

    /**
     * Where an account lands without a choice: the household used last,
     * active ones first — a suspended household is no place to start.
     */
    public function defaultMembership(): ?Membership
    {
        return $this->memberships()
            ->accepted()
            ->join('households', 'households.id', '=', 'memberships.household_id')
            ->orderByRaw('households.suspended_at IS NOT NULL')
            ->orderByDesc('memberships.updated_at')
            ->orderBy('memberships.id')
            ->select('memberships.*')
            ->first();
    }

    /**
     * The operator may always enter: their account belongs to no household,
     * they manage all of them. Everyone else needs a household they manage —
     * without one every query would be empty, because the global scope shows
     * nothing without a context. `is_operator` is deliberately not fillable.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->is_operator === true) {
            return true;
        }

        return $this->managesAnyHousehold();
    }

    /** Whether the account may manage at least one of its households. */
    public function managesAnyHousehold(): bool
    {
        $abilities = app(Abilities::class);

        return $this->memberships()->accepted()->get()->contains(
            fn (Membership $membership): bool => in_array(Permission::HouseholdManage, $abilities->ofMembership($membership), true),
        );
    }
}
