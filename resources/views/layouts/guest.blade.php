<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CSMS') }}</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet" />

    <style>
        [x-cloak] {
            display: none;
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <div
        x-data="mainState"
        class="font-sans antialiased"
        :class="{ dark: isDarkMode }"
        x-cloak
    >
        <div class="flex min-h-screen bg-gray-50 text-gray-900 dark:bg-dark-eval-0 dark:text-gray-100">
            {{-- Brand panel --}}
            <aside
                class="relative hidden w-0 flex-shrink-0 overflow-hidden lg:block lg:w-1/2 xl:w-[44%]"
                aria-hidden="true"
            >
                <div class="absolute inset-0 bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950"></div>
                <div
                    class="absolute inset-0 opacity-[0.07]"
                    style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 24px 24px;"
                ></div>

                <div class="relative flex h-full flex-col justify-between p-10 xl:p-14">
                    <div>
                        <a href="/" class="inline-flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80">
                            <x-application-logo variant="inverse" class="h-10 w-10" />
                            <span class="text-lg font-semibold tracking-tight text-white">
                                {{ config('app.name', 'CSMS') }}
                            </span>
                        </a>
                    </div>

                    <div class="max-w-md space-y-6">
                        <h2 class="text-3xl font-semibold leading-tight tracking-tight text-white xl:text-4xl">
                            Charging Station Management
                        </h2>
                        <p class="text-base leading-relaxed text-blue-100/90">
                            Monitor stations, manage tenants, and control OCPP sessions from one dashboard.
                        </p>

                        <ul class="space-y-3 text-sm text-blue-100/80">
                            <li class="flex items-center gap-3">
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-white/10">
                                    <x-heroicon-o-lightning-bolt class="h-4 w-4 text-white" aria-hidden="true" />
                                </span>
                                OCPP 1.6 &amp; 2.1 compliant
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-white/10">
                                    <x-heroicon-o-office-building class="h-4 w-4 text-white" aria-hidden="true" />
                                </span>
                                Multi-tenant company isolation
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-white/10">
                                    <x-heroicon-o-status-online class="h-4 w-4 text-white" aria-hidden="true" />
                                </span>
                                Real-time station monitoring
                            </li>
                        </ul>
                    </div>

                    <p class="text-xs text-blue-200/60">
                        &copy; {{ date('Y') }} {{ config('app.name', 'CSMS') }}
                    </p>
                </div>
            </aside>

            {{-- Form panel --}}
            <div class="flex min-h-screen flex-1 flex-col">
                <header class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-10">
                    <a
                        href="/"
                        class="inline-flex items-center gap-2 rounded-lg lg:hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
                    >
                        <x-application-logo class="h-9 w-9" />
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ config('app.name', 'CSMS') }}
                        </span>
                    </a>
                    <div class="ml-auto">
                        <x-button
                            type="button"
                            icon-only
                            variant="secondary"
                            sr-text="Toggle dark mode"
                            x-on:click="toggleTheme"
                        >
                            <x-heroicon-o-moon
                                x-show="!isDarkMode"
                                aria-hidden="true"
                                class="h-5 w-5"
                            />
                            <x-heroicon-o-sun
                                x-show="isDarkMode"
                                aria-hidden="true"
                                class="h-5 w-5"
                            />
                        </x-button>
                    </div>
                </header>

                <main class="flex flex-1 flex-col justify-center px-4 pb-8 sm:px-6 lg:px-10">
                    {{ $slot }}
                </main>

                <x-footer />
            </div>
        </div>
    </div>
</body>
</html>
