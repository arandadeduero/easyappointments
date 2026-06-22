/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

App.Pages.InPersonConfirmation = (function () {
    const $message = $('#local-printing-message');
    const $reprintButton = $('#reprint-walkin-ticket');

    function initialize() {
        const ticket = vars('walkin_ticket');

        if (!ticket || !ticket.queue_number || !App.Utils.LocalPrinting.isEnabled()) {
            $reprintButton.addClass('d-none');
            return;
        }

        const failedHash = window.sessionStorage.getItem('easyappointments_local_print_failed');

        if (failedHash === ticket.appointment_hash) {
            showMessage(lang('local_printing_failed'), 'warning');
        }

        $reprintButton.on('click', () => {
            $reprintButton.prop('disabled', true);

            App.Utils.LocalPrinting.print(ticket)
                .then(() => {
                    window.sessionStorage.removeItem('easyappointments_local_print_failed');
                    showMessage(lang('local_printing_success'), 'success');
                })
                .catch((error) => {
                    window.sessionStorage.setItem('easyappointments_local_print_failed', ticket.appointment_hash);
                    showMessage(lang('local_printing_failed') + ' ' + error.message, 'warning');
                })
                .finally(() => {
                    $reprintButton.prop('disabled', false);
                });
        });
    }

    function showMessage(message, type) {
        $message.removeClass('d-none alert-success alert-warning alert-danger').addClass('alert-' + type).text(message);
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
