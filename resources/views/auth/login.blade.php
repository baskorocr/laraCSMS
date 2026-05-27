<x-guest-layout>
    <x-auth-card
        :title="__('Sign in')"
        :description="__('Enter your credentials to access the CSMS dashboard.')"
    >
        <x-auth-session-status class="mb-6" :status="session('status')" />

        <x-auth-validation-errors class="mb-6" :errors="$errors" />

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

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
                        placeholder="name@company.com"
                        required
                        autofocus
                        autocomplete="username"
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
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                </x-form.input-with-icon-wrapper>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label for="remember_me" class="inline-flex cursor-pointer items-center">
                    <input
                        id="remember_me"
                        type="checkbox"
                        class="rounded border-gray-300 text-brand-700 shadow-sm focus:ring-brand-600 dark:border-gray-600 dark:bg-dark-eval-2 dark:focus:ring-offset-dark-eval-1"
                        name="remember"
                    >
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Remember me') }}
                    </span>
                </label>

                @if (Route::has('password.request'))
                    <a class="auth-link text-sm" href="{{ route('password.request') }}">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>

            <x-button class="w-full justify-center py-2.5">
                {{ __('Sign in') }}
            </x-button>

            @if (Route::has('register'))
                <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Don’t have an account?') }}
                    <a href="{{ route('register') }}" class="auth-link">
                        {{ __('Create account') }}
                    </a>
                </p>
            @endif
        </form>
    </x-auth-card>
</x-guest-layout>
