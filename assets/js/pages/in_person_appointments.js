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
    const $timeStep = $('#walkin-time-step');
    const $serviceDocumentationText = $('#service-documentation-text');
    const $walkinTimeValue = $('#walkin-time-value');
    const $walkinDateValue = $('#walkin-date-value');
    const $cancelWalkinTime = $('#cancel-walkin-time');
    const $confirmWalkinTime = $('#confirm-walkin-time');
    let inactivityTimer = null;
    let isCreatingWalkin = false;

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
        startInactivityTimer();
    }

    function addEventListeners() {
        $(document).on('click touchstart keydown change', resetInactivityTimer);

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

            $serviceDocumentationText.html(renderDocumentationHtml(documentationRequirements));
            $serviceStep.addClass('d-none');
            $documentationStep.removeClass('d-none');
        });

        $('#back-walkin').on('click', () => {
            $documentationStep.addClass('d-none');
            $serviceStep.removeClass('d-none');
        });

        $createWalkin.on('click', previewWalkinTime);

        $cancelWalkinTime.on('click', () => {
            $timeStep.addClass('d-none');
            $documentationStep.removeClass('d-none');
        });

        $confirmWalkinTime.on('click', createWalkin);
    }

    function getSelectedServiceDocumentation() {
        const selectedServiceId = Number($service.val());
        const selectedService = vars('available_services').find(
            (availableService) => Number(availableService.id) === selectedServiceId,
        );

        return (selectedService?.documentation_requirements || '').trim();
    }

    function renderDocumentationHtml(documentation) {
        const sanitizedDocumentation = App.Utils.String.sanitizeHtml(documentation);

        if (/<[a-z][\s\S]*>/i.test(sanitizedDocumentation)) {
            return sanitizedDocumentation;
        }

        return App.Utils.String.escapeHtml(documentation).replaceAll('\n', '<br/>');
    }

    function getWalkinPostData() {
        return {
            appointment: {
                id_users_provider: vars('provider').id,
                id_services: $service.val(),
                appointment_type: 'walkin',
            },
        };
    }

    function previewWalkinTime() {
        if (!validate()) {
            return;
        }

        $createWalkin.prop('disabled', true);
        $continueWalkin.prop('disabled', true);

        App.Http.InPersonAppointments.preview(getWalkinPostData())
            .done((response) => {
                const timeFormat = vars('time_format') === 'regular' ? 'h:mm a' : 'HH:mm';
                $walkinTimeValue.text(moment(response.start_datetime).format(timeFormat).toLowerCase());
                $walkinDateValue.text(App.Utils.Date.format(response.start_datetime, vars('date_format'), vars('time_format'), true));
                $documentationStep.addClass('d-none');
                $timeStep.removeClass('d-none');
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

    function createWalkin() {
        if (!validate()) {
            return;
        }

        isCreatingWalkin = true;
        window.clearTimeout(inactivityTimer);

        $createWalkin.prop('disabled', true);
        $continueWalkin.prop('disabled', true);
        $confirmWalkinTime.prop('disabled', true);

        App.Http.InPersonAppointments.store(getWalkinPostData())
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
                $confirmWalkinTime.prop('disabled', false);
                isCreatingWalkin = false;
                resetInactivityTimer();
            });
    }

    function startInactivityTimer() {
        const returnUrl = vars('walkin_return_url');

        if (!returnUrl) {
            return;
        }

        resetInactivityTimer();
    }

    function resetInactivityTimer() {
        const returnUrl = vars('walkin_return_url');

        if (!returnUrl || isCreatingWalkin) {
            return;
        }

        const delay = Number(vars('walkin_request_timeout')) || 60000;

        window.clearTimeout(inactivityTimer);

        inactivityTimer = window.setTimeout(() => {
            window.location.href = returnUrl;
        }, delay);
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
