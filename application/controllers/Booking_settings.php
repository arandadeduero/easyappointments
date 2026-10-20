<?php defined('BASEPATH') or exit('No direct script access allowed');

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
 * Booking settings controller.
 *
 * Handles booking settings related operations.
 *
 * @package Controllers
 */
class Booking_settings extends EA_Controller
{
    public array $allowed_setting_fields = ['id', 'name', 'value'];

    public array $optional_setting_fields = [
        //
    ];

    /**
     * Booking_settings constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('kiosk_devices_model');
        $this->load->model('services_model');
        $this->load->model('providers_model');
        $this->load->model('roles_model');
        $this->load->model('settings_model');

        $this->load->library('accounts');
        $this->load->library('google_sync');
        $this->load->library('notifications');
        $this->load->library('synchronization');
        $this->load->library('timezones');
    }

    /**
     * Render the settings page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('booking_settings')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_SYSTEM_SETTINGS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');
        $kiosk_providers = filter_sensitive_users_data($this->providers_model->get());

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'booking_settings' => filter_sensitive_settings($this->settings_model->get_batch()),
            'kiosk_devices' => $this->kiosk_devices_model->get_devices(),
            'can_manage_kiosk_devices' => can('edit', PRIV_SYSTEM_SETTINGS),
        ]);

        html_vars([
            'page_title' => lang('settings'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'kiosk_providers' => $kiosk_providers,
        ]);

        $this->load->view('pages/booking_settings');
    }

    /**
     * Save booking settings.
     */
    public function save(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('booking_settings', 'array|null');

            $rich_text_settings = ['disable_booking_message'];

            $settings = request('booking_settings', []);

            foreach ($settings as $setting) {
                $existing_setting = $this->settings_model->query()->where('name', $setting['name'])->get()->row_array();

                if (!empty($existing_setting)) {
                    $setting['id'] = $existing_setting['id'];
                }

                if (!empty($setting['name']) && str_starts_with($setting['name'], 'label_custom_field_')) {
                    $setting['value'] = strip_tags($setting['value'] ?? '');
                }

                if (!empty($setting['name']) && in_array($setting['name'], $rich_text_settings, true)) {
                    $setting['value'] = pure_html($setting['value'] ?? '');
                }

                $this->settings_model->only($setting, $this->allowed_setting_fields);

                $this->settings_model->optional($setting, $this->optional_setting_fields);

                $this->settings_model->save($setting);

                if (($setting['name'] ?? null) === 'disable_booking_appointments') {
                    setting(['disable_booking' => $setting['value']]);
                }
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Generate a short-lived kiosk pairing code.
     */
    public function generate_kiosk_pairing_code(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('provider_id', 'numeric');
            check('device_name', 'string');

            $provider_id = (int) request('provider_id');
            $device_name = trim((string) request('device_name'));
            $provider = $this->providers_model->find($provider_id);

            if (empty($provider) || $device_name === '') {
                throw new InvalidArgumentException(lang('fields_are_required'));
            }

            $code = $this->kiosk_devices_model->create_pairing_code(
                $provider_id,
                mb_substr($device_name, 0, 255),
                session('user_id') ? (int) session('user_id') : null,
            );

            json_response([
                'code' => $code,
                'expires_in_minutes' => 5,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Revoke an authorized kiosk device.
     */
    public function revoke_kiosk_device(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('device_id', 'numeric');

            $this->kiosk_devices_model->revoke((int) request('device_id'));

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
