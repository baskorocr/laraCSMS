<div
    id="ocpp-payload-modal"
    class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/50 p-3 sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ocpp-payload-modal-title"
>
    <div class="flex min-h-full items-end justify-center sm:items-center">
        <div class="w-full max-w-4xl rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-dark-eval-1 sm:my-8">
            <div class="sticky top-0 z-10 rounded-t-xl border-b border-gray-200 bg-white/95 px-4 py-4 backdrop-blur-sm dark:border-gray-700 dark:bg-dark-eval-1/95 sm:px-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h2 id="ocpp-payload-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">OCPP Payload (WS :9001)</h2>
                        <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">
                            Charge Point: <span id="ocpp-payload-charge-point" class="font-medium">-</span>
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Pusher/Echo:
                            <span id="ocpp-payload-connection-badge" class="font-semibold text-gray-500">Connecting...</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            type="button"
                            id="close-ocpp-payload-modal"
                            class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4 px-4 py-4 sm:px-5 sm:py-5">
                <div id="ocpp-payload-empty" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-500 dark:border-gray-600 dark:bg-dark-eval-2 dark:text-gray-400">
                    Belum ada payload OCPP untuk charge point ini. Jalankan <code class="text-xs">php artisan ocpp:serve --port=9001</code> lalu hubungkan simulator.
                </div>
                <div id="ocpp-payload-latest" class="hidden"></div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('ocpp-payload-modal');
        const closeModalButton = document.getElementById('close-ocpp-payload-modal');
        const modalChargePoint = document.getElementById('ocpp-payload-charge-point');
        const modalLatest = document.getElementById('ocpp-payload-latest');
        const modalEmpty = document.getElementById('ocpp-payload-empty');
        const connectionBadge = document.getElementById('ocpp-payload-connection-badge');
        const liveEndpoint = @json(route('master.charge-points.ocpp-live'));

        if (!modal || !closeModalButton || !modalLatest || !modalEmpty) {
            return;
        }

        const latestMessageStore = new Map();
        let activeChargePointPk = null;
        let activeChargePointCode = null;
        let echoConnected = false;
        let echoListenersReady = false;
        let syncInFlight = false;

        const isModalOpen = () => !modal.classList.contains('hidden');

        const messageTypeLabel = (typeId) => {
            if (typeId === 2) {
                return 'CALL';
            }
            if (typeId === 3) {
                return 'CALLRESULT';
            }
            if (typeId === 4) {
                return 'CALLERROR';
            }

            return 'UNKNOWN';
        };

        const stringifyPretty = (value) => {
            try {
                return JSON.stringify(value, null, 2);
            } catch (error) {
                return String(value ?? '');
            }
        };

        const isNewerMessage = (current, candidate) => {
            if (!current) {
                return true;
            }

            if (candidate.id && current.id) {
                return Number(candidate.id) > Number(current.id);
            }

            return String(candidate.received_at || '') >= String(current.received_at || '');
        };

        const setLatestMessage = (item) => {
            if (!item?.charge_point_id) {
                return;
            }

            const pk = String(item.charge_point_id);
            const current = latestMessageStore.get(pk);

            if (!isNewerMessage(current, item)) {
                return;
            }

            latestMessageStore.set(pk, item);
        };

        const applyChargePointRow = (payload) => {
            if (!payload?.id) {
                return;
            }

            const row = document.querySelector(`[data-charge-point-row="${payload.id}"]`);
            if (!row) {
                return;
            }

            const statusCell = row.querySelector('[data-charge-point-status]');
            const onlineCell = row.querySelector('[data-charge-point-online]');

            if (statusCell) {
                statusCell.textContent = payload.status || '-';
            }

            if (onlineCell) {
                onlineCell.textContent = payload.is_online ? 'Online' : 'Offline';
            }
        };

        const refreshConnectionBadge = () => {
            if (!connectionBadge) {
                return;
            }

            if (!window.Echo) {
                connectionBadge.textContent = 'Echo belum aktif';
                connectionBadge.className = 'font-semibold text-amber-600 dark:text-amber-400';
                return;
            }

            if (typeof window.Echo.connectionStatus === 'function' && window.Echo.connectionStatus() === 'connecting') {
                connectionBadge.textContent = 'Menghubungkan...';
                connectionBadge.className = 'font-semibold text-amber-600 dark:text-amber-400';
                return;
            }

            if (!echoConnected) {
                connectionBadge.textContent = 'Echo terputus';
                connectionBadge.className = 'font-semibold text-red-600 dark:text-red-400';
                return;
            }

            connectionBadge.textContent = 'Connected — stream OCPP aktif';
            connectionBadge.className = 'font-semibold text-green-600 dark:text-green-400';
        };

        const syncEchoConnectionState = () => {
            if (!window.Echo || typeof window.Echo.connectionStatus !== 'function') {
                echoConnected = false;
                refreshConnectionBadge();
                return;
            }

            echoConnected = window.Echo.connectionStatus() === 'connected';
            refreshConnectionBadge();
        };

        const renderLatestMessage = () => {
            if (!activeChargePointPk) {
                return;
            }

            const item = latestMessageStore.get(String(activeChargePointPk)) || null;
            modalLatest.innerHTML = '';
            modalLatest.classList.toggle('hidden', !item);
            modalEmpty.classList.toggle('hidden', !!item);

            if (!item) {
                return;
            }

            const wrapper = document.createElement('section');
            wrapper.className = 'rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-dark-eval-2';

            const typeLabel = messageTypeLabel(Number(item.message_type_id ?? 0));
            const actionLabel = item.action || '-';
            const directionLabel = item.direction === 'outgoing'
                ? '← CSMS response'
                : '→';
            const receivedAt = item.received_at || '-';
            const chargePointCode = item.charge_point_code || activeChargePointCode || '-';
            const messageUid = item.message_uid || '-';

            const meta = document.createElement('div');
            meta.className = 'mb-2 font-mono text-xs text-gray-600 dark:text-gray-300';
            meta.textContent = `[${receivedAt}] [OCPP] ${chargePointCode} ${directionLabel} ${typeLabel} ${actionLabel} (msg=${messageUid})`;
            wrapper.appendChild(meta);

            const pre = document.createElement('pre');
            pre.className = 'overflow-x-auto rounded bg-gray-900 p-3 text-xs leading-relaxed text-green-200';
            pre.textContent = `  payload: ${stringifyPretty(item.payload ?? {})}`;
            wrapper.appendChild(pre);

            modalLatest.appendChild(wrapper);
        };

        const ingestMessage = (item) => {
            if (!item?.charge_point_id) {
                return;
            }

            setLatestMessage(item);

            if (String(item.charge_point_id) === String(activeChargePointPk) && isModalOpen()) {
                renderLatestMessage();
            }
        };

        const setupEchoListeners = () => {
            if (!window.Echo) {
                syncEchoConnectionState();
                return;
            }

            syncEchoConnectionState();

            if (echoListenersReady) {
                return;
            }

            echoListenersReady = true;

            if (typeof window.Echo.onConnectionChange === 'function') {
                window.Echo.onConnectionChange((status) => {
                    echoConnected = status === 'connected';
                    refreshConnectionBadge();
                });
            }

            window.Echo.channel('charge-points').listen('.charge-point.status.updated', (event) => {
                applyChargePointRow(event?.chargePoint ?? null);
            });

            window.Echo.channel('ocpp-messages').listen('.ocpp.message.received', (event) => {
                const payload = event?.message ?? null;
                if (!payload) {
                    return;
                }

                ingestMessage({
                    ...payload,
                    direction: payload.direction ?? 'incoming',
                });
            });
        };

        const waitForEcho = (callback, attemptsLeft = 80) => {
            if (window.Echo) {
                callback();
                return;
            }

            if (attemptsLeft <= 0) {
                refreshConnectionBadge();
                return;
            }

            window.setTimeout(() => waitForEcho(callback, attemptsLeft - 1), 100);
        };

        const loadHistory = () => {
            if (!activeChargePointCode || syncInFlight) {
                return Promise.resolve();
            }

            syncInFlight = true;

            const url = `${liveEndpoint}?charge_point_id=${encodeURIComponent(activeChargePointCode)}`;

            return fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.ok ? response.json() : Promise.reject(new Error('sync-failed')))
                .then((json) => {
                    const data = Array.isArray(json.data) ? json.data : [];
                    const latest = data.length > 0 ? data[0] : null;

                    if (latest) {
                        setLatestMessage(latest);
                    }

                    renderLatestMessage();
                })
                .catch(() => {
                    modalEmpty.textContent = 'Gagal memuat history payload — cek php artisan serve';
                    modalEmpty.classList.remove('hidden');
                })
                .finally(() => {
                    syncInFlight = false;
                });
        };

        const lockBodyScroll = (locked) => {
            document.body.classList.toggle('overflow-hidden', locked);
        };

        const openModal = (button) => {
            activeChargePointPk = String(button.dataset.chargePointId);
            activeChargePointCode = String(button.dataset.chargePointCode || '');
            modalChargePoint.textContent = activeChargePointCode || '-';
            modalLatest.innerHTML = '';
            modalLatest.classList.add('hidden');
            modalEmpty.classList.remove('hidden');

            modal.classList.remove('hidden');
            lockBodyScroll(true);

            syncEchoConnectionState();
            waitForEcho(() => {
                setupEchoListeners();
                loadHistory();
            });
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            lockBodyScroll(false);
            activeChargePointPk = null;
            activeChargePointCode = null;
        };

        document.addEventListener('click', (event) => {
            const openButton = event.target.closest('[data-open-ocpp-payload]');
            if (openButton) {
                openModal(openButton);
                return;
            }

            if (event.target === modal) {
                closeModal();
            }
        });

        closeModalButton.addEventListener('click', closeModal);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isModalOpen()) {
                closeModal();
            }
        });

        waitForEcho(setupEchoListeners);
    });
</script>
