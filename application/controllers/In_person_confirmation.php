<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

/**
 * In-person appointment confirmation controller.
 */
class In_person_confirmation extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->library('google_sync');
    }

    /**
     * Display confirmation with queue number when available.
     */
    public function of(): void
    {
        $appointment_hash = $this->uri->segment(3);

        $occurrences = $this->appointments_model->get(['hash' => $appointment_hash]);

        if (empty($occurrences)) {
            redirect('booking');

            return;
        }

        $appointment = $occurrences[0];
        $provider = $this->providers_model->find((int) $appointment['id_users_provider']);
        $service = $this->services_model->find((int) $appointment['id_services']);

        $add_to_google_url = $this->google_sync->get_add_to_google_url($appointment['id']);

        script_vars([
            'local_printing_enabled' => $provider['settings']['local_printing_enabled'] ?? setting(
                'local_printing_enabled',
                '0',
            ),
            'local_printing_endpoint' => $provider['settings']['local_printing_endpoint'] ?? setting(
                'local_printing_endpoint',
                'http://127.0.0.1:8787/print',
            ),
            'local_printing_token' => $provider['settings']['local_printing_token'] ?? setting(
                'local_printing_token',
                '',
            ),
            'walkin_ticket' => [
                'queue_number' => $appointment['queue_number'] ?? null,
                'company_name' => setting('company_name'),
                'department' => trim($provider['first_name'] . ' ' . $provider['last_name']),
                'service' => $service['name'],
                'datetime' => format_date_time($appointment['start_datetime']),
                'appointment_hash' => $appointment['hash'],
            ],
            'walkin_return_url' => site_url(
                'in_person_appointments?departamento=' . (int) $appointment['id_users_provider'],
            ),
            'walkin_return_delay' => 8000,
        ]);

        html_vars([
            'page_title' => lang('success'),
            'company_color' => setting('company_color'),
            'google_analytics_code' => setting('google_analytics_code'),
            'matomo_analytics_url' => setting('matomo_analytics_url'),
            'matomo_analytics_site_id' => setting('matomo_analytics_site_id'),
            'add_to_google_url' => $add_to_google_url,
            'display_add_to_google_calendar' => setting('display_add_to_google_calendar', '1'),
            'is_walkin' => ($appointment['appointment_type'] ?? APPOINTMENT_TYPE_BOOKING) === APPOINTMENT_TYPE_WALKIN,
            'queue_number' => $appointment['queue_number'] ?? null,
        ]);

        $this->load->view('pages/booking_confirmation');
    }
}
