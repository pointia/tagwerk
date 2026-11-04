<?php

declare(strict_types=1);

use Laravel\Socialite\Two\GoogleProvider;

/*
 * The sign-in providers. A provider appears once its client id and secret
 * are set; the redirect address comes from the route, /social/<key>/callback.
 *
 * `driver` is any Socialite provider class. A further one is a Composer
 * package from SocialiteProviders (socialiteproviders/microsoft, …) plus an
 * entry here. `verified_claim` names the field of the provider's answer that
 * says the address is confirmed — without it an account is never found by
 * its address, because anyone could claim any address there.
 */
return [
    'providers' => [
        'google' => [
            'label' => 'Google',
            'driver' => GoogleProvider::class,
            'client_id' => env('SOCIAL_GOOGLE_CLIENT_ID'),
            'client_secret' => env('SOCIAL_GOOGLE_CLIENT_SECRET'),
            'verified_claim' => 'email_verified',
        ],
    ],
];
