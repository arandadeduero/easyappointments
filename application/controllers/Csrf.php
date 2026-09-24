<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

/**
 * Lightweight endpoint used by long-lived browser tabs to renew CSRF state.
 */
class Csrf extends CI_Controller
{
    public function refresh(): void
    {
        if (strtoupper($this->input->method()) !== 'GET') {
            show_error('Method Not Allowed', 405);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_header('Cache-Control: no-store')
            ->set_output(
                json_encode([
                    'success' => true,
                ]),
            );
    }
}
