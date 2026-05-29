<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                <span data-csms-pusher-status class="font-medium text-amber-600 dark:text-amber-400">Pusher: menghubungkan...</span>
                · Status connector realtime (sama seperti Charge Points)
            </p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
            <form method="GET" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="space-y-1 text-sm md:col-span-2">
                    <span class="text-gray-600 dark:text-gray-300">Search</span>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="auth-input px-3 py-2 text-sm" placeholder="Cari charge point / company...">
                </label>

                @if ($isAdmin)
                    <label class="space-y-1 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Company</span>
                        <select name="company_id" class="auth-input px-3 py-2 text-sm">
                            <option value="">Semua</option>
                            @foreach ($companyOptions as $company)
                                <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
                        Search
                    </button>
                    <a href="{{ route('master.sessions') }}" class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-dark-eval-1">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-dark-eval-2">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Charge Point</th>
                            @if ($isAdmin)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Company</th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Connectors</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60" data-charge-point-row="{{ $row->id }}">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="font-medium">{{ $row->charge_point_id }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row->name }}</div>
                                </td>
                                @if ($isAdmin)
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->company_name ?? '-' }}</td>
                                @endif
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    @if ($row->connector_count > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach (explode('|', $row->connector_statuses) as $connectorStatus)
                                                @php
                                                    [$connectorId, $status] = explode(':', $connectorStatus);
                                                    $statusColor = match($status) {
                                                        'Available' => 'bg-green-100 text-green-800',
                                                        'Charging', 'Occupied' => 'bg-blue-100 text-blue-800',
                                                        'Preparing', 'Finishing', 'Reserved' => 'bg-yellow-100 text-yellow-800',
                                                        'Faulted', 'Unavailable' => 'bg-red-100 text-red-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                @endphp
                                                <span
                                                    class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $statusColor }}"
                                                    data-connector-badge="{{ $connectorId }}"
                                                >
                                                    #{{ $connectorId }}: {{ $status }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-gray-400 text-xs">No connectors</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    @if ($row->connector_count > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach (explode('|', $row->connector_statuses) as $connectorStatus)
                                                @php
                                                    [$connectorId, $status] = explode(':', $connectorStatus);
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="inline-flex rounded bg-brand-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-brand-800"
                                                    data-open-monitor
                                                    data-charge-point-id="{{ $row->id }}"
                                                    data-charge-point-code="{{ $row->charge_point_id }}"
                                                    data-connector-id="{{ $connectorId }}"
                                                >
                                                    Monitor #{{ $connectorId }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <button
                                            type="button"
                                            class="inline-flex rounded bg-brand-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-brand-800"
                                            data-open-monitor
                                            data-charge-point-id="{{ $row->id }}"
                                            data-charge-point-code="{{ $row->charge_point_id }}"
                                        >
                                            Monitor
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 4 : 3 }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Data belum tersedia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @include('master.partials.charge-point-realtime')
    @include('master.partials.charge-point-monitor')
</x-app-layout>
