<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-2xl">
                    {{ __('Dashboard') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($isGlobalAdmin)
                        {{ __('Overview of all companies and charge points') }}
                    @else
                        {{ __('Overview for') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ $companyName ?? __('your company') }}</span>
                    @endif
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
                    @if ($isGlobalAdmin)
                        {{ __('Monitor all tenants, charge points, sessions, and energy across the platform.') }}
                    @else
                        {{ __('Monitor charge points, sessions, and energy for your company only.') }}
                    @endif
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
                $statsData = [
                    ['label' => __('Charge Points'), 'value' => $stats['chargePoints'], 'hint' => __('Registered')],
                    ['label' => __('Active Sessions'), 'value' => $stats['activeSessions'], 'hint' => __('In progress')],
                    ['label' => __('Energy Today'), 'value' => $stats['energyToday'], 'hint' => __('kWh')],
                    ['label' => __('Connectors Available'), 'value' => $stats['connectorsAvailable'], 'hint' => __('Online/Available')],
                    ['label' => __('Connectors Fault'), 'value' => $stats['connectorsFault'], 'hint' => __('Offline/Faulted')],
                ];

                if ($isGlobalAdmin) {
                    array_splice($statsData, 3, 0, [[
                        'label' => __('Companies'),
                        'value' => $stats['companies'],
                        'hint' => __('Tenants'),
                    ]]);
                }
            @endphp

            @foreach ($statsData as $stat)
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

        {{-- Charge Point Map --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-dark-eval-1">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Charge Point Locations') }}</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ $chargePointMarkers->count() }} charge point terpasang lokasi
                </p>
            </div>
            <div id="dashboard-map" class="h-80 w-full"></div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Recent transactions --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white xl:col-span-2 dark:border-gray-700 dark:bg-dark-eval-1">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ __('Recent transactions') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            @if ($isGlobalAdmin)
                                {{ __('Latest charging sessions — all companies') }}
                            @else
                                {{ __('Latest charging sessions — your company') }}
                            @endif
                        </p>
                    </div>
                    @if (auth()->user()->canAccessRoute('master.transactions'))
                        <a
                            href="{{ route('master.transactions') }}"
                            class="text-xs font-medium text-brand-700 hover:text-brand-800 dark:text-brand-500 dark:hover:text-brand-400"
                        >
                            {{ __('View all') }} &rarr;
                        </a>
                    @endif
                </div>

                @if ($recentTransactions->isEmpty())
                    <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-dark-eval-2">
                            <x-heroicon-o-lightning-bolt class="h-7 w-7 text-gray-400" aria-hidden="true" />
                        </div>
                        <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">
                            {{ __('No transactions yet') }}
                        </p>
                        <p class="mt-1 max-w-xs text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Transactions appear when a charging session starts on a charge point.') }}
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                            <thead class="bg-gray-50/80 dark:bg-dark-eval-2/50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Code') }}</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Charge point') }}</th>
                                    @if ($isGlobalAdmin)
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Company') }}</th>
                                    @endif
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Status') }}</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Energy') }}</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Started') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($recentTransactions as $tx)
                                    @php
                                        $energyKwh = $tx->meter_stop !== null
                                            ? max(0, round(((float) $tx->meter_stop - (float) $tx->meter_start) / 1000, 2))
                                            : null;
                                        $statusColor = match ($tx->status) {
                                            'ongoing' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-300',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-dark-eval-2 dark:text-gray-300',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60">
                                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $tx->transaction_code ?: '#'.$tx->id }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            <div>{{ $tx->cp_code ?? '-' }}</div>
                                            @if ($tx->connector_no !== null)
                                                <div class="text-xs text-gray-500 dark:text-gray-400">#{{ $tx->connector_no }}</div>
                                            @endif
                                        </td>
                                        @if ($isGlobalAdmin)
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $tx->company_name ?? '-' }}
                                            </td>
                                        @endif
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $statusColor }}">
                                                {{ ucfirst($tx->status) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-gray-700 dark:text-gray-300">
                                            {{ $energyKwh !== null ? $energyKwh.' kWh' : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $tx->started_at ? \Illuminate\Support\Carbon::parse($tx->started_at)->format('d M Y H:i') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Total energy distributed --}}
            <section class="overflow-hidden rounded-xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white dark:border-emerald-900/40 dark:from-emerald-950/30 dark:to-dark-eval-1">
                <div class="border-b border-emerald-100 px-6 py-4 dark:border-emerald-900/30">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ __('Total energy distributed') }}
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        @if ($isGlobalAdmin)
                            {{ __('All companies — completed sessions') }}
                        @else
                            {{ __('Your company — completed sessions') }}
                        @endif
                    </p>
                </div>

                <div class="px-6 py-8">
                    <div class="flex items-end gap-2">
                        <p class="text-4xl font-semibold tabular-nums tracking-tight text-emerald-800 dark:text-emerald-300">
                            {{ number_format($totalEnergyDistributedKwh, 2, ',', '.') }}
                        </p>
                        <p class="pb-1.5 text-lg font-medium text-emerald-700 dark:text-emerald-400">kWh</p>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        @if ($isGlobalAdmin)
                            {{ __('Total energy delivered across all finished charging transactions on the platform.') }}
                        @else
                            {{ __('Total energy delivered for finished sessions at your company.') }}
                        @endif
                    </p>
                    <dl class="mt-6 space-y-3 border-t border-emerald-100 pt-5 dark:border-emerald-900/30">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Energy today') }}</dt>
                            <dd class="font-medium tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['energyToday'], 2, ',', '.') }} kWh</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Active sessions') }}</dt>
                            <dd class="font-medium tabular-nums text-gray-900 dark:text-white">{{ $stats['activeSessions'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-sm border-t border-emerald-100 pt-3 dark:border-emerald-900/30">
                            <dt class="font-medium text-gray-700 dark:text-gray-300">Total Pendapatan</dt>
                            <dd class="font-semibold tabular-nums text-emerald-800 dark:text-emerald-300">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const markers = @json($chargePointMarkers);

    const map = L.map('dashboard-map').setView([-2.5, 118], 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    window.addEventListener('load', () => map.invalidateSize());
    window.addEventListener('resize', () => map.invalidateSize());

    const bounds = [];

    markers.forEach((cp) => {
        const color = cp.is_online ? '#10b981' : '#6b7280';
        const icon = L.divIcon({
            className: '',
            html: `<div style="width:14px;height:14px;border-radius:50%;background:${color};border:2px solid white;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7],
        });

        const marker = L.marker([cp.lat, cp.lng], { icon }).addTo(map);
        marker.bindPopup(
            `<div style="min-width:160px">
                <div style="font-weight:600;font-size:13px">${cp.name}</div>
                <div style="font-size:11px;color:#6b7280">${cp.cp_id}</div>
                ${cp.company ? `<div style="font-size:11px;color:#6b7280">${cp.company}</div>` : ''}
                <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${color}"></span>
                    <span style="font-size:12px">${cp.is_online ? 'Online' : 'Offline'} &mdash; ${cp.status}</span>
                </div>
            </div>`
        );

        bounds.push([cp.lat, cp.lng]);
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 14);
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [40, 40] });
    }
})();
</script>
