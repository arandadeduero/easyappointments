/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

App.Pages.InPersonAppointments = (function () {
    const $departmentName = $('#department-name');
    const $service = $('#service');
    const $formMessage = $('#form-message');
    const $createWalkin = $('#create-walkin');
    const $serviceDocumentation = $('#service-documentation');
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
            renderSelectedServiceDocumentation();
        });

        $createWalkin.on('click', () => {
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

            App.Http.InPersonAppointments.store(postData)
                .done((response) => {
                    window.location.href = App.Utils.Url.siteUrl(
                        'in_person_confirmation/of/' + response.appointment_hash,
                    );
                })
                .fail((xhr) => {
                    const responseMessage = xhr?.responseJSON?.message || lang('unexpected_issues');
                    showError(responseMessage);
                })
                .always(() => {
                    $createWalkin.prop('disabled', false);
                });
        });
    }

    function renderSelectedServiceDocumentation() {
        const selectedServiceId = Number($service.val());

        if (!selectedServiceId) {
            $serviceDocumentation.addClass('d-none');
            $serviceDocumentationText.text('');
            return;
        }

        const selectedService = vars('available_services').find(
            (availableService) => Number(availableService.id) === selectedServiceId,
        );

        const documentationRequirements = (selectedService?.documentation_requirements || '').trim();

        if (!documentationRequirements) {
            $serviceDocumentation.addClass('d-none');
            $serviceDocumentationText.text('');
            return;
        }

        const escapedDocumentationRequirements = App.Utils.String.escapeHtml(documentationRequirements);
        const multilineDocumentationRequirements = escapedDocumentationRequirements.replaceAll('\n', '<br/>');

        $serviceDocumentation.removeClass('d-none');
        $serviceDocumentationText.html(multilineDocumentationRequirements);
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
