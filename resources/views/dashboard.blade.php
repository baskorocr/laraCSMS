<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-2xl">
                    {{ __('Dashboard') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Overview of your charging network') }}
                </p>
            </div>
            <time
                datetime="{{ now()->toDateString() }}"
                class="text-sm text-gray-500 dark:text-gray-400"
            >
                {{ now()->translatedFormat('l, d F Y') }}
            </time>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Welcome --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-dark-eval-1">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4 dark:border-gray-700 dark:bg-dark-eval-2/50">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Welcome back') }}
                </p>
                <h2 class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ Auth::user()->name }}
                </h2>
            </div>
            <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                    {{ __('Monitor charge points, active sessions, and energy delivery across your organization.') }}
                </p>
                <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-medium text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-green-500" aria-hidden="true"></span>
                    {{ __('Online') }}
                </span>
            </div>
        </section>

        {{-- Stats --}}
        <section
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="{{ __('Statistics') }}"
        >
            @php
                $stats = [
                    ['label' => __('Charge Points'), 'value' => '0', 'hint' => __('Registered')],
                    ['label' => __('Active Sessions'), 'value' => '0', 'hint' => __('In progress')],
                    ['label' => __('Energy Today'), 'value' => '0', 'hint' => __('kWh')],
                    ['label' => __('Companies'), 'value' => '0', 'hint' => __('Tenants')],
                ];
            @endphp

            @foreach ($stats as $stat)
                <article class="dashboard-stat-card">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $stat['label'] }}
                    </p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-gray-900 dark:text-white">
                        {{ $stat['value'] }}
                    </p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        {{ $stat['hint'] }}
                    </p>
                </article>
            @endforeach
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Activity --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white xl:col-span-2 dark:border-gray-700 dark:bg-dark-eval-1">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ __('Recent activity') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Latest OCPP events') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col items-center justify-center px-6 py-20 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-dark-eval-2">
                        <x-heroicon-o-inbox class="h-7 w-7 text-gray-400" aria-hidden="true" />
                    </div>
                    <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">
                        {{ __('No events yet') }}
                    </p>
                    <p class="mt-1 max-w-xs text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Activity will appear when charge points connect via OCPP.') }}
                    </p>
                </div>
            </section>

            {{-- System status --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-dark-eval-1">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ __('System status') }}
                    </h2>
                </div>

                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ([
                        ['label' => __('OCPP WebSocket'), 'status' => __('Ready'), 'ok' => true],
                        ['label' => __('Authorization'), 'status' => __('Ready'), 'ok' => true],
                        ['label' => __('Meter values'), 'status' => __('Waiting'), 'ok' => false],
                    ] as $item)
                        <li class="flex items-center justify-between gap-3 px-6 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-400' => $item['ok'],
                                'bg-gray-100 text-gray-600 dark:bg-dark-eval-2 dark:text-gray-400' => ! $item['ok'],
                            ])>
                                <span @class([
                                    'h-1.5 w-1.5 rounded-full',
                                    'bg-green-500' => $item['ok'],
                                    'bg-gray-400' => ! $item['ok'],
                                ]) aria-hidden="true"></span>
                                {{ $item['status'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-700">
                    <a
                        href="{{ route('profile.edit') }}"
                        class="text-sm font-medium text-brand-700 hover:text-brand-800 focus:outline-none focus-visible:underline dark:text-brand-500 dark:hover:text-brand-400"
                    >
                        {{ __('Account settings') }} &rarr;
                    </a>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
