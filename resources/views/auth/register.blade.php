<x-guest-layout>
    <x-auth-card
        :title="__('Create account')"
        :description="__('Register to manage charging stations and company operations.')"
    >
        <x-auth-validation-errors class="mb-6" :errors="$errors" />

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div class="space-y-2">
                <x-form.label for="name" :value="__('Name')" />

                <x-form.input-with-icon-wrapper>
                    <x-slot name="icon">
                        <x-heroicon-o-user aria-hidden="true" class="h-5 w-5 text-gray-400" />
                    </x-slot>

                    <x-form.input
                        withicon
                        id="name"
                        type="text"
                        name="name"
                        :value="old('name')"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="{{ __('Full name') }}"
                    />
                </x-form.input-with-icon-wrapper>
            </div>

            <div class="space-y-2">
                <x-form.label for="email" :value="__('Email')" />

                <x-form.input-with-icon-wrapper>
                    <x-slot name="icon">
                        <x-heroicon-o-mail aria-hidden="true" class="h-5 w-5 text-gray-400" />
                    </x-slot>

                    <x-form.input
                        withicon
                        id="email"
                        type="email"
                        name="email"
                        :value="old('email')"
                        required
                        autocomplete="username"
                        placeholder="name@company.com"
                    />
                </x-form.input-with-icon-wrapper>
            </div>

            <div class="space-y-2">
                <x-form.label for="password" :value="__('Password')" />

                <x-form.input-with-icon-wrapper>
                    <x-slot name="icon">
                        <x-heroicon-o-lock-closed aria-hidden="true" class="h-5 w-5 text-gray-400" />
                    </x-slot>

                    <x-form.input
                        withicon
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                </x-form.input-with-icon-wrapper>
            </div>

            <div class="space-y-2">
                <x-form.label for="password_confirmation" :value="__('Confirm Password')" />

                <x-form.input-with-icon-wrapper>
                    <x-slot name="icon">
                        <x-heroicon-o-lock-closed aria-hidden="true" class="h-5 w-5 text-gray-400" />
                    </x-slot>

                    <x-form.input
                        withicon
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                </x-form.input-with-icon-wrapper>
            </div>

            <x-button class="w-full justify-center py-2.5">
                {{ __('Create account') }}
            </x-button>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                {{ __('Already registered?') }}
                <a href="{{ route('login') }}" class="auth-link">
                    {{ __('Sign in') }}
                </a>
            </p>
        </form>
    </x-auth-card>
</x-guest-layout>
