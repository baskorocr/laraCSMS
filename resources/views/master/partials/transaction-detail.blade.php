<div
    id="transaction-detail-modal"
    class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/50 p-3 sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="transaction-detail-title"
>
    <div class="flex min-h-full items-end justify-center sm:items-center">
        <div class="w-full max-w-4xl rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-dark-eval-1 sm:my-8">
            <div class="sticky top-0 z-10 rounded-t-xl border-b border-gray-200 bg-white/95 px-4 py-4 backdrop-blur-sm dark:border-gray-700 dark:bg-dark-eval-1/95 sm:px-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h2 id="transaction-detail-title" class="text-lg font-semibold text-gray-900 dark:text-white">Detail Pengecasan</h2>
                        <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">
                            Transaksi: <span id="txn-detail-code" class="font-medium">-</span>
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Charge Point: <span id="txn-detail-cp" class="font-medium">-</span>
                            <span class="mx-1">·</span>
                            Connector: <span id="txn-detail-connector" class="font-medium">-</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        id="close-transaction-detail-modal"
                        class="shrink-0 rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="space-y-4 px-4 py-4 sm:px-5 sm:py-5">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Status</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white" id="txn-detail-status">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Durasi</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white" id="txn-detail-duration">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Energi (kWh)</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white sm:text-xl" id="txn-detail-energy">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Meter Start (Wh)</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white" id="txn-detail-meter-start">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Meter Stop (Wh)</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white" id="txn-detail-meter-stop">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">SoC (%)</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white sm:text-xl" id="txn-detail-soc">-</div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Mulai</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-white" id="txn-detail-started">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Selesai</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-white" id="txn-detail-stopped">-</div>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Meter Values</h3>
                    <div class="max-h-56 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-dark-eval-2">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-500">Waktu</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-500">Measurand</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-500">Nilai</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-500">Unit</th>
                                </tr>
                            </thead>
                            <tbody id="txn-detail-meter-body" class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-gray-500">Memuat...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('transaction-detail-modal');
        const closeBtn = document.getElementById('close-transaction-detail-modal');
        const meterBody = document.getElementById('txn-detail-meter-body');
        const detailUrlTemplate = @json(route('master.transactions.detail', ['id' => '__ID__']));

        if (!modal || !closeBtn) {
            return;
        }

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value ?? '-';
            }
        };

        const openModal = async (transactionId) => {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            meterBody.innerHTML = '<tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">Memuat...</td></tr>';

            const url = detailUrlTemplate.replace('__ID__', String(transactionId));

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat detail transaksi');
                }

                const json = await response.json();
                const txn = json.transaction ?? {};
                const summary = json.summary ?? {};

                setText('txn-detail-code', txn.transaction_code);
                setText('txn-detail-cp', txn.charge_point_id);
                setText('txn-detail-connector', txn.connector_id !== null ? `#${txn.connector_id}` : '-');
                setText('txn-detail-status', txn.status);
                setText('txn-detail-duration', summary.duration);
                setText('txn-detail-energy', summary.energy_kwh !== null && summary.energy_kwh !== undefined ? Number(summary.energy_kwh).toFixed(3) : '-');
                setText('txn-detail-meter-start', txn.meter_start);
                setText('txn-detail-meter-stop', txn.meter_stop ?? '-');
                setText('txn-detail-soc', summary.latest_soc_percent ?? '-');
                setText('txn-detail-started', txn.started_at);
                setText('txn-detail-stopped', txn.stopped_at ?? '-');

                const rows = Array.isArray(json.meter_values) ? json.meter_values : [];
                if (rows.length === 0) {
                    meterBody.innerHTML = '<tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">Belum ada meter values.</td></tr>';
                } else {
                    meterBody.innerHTML = rows.map((row) => `
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">${row.sampled_at ?? '-'}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">${row.measurand ?? '-'}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">${row.value ?? '-'}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">${row.unit ?? '-'}</td>
                        </tr>
                    `).join('');
                }
            } catch (error) {
                meterBody.innerHTML = `<tr><td colspan="4" class="px-3 py-6 text-center text-red-500">${error.message}</td></tr>`;
            }
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-open-transaction-detail]');
            if (button) {
                openModal(button.dataset.transactionId);
                return;
            }

            if (event.target === modal) {
                closeModal();
            }
        });

        closeBtn.addEventListener('click', closeModal);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    });
</script>
