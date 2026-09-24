/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

/**
 * Calendar real-time websocket utility.
 *
 * Keeps backend calendars in sync across multiple desks without page reload.
 */
App.Utils.CalendarRealtime = (function () {
    const $reloadAppointments = $('#reload-appointments');

    const REALTIME_EVENT_NAME = 'aad:calendar-realtime-update';
    const FALLBACK_REFRESH_INTERVAL_MS = 60000;
    const RECONNECT_BASE_DELAY_MS = 2000;
    const RECONNECT_MAX_DELAY_MS = 30000;
    const RECONNECT_JITTER_MS = 1000;
    const STABLE_CONNECTION_MS = 30000;

    let socket = null;
    let reconnectTimer = null;
    let reconnectAttempts = 0;
    let stableConnectionTimer = null;
    let pollTimer = null;
    let reloadTimer = null;
    let refreshWhenVisible = false;
    let refreshAfterReconnect = false;
    let initialized = false;

    function scheduleReload(delay = 350) {
        if (reloadTimer) {
            return;
        }

        delay = Math.max(delay, App.getRateLimitDelay?.() || 0);
        reloadTimer = setTimeout(() => {
            reloadTimer = null;

            if ($('.popover').length || App.Utils.CalendarSync?.isCurrentlySyncing?.()) {
                scheduleReload(5000);
                return;
            }

            $reloadAppointments.trigger('click', [{source: 'realtime'}]);
        }, delay);
    }

    function emitRealtimeUpdate(detail = {}) {
        document.dispatchEvent(
            new CustomEvent(REALTIME_EVENT_NAME, {
                detail,
            }),
        );
    }

    function fallbackReload() {
        scheduleReload();
    }

    function isDefaultCalendarView() {
        return vars('calendar_view') !== 'table';
    }

    function handleAppointmentSaved(payload) {
        const appointmentId = payload?.data?.appointment_id;

        emitRealtimeUpdate({type: 'appointment_saved', payload});

        if (!appointmentId) {
            fallbackReload();
            return;
        }

        if (!isDefaultCalendarView() || !App.Utils.CalendarDefaultView?.upsertAppointmentEvent) {
            fallbackReload();
            return;
        }

        App.Utils.CalendarDefaultView?.setLastUpdateLoading?.(true);

        App.Http.Calendar.getAppointment(appointmentId)
            .done((appointment) => {
                App.Utils.CalendarDefaultView.upsertAppointmentEvent(appointment);
            })
            .fail(() => {
                fallbackReload();
            })
            .always(() => {
                App.Utils.CalendarDefaultView?.setLastUpdateLoading?.(false);
            });
    }

    function handleAppointmentDeleted(payload) {
        const appointmentId = payload?.data?.appointment_id;

        emitRealtimeUpdate({type: 'appointment_deleted', payload});

        if (!appointmentId) {
            fallbackReload();
            return;
        }

        if (!isDefaultCalendarView() || !App.Utils.CalendarDefaultView?.removeAppointmentEvent) {
            fallbackReload();
            return;
        }

        App.Utils.CalendarDefaultView.removeAppointmentEvent(appointmentId);
    }

    function isRelevantEvent(payload) {
        const providerId = String(payload?.data?.provider_id || '');
        const serviceId = String(payload?.data?.service_id || '');

        if (vars('calendar_view') === 'table') {
            const selectedProviders = [].concat($('#filter-provider').val() || []).map(String);
            const selectedServices = [].concat($('#filter-service').val() || []).map(String);

            if (providerId && selectedProviders.length && !selectedProviders.includes(providerId)) {
                return false;
            }

            if (serviceId && selectedServices.length && !selectedServices.includes(serviceId)) {
                return false;
            }

            return true;
        }

        const $selectedFilter = $('#select-filter-item option:selected');
        const filterType = String($selectedFilter.attr('type') || '');
        const filterValue = String($selectedFilter.val() || '');

        if (filterType === 'provider' && providerId) {
            return filterValue === providerId;
        }

        if (filterType === 'service' && serviceId) {
            return filterValue === serviceId;
        }

        return true;
    }

    function handleMessage(event) {
        try {
            const payload = JSON.parse(event.data || '{}');
            const eventName = payload.event || '';

            if (!eventName.startsWith('calendar.')) {
                return;
            }

            if (eventName === 'calendar.connected') {
                return;
            }

            if (!isRelevantEvent(payload)) {
                return;
            }

            if (document.hidden) {
                refreshWhenVisible = true;
                return;
            }

            if (eventName === 'calendar.appointment_saved') {
                handleAppointmentSaved(payload);
                return;
            }

            if (eventName === 'calendar.appointment_deleted') {
                handleAppointmentDeleted(payload);
                return;
            }

            emitRealtimeUpdate({type: 'calendar_update', payload});
            fallbackReload();
        } catch (error) {
            console.error('Realtime calendar message parse error:', error);
        }
    }

    function isConnected() {
        return Boolean(socket && 'WebSocket' in window && socket.readyState === window.WebSocket.OPEN);
    }

    function scheduleFallbackPoll(delay = FALLBACK_REFRESH_INTERVAL_MS) {
        if (document.hidden || window.navigator.onLine === false || isConnected() || pollTimer) {
            return;
        }

        delay = Math.max(delay, App.getRateLimitDelay?.() || 0);
        pollTimer = setTimeout(() => {
            pollTimer = null;
            emitRealtimeUpdate({type: 'fallback_poll'});
            scheduleReload();
            scheduleFallbackPoll();
        }, delay);
    }

    function stopFallbackPolling() {
        if (!pollTimer) {
            return;
        }

        clearTimeout(pollTimer);
        pollTimer = null;
    }

    function connect() {
        const isEnabled = Boolean(Number(vars('realtime_ws_enabled')));
        const wsUrl = vars('realtime_ws_url');

        if (!isEnabled || !wsUrl) {
            scheduleFallbackPoll();
            return;
        }

        if (!('WebSocket' in window)) {
            console.warn('Realtime calendar is not supported by this browser.');
            scheduleFallbackPoll();
            return;
        }

        if (document.hidden || socket?.readyState === window.WebSocket.CONNECTING || isConnected()) {
            return;
        }

        let connection;
        try {
            connection = new WebSocket(wsUrl);
            socket = connection;
        } catch (error) {
            console.error('Realtime calendar websocket init failed:', error);
            socket = null;
            scheduleFallbackPoll();
            scheduleReconnect();
            return;
        }

        connection.addEventListener('open', () => {
            if (socket !== connection) {
                connection.close();
                return;
            }

            stopFallbackPolling();

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
                emitRealtimeUpdate({type: 'reconnected'});
                scheduleReload();
            }
        });

        connection.addEventListener('message', handleMessage);

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
            scheduleFallbackPoll();
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
            isConnected()
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
            connect();
        }, reconnectDelay);
    }

    function handleVisibilityChange() {
        if (document.hidden) {
            stopFallbackPolling();

            if (reconnectTimer) {
                clearTimeout(reconnectTimer);
                reconnectTimer = null;
            }

            return;
        }

        if (refreshWhenVisible || !isConnected()) {
            refreshWhenVisible = false;
            emitRealtimeUpdate({type: 'visibility_resume'});
            scheduleReload();
        }

        if (!isConnected()) {
            connect();
            scheduleFallbackPoll();
        }
    }

    function handleOnline() {
        if (document.hidden || isConnected()) {
            return;
        }

        reconnectAttempts = 0;
        emitRealtimeUpdate({type: 'online'});
        scheduleReload();
        connect();
        scheduleFallbackPoll();
    }

    function initialize() {
        if (initialized) {
            return;
        }

        initialized = true;
        document.addEventListener('visibilitychange', handleVisibilityChange);
        document.addEventListener('aad:rate-limit', () => {
            if (reloadTimer) {
                clearTimeout(reloadTimer);
                reloadTimer = null;
                scheduleReload();
            }

            if (pollTimer) {
                stopFallbackPolling();
                scheduleFallbackPoll();
            }
        });
        window.addEventListener('online', handleOnline);
        connect();
        scheduleFallbackPoll();
    }

    return {
        initialize,
        isConnected,
        REALTIME_EVENT_NAME,
    };
})();
