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
 * Business settings page.
 *
 * This module implements the functionality of the business settings page.
 */
App.Pages.BusinessSettings = (function () {
    const APPOINTMENT_TYPE_BOOKING = 'booking';
    const APPOINTMENT_TYPE_WALKIN = 'walkin';

    const $saveSettings = $('#save-settings');
    const $applyGlobalWorkingPlan = $('#apply-global-working-plan');
    const $appointmentPlanTabs = $('.appointment-plan-tab');
    let workingPlanManager = null;
    let workingPlanByType = {};
    let currentPlanType = APPOINTMENT_TYPE_BOOKING;

    function setPlanType(type) {
        currentPlanType = type || APPOINTMENT_TYPE_BOOKING;

        $appointmentPlanTabs.removeClass('active');
        $appointmentPlanTabs.filter('[data-type="' + currentPlanType + '"]').addClass('active');

        renderPlanTypeState(currentPlanType);
        workingPlanManager.timepickers(false);
    }

    function persistCurrentPlanTypeState() {
        if (!workingPlanManager) {
            return;
        }

        const selectedType = currentPlanType || APPOINTMENT_TYPE_BOOKING;
        const workingPlan = workingPlanManager.get(false);

        if (workingPlan) {
            workingPlanByType[selectedType] = workingPlan;
        }
    }

    function renderPlanTypeState(type) {
        const selectedType = type || APPOINTMENT_TYPE_BOOKING;
        const fallbackPlan = JSON.parse(vars('company_working_plan') || '{}');
        const workingPlan = workingPlanByType[selectedType] || fallbackPlan;

        workingPlanManager.setup(workingPlan);
    }

    /**
     * Check if the form has invalid values.
     *
     * @return {Boolean}
     */
    function isInvalid() {
        try {
            $('#business-settings .is-invalid').removeClass('is-invalid');

            // Validate required fields.

            let missingRequiredFields = false;

            $('#business-settings .required').each((index, requiredField) => {
                const $requiredField = $(requiredField);

                if (!$requiredField.val()) {
                    $requiredField.addClass('is-invalid');
                    missingRequiredFields = true;
                }
            });

            if (missingRequiredFields) {
                throw new Error(lang('fields_are_required'));
            }

            return false;
        } catch (error) {
            App.Layouts.Backend.displayNotification(error.message);
            return true;
        }
    }

    function deserialize(businessSettings) {
        businessSettings.forEach((businessSetting) => {
            const $field = $('[data-field="' + businessSetting.name + '"]');

            $field.is(':checkbox')
                ? $field.prop('checked', Boolean(Number(businessSetting.value)))
                : $field.val(businessSetting.value);
        });
    }

    function serialize() {
        const businessSettings = [];

        $('[data-field]').each((index, field) => {
            const $field = $(field);

            businessSettings.push({
                name: $field.data('field'),
                value: $field.is(':checkbox') ? Number($field.prop('checked')) : $field.val(),
            });
        });

        persistCurrentPlanTypeState();

        const bookingWorkingPlan = workingPlanByType[APPOINTMENT_TYPE_BOOKING];
        const walkinWorkingPlan = workingPlanByType[APPOINTMENT_TYPE_WALKIN];

        if (bookingWorkingPlan === null || walkinWorkingPlan === null) {
            return null;
        }

        businessSettings.push({
            name: 'company_working_plan',
            value: JSON.stringify(bookingWorkingPlan),
        });

        businessSettings.push({
            name: 'company_working_plan_walkin',
            value: JSON.stringify(walkinWorkingPlan),
        });

        return businessSettings;
    }

    /**
     * Save the account information.
     */
    function onSaveSettingsClick() {
        if (isInvalid()) {
            App.Layouts.Backend.displayNotification(lang('settings_are_invalid'));

            return;
        }

        const businessSettings = serialize();

        if (businessSettings === null) {
            return;
        }

        App.Http.BusinessSettings.save(businessSettings).done(() => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));
        });
    }

    /**
     * Save the global working plan information.
     */
    function onApplyGlobalWorkingPlan() {
        const buttons = [
            {
                text: lang('cancel'),
                click: (event, messageModal) => {
                    messageModal.hide();
                },
            },
            {
                text: 'OK',
                click: (event, messageModal) => {
                    const workingPlan = workingPlanManager.get();

                    if (workingPlan === null) {
                        messageModal.hide();
                        return;
                    }

                    App.Http.BusinessSettings.applyGlobalWorkingPlan(
                        workingPlan,
                        currentPlanType || APPOINTMENT_TYPE_BOOKING,
                    )
                        .done(() => {
                            App.Layouts.Backend.displayNotification(lang('working_plans_got_updated'));
                        })
                        .always(() => {
                            messageModal.hide();
                        });
                },
            },
        ];

        App.Utils.Message.show(lang('working_plan'), lang('overwrite_existing_working_plans'), buttons);
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        const businessSettings = vars('business_settings');

        deserialize(businessSettings);

        let companyWorkingPlan = {};
        let companyWorkingPlanWalkin = {};
        vars('business_settings').forEach((businessSetting) => {
            if (businessSetting.name === 'company_working_plan') {
                companyWorkingPlan = JSON.parse(businessSetting.value);
            }

            if (businessSetting.name === 'company_working_plan_walkin') {
                companyWorkingPlanWalkin = JSON.parse(businessSetting.value);
            }

        });

        if (!Object.keys(companyWorkingPlanWalkin).length) {
            companyWorkingPlanWalkin = companyWorkingPlan;
        }

        workingPlanByType = {
            [APPOINTMENT_TYPE_BOOKING]: companyWorkingPlan,
            [APPOINTMENT_TYPE_WALKIN]: companyWorkingPlanWalkin,
        };

        workingPlanManager = new App.Utils.WorkingPlan();
        workingPlanManager.setup(companyWorkingPlan);
        workingPlanManager.timepickers(false);
        workingPlanManager.addEventListeners();

        $saveSettings.on('click', onSaveSettingsClick);

        $applyGlobalWorkingPlan.on('click', onApplyGlobalWorkingPlan);

        $appointmentPlanTabs.on('click', (event) => {
            persistCurrentPlanTypeState();
            setPlanType($(event.currentTarget).data('type'));
        });

        setPlanType(APPOINTMENT_TYPE_BOOKING);
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
