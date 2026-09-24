/* ----------------------------------------------------------------------------
 * Easy!Appointments - Queue Display
 * ---------------------------------------------------------------------------- */

App.Pages.Turnos = (function () {
    const CALL_DISPLAY_MS = 30000;
    const REFRESH_INTERVAL_MS = 5000;
    const RECONNECT_BASE_DELAY_MS = 2000;
    const RECONNECT_MAX_DELAY_MS = 30000;
    const RECONNECT_JITTER_MS = 1000;
    const STABLE_CONNECTION_MS = 30000;

    const $date = $('#queue-date');
    const $time = $('#queue-time');
    const $called = $('#queue-called');
    const $pending = $('#queue-pending');

    let socket = null;
    let reconnectTimer = null;
    let reconnectAttempts = 0;
    let stableConnectionTimer = null;
    let pollTimer = null;
    let refreshTimer = null;
    let refreshRequest = null;
    let refreshQueued = false;
    let refreshWhenVisible = false;
    let refreshAfterReconnect = false;
    let currentDate = null;
    let previousCalledSignatures = null;
    let displayedCall = null;
    let latestDisplayedCallSignature = null;
    let callQueue = [];
    let callTimer = null;
    let audioContext = null;

    function initialize() {
        $called.attr('data-empty', lang('queue_no_records'));
        $pending.attr('data-empty', lang('queue_no_records'));
        initializeBell();

        updateClock();
        refresh();
        connectRealtime();

        setInterval(updateClock, 1000);
        document.addEventListener('visibilitychange', handleVisibilityChange);
        document.addEventListener('aad:rate-limit', handleRateLimit);
        window.addEventListener('online', handleOnline);
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
        if (document.hidden) {
            refreshWhenVisible = true;
            return;
        }

        if (refreshTimer) {
            return;
        }

        const delay = Math.max(350, App.getRateLimitDelay?.() || 0);

        refreshTimer = setTimeout(() => {
            refreshTimer = null;
            refresh();
        }, delay);
    }

    function refresh() {
        if (document.hidden) {
            refreshWhenVisible = true;
            return refreshRequest;
        }

        if (refreshRequest) {
            refreshQueued = true;
            return refreshRequest;
        }

        const retryDelay = App.getRateLimitDelay?.() || 0;

        if (retryDelay > 0) {
            schedulePoll(retryDelay);
            return null;
        }

        refreshQueued = false;
        refreshRequest = App.Http.Turnos.getData()
            .done(render)
            .always(() => {
                refreshRequest = null;

                if (refreshQueued) {
                    refreshQueued = false;
                    scheduleRefresh();
                    return;
                }

                schedulePoll();
            });

        return refreshRequest;
    }

    function isRealtimeConnected() {
        return Boolean(socket && 'WebSocket' in window && socket.readyState === window.WebSocket.OPEN);
    }

    function schedulePoll(delay = REFRESH_INTERVAL_MS) {
        if (document.hidden || window.navigator.onLine === false || isRealtimeConnected() || pollTimer) {
            return;
        }

        delay = Math.max(delay, App.getRateLimitDelay?.() || 0);
        pollTimer = setTimeout(() => {
            pollTimer = null;
            refresh();
        }, delay);
    }

    function stopPolling() {
        if (!pollTimer) {
            return;
        }

        clearTimeout(pollTimer);
        pollTimer = null;
    }

    function handleRateLimit() {
        if (refreshTimer) {
            clearTimeout(refreshTimer);
            refreshTimer = null;
        }

        stopPolling();
        schedulePoll();
    }

    function render(response) {
        const called = response?.called || [];
        const pending = response?.pending || [];
        const calledSlots = Math.max(3, parseInt(response?.called_slots || response?.provider?.called_slots || 3, 10));

        $called.css('--queue-called-slots', calledSlots);

        renderCalled(called);
        renderPending(pending);
        enqueueNewCalls(called);
        announceLatestCallChange(called);
    }

    function renderCalled(items) {
        $called.empty();

        const visibleItems = items.slice(0, getCalledSlots()).reverse();
        visibleItems.forEach((item) => {
            $('<article/>', {
                class: 'aad-queue-called-item',
                'data-appointment-id': item.id,
                html: [
                    $('<div/>', {
                        class: 'aad-queue-called-desk',
                        html: [
                            $('<span/>', {
                                class: 'aad-queue-called-label',
                                text: lang('queue_desk'),
                            }),
                            $('<strong/>', {
                                class: 'aad-queue-called-desk-value',
                                text: getDeskText(item) || '--',
                            }),
                        ],
                    }),
                    $('<div/>', {
                        class: 'aad-queue-called-code',
                        html: [
                            $('<span/>', {
                                class: 'aad-queue-called-label',
                                text: lang('queue_turn'),
                            }),
                            $('<strong/>', {
                                class: 'aad-queue-called-code-value',
                                text: item.code || '--',
                            }),
                        ],
                    }),
                ],
            }).appendTo($called);
        });
    }

    function getCalledSlots() {
        const slots = parseInt($called.css('--queue-called-slots'), 10);

        return Number.isFinite(slots) && slots >= 3 ? slots : 3;
    }

    function renderPending(items) {
        $pending.empty();

        items.slice(0, 6).forEach((item) => {
            $('<article/>', {
                class: 'aad-queue-called-item',
                'data-appointment-id': item.id,
                html: [
                    $('<strong/>', {
                        class: 'aad-queue-called-code-value',
                        text: item.code || '--',
                    }),
                ],
            }).appendTo($pending);
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

    function initializeBell() {
        document.addEventListener('pointerdown', unlockBell, {once: true});
        document.addEventListener('keydown', unlockBell, {once: true});
        document.addEventListener('touchstart', unlockBell, {once: true, passive: true});
    }

    function unlockBell() {
        const context = getAudioContext();

        if (!context) {
            return;
        }

        context.resume?.();
    }

    function announceLatestCallChange(items) {
        if (!items.length || callQueue.length || callTimer) {
            return;
        }

        const latestCall = items[0];
        const latestSignature = getCallSignature(latestCall);

        if (latestDisplayedCallSignature === null) {
            latestDisplayedCallSignature = latestSignature;
            return;
        }

        if (latestDisplayedCallSignature === latestSignature) {
            return;
        }

        latestDisplayedCallSignature = latestSignature;
        showCall(latestCall, true);
    }

    function processCallQueue() {
        if (callTimer || !callQueue.length) {
            return;
        }

        const item = callQueue.shift();

        latestDisplayedCallSignature = getCallSignature(item);
        showCall(item, true);

        callTimer = setTimeout(() => {
            callTimer = null;
            processCallQueue();
        }, CALL_DISPLAY_MS);
    }

    function showCall(item, animate) {
        displayedCall = item;

        if (animate) {
            flashCurrentCall();
            playBell();
        }
    }

    function flashCurrentCall() {
        $called.removeClass('aad-queue-current-call-flash');

        window.requestAnimationFrame(() => {
            $called.addClass('aad-queue-current-call-flash');

            setTimeout(() => {
                $called.removeClass('aad-queue-current-call-flash');
            }, 1800);
        });
    }

    function getDeskText(item) {
        return item.caller_desk_number || item.caller_name || item.desk || '';
    }

    function getDeskNumberText(item) {
        return item.caller_desk_number || item.desk || '';
    }

    function getAudioContext() {
        const AudioContextConstructor = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextConstructor) {
            return null;
        }

        if (!audioContext) {
            audioContext = new AudioContextConstructor();
        }

        return audioContext;
    }

    function playBell() {
        const context = getAudioContext();

        if (!context) {
            return;
        }

        context.resume?.();

        const startTime = context.currentTime + 0.02;

        playBellTone(context, startTime, 880, 0.9);
        playBellTone(context, startTime + 0.18, 1175, 0.55);
        playBellTone(context, startTime + 0.38, 880, 0.45);
    }

    function playBellTone(context, startTime, frequency, volume) {
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(frequency, startTime);

        gain.gain.setValueAtTime(0.0001, startTime);
        gain.gain.exponentialRampToValueAtTime(volume, startTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, startTime + 0.75);

        oscillator.connect(gain);
        gain.connect(context.destination);

        oscillator.start(startTime);
        oscillator.stop(startTime + 0.8);
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
            schedulePoll();
            return;
        }

        if (document.hidden || socket?.readyState === WebSocket.CONNECTING || isRealtimeConnected()) {
            return;
        }

        let connection;
        try {
            connection = new WebSocket(wsUrl);
            socket = connection;
        } catch (error) {
            console.error('Queue display websocket init failed:', error);
            socket = null;
            schedulePoll();
            scheduleReconnect();
            return;
        }

        connection.addEventListener('open', () => {
            if (socket !== connection) {
                connection.close();
                return;
            }

            stopPolling();

            if (stableConnectionTimer) {
                clearTimeout(stableConnectionTimer);
            }

            stableConnectionTimer = setTimeout(() => {
                reconnectAttempts = 0;
                stableConnectionTimer = null;
            }, STABLE_CONNECTION_MS);

            if (reconnectTimer) {
                clearTimeout(reconnectTimer);
                reconnectTimer = null;
            }

            if (refreshAfterReconnect) {
                refreshAfterReconnect = false;
                scheduleRefresh();
            }
        });

        connection.addEventListener('message', handleRealtimeMessage);

        connection.addEventListener('close', () => {
            if (socket !== connection) {
                return;
            }

            if (stableConnectionTimer) {
                clearTimeout(stableConnectionTimer);
                stableConnectionTimer = null;
            }

            socket = null;

            refreshAfterReconnect = true;
            schedulePoll(0);
            scheduleReconnect();
        });

        connection.addEventListener('error', () => {
            connection.close();
        });
    }

    function scheduleReconnect() {
        const isEnabled = Boolean(Number(vars('realtime_ws_enabled')));

        if (
            !isEnabled ||
            document.hidden ||
            window.navigator.onLine === false ||
            reconnectTimer ||
            isRealtimeConnected()
        ) {
            return;
        }

        const exponentialDelay = Math.min(
            RECONNECT_MAX_DELAY_MS,
            RECONNECT_BASE_DELAY_MS * 2 ** Math.min(reconnectAttempts, 4),
        );
        const reconnectDelay = exponentialDelay + Math.floor(Math.random() * RECONNECT_JITTER_MS);

        reconnectAttempts += 1;
        reconnectTimer = setTimeout(() => {
            reconnectTimer = null;
            connectRealtime();
        }, reconnectDelay);
    }

    function handleVisibilityChange() {
        if (document.hidden) {
            stopPolling();

            if (reconnectTimer) {
                clearTimeout(reconnectTimer);
                reconnectTimer = null;
            }

            return;
        }

        if (refreshWhenVisible || !isRealtimeConnected()) {
            refreshWhenVisible = false;
            scheduleRefresh();
        }

        if (!isRealtimeConnected()) {
            connectRealtime();
        }
    }

    function handleOnline() {
        if (document.hidden || isRealtimeConnected()) {
            return;
        }

        reconnectAttempts = 0;
        scheduleRefresh();
        connectRealtime();
    }

    function handleRealtimeMessage(event) {
        try {
            const payload = JSON.parse(event.data || '{}');
            const eventName = payload.event || '';

            if (!eventName.startsWith('calendar.')) {
                return;
            }

            if (eventName === 'calendar.connected') {
                return;
            }

            if (document.hidden) {
                refreshWhenVisible = true;
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
