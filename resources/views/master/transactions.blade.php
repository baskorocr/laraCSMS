<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
            <form method="GET" id="transactions-filter-form" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <label class="space-y-1 text-sm md:col-span-2">
                    <span class="text-gray-600 dark:text-gray-300">Search</span>
                    <input type="text" name="search" value="{{ $filters['search'] }}" class="auth-input px-3 py-2 text-sm" placeholder="Kode transaksi / id tag / charge point...">
                </label>

                <label class="space-y-1 text-sm">
                    <span class="text-gray-600 dark:text-gray-300">Dari tanggal</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="auth-input px-3 py-2 text-sm">
                </label>

                <label class="space-y-1 text-sm">
                    <span class="text-gray-600 dark:text-gray-300">Sampai tanggal</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="auth-input px-3 py-2 text-sm">
                </label>

                <label class="space-y-1 text-sm">
                    <span class="text-gray-600 dark:text-gray-300">Status</span>
                    <select name="status" class="auth-input px-3 py-2 text-sm">
                        <option value="">Semua</option>
                        <option value="ongoing" @selected($filters['status'] === 'ongoing')>Ongoing</option>
                        <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                    </select>
                </label>

                @if ($isAdmin)
                    <label class="space-y-1 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Company</span>
                        <select name="company_id" class="auth-input px-3 py-2 text-sm">
                            <option value="">Semua</option>
                            @foreach ($companyOptions as $company)
                                <option value="{{ $company->id }}" @selected((string) $filters['company_id'] === (string) $company->id)>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-1">
                    <button type="submit" class="inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
                        Filter
                    </button>
                    <a href="{{ route('master.transactions') }}" class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2">
                        Reset
                    </a>
                </div>
            </form>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                <button
                    type="button"
                    id="export-selected-btn"
                    class="inline-flex rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2"
                >
                    Export Excel (terpilih)
                </button>
                <a
                    href="{{ route('master.transactions.export', request()->query()) }}"
                    class="inline-flex rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                >
                    Export Excel (semua filter)
                </a>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-dark-eval-1">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-dark-eval-2">
                        <tr>
                            <th class="px-3 py-3">
                                <input type="checkbox" id="select-all-transactions" class="rounded border-gray-300 text-brand-700 focus:ring-brand-500">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Code</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Charge Point</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Connector</th>
                            @if ($isAdmin)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Company</th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Energy (kWh)</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Started</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Stopped</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            @php
                                $energyKwh = $row->meter_stop !== null
                                    ? max(0, round(((float) $row->meter_stop - (float) $row->meter_start) / 1000, 3))
                                    : null;
                            @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60">
                                <td class="px-3 py-3">
                                    <input type="checkbox" class="transaction-checkbox rounded border-gray-300 text-brand-700 focus:ring-brand-500" value="{{ $row->id }}">
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $row->transaction_code }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->cp_code ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $row->connector_no !== null ? '#'.$row->connector_no : '-' }}
                                </td>
                                @if ($isAdmin)
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->company_name ?? '-' }}</td>
                                @endif
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    @php
                                        $statusColor = match($row->status) {
                                            'ongoing' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $statusColor }}">{{ $row->status }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $energyKwh !== null ? number_format($energyKwh, 3) : '-' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->started_at }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->stopped_at ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <button
                                        type="button"
                                        class="inline-flex rounded bg-brand-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-brand-800"
                                        data-open-transaction-detail
                                        data-transaction-id="{{ $row->id }}"
                                    >
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 10 : 9 }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Data transaksi belum tersedia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>

@include('master.partials.transaction-detail')

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const selectAll = document.getElementById('select-all-transactions');
        const checkboxes = () => Array.from(document.querySelectorAll('.transaction-checkbox'));
        const exportSelectedBtn = document.getElementById('export-selected-btn');
        const exportBaseUrl = @json(route('master.transactions.export'));

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                checkboxes().forEach((cb) => {
                    cb.checked = selectAll.checked;
                });
            });
        }

        if (exportSelectedBtn) {
            exportSelectedBtn.addEventListener('click', () => {
                const ids = checkboxes().filter((cb) => cb.checked).map((cb) => cb.value);
                if (ids.length === 0) {
                    alert('Pilih minimal satu transaksi untuk di-export.');
                    return;
                }

                const params = new URLSearchParams(window.location.search);
                ids.forEach((id) => params.append('ids[]', id));
                window.location.href = `${exportBaseUrl}?${params.toString()}`;
            });
        }
    });
</script>
