<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

/**
 * Public queue display screen.
 */
class Turnos extends EA_Controller
{
    private const STATUS_RESERVED = 'Reservado';
    private const STATUS_IN_PROCESS = 'En proceso';
    private const STATUS_LEGACY_INVALID = '[object Object]';

    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
    }

    /**
     * Render the public department queue screen.
     */
    public function index(): void
    {
        method('get');

        if (!is_app_installed()) {
            redirect('installation');

            return;
        }

        check('departamento', 'numeric');

        $provider_id = (int) request('departamento');

        if ($provider_id <= 0) {
            abort(404, lang('invalid_provider_selection'));
        }

        $provider = $this->providers_model->find($provider_id);

        if (empty($provider)) {
            abort(404, lang('invalid_provider_selection'));
        }

        script_vars([
            'turnos_provider_id' => $provider_id,
            'turnos_data_url' => site_url('turnos/data?departamento=' . $provider_id),
            'realtime_ws_enabled' => config('realtime_ws_enabled', false),
            'realtime_ws_url' => config('realtime_ws_url', ''),
        ]);

        html_vars([
            'page_title' => lang('queue_display'),
            'company_color' => setting('company_color'),
            'provider' => filter_sensitive_user_data($provider),
        ]);

        $this->load->view('pages/turnos');
    }

    /**
     * Return today's visible queue appointments for a department.
     */
    public function data(): void
    {
        try {
            method('get');

            check('departamento', 'numeric');

            $provider_id = (int) request('departamento');

            if ($provider_id <= 0) {
                throw new RuntimeException(lang('invalid_provider_selection'));
            }

            $provider = $this->providers_model->find($provider_id);

            if (empty($provider)) {
                throw new RuntimeException(lang('invalid_provider_selection'));
            }

            $provider_timezone = new DateTimeZone($provider['timezone'] ?: setting('default_timezone'));
            $today = (new DateTime('now', $provider_timezone))->format('Y-m-d');

            $this->appointments_model->ensure_daily_queue_numbers($provider_id, $today);

            $appointments = $this->db
                ->select(
                    '
                    appointments.id,
                    appointments.start_datetime,
                    appointments.update_datetime,
                    appointments.status,
                    appointments.appointment_type,
                    appointments.queue_number,
                    appointments.id_users_caller,
                    appointments.location,
                    services.name AS service_name,
                    services.location AS service_location,
                    callers.first_name AS caller_first_name,
                    callers.last_name AS caller_last_name
                ',
                )
                ->from('appointments AS appointments')
                ->join('services AS services', 'services.id = appointments.id_services', 'left')
                ->join('users AS callers', 'callers.id = appointments.id_users_caller', 'left')
                ->where('appointments.id_users_provider', $provider_id)
                ->where('DATE(start_datetime) = ' . $this->db->escape($today), null, false)
                ->where('appointments.is_unavailability', false)
                ->where_in('appointments.status', [
                    self::STATUS_IN_PROCESS,
                    self::STATUS_RESERVED,
                    self::STATUS_LEGACY_INVALID,
                ])
                ->order_by('appointments.start_datetime', 'ASC')
                ->order_by('appointments.queue_number', 'ASC')
                ->get()
                ->result_array();

            $in_process = [];
            $reserved = [];

            foreach ($appointments as $appointment) {
                $item = $this->serialize_queue_item($appointment);

                if ($appointment['status'] === self::STATUS_IN_PROCESS) {
                    $in_process[] = $item;
                    continue;
                }

                $reserved[] = $item;
            }

            usort($in_process, static function (array $first, array $second): int {
                return strcmp((string) ($second['update_datetime'] ?? ''), (string) ($first['update_datetime'] ?? ''));
            });

            json_response([
                'date' => $today,
                'provider' => [
                    'id' => (int) $provider['id'],
                    'name' => trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? '')),
                ],
                'in_process' => $in_process,
                'reserved' => $reserved,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Convert appointment data to the public queue screen shape.
     */
    private function serialize_queue_item(array $appointment): array
    {
        $appointment_type = $appointment['appointment_type'] ?: APPOINTMENT_TYPE_BOOKING;
        $queue_number = (int) ($appointment['queue_number'] ?? 0);

        return [
            'id' => (int) $appointment['id'],
            'code' => (string) $queue_number,
            'status' => $appointment['status'] === self::STATUS_LEGACY_INVALID ? self::STATUS_RESERVED : $appointment['status'],
            'appointment_type' => $appointment_type,
            'start_datetime' => $appointment['start_datetime'],
            'update_datetime' => $appointment['update_datetime'] ?? null,
            'desk' => $appointment['location'] ?: ($appointment['service_location'] ?? ''),
            'caller_name' => trim(($appointment['caller_first_name'] ?? '') . ' ' . ($appointment['caller_last_name'] ?? '')),
        ];
    }
}
