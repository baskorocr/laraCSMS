<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $config['title'] }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $config['subtitle'] }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (auth()->user()->hasRole('admin'))
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Tambah Data</h2>
                <form method="POST" action="{{ route('master.catalog.store', ['catalog' => $catalog]) }}" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @csrf
                    @foreach ($config['fields'] as $field)
                        <label class="space-y-1 text-sm">
                            <span class="text-gray-600 dark:text-gray-300">{{ str_replace('_', ' ', ucfirst($field)) }}</span>
                            @if ($field === 'is_active')
                                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                            @else
                                <input
                                    type="{{ in_array($field, ['sort_order', 'max_current_ampere', 'max_voltage'], true) ? 'number' : 'text' }}"
                                    name="{{ $field }}"
                                    class="auth-input px-3 py-2 text-sm"
                                    placeholder="{{ $field === 'supported_versions' ? '1.6,2.1' : '' }}"
                                    value="{{ old($field) }}"
                                >
                            @endif
                        </label>
                    @endforeach

                    <div class="md:col-span-2 xl:col-span-3">
                        <button type="submit" class="inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
                            Simpan
                        </button>
                    </div>
                </form>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-dark-eval-1">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-dark-eval-2">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">ID</th>
                            @foreach ($config['fields'] as $field)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {{ str_replace('_', ' ', $field) }}
                                </th>
                            @endforeach
                            @if (auth()->user()->hasRole('admin'))
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60">
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->id }}</td>
                                @foreach ($config['fields'] as $field)
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @if ($field === 'is_active')
                                            {{ $row->is_active ? 'Active' : 'Inactive' }}
                                        @elseif ($field === 'supported_versions')
                                            {{ implode(', ', json_decode($row->supported_versions ?? '[]', true)) }}
                                        @else
                                            {{ $row->{$field} }}
                                        @endif
                                    </td>
                                @endforeach
                                @if (auth()->user()->hasRole('admin'))
                                    <td class="px-4 py-3 text-sm">
                                        <form method="POST" action="{{ route('master.catalog.destroy', ['catalog' => $catalog, 'id' => $row->id]) }}" class="mb-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white">Delete</button>
                                        </form>

                                        <details class="group">
                                            <summary class="cursor-pointer text-brand-700 hover:text-brand-800">Edit</summary>
                                            <form method="POST" action="{{ route('master.catalog.update', ['catalog' => $catalog, 'id' => $row->id]) }}" class="mt-3 grid gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                                @csrf
                                                @method('PATCH')
                                                @foreach ($config['fields'] as $field)
                                                    <label class="space-y-1">
                                                        <span class="text-xs text-gray-500">{{ $field }}</span>
                                                        @if ($field === 'is_active')
                                                            <input type="checkbox" name="is_active" value="1" @checked((bool) $row->is_active) class="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                                                        @else
                                                            <input
                                                                type="{{ in_array($field, ['sort_order', 'max_current_ampere', 'max_voltage'], true) ? 'number' : 'text' }}"
                                                                name="{{ $field }}"
                                                                value="{{ $field === 'supported_versions' ? implode(',', json_decode($row->supported_versions ?? '[]', true)) : $row->{$field} }}"
                                                                class="auth-input px-2 py-1.5 text-xs"
                                                            >
                                                        @endif
                                                    </label>
                                                @endforeach
                                                <button type="submit" class="rounded bg-brand-700 px-2.5 py-1.5 text-xs font-medium text-white">Update</button>
                                            </form>
                                        </details>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($config['fields']) + 2 }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
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

