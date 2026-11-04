@if ($providers !== [])
    <div class="tw-social-login">
        @foreach ($providers as $key => $label)
            <x-filament::button
                tag="a"
                color="gray"
                outlined
                :href="route('social.redirect', ['provider' => $key, 'to' => 'admin'])"
            >
                {{ __('Sign in with :provider', ['provider' => $label]) }}
            </x-filament::button>
        @endforeach
    </div>
@endif
