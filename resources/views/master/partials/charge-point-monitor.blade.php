<div
    id="monitor-modal"
    class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/50 p-3 sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="monitor-modal-title"
>
    <div class="flex min-h-full items-end justify-center sm:items-center">
        <div class="w-full max-w-4xl rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-dark-eval-1 sm:my-8">
            <div class="sticky top-0 z-10 rounded-t-xl border-b border-gray-200 bg-white/95 px-4 py-4 backdrop-blur-sm dark:border-gray-700 dark:bg-dark-eval-1/95 sm:px-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h2 id="monitor-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Real-time Monitoring</h2>
                        <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">
                            Charge Point: <span id="monitor-charge-point" class="font-medium">-</span>
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Pusher/Echo:
                            <span id="realtime-connection-badge" class="font-semibold text-gray-500">Connecting...</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            type="button"
                            id="close-monitor-modal"
                            class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4 px-4 py-4 sm:px-5 sm:py-5">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Status</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white" id="monitor-status">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Online</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white" id="monitor-online">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Energy (Wh)</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white sm:text-xl" id="monitor-energy">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Power (kW)</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white sm:text-xl" id="monitor-power">-</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">SoC (%)</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white sm:text-xl" id="monitor-soc">-</div>
                    </div>
                    <div class="col-span-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700 sm:col-span-1">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Last Sample</div>
                        <div class="mt-1 text-sm font-medium text-gray-900 dark:text-white" id="monitor-sampled-at">-</div>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Realtime Events</h3>
                    <div id="monitor-log" class="max-h-48 space-y-2 overflow-y-auto overscroll-contain rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs sm:max-h-64 dark:border-gray-700 dark:bg-dark-eval-2 dark:text-gray-200"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('monitor-modal');
        const closeModalButton = document.getElementById('close-monitor-modal');
        const monitorChargePoint = document.getElementById('monitor-charge-point');
        const monitorStatus = document.getElementById('monitor-status');
        const monitorOnline = document.getElementById('monitor-online');
        const monitorEnergy = document.getElementById('monitor-energy');
        const monitorPower = document.getElementById('monitor-power');
        const monitorSoc = document.getElementById('monitor-soc');
        const monitorSampledAt = document.getElementById('monitor-sampled-at');
        const monitorLog = document.getElementById('monitor-log');
        const connectionBadge = document.getElementById('realtime-connection-badge');
        const liveEndpoint = @json(route('master.sessions.live', request()->query()));

        if (!modal || !closeModalButton) {
            return;
        }

        let selectedChargePointPk = null;
        let selectedChargePointCode = null;
        let echoConnected = false;
        let monitorOnlineState = false;
        let echoListenersReady = false;
        let logFlushTimer = null;
        let autoResyncTimer = null;
        let syncInFlight = false;
        const pendingLogs = [];

        const isModalOpen = () => !modal.classList.contains('hidden');

        const isSelectedChargePoint = (payload) => {
            if (!payload || !isModalOpen()) {
                return false;
            }

            const payloadPk = payload.id ?? payload.charge_point_pk ?? payload.chargePointPk;
            if (payloadPk !== null && payloadPk !== undefined && String(payloadPk) === String(selectedChargePointPk)) {
                return true;
            }

            const payloadCode = payload.charge_point_id ?? payload.chargePointId ?? payload.chargePointCode;
            return !!payloadCode && String(payloadCode) === String(selectedChargePointCode);
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

        const flushLogs = () => {
            if (!monitorLog || pendingLogs.length === 0) {
                return;
            }

            const fragment = document.createDocumentFragment();
            pendingLogs.splice(0).forEach((text) => {
                const line = document.createElement('div');
                line.textContent = text;
                fragment.appendChild(line);
            });

            monitorLog.prepend(fragment);

            while (monitorLog.children.length > 50) {
                monitorLog.removeChild(monitorLog.lastElementChild);
            }
        };

        const appendLog = (text) => {
            pendingLogs.unshift(text);
            if (logFlushTimer) {
                return;
            }
            logFlushTimer = window.setTimeout(() => {
                logFlushTimer = null;
                flushLogs();
            }, 80);
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

            connectionBadge.textContent = monitorOnlineState
                ? 'Connected — push aktif'
                : 'Connected — charge point offline';
            connectionBadge.className = monitorOnlineState
                ? 'font-semibold text-green-600 dark:text-green-400'
                : 'font-semibold text-gray-600 dark:text-gray-300';
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

        const setMonitorCard = (field, value) => {
            const text = value ?? '-';
            const targets = {
                status: monitorStatus,
                online: monitorOnline,
                energy: monitorEnergy,
                power: monitorPower,
                soc: monitorSoc,
                sampled_at: monitorSampledAt,
            };

            if (targets[field]) {
                targets[field].textContent = text;
            }
        };

        const clearMeterCards = () => {
            setMonitorCard('energy', '-');
            setMonitorCard('power', '-');
            setMonitorCard('soc', '-');
        };

        const hasMissingMeterData = () => {
            return monitorEnergy?.textContent === '-'
                && monitorPower?.textContent === '-'
                && monitorSoc?.textContent === '-';
        };

        const stopAutoResync = () => {
            if (autoResyncTimer) {
                clearInterval(autoResyncTimer);
                autoResyncTimer = null;
            }
        };

        const scheduleAutoResync = () => {
            stopAutoResync();

            if (!isModalOpen() || !monitorOnlineState || !hasMissingMeterData()) {
                return;
            }

            syncFromDatabase({ silent: true });

            autoResyncTimer = window.setInterval(() => {
                if (!isModalOpen() || !monitorOnlineState || !hasMissingMeterData()) {
                    stopAutoResync();
                    return;
                }

                syncFromDatabase({ silent: true });
            }, 3000);
        };

        const applyMonitorSnapshot = (item) => {
            if (!item) {
                return;
            }

            monitorOnlineState = !!item.is_online;
            setMonitorCard('status', item.status || '-');
            setMonitorCard('online', item.is_online ? 'Online' : 'Offline');

            if (item.is_online) {
                setMonitorCard('energy', item.latest_energy ?? '-');
                setMonitorCard('power', item.latest_power ?? '-');
                setMonitorCard('soc', item.latest_soc ?? '-');
                setMonitorCard('sampled_at', item.last_sampled_at ?? '-');
            } else {
                clearMeterCards();
                setMonitorCard('sampled_at', '-');
            }

            refreshConnectionBadge();
            scheduleAutoResync();
        };

        const applyChargePointStatus = (payload) => {
            if (!payload) {
                return;
            }

            applyChargePointRow(payload);

            if (!isSelectedChargePoint(payload)) {
                return;
            }

            monitorOnlineState = !!payload.is_online;
            setMonitorCard('status', payload.status || '-');
            setMonitorCard('online', payload.is_online ? 'Online' : 'Offline');

            if (!payload.is_online) {
                clearMeterCards();
                setMonitorCard('sampled_at', '-');
                stopAutoResync();
            } else {
                scheduleAutoResync();
            }

            refreshConnectionBadge();
            appendLog(`Status: ${payload.status || '-'} | ${payload.is_online ? 'Online' : 'Offline'}`);
        };

        const applyMeterValue = (payload) => {
            if (!isSelectedChargePoint(payload) || !monitorOnlineState) {
                return;
            }

            if (payload.sampled_at) {
                setMonitorCard('sampled_at', payload.sampled_at);
            }

            if (payload.measurand === 'Energy.Active.Import.Register') {
                setMonitorCard('energy', String(payload.value ?? '-'));
            }
            if (payload.measurand === 'Power.Active.Import') {
                setMonitorCard('power', String(payload.value ?? '-'));
            }
            if (payload.measurand === 'SoC') {
                setMonitorCard('soc', String(payload.value ?? '-'));
            }

            if (!hasMissingMeterData()) {
                stopAutoResync();
            }

            appendLog(`[${payload.sampled_at ?? '-'}] ${payload.measurand}: ${payload.value} ${payload.unit ?? ''}`.trim());
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
                applyChargePointStatus(event?.chargePoint ?? null);
            });

            window.Echo.channel('meter-values').listen('.meter-value.received', (event) => {
                applyMeterValue(event?.meterValue ?? null);
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

        const syncFromDatabase = ({ silent = false } = {}) => {
            if (!selectedChargePointCode || syncInFlight) {
                return Promise.resolve();
            }

            syncInFlight = true;

            const url = `${liveEndpoint}${liveEndpoint.includes('?') ? '&' : '?'}charge_point_id=${encodeURIComponent(selectedChargePointCode)}`;

            return fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.ok ? response.json() : Promise.reject(new Error('sync-failed')))
                .then((json) => {
                    const data = Array.isArray(json.data) ? json.data : [];
                    const monitored = data.find((item) => item.charge_point_id === selectedChargePointCode);

                    if (monitored) {
                        applyMonitorSnapshot(monitored);
                        applyChargePointRow(monitored);

                        if (!silent) {
                            appendLog(`resync ${json.synced_at}: E=${monitored.latest_energy ?? '-'} P=${monitored.latest_power ?? '-'} SoC=${monitored.latest_soc ?? '-'}`);
                        }
                    } else if (!silent) {
                        appendLog(`resync ${json.synced_at}: data tidak ditemukan`);
                    }
                })
                .catch(() => {
                    if (!silent) {
                        appendLog('Resync gagal — cek php artisan serve');
                    }
                })
                .finally(() => {
                    syncInFlight = false;
                });
        };

        const resetMonitorCards = () => {
            monitorOnlineState = false;
            ['status', 'online', 'energy', 'power', 'soc', 'sampled_at'].forEach((field) => setMonitorCard(field, '-'));
        };

        const lockBodyScroll = (locked) => {
            document.body.classList.toggle('overflow-hidden', locked);
        };

        const openModal = (button) => {
            selectedChargePointPk = String(button.dataset.chargePointId);
            selectedChargePointCode = String(button.dataset.chargePointCode || '');
            monitorChargePoint.textContent = selectedChargePointCode || '-';
            monitorLog.innerHTML = '';
            pendingLogs.length = 0;
            resetMonitorCards();

            modal.classList.remove('hidden');
            lockBodyScroll(true);
            appendLog(`Monitoring ${selectedChargePointCode} via Pusher/Echo`);

            syncEchoConnectionState();
            waitForEcho(() => {
                setupEchoListeners();
                syncFromDatabase();
            });
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            lockBodyScroll(false);
            stopAutoResync();
            selectedChargePointPk = null;
            selectedChargePointCode = null;
            monitorOnlineState = false;
        };

        document.addEventListener('click', (event) => {
            const openButton = event.target.closest('[data-open-monitor]');
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
