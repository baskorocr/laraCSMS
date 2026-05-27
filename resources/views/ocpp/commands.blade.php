<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">OCPP Command Queue</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitor status command outbound dan kirim preset command ke charger.</p>
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

        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Send Command</h2>

            <form method="POST" action="{{ route('ocpp.commands.store') }}" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @csrf
                <label class="space-y-1 text-sm">
                    <span class="text-gray-600 dark:text-gray-300">Charge Point</span>
                    <select name="charge_point_id" class="auth-input px-3 py-2 text-sm" required>
                        <option value="">Select charge point</option>
                        @foreach ($chargePoints as $cp)
                            <option value="{{ $cp }}">{{ $cp }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="text-gray-600 dark:text-gray-300">Preset</span>
                    <select id="preset-select" class="auth-input px-3 py-2 text-sm">
                        <option value="">Custom</option>
                        @foreach ($presets as $preset)
                            <option value="{{ $preset['action'] }}" data-payload="{{ $preset['payload'] }}">{{ $preset['action'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="text-gray-600 dark:text-gray-300">Action</span>
                    <input id="action-input" name="action" class="auth-input px-3 py-2 text-sm" required>
                </label>

                <label class="space-y-1 text-sm md:col-span-2 xl:col-span-4">
                    <span class="text-gray-600 dark:text-gray-300">Payload JSON</span>
                    <textarea id="payload-input" name="payload" rows="3" class="auth-input px-3 py-2 text-sm font-mono" placeholder='{"connectorId":1}'></textarea>
                </label>

                <div class="md:col-span-2 xl:col-span-4">
                    <button type="submit" class="inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">Queue Command</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-dark-eval-1">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-dark-eval-2">
                        <tr>
                            @foreach (['ID','Charge Point','Action','Status','Attempts','Message UID','Sent At','Responded At','Error'] as $col)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->id }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->cp_code ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->action }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold @if($row->status === 'acknowledged') bg-green-100 text-green-700 @elseif($row->status === 'error' || $row->status === 'timeout') bg-red-100 text-red-700 @elseif($row->status === 'sent') bg-blue-100 text-blue-700 @else bg-gray-100 text-gray-700 @endif">
                                        {{ $row->status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->attempts }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-mono text-gray-600 dark:text-gray-300">{{ $row->message_uid ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->sent_at ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->responded_at ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400">{{ $row->error_code ? $row->error_code.': '.$row->error_description : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada command queued.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const preset = document.getElementById('preset-select');
            const actionInput = document.getElementById('action-input');
            const payloadInput = document.getElementById('payload-input');

            preset?.addEventListener('change', function () {
                const opt = preset.options[preset.selectedIndex];
                if (!opt || !opt.value) return;
                actionInput.value = opt.value;
                payloadInput.value = opt.dataset.payload || '{}';
            });
        });
    </script>
</x-app-layout>

