/* ----------------------------------------------------------------------------
 * Easy!Appointments - Active Calls Fixed Bar
 *
 * Displays a fixed bottom bar showing all appointments currently "En proceso"
 * (in the calling/attention state). Allows editing, finishing, and finishing
 * while immediately calling the next queued appointment for the same provider.
 * ---------------------------------------------------------------------------- */

App.Pages.CalendarActiveCalls = (function () {
    'use strict';

    // Module state
    let activeCalls = [];
    let selectedCallId = null;
    let refreshRequest = null;
    let refreshQueued = false;

    // DOM references (resolved after DOMContentLoaded)
    const $bar = $('#aad-active-calls-bar');
    const $chips = $('#aad-calls-chips');
    const $detail = $('#aad-calls-detail');
    const $detailInfo = $('#aad-calls-detail-info');
    const $btnEdit = $('#aad-btn-edit-call');
    const $btnFinish = $('#aad-btn-finish-call');
    const $btnFinishNext = $('#aad-btn-finish-next');
    const $selectFilterItem = $('#select-filter-item');

    function getSelectedProviderId() {
        if (!$selectFilterItem.length) {
            return null;
        }

        const $selectedOption = $selectFilterItem.find('option:selected');
        const selectedType = String($selectedOption.attr('type') || '').toLowerCase();
        const selectedValue = Number($selectFilterItem.val());

        if (selectedType === 'provider' && Number.isFinite(selectedValue) && selectedValue > 0) {
            return selectedValue;
        }

        return null;
    }

    /**
     * Return the currently selected call object or null.
     */
    function getSelectedCall() {
        return activeCalls.find((c) => Number(c.id) === Number(selectedCallId)) || null;
    }

    /**
     * Re-render the chips row from the current activeCalls list.
     */
    function renderChips() {
        $chips.empty();

        activeCalls.forEach((call) => {
            const caller = call.caller || {};
            const provider = call.provider || {};
            const callerName = [caller.first_name, caller.last_name].filter(Boolean).join(' ');
            const providerName = [provider.first_name, provider.last_name].filter(Boolean).join(' ');
            const chipName = callerName || providerName || '—';
            const queueCode = call.queue_number ? '#' + call.queue_number : '';

            const $chip = $('<button/>', {
                type: 'button',
                class: 'aad-call-chip' + (call.id === selectedCallId ? ' active' : ''),
                html: [
                    $('<i/>', {class: 'fas fa-user-circle me-1'}),
                    $('<span/>', {class: 'aad-chip-provider', text: chipName}),
                    queueCode
                        ? $('<span/>', {class: 'aad-chip-queue badge bg-white text-dark ms-1', text: queueCode})
                        : null,
                ].filter(Boolean),
                'data-id': call.id,
            });

            $chips.append($chip);
        });

        // Deselect if selected call no longer exists.
        if (selectedCallId !== null && !activeCalls.find((c) => Number(c.id) === Number(selectedCallId))) {
            selectedCallId = null;
        }
    }

    /**
     * Re-render the detail panel for the selected call.
     */
    function renderDetail() {
        const call = getSelectedCall();

        if (!call) {
            $detail.attr('hidden', '');
            $btnEdit.prop('disabled', true);
            $btnFinish.prop('disabled', true);
            $btnFinishNext.prop('disabled', true);
            return;
        }

        const customer = call.customer || {};
        const service = call.service || {};
        const provider = call.provider || {};
        const caller = call.caller || {};

        const customerName = [customer.first_name, customer.last_name].filter(Boolean).join(' ') || '—';
        const providerName = [provider.first_name, provider.last_name].filter(Boolean).join(' ') || '—';
        const callerName = [caller.first_name, caller.last_name].filter(Boolean).join(' ') || '—';

        const items = [
            {label: lang('customer'), value: customerName},
            {label: lang('phone'), value: customer.phone_number || '—'},
            {label: lang('email'), value: customer.email || '—'},
            {label: lang('service'), value: service.name || '—'},
            {label: lang('active_calls_attended_by'), value: callerName},
            {label: lang('provider'), value: providerName},
            {label: lang('active_calls_turn'), value: call.queue_number ? '#' + call.queue_number : '—'},
        ];

        $detailInfo.empty();
        items.forEach(({label, value}) => {
            $detailInfo.append(
                $('<div/>', {class: 'aad-calls-bar__detail-item'}).append(
                    $('<span/>', {class: 'aad-calls-bar__detail-label', text: label}),
                    $('<span/>', {class: 'aad-calls-bar__detail-value', text: value}),
                ),
            );
        });

        $detail.removeAttr('hidden');
        $btnEdit.prop('disabled', false);
        $btnFinish.prop('disabled', false);
        $btnFinishNext.prop('disabled', false);
    }

    /**
     * Fetch active calls and update the bar.
     */
    function refresh() {
        if (refreshRequest) {
            refreshQueued = true;
            return refreshRequest;
        }

        refreshQueued = false;
        refreshRequest = App.Http.Calendar.getActiveCalls(getSelectedProviderId())
            .done((response) => {
                activeCalls = (response.active_calls || []).map((call) => ({
                    ...call,
                    id: Number(call.id),
                }));

                if (activeCalls.length > 0) {
                    $bar.removeAttr('hidden');

                    if (!selectedCallId || !activeCalls.find((call) => Number(call.id) === Number(selectedCallId))) {
                        selectedCallId = Number(activeCalls[0].id);
                    }
                } else {
                    $bar.attr('hidden', '');
                    selectedCallId = null;
                }

                renderChips();
                renderDetail();
            })
            .fail(() => {
                // Silent fail – do not interrupt the calendar experience.
            })
            .always(() => {
                refreshRequest = null;

                if (refreshQueued) {
                    refreshQueued = false;
                    App.deferUntilRateLimitEnds(refresh);
                }
            });

        return refreshRequest;
    }

    /**
     * Open the appointment edit modal for the selected call.
     * Uses whichever calendar view module is loaded and exposes populateAppointmentModal.
     */
    function editSelectedCall() {
        const call = getSelectedCall();

        if (!call) {
            return;
        }

        // Try default view first, then table view.
        const viewModule =
            (typeof App.Utils.CalendarDefaultView !== 'undefined' &&
                App.Utils.CalendarDefaultView.populateAppointmentModal &&
                App.Utils.CalendarDefaultView) ||
            (typeof App.Utils.CalendarTableView !== 'undefined' &&
                App.Utils.CalendarTableView.populateAppointmentModal &&
                App.Utils.CalendarTableView) ||
            null;

        if (viewModule) {
            viewModule.populateAppointmentModal(call);
        } else {
            App.Layouts.Backend.displayNotification(lang('active_calls_edit_unavailable'));
        }
    }

    /**
     * Bind all UI event listeners.
     */
    function addEventListeners() {
        // Chip click: select / deselect a call.
        $chips.on('click', '.aad-call-chip', function () {
            const id = Number($(this).data('id'));
            selectedCallId = selectedCallId === id ? null : id;
            renderChips();
            renderDetail();
        });

        // Edit button.
        $btnEdit.on('click', () => {
            editSelectedCall();
        });

        // Finish button.
        $btnFinish.on('click', () => {
            const call = getSelectedCall();

            if (!call) {
                return;
            }

            $btnFinish.prop('disabled', true);
            $btnFinishNext.prop('disabled', true);

            App.Http.Calendar.finishAppointment(call.id)
                .done(() => {
                    selectedCallId = null;
                    refresh();
                    $('#reload-appointments').trigger('click', [{source: 'active_calls'}]);
                    App.Layouts.Backend.displayNotification(lang('active_calls_finish_success'));
                })
                .fail(() => {
                    App.Layouts.Backend.displayNotification(lang('active_calls_finish_error'));
                    $btnFinish.prop('disabled', false);
                    $btnFinishNext.prop('disabled', false);
                });
        });

        // Finish & call next button.
        $btnFinishNext.on('click', () => {
            const call = getSelectedCall();

            if (!call) {
                return;
            }

            $btnFinish.prop('disabled', true);
            $btnFinishNext.prop('disabled', true);

            App.Http.Calendar.finishAndCallNext(call.id)
                .done((response) => {
                    selectedCallId = null;
                    refresh();
                    $('#reload-appointments').trigger('click', [{source: 'active_calls'}]);

                    if (response.next_called) {
                        App.Layouts.Backend.displayNotification(lang('active_calls_finish_next_success'));
                    } else {
                        App.Layouts.Backend.displayNotification(lang('active_calls_finish_next_empty'));
                    }
                })
                .fail(() => {
                    App.Layouts.Backend.displayNotification(lang('active_calls_action_error'));
                    $btnFinish.prop('disabled', false);
                    $btnFinishNext.prop('disabled', false);
                });
        });
    }

    /**
     * Initialize the active calls bar.
     */
    function initialize() {
        addEventListeners();
        refresh();

        // Keep the fixed bar aligned with explicit calendar reloads.
        $('#reload-appointments').on('click.activeCalls', (event, options = {}) => {
            if (options.source === 'realtime' || options.source === 'active_calls') {
                return;
            }

            setTimeout(refresh, 1000);
        });

        // Department/provider filter changes should update the shared bar scope.
        $selectFilterItem.on('change.activeCalls', refresh);

        // Reuse the existing calendar websocket stream instead of polling.
        document.addEventListener(
            App.Utils.CalendarRealtime?.REALTIME_EVENT_NAME || 'aad:calendar-realtime-update',
            () => {
                refresh();
            },
        );
    }

    // Auto-initialize on DOM ready.
    document.addEventListener('DOMContentLoaded', initialize);

    return {
        initialize,
        refresh,
    };
})();
