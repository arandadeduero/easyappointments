/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Booking settings page.
 *
 * This module implements the functionality of the booking settings page.
 */
App.Pages.BookingSettings = (function () {
    const $bookingSettings = $('#booking-settings');
    const $saveSettings = $('#save-settings');
    const $disableBookingAppointments = $('#disable-booking-appointments');
    const $disableBookingMessage = $('#disable-booking-message');
    const $kioskProvider = $('#kiosk-provider');
    const $kioskDeviceName = $('#kiosk-device-name');
    const $kioskDevicesBody = $('#kiosk-devices-body');

    /**
     * Check if the form has invalid values.
     *
     * @return {Boolean}
     */
    function isInvalid() {
        try {
            $('#booking-settings .is-invalid').removeClass('is-invalid');

            // Validate required fields.

            let missingRequiredFields = false;

            $('#booking-settings .required').each((index, requiredField) => {
                const $requiredField = $(requiredField);

                if (!$requiredField.val()) {
                    $requiredField.addClass('is-invalid');
                    missingRequiredFields = true;
                }
            });

            if (missingRequiredFields) {
                throw new Error(lang('fields_are_required'));
            }

            // Ensure there is at least one field displayed.

            if (!$('.display-switch:checked').length) {
                throw new Error(lang('at_least_one_field'));
            }

            // Ensure there is at least one field required.

            if (!$('.require-switch:checked').length) {
                throw new Error(lang('at_least_one_field_required'));
            }

            return false;
        } catch (error) {
            App.Layouts.Backend.displayNotification(error.message);
            return true;
        }
    }

    /**
     * Apply the booking settings into the page.
     *
     * @param {Object} bookingSettings
     */
    function deserialize(bookingSettings) {
        let hasDisableBookingAppointments = false;
        let legacyDisableBooking = null;

        bookingSettings.forEach((bookingSetting) => {
            if (bookingSetting.name === 'disable_booking_appointments') {
                hasDisableBookingAppointments = true;
            }

            if (bookingSetting.name === 'disable_booking') {
                legacyDisableBooking = bookingSetting.value;
                return;
            }

            if (bookingSetting.name === 'disable_booking_message') {
                $disableBookingMessage.trumbowyg('html', bookingSetting.value);
                return;
            }

            const $field = $('[data-field="' + bookingSetting.name + '"]');

            if ($field.is(':checkbox')) {
                $field.prop('checked', Boolean(Number(bookingSetting.value)));
            } else {
                $field.val(bookingSetting.value);
            }
        });

        if (!hasDisableBookingAppointments && legacyDisableBooking !== null) {
            $disableBookingAppointments.prop('checked', Boolean(Number(legacyDisableBooking)));
        }
    }

    /**
     * Serialize the page values into an array.
     *
     * @returns {Array}
     */
    function serialize() {
        const bookingSettings = [];

        $('[data-field]').each((index, field) => {
            const $field = $(field);

            bookingSettings.push({
                name: $field.data('field'),
                value: $field.is(':checkbox') ? Number($field.prop('checked')) : $field.val(),
            });
        });

        bookingSettings.push({
            name: 'disable_booking_message',
            value: $disableBookingMessage.trumbowyg('html'),
        });

        return bookingSettings;
    }

    /**
     * Update the UI based on the display switch state.
     *
     * @param {jQuery} $displaySwitch
     */
    function updateDisplaySwitch($displaySwitch) {
        const isChecked = $displaySwitch.prop('checked');

        const $formGroup = $displaySwitch.closest('.form-group');

        $formGroup.find('.require-switch').prop('disabled', !isChecked);

        $formGroup.find('.form-label, .form-control').toggleClass('opacity-25', !isChecked);

        if (!isChecked) {
            $formGroup.find('.require-switch').prop('checked', false);
            $formGroup.find('.text-danger').hide();
        }
    }

    /**
     * Update the UI based on the require switch state.
     *
     * @param {jQuery} $requireSwitch
     */
    function updateRequireSwitch($requireSwitch) {
        const isChecked = $requireSwitch.prop('checked');

        const $formGroup = $requireSwitch.closest('.form-group');

        $formGroup.find('.text-danger').toggle(isChecked);
    }

    /**
     * Update the UI based on the initial values.
     */
    function applyInitialState() {
        $bookingSettings.find('.display-switch').each((index, displaySwitchEl) => {
            const $displaySwitch = $(displaySwitchEl);

            updateDisplaySwitch($displaySwitch);
        });

        $bookingSettings.find('.require-switch').each((index, requireSwitchEl) => {
            const $requireSwitch = $(requireSwitchEl);

            updateRequireSwitch($requireSwitch);
        });

        $disableBookingMessage.closest('.form-group').prop('hidden', !$disableBookingAppointments.prop('checked'));
    }

    /**
     * Save the account information.
     */
    function onSaveSettingsClick() {
        if (isInvalid()) {
            return;
        }

        const bookingSettings = serialize();

        App.Http.BookingSettings.save(bookingSettings).done(() => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));
        });
    }

    /**
     * Update the UI.
     *
     * @param {jQuery} event
     */
    function onDisplaySwitchClick(event) {
        const $displaySwitch = $(event.target);

        updateDisplaySwitch($displaySwitch);
    }

    /**
     * Update the UI.
     *
     * @param {Event} event
     */
    function onRequireSwitchClick(event) {
        const $requireSwitch = $(event.target);

        updateRequireSwitch($requireSwitch);
    }

    /**
     * Toggle the message container.
     */
    function onDisableBookingClick() {
        $disableBookingMessage.closest('.form-group').prop('hidden', !$disableBookingAppointments.prop('checked'));
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        const bookingSettings = vars('booking_settings');

        $saveSettings.on('click', onSaveSettingsClick);

        $disableBookingAppointments.on('click', onDisableBookingClick);

        $bookingSettings
            .on('click', '.display-switch', onDisplaySwitchClick)
            .on('click', '.require-switch', onRequireSwitchClick);

        $disableBookingMessage.trumbowyg();

        deserialize(bookingSettings);

        applyInitialState();
        initializeKioskDevices();
    }

    function initializeKioskDevices() {
        renderKioskDevices();

        $('#generate-kiosk-code').on('click', () => {
            const providerId = $kioskProvider.val();
            const deviceName = $kioskDeviceName.val().trim();

            if (!providerId || !deviceName) {
                App.Layouts.Backend.displayNotification(lang('fields_are_required'));
                return;
            }

            App.Http.BookingSettings.generateKioskPairingCode(providerId, deviceName).done((response) => {
                $('#kiosk-pairing-code-value').text(response.code);
                $('#kiosk-pairing-code').removeClass('d-none');
            });
        });

        $kioskDevicesBody.on('click', '.revoke-kiosk-device', (event) => {
            const deviceId = $(event.currentTarget).data('id');

            App.Http.BookingSettings.revokeKioskDevice(deviceId).done(() => {
                window.location.reload();
            });
        });
    }

    function renderKioskDevices() {
        const devices = vars('kiosk_devices');

        $kioskDevicesBody.empty();

        if (!devices.length) {
            $('<tr/>').append($('<td/>', {colspan: 6, class: 'text-center text-muted', text: lang('no_records_found')}))
                .appendTo($kioskDevicesBody);
            return;
        }

        devices.forEach((device) => {
            const isRevoked = Boolean(device.revoked_datetime);
            const department = [device.first_name, device.last_name].filter(Boolean).join(' ');
            const $row = $('<tr/>');

            $('<td/>').text(device.name).appendTo($row);
            $('<td/>').text(department).appendTo($row);
            $('<td/>').text(device.last_used_datetime || '-').appendTo($row);
            $('<td/>').text(device.last_ip_address || '-').appendTo($row);
            $('<td/>').append(
                $('<span/>', {
                    class: 'badge ' + (isRevoked ? 'bg-secondary' : 'bg-success'),
                    text: isRevoked ? lang('kiosk_revoked') : lang('kiosk_active'),
                }),
            ).appendTo($row);

            const $actions = $('<td/>', {class: 'text-end'});
            if (!isRevoked && vars('can_manage_kiosk_devices')) {
                $('<button/>', {
                    type: 'button',
                    class: 'btn btn-sm btn-outline-danger revoke-kiosk-device',
                    text: lang('revoke'),
                }).attr('data-id', device.id).appendTo($actions);
            }
            $actions.appendTo($row);
            $row.appendTo($kioskDevicesBody);
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
