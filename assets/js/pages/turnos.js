/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

App.Pages.Turnos = (function () {
    const $date = $('#queue-date');
    const $time = $('#queue-time');
    const $inProcess = $('#queue-in-process');
    const $reserved = $('#queue-reserved');
    const $empty = $('#queue-empty');

    let socket = null;
    let reconnectTimer = null;
    let refreshTimer = null;
    let currentDate = null;
    let previousInProcessIds = null;

    function initialize() {
        $inProcess.attr('data-empty', lang('queue_no_records'));
        $reserved.attr('data-empty', lang('queue_no_records'));

        updateClock();
        refresh();
        connectRealtime();

        setInterval(updateClock, 1000);
        setInterval(refresh, 60000);
    }

    function updateClock() {
        const now = moment();
        const today = now.format('YYYY-MM-DD');

        $date.text(now.format('DD/MM/YYYY'));
        $time.text(now.format('HH:mm'));

        if (currentDate && currentDate !== today) {
            scheduleRefresh();
        }

        currentDate = today;
    }

    function scheduleRefresh() {
        if (refreshTimer) {
            return;
        }

        refreshTimer = setTimeout(() => {
            refreshTimer = null;
            refresh();
        }, 350);
    }

    function refresh() {
        App.Http.Turnos.getData().done(render);
    }

    function render(response) {
        const inProcess = response?.in_process || [];
        const reserved = response?.reserved || [];
        const inProcessIds = inProcess.map((item) => Number(item.id));
        const hasNewInProcess =
            previousInProcessIds !== null && inProcessIds.some((id) => !previousInProcessIds.includes(id));

        renderItems($inProcess, inProcess, true);
        renderItems($reserved, reserved, false);

        $empty.toggleClass('d-none', Boolean(inProcess.length || reserved.length));

        if (hasNewInProcess) {
            flashScreen();
        }

        previousInProcessIds = inProcessIds;
    }

    function renderItems($target, items, showCaller) {
        $target.empty();

        items.forEach((item) => {
            const deskText = showCaller && item.caller_name ? item.caller_name : item.desk || '';

            $('<article/>', {
                class: 'aad-queue-ticket',
                'data-appointment-id': item.id,
                'data-appointment-type': item.appointment_type,
                'data-desk': deskText,
                html: [
                    $('<span/>', {
                        class: 'aad-queue-desk',
                        text: deskText,
                    }),
                    $('<span/>', {
                    class: 'aad-queue-code',
                    text: item.code,
                    }),
                ],
            }).appendTo($target);
        });
    }

    function flashScreen() {
        const $body = $('body');

        $body.removeClass('aad-queue-flash');

        window.requestAnimationFrame(() => {
            $body.addClass('aad-queue-flash');

            setTimeout(() => {
                $body.removeClass('aad-queue-flash');
            }, 2600);
        });
    }

    function connectRealtime() {
        const isEnabled = Boolean(Number(vars('realtime_ws_enabled')));
        const wsUrl = vars('realtime_ws_url');

        if (!isEnabled || !wsUrl || !('WebSocket' in window)) {
            return;
        }

        try {
            socket = new WebSocket(wsUrl);
        } catch (error) {
            console.error('Queue display websocket init failed:', error);
            return;
        }

        socket.addEventListener('open', () => {
            if (reconnectTimer) {
                clearTimeout(reconnectTimer);
                reconnectTimer = null;
            }
        });

        socket.addEventListener('message', handleRealtimeMessage);

        socket.addEventListener('close', () => {
            if (reconnectTimer) {
                return;
            }

            reconnectTimer = setTimeout(() => {
                reconnectTimer = null;
                connectRealtime();
            }, 2000);
        });

        socket.addEventListener('error', () => {
            if (socket) {
                socket.close();
            }
        });
    }

    function handleRealtimeMessage(event) {
        try {
            const payload = JSON.parse(event.data || '{}');
            const eventName = payload.event || '';

            if (!eventName.startsWith('calendar.')) {
                return;
            }

            const providerId = Number(payload?.data?.provider_id || 0);

            if (providerId && providerId !== Number(vars('turnos_provider_id'))) {
                return;
            }

            scheduleRefresh();
        } catch (error) {
            console.error('Queue display websocket message parse error:', error);
        }
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        initialize,
        refresh,
    };
})();
