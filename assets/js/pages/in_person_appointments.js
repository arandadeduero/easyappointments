/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

App.Pages.InPersonAppointments = (function () {
    const $departmentName = $('#department-name');
    const $service = $('#service');
    const $formMessage = $('#form-message');
    const $continueWalkin = $('#continue-walkin');
    const $createWalkin = $('#create-walkin');
    const $serviceStep = $('#walkin-service-step');
    const $documentationStep = $('#walkin-documentation-step');
    const $serviceDocumentationText = $('#service-documentation-text');

    function initialize() {
        const provider = vars('provider');

        $departmentName.text((provider.first_name || '') + ' ' + (provider.last_name || ''));

        $service.empty();
        $service.append(new Option(lang('please_select'), ''));

        vars('available_services').forEach((availableService) => {
            $service.append(new Option(availableService.name, availableService.id));
        });

        if ($service.find('option').length === 2) {
            $service.find('option[value=""]').remove();
        }

        addEventListeners();
    }

    function addEventListeners() {
        $service.on('change', () => {
            $formMessage.removeClass('alert-danger').addClass('d-none').text('');
        });

        $continueWalkin.on('click', () => {
            if (!validate()) {
                return;
            }

            const documentationRequirements = getSelectedServiceDocumentation();

            if (!documentationRequirements) {
                createWalkin();
                return;
            }

            const escapedDocumentationRequirements = App.Utils.String.escapeHtml(documentationRequirements);
            $serviceDocumentationText.html(escapedDocumentationRequirements.replaceAll('\n', '<br/>'));
            $serviceStep.addClass('d-none');
            $documentationStep.removeClass('d-none');
        });

        $('#back-walkin').on('click', () => {
            $documentationStep.addClass('d-none');
            $serviceStep.removeClass('d-none');
        });

        $createWalkin.on('click', createWalkin);
    }

    function getSelectedServiceDocumentation() {
        const selectedServiceId = Number($service.val());
        const selectedService = vars('available_services').find(
            (availableService) => Number(availableService.id) === selectedServiceId,
        );

        return (selectedService?.documentation_requirements || '').trim();
    }

    function createWalkin() {
        if (!validate()) {
            return;
        }

        const postData = {
            appointment: {
                id_users_provider: vars('provider').id,
                id_services: $service.val(),
                appointment_type: 'walkin',
            },
        };

        $createWalkin.prop('disabled', true);
        $continueWalkin.prop('disabled', true);

        App.Http.InPersonAppointments.store(postData)
            .done((response) => {
                printTicket(response).finally(() => {
                    window.location.href = App.Utils.Url.siteUrl(
                        'in_person_confirmation/of/' + response.appointment_hash,
                    );
                });
            })
            .fail((xhr) => {
                const responseMessage = xhr?.responseJSON?.message || lang('unexpected_issues');
                showError(responseMessage);
            })
            .always(() => {
                $createWalkin.prop('disabled', false);
                $continueWalkin.prop('disabled', false);
            });
    }

    function printTicket(response) {
        if (!App.Utils.LocalPrinting.isEnabled()) {
            return Promise.resolve();
        }

        const ticket = {
            queue_number: response.queue_number,
            company_name: response.company_name,
            department: response.department,
            service: response.service,
            datetime: App.Utils.Date.format(response.start_datetime, vars('date_format'), vars('time_format'), true),
            appointment_hash: response.appointment_hash,
        };

        return App.Utils.LocalPrinting.print(ticket)
            .then(() => {
                window.sessionStorage.removeItem('easyappointments_local_print_failed');
            })
            .catch(() => {
                window.sessionStorage.setItem('easyappointments_local_print_failed', response.appointment_hash);
            });
    }

    function validate() {
        $('.is-invalid').removeClass('is-invalid');
        $formMessage.removeClass('alert-danger').addClass('d-none').text('');

        if (!$service.val()) {
            $service.addClass('is-invalid');
            showError(lang('fields_are_required'));
            return false;
        }

        return true;
    }

    function showError(message) {
        $formMessage.removeClass('d-none').addClass('alert-danger').text(message);
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        initialize,
    };
})();
