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
 * Booking Settings HTTP client.
 *
 * This module implements the booking settings related HTTP requests.
 */
App.Http.BookingSettings = (function () {
    /**
     * Save booking settings.
     *
     * @param {Object} bookingSettings
     *
     * @return {Object}
     */
    function save(bookingSettings) {
        const url = App.Utils.Url.siteUrl('booking_settings/save');

        const data = {
            csrf_token: vars('csrf_token'),
            booking_settings: bookingSettings,
        };

        return $.post(url, data);
    }

    function generateKioskPairingCode(providerId, deviceName) {
        return $.post(App.Utils.Url.siteUrl('booking_settings/generate_kiosk_pairing_code'), {
            csrf_token: vars('csrf_token'),
            provider_id: providerId,
            device_name: deviceName,
        });
    }

    function revokeKioskDevice(deviceId) {
        return $.post(App.Utils.Url.siteUrl('booking_settings/revoke_kiosk_device'), {
            csrf_token: vars('csrf_token'),
            device_id: deviceId,
        });
    }

    return {
        save,
        generateKioskPairingCode,
        revokeKioskDevice,
    };
})();
