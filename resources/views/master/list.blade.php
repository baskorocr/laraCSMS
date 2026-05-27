<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
        </div>
    </x-slot>

    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-dark-eval-1">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-dark-eval-2">
                    <tr>
                        @foreach ($columns as $column)
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ $column }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800" id="master-list-body">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60">
                            @foreach ($row as $cell)
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $cell }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr data-empty-state>
                            <td colspan="{{ count($columns) }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                Data belum tersedia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>

@if (! empty($realtimeConfig))
    <script>
        (function () {
            if (!window.Echo) {
                return;
            }

            const tbody = document.getElementById('master-list-body');
            if (!tbody) {
                return;
            }

            const emptyText = @json($realtimeConfig['emptyText'] ?? 'Data belum tersedia.');
            const channelName = @json($realtimeConfig['channel'] ?? '');
            const eventName = @json($realtimeConfig['event'] ?? '');
            const columnCount = {{ count($columns) }};

            if (!channelName || !eventName) {
                return;
            }

            const createCell = (value) => {
                const td = document.createElement('td');
                td.className = 'whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300';
                td.textContent = value ?? '-';
                return td;
            };

            window.Echo.channel(channelName).listen(eventName, (event) => {
                const payload = event && event.meterValue ? event.meterValue : null;
                if (!payload || !payload.id) {
                    return;
                }

                const emptyRow = tbody.querySelector('[data-empty-state]');
                if (emptyRow) {
                    emptyRow.remove();
                }

                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60';
                tr.appendChild(createCell(String(payload.id)));
                tr.appendChild(createCell(payload.charge_point_id || '-'));
                tr.appendChild(createCell(payload.measurand || '-'));
                tr.appendChild(createCell(String(payload.value ?? '-')));
                tr.appendChild(createCell(payload.unit || '-'));
                tr.appendChild(createCell(payload.sampled_at || '-'));
                tbody.prepend(tr);

                while (tbody.children.length > 200) {
                    tbody.removeChild(tbody.lastElementChild);
                }

                if (!tbody.children.length) {
                    const fallback = document.createElement('tr');
                    fallback.setAttribute('data-empty-state', '');
                    const cell = document.createElement('td');
                    cell.colSpan = columnCount;
                    cell.className = 'px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400';
                    cell.textContent = emptyText;
                    fallback.appendChild(cell);
                    tbody.appendChild(fallback);
                }
            });
        })();
    </script>
@endif

