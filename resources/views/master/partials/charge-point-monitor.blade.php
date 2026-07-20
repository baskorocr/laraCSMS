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
                        <form method="POST" action="{{ route('ocpp.commands.store') }}" id="monitor-unlock-form">
                            @csrf
                            <input type="hidden" name="charge_point_id" id="monitor-unlock-charge-point-code">
                            <input type="hidden" name="action" value="UnlockConnector">
                            <input type="hidden" name="payload" id="monitor-unlock-payload">
                            <button
                                type="submit"
                                id="monitor-unlock-button"
                                class="rounded bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                                disabled
                                title="Buka monitor via tombol Monitor #connector"
                                onclick="return confirm('Unlock connector ini?')"
                            >
                                Unlock Connector
                            </button>
                        </form>
                        <form method="POST" action="{{ route('master.sessions.stop') }}" id="monitor-stop-form">
                            @csrf
                            <input type="hidden" name="charge_point_id" id="monitor-stop-charge-point-id">
                            <input type="hidden" name="connector_id" id="monitor-stop-connector-id">
                            <button
                                type="submit"
                                id="monitor-stop-button"
                                class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-300"
                                disabled
                                title="Buka monitor via tombol Monitor #connector"
                                onclick="return confirm('Stop transaksi aktif pada connector ini?')"
                            >
                                Stop Connector
                            </button>
                        </form>
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
        const stopForm = document.getElementById('monitor-stop-form');
        const stopButton = document.getElementById('monitor-stop-button');
        const stopChargePointInput = document.getElementById('monitor-stop-charge-point-id');
        const stopConnectorInput = document.getElementById('monitor-stop-connector-id');
        const unlockForm = document.getElementById('monitor-unlock-form');
        const unlockButton = document.getElementById('monitor-unlock-button');
        const unlockChargePointInput = document.getElementById('monitor-unlock-charge-point-code');
        const unlockPayloadInput = document.getElementById('monitor-unlock-payload');
        const liveEndpoint = @json(route('master.sessions.live', request()->query()));

        if (!modal || !closeModalButton) {
            return;
        }

        let selectedChargePointPk = null;
        let selectedChargePointCode = null;
        let selectedConnectorId = null;
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
            if (typeof window.applyChargePointRealtimeRow === 'function') {
                window.applyChargePointRealtimeRow(payload);
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

            const hasActiveTransaction = item.active_transaction_count > 0;

            if (item.is_online && hasActiveTransaction) {
                setMonitorCard('energy', item.latest_energy ?? '-');
                setMonitorCard('power', item.latest_power ?? '-');
                setMonitorCard('soc', item.latest_soc ?? '-');
                setMonitorCard('sampled_at', item.last_sampled_at ?? '-');
            } else {
                clearMeterCards();
                setMonitorCard('sampled_at', '-');
            }

            updateUnlockButtonState(item.status);
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

            updateUnlockButtonState(payload.status);
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
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        return Promise.reject(new Error(`sync-failed:${response.status}`));
                    }

                    return response.json();
                })
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
                .catch((error) => {
                    if (!silent) {
                        const status = String(error?.message || '').includes('403')
                            ? '403 (izin route)'
                            : String(error?.message || '').replace('sync-failed:', 'HTTP ');
                        appendLog(`Resync gagal${status ? ` — ${status}` : ''}`);
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

        const updateUnlockButtonState = (status) => {
            if (!selectedConnectorId) return;

            const isCharging = status === 'Charging';

            if (stopButton) {
                stopButton.disabled = !isCharging;
                stopButton.title = isCharging ? '' : 'Connector tidak sedang charging';
            }

            if (unlockButton) {
                unlockButton.disabled = isCharging;
                unlockButton.title = isCharging
                    ? 'Tidak bisa unlock saat charging'
                    : `Unlock connector #${selectedConnectorId}`;
            }
        };

        const openModal = (button) => {
            selectedChargePointPk = String(button.dataset.chargePointId);
            selectedChargePointCode = String(button.dataset.chargePointCode || '');
            selectedConnectorId = button.dataset.connectorId ? String(button.dataset.connectorId) : null;
            monitorChargePoint.textContent = selectedChargePointCode || '-';
            monitorLog.innerHTML = '';
            pendingLogs.length = 0;
            resetMonitorCards();

            if (stopChargePointInput) {
                stopChargePointInput.value = selectedChargePointPk ?? '';
            }
            if (stopConnectorInput) {
                stopConnectorInput.value = selectedConnectorId ?? '';
            }
            if (stopButton) {
                const hasConnector = !!selectedConnectorId;
                stopButton.disabled = true;
                stopButton.textContent = hasConnector ? `Stop Connector #${selectedConnectorId}` : 'Stop Connector';
                stopButton.title = hasConnector
                    ? 'Menunggu status connector...'
                    : 'Buka monitor via tombol Monitor #connector';
            }

            if (unlockChargePointInput) {
                unlockChargePointInput.value = selectedChargePointCode ?? '';
            }
            if (unlockPayloadInput && selectedConnectorId) {
                unlockPayloadInput.value = JSON.stringify({ connectorId: parseInt(selectedConnectorId) });
            }
            if (unlockButton) {
                const hasConnector = !!selectedConnectorId;
                unlockButton.disabled = !hasConnector;
                unlockButton.textContent = hasConnector ? `Unlock Connector #${selectedConnectorId}` : 'Unlock Connector';
                unlockButton.title = hasConnector
                    ? `Unlock connector #${selectedConnectorId}`
                    : 'Buka monitor via tombol Monitor #connector';
            }

            modal.classList.remove('hidden');
            lockBodyScroll(true);
            appendLog(`Monitoring ${selectedChargePointCode}${selectedConnectorId ? ` connector #${selectedConnectorId}` : ''} via Pusher/Echo`);

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
            selectedConnectorId = null;
            monitorOnlineState = false;

            if (stopChargePointInput) {
                stopChargePointInput.value = '';
            }
            if (stopConnectorInput) {
                stopConnectorInput.value = '';
            }
            if (stopButton) {
                stopButton.disabled = true;
                stopButton.textContent = 'Stop Connector';
                stopButton.title = 'Buka monitor via tombol Monitor #connector';
            }

            if (unlockChargePointInput) {
                unlockChargePointInput.value = '';
            }
            if (unlockPayloadInput) {
                unlockPayloadInput.value = '';
            }
            if (unlockButton) {
                unlockButton.disabled = true;
                unlockButton.textContent = 'Unlock Connector';
                unlockButton.title = 'Buka monitor via tombol Monitor #connector';
            }
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

        document.addEventListener('csms:charge-point-status', (event) => {
            applyChargePointStatus(event.detail ?? null);
        });

        waitForEcho(setupEchoListeners);
    });
</script>
