<script>
    (() => {
        const connectorStatusClass = (status) => {
            switch (status) {
                case 'Available':
                    return 'bg-green-100 text-green-800';
                case 'Charging':
                case 'Occupied':
                    return 'bg-blue-100 text-blue-800';
                case 'Preparing':
                case 'Finishing':
                case 'Reserved':
                    return 'bg-yellow-100 text-yellow-800';
                case 'Faulted':
                case 'Unavailable':
                    return 'bg-red-100 text-red-800';
                default:
                    return 'bg-gray-100 text-gray-800';
            }
        };

        const applyConnectorStatuses = (row, connectorStatuses) => {
            if (!row || !connectorStatuses) {
                return;
            }

            String(connectorStatuses).split('|').filter(Boolean).forEach((entry) => {
                const [connectorId, status] = entry.split(':');
                if (!connectorId || !status) {
                    return;
                }

                row.querySelectorAll(`[data-connector-badge="${connectorId}"]`).forEach((badge) => {
                    badge.textContent = `#${connectorId}: ${status}`;
                    badge.className = `inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${connectorStatusClass(status)}`;
                });
            });

            updateStartButtons(row);
        };

        const updateStartButtons = (row) => {
            if (!row) {
                return;
            }

            const onlineCell = row.querySelector('[data-charge-point-online]');
            const isOnline = onlineCell?.textContent?.trim() === 'Online';

            row.querySelectorAll('[data-start-button]').forEach((button) => {
                const connectorId = button.getAttribute('data-start-connector');
                const badge = row.querySelector(`[data-connector-badge="${connectorId}"]`);
                const statusMatch = badge?.textContent?.match(/:\s*(.+)$/);
                const status = statusMatch ? statusMatch[1].trim() : '';
                const isCharging = status === 'Charging' || status === 'Occupied';
                const canStart = isOnline && ! isCharging;

                button.disabled = ! canStart;
                button.className = `inline-flex rounded px-2 py-1 text-[11px] font-medium text-white ${canStart ? 'bg-emerald-600 hover:bg-emerald-700' : 'cursor-not-allowed bg-gray-400'}`;
                button.title = ! isOnline
                    ? 'Charge point offline'
                    : (canStart
                        ? `Remote start transaction untuk test connector #${connectorId}`
                        : 'Connector sedang charging');
            });
        };

        const syncBadgesFromChargePointStatus = (row, status) => {
            if (!row || !status) {
                return;
            }

            row.querySelectorAll('[data-connector-badge]').forEach((badge) => {
                const connectorId = badge.getAttribute('data-connector-badge');
                if (!connectorId) {
                    return;
                }

                badge.textContent = `#${connectorId}: ${status}`;
                badge.className = `inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${connectorStatusClass(status)}`;
            });
        };

        window.applyChargePointRealtimeRow = (payload) => {
            if (!payload?.id) {
                return;
            }

            const row = document.querySelector(`[data-charge-point-row="${String(payload.id)}"]`);
            if (!row) {
                return;
            }

            const statusCell = row.querySelector('[data-charge-point-status]');
            const onlineCell = row.querySelector('[data-charge-point-online]');

            if (statusCell && payload.status) {
                statusCell.textContent = payload.status;
            }

            if (onlineCell && typeof payload.is_online === 'boolean') {
                onlineCell.textContent = payload.is_online ? 'Online' : 'Offline';
            }

            if (payload.connector_statuses) {
                applyConnectorStatuses(row, payload.connector_statuses);
            } else if (payload.status) {
                syncBadgesFromChargePointStatus(row, payload.status);
                updateStartButtons(row);
            } else {
                updateStartButtons(row);
            }
        };

        const normalizeChargePointPayload = (event) => event?.chargePoint ?? event?.charge_point ?? event ?? null;

        const updatePusherBadge = (state) => {
            document.querySelectorAll('[data-csms-pusher-status]').forEach((el) => {
                if (state === 'connected') {
                    el.textContent = 'Pusher: terhubung';
                    el.className = 'font-medium text-green-600 dark:text-green-400';
                } else if (state === 'connecting') {
                    el.textContent = 'Pusher: menghubungkan...';
                    el.className = 'font-medium text-amber-600 dark:text-amber-400';
                } else {
                    el.textContent = 'Pusher: tidak terhubung';
                    el.className = 'font-medium text-red-600 dark:text-red-400';
                }
            });
        };

        const dispatchStatus = (payload) => {
            if (!payload) {
                return;
            }

            window.applyChargePointRealtimeRow(payload);
            document.dispatchEvent(new CustomEvent('csms:charge-point-status', { detail: payload }));
        };

        const setupEcho = () => {
            if (!window.Echo || window.__chargePointEchoReady) {
                return;
            }

            window.__chargePointEchoReady = true;

            window.Echo.channel('charge-points').listen('.charge-point.status.updated', (event) => {
                dispatchStatus(normalizeChargePointPayload(event));
            });

            if (typeof window.Echo.onConnectionChange === 'function') {
                window.Echo.onConnectionChange((state) => {
                    window.__chargePointEchoState = state;
                    updatePusherBadge(state);
                });
                updatePusherBadge(window.Echo.connectionStatus?.() ?? 'connecting');
            } else {
                updatePusherBadge('connected');
            }
        };

        const waitForEcho = (attemptsLeft = 100) => {
            if (window.Echo) {
                setupEcho();
                return;
            }

            if (attemptsLeft <= 0) {
                updatePusherBadge('disconnected');
                return;
            }

            updatePusherBadge('connecting');
            window.setTimeout(() => waitForEcho(attemptsLeft - 1), 100);
        };

        waitForEcho();
    })();
</script>
