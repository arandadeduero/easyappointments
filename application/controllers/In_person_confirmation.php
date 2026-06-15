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

        $add_to_google_url = $this->google_sync->get_add_to_google_url($appointment['id']);

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
