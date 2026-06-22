/* ----------------------------------------------------------------------------
 * Easy!Appointments - Queue Display
 * ---------------------------------------------------------------------------- */

App.Pages.Turnos = (function () {
    const CALL_DISPLAY_MS = 30000;
    const REFRESH_INTERVAL_MS = 5000;

    const $date = $('#queue-date');
    const $time = $('#queue-time');
    const $currentCall = $('#queue-current-call');
    const $currentCode = $('#queue-current-code');
    const $currentDesk = $('#queue-current-desk');
    const $called = $('#queue-called');

    let socket = null;
    let reconnectTimer = null;
    let refreshTimer = null;
    let currentDate = null;
    let previousCalledSignatures = null;
    let displayedCall = null;
    let callQueue = [];
    let callTimer = null;

    function initialize() {
        $called.attr('data-empty', lang('queue_no_records'));

        updateClock();
        refresh();
        connectRealtime();

        setInterval(updateClock, 1000);
        setInterval(refresh, REFRESH_INTERVAL_MS);
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
        const called = response?.called || [];
        const pending = response?.pending || [];

        renderPending(pending);
        enqueueNewCalls(called);

        if (!displayedCall && !callQueue.length && called.length) {
            showCall(called[0], false);
        }
    }

    function renderPending(items) {
        $called.empty();

        items.slice(0, 6).forEach((item) => {
            $('<article/>', {
                class: 'aad-queue-called-item',
                'data-appointment-id': item.id,
                html: [
                    $('<strong/>', {
                        class: 'aad-queue-called-code',
                        text: item.code,
                    }),
                ],
            }).appendTo($called);
        });
    }

    function enqueueNewCalls(items) {
        const signatures = items.map(getCallSignature);
        const newItems = [];

        if (previousCalledSignatures === null) {
            previousCalledSignatures = signatures;
            return;
        }

        items.forEach((item) => {
            const signature = getCallSignature(item);

            if (previousCalledSignatures.includes(signature) || isAlreadyQueued(signature)) {
                return;
            }

            newItems.push(item);
        });

        callQueue.push(...newItems.reverse());
        previousCalledSignatures = signatures;
        processCallQueue();
    }

    function processCallQueue() {
        if (callTimer || !callQueue.length) {
            return;
        }

        showCall(callQueue.shift(), true);

        callTimer = setTimeout(() => {
            callTimer = null;
            processCallQueue();
        }, CALL_DISPLAY_MS);
    }

    function showCall(item, animate) {
        displayedCall = item;

        $currentCode.text(item.code || '--');
        $currentDesk.text(getDeskText(item) || '--');
        $currentCall.removeClass('aad-queue-current-call-empty');

        if (animate) {
            flashCurrentCall();
        }
    }

    function flashCurrentCall() {
        $currentCall.removeClass('aad-queue-current-call-flash');

        window.requestAnimationFrame(() => {
            $currentCall.addClass('aad-queue-current-call-flash');

            setTimeout(() => {
                $currentCall.removeClass('aad-queue-current-call-flash');
            }, 1800);
        });
    }

    function getDeskText(item) {
        return item.caller_desk_number || item.caller_name || item.desk || '';
    }

    function getCallSignature(item) {
        return [item.id, item.call_datetime || item.update_datetime || '', item.status || ''].join(':');
    }

    function isAlreadyQueued(signature) {
        if (displayedCall && getCallSignature(displayedCall) === signature) {
            return true;
        }

        return callQueue.some((item) => getCallSignature(item) === signature);
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
