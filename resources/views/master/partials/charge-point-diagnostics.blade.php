<div
    id="diagnostics-modal"
    class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/50 p-3 sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="diagnostics-modal-title"
>
    <div class="flex min-h-full items-end justify-center sm:items-center">
        <div class="w-full max-w-5xl rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-dark-eval-1 sm:my-8">
            <div class="sticky top-0 z-10 rounded-t-xl border-b border-gray-200 bg-white/95 px-4 py-4 backdrop-blur-sm dark:border-gray-700 dark:bg-dark-eval-1/95 sm:px-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h2 id="diagnostics-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Get Diagnostics</h2>
                        <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">
                            Charge Point: <span id="diagnostics-charge-point" class="font-medium">-</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        id="close-diagnostics-modal"
                        class="shrink-0 rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="space-y-4 px-4 py-4 sm:px-5 sm:py-5">
                <div class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-dark-eval-2">
                    <label class="space-y-1 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Dari tanggal</span>
                        <input type="date" id="diagnostics-date-from" class="auth-input px-3 py-2 text-sm">
                    </label>
                    <label class="space-y-1 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Sampai tanggal</span>
                        <input type="date" id="diagnostics-date-to" class="auth-input px-3 py-2 text-sm">
                    </label>
                    <button
                        type="button"
                        id="diagnostics-request-btn"
                        class="inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800"
                    >
                        Request GetDiagnostics
                    </button>
                    <button
                        type="button"
                        id="diagnostics-refresh-btn"
                        class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2"
                    >
                        Refresh
                    </button>
                </div>

                <p id="diagnostics-feedback" class="hidden rounded-lg px-3 py-2 text-sm"></p>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-dark-eval-2">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">ID</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">FTP Location</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Retries</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Created</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody id="diagnostics-table-body" class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-gray-500">Memuat...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('diagnostics-modal');
        const closeBtn = document.getElementById('close-diagnostics-modal');
        const tableBody = document.getElementById('diagnostics-table-body');
        const chargePointLabel = document.getElementById('diagnostics-charge-point');
        const dateFrom = document.getElementById('diagnostics-date-from');
        const dateTo = document.getElementById('diagnostics-date-to');
        const requestBtn = document.getElementById('diagnostics-request-btn');
        const refreshBtn = document.getElementById('diagnostics-refresh-btn');
        const feedback = document.getElementById('diagnostics-feedback');
        const listUrlTemplate = @json(route('master.charge-points.diagnostics.index', ['id' => '__ID__']));
        const storeUrlTemplate = @json(route('master.charge-points.diagnostics.store', ['id' => '__ID__']));
        const downloadUrlTemplate = @json(route('master.diagnostics.download', ['requestId' => '__REQUEST_ID__']));
        const csrfToken = @json(csrf_token());

        if (!modal || !closeBtn) {
            return;
        }

        let selectedChargePointPk = null;
        let selectedChargePointCode = null;

        const showFeedback = (message, isError = false) => {
            if (!feedback) {
                return;
            }
            feedback.textContent = message;
            feedback.classList.remove('hidden', 'bg-green-50', 'text-green-700', 'bg-red-50', 'text-red-700');
            feedback.classList.add(isError ? 'bg-red-50' : 'bg-green-50', isError ? 'text-red-700' : 'text-green-700');
        };

        const statusBadge = (status) => {
            const colors = {
                Requested: 'bg-blue-100 text-blue-800',
                Uploading: 'bg-yellow-100 text-yellow-800',
                Uploaded: 'bg-green-100 text-green-800',
                UploadFailed: 'bg-red-100 text-red-800',
                Failed: 'bg-red-100 text-red-800',
            };
            const cls = colors[status] || 'bg-gray-100 text-gray-800';
            return `<span class="inline-flex rounded px-2 py-0.5 text-xs font-medium ${cls}">${status}</span>`;
        };

        const loadDiagnostics = async () => {
            if (!selectedChargePointPk) {
                return;
            }

            tableBody.innerHTML = '<tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">Memuat...</td></tr>';

            const url = listUrlTemplate.replace('__ID__', String(selectedChargePointPk));

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat data diagnostics');
                }

                const json = await response.json();
                const rows = Array.isArray(json.data) ? json.data : [];

                if (rows.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">Belum ada permintaan diagnostics.</td></tr>';
                    return;
                }

                tableBody.innerHTML = rows.map((row) => {
                    const downloadBtn = row.status === 'Uploaded'
                        ? `<a href="${downloadUrlTemplate.replace('__REQUEST_ID__', row.id)}" class="text-brand-700 hover:underline" target="_blank" rel="noopener">Download</a>`
                        : `<span class="text-xs text-gray-400">${row.status}</span>`;

                    return `
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">${row.id}</td>
                            <td class="max-w-xs truncate px-3 py-2 text-gray-700 dark:text-gray-300" title="${row.location ?? ''}">${row.location ?? '-'}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">${row.retries ?? '-'}</td>
                            <td class="whitespace-nowrap px-3 py-2">${statusBadge(row.status)}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">${row.created_at ?? '-'}</td>
                            <td class="whitespace-nowrap px-3 py-2">${downloadBtn}</td>
                        </tr>
                    `;
                }).join('');
            } catch (error) {
                tableBody.innerHTML = `<tr><td colspan="6" class="px-3 py-8 text-center text-red-500">${error.message}</td></tr>`;
            }
        };

        const openModal = (button) => {
            selectedChargePointPk = String(button.dataset.chargePointId);
            selectedChargePointCode = String(button.dataset.chargePointCode || '');
            chargePointLabel.textContent = selectedChargePointCode || '-';
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            loadDiagnostics();
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            selectedChargePointPk = null;
            selectedChargePointCode = null;
            if (feedback) {
                feedback.classList.add('hidden');
            }
        };

        requestBtn?.addEventListener('click', async () => {
            if (!selectedChargePointPk) {
                return;
            }

            requestBtn.disabled = true;
            const url = storeUrlTemplate.replace('__ID__', String(selectedChargePointPk));

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        date_from: dateFrom?.value || null,
                        date_to: dateTo?.value || null,
                        retries: 3,
                        retry_interval: 60,
                    }),
                });

                const json = await response.json();

                if (!response.ok || !json.success) {
                    throw new Error(json.message || 'Gagal mengirim permintaan diagnostics');
                }

                showFeedback(json.message || 'Permintaan berhasil dikirim.', false);
                await loadDiagnostics();
            } catch (error) {
                showFeedback(error.message, true);
            } finally {
                requestBtn.disabled = false;
            }
        });

        refreshBtn?.addEventListener('click', loadDiagnostics);

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-open-diagnostics]');
            if (button) {
                openModal(button);
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
