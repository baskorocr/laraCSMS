<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
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
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="font-medium">{{ $row->charge_point_id }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row->name }}</div>
                                </td>
                                @if ($isAdmin)
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->company_name ?? '-' }}</td>
                                @endif
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <button
                                        type="button"
                                        class="inline-flex rounded bg-brand-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-brand-800"
                                        data-open-monitor
                                        data-charge-point-id="{{ $row->id }}"
                                        data-charge-point-code="{{ $row->charge_point_id }}"
                                    >
                                        Monitor
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 3 : 2 }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Data belum tersedia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>

@include('master.partials.charge-point-monitor')
