<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - OCPP 1.6 & 2.1 Charging Management System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
    <nav class="fixed top-0 z-50 w-full border-b border-gray-200 bg-white/80 backdrop-blur-md dark:border-gray-700 dark:bg-gray-900/80">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-brand-600 to-brand-700">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900 dark:text-white">CGS</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Charging Gateway System</p>
                    </div>
                </div>
                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                Login
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
                                    Get Started
                                </a>
                            @endif
                        @endauth
                    </div>
                @endif
            </div>
        </div>
    </nav>

    <main class="pt-16">
        <section class="relative overflow-hidden py-20 sm:py-32">
            <div class="absolute inset-0 -z-10">
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:24px_24px]"></div>
            </div>
            
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700 dark:border-brand-900 dark:bg-brand-950 dark:text-brand-300">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        OCPP 1.6 &amp; 2.1 — Service Pertama di Indonesia
                    </div>
                    
                    <h1 class="mb-6 text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl dark:text-white">
                        Kelola Charging Station<br/>
                        <span class="bg-gradient-to-r from-brand-600 to-brand-700 bg-clip-text text-transparent">Dengan Mudah & Efisien</span>
                    </h1>
                    
                    <p class="mx-auto mb-6 max-w-2xl text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                        Platform manajemen charging station berbasis <strong class="font-medium text-gray-800 dark:text-gray-200">OCPP 1.6 &amp; OCPP 2.1</strong> yang modern, aman, dan scalable.
                        Monitoring real-time, remote control, dan analytics dalam satu dashboard.
                    </p>
                    <div class="mb-10 flex flex-wrap items-center justify-center gap-2">
                        <span class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">OCPP 1.6 JSON</span>
                        <span class="rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-800 dark:border-brand-800 dark:bg-brand-950 dark:text-brand-200">OCPP 2.1</span>
                    </div>
                    
                    <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-6 py-3 text-base font-semibold text-white shadow-lg hover:bg-brand-800">
                                Buka Dashboard
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-6 py-3 text-base font-semibold text-white shadow-lg hover:bg-brand-800">
                                Mulai Gratis
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </a>
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-6 py-3 text-base font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                Login
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </section>

        <section class="py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-8 md:grid-cols-3">
                    <div class="rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900">
                            <svg class="h-6 w-6 text-brand-700 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h3 class="mb-2 text-xl font-semibold text-gray-900 dark:text-white">Real-time Monitoring</h3>
                        <p class="text-gray-600 dark:text-gray-300">Monitor status charging, energy consumption, dan meter values secara real-time dengan WebSocket.</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900">
                            <svg class="h-6 w-6 text-brand-700 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                        </div>
                        <h3 class="mb-2 text-xl font-semibold text-gray-900 dark:text-white">Remote Control</h3>
                        <p class="text-gray-600 dark:text-gray-300">Kontrol charging station dari jarak jauh: start/stop transaction, unlock connector, reset, dan lainnya.</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900">
                            <svg class="h-6 w-6 text-brand-700 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 class="mb-2 text-xl font-semibold text-gray-900 dark:text-white">OCPP 1.6 &amp; 2.1 Compliant</h3>
                        <p class="text-gray-600 dark:text-gray-300">Mendukung OCPP 1.6 JSON dan OCPP 2.1 — kompatibel dengan berbagai merk charging station, satu CSMS untuk infrastruktur lama maupun terbaru.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-gray-200 bg-gray-50 py-20 dark:border-gray-700 dark:bg-gray-900/50 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="mb-4 text-3xl font-bold text-gray-900 dark:text-white">Fitur Lengkap untuk Manajemen Charging</h2>
                    <p class="mx-auto mb-12 max-w-2xl text-lg text-gray-600 dark:text-gray-300">
                        Semua yang Anda butuhkan untuk mengelola infrastruktur charging station
                    </p>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([
                        ['icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'title' => 'Multi-tenant', 'desc' => 'Kelola multiple companies'],
                        ['icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Transaction History', 'desc' => 'Riwayat lengkap charging'],
                        ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'title' => 'Analytics', 'desc' => 'Dashboard & reporting'],
                        ['icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'title' => 'User Management', 'desc' => 'Role & permission control'],
                        ['icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'title' => 'Reservation', 'desc' => 'Booking connector'],
                        ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'title' => 'Diagnostics', 'desc' => 'Remote diagnostics & logs'],
                        ['icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'title' => 'Real-time Events', 'desc' => 'Push notifications'],
                        ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title' => 'Secure', 'desc' => 'Authentication & encryption'],
                    ] as $feature)
                        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                            <svg class="mb-3 h-8 w-8 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feature['icon'] }}"/>
                            </svg>
                            <h4 class="mb-1 font-semibold text-gray-900 dark:text-white">{{ $feature['title'] }}</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $feature['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl border border-gray-200 bg-gradient-to-br from-brand-600 to-brand-700 p-12 text-center dark:border-gray-700">
                    <h2 class="mb-4 text-3xl font-bold text-white">Siap Memulai?</h2>
                    <p class="mx-auto mb-8 max-w-2xl text-lg text-brand-100">
                        Bergabunglah dengan platform OCPP 1.6 &amp; 2.1 charging management terdepan di Indonesia
                    </p>
                    @guest
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-8 py-3 text-base font-semibold text-brand-700 shadow-lg hover:bg-gray-50">
                            Daftar Sekarang - Gratis
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                    @else
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-8 py-3 text-base font-semibold text-brand-700 shadow-lg hover:bg-gray-50">
                            Buka Dashboard
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                    @endguest
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-gray-200 bg-white py-12 dark:border-gray-700 dark:bg-gray-900">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="mb-4 flex items-center justify-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-brand-600 to-brand-700">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="text-left">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">CGS</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Charging Gateway System</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    OCPP 1.6 &amp; 2.1 Charging Management System — Pertama di Indonesia
                </p>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-500">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
