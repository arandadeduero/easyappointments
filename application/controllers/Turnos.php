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
            'company_logo' => setting('company_logo') ?: base_url('assets/img/logo.png'),
            'company_name' => setting('company_name'),
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
            $has_call_datetime = $this->db->field_exists('call_datetime', 'appointments');
            $has_desk_number = $this->db->field_exists('desk_number', 'user_settings');

            $this->appointments_model->ensure_daily_queue_numbers($provider_id, $today);

            $appointments = $this->db
                ->select(
                    '
                    appointments.id,
                    appointments.start_datetime,
                    appointments.update_datetime,
                    ' .
                        ($has_call_datetime ? 'appointments.call_datetime,' : 'NULL AS call_datetime,') .
                        '
                    appointments.status,
                    appointments.appointment_type,
                    appointments.queue_number,
                    appointments.id_users_caller,
                    appointments.location,
                    services.name AS service_name,
                    services.location AS service_location,
                    ' .
                        ($has_desk_number ? 'caller_settings.desk_number AS caller_desk_number,' : 'NULL AS caller_desk_number,') .
                        '
                    callers.first_name AS caller_first_name,
                    callers.last_name AS caller_last_name
                ',
                    false,
                )
                ->from('appointments AS appointments')
                ->join('services AS services', 'services.id = appointments.id_services', 'left')
                ->join('users AS callers', 'callers.id = appointments.id_users_caller', 'left')
                ->join('user_settings AS caller_settings', 'caller_settings.id_users = callers.id', 'left')
                ->where('appointments.id_users_provider', $provider_id)
                ->where('DATE(start_datetime) = ' . $this->db->escape($today), null, false)
                ->where('appointments.is_unavailability', false)
                ->group_start()
                ->where('appointments.status', self::STATUS_RESERVED)
                ->or_where('appointments.status', self::STATUS_LEGACY_INVALID)
                ->or_where('appointments.status', self::STATUS_IN_PROCESS)
                ->or_where('appointments.id_users_caller IS NOT NULL', null, false)
                ->group_end()
                ->order_by('appointments.start_datetime', 'ASC')
                ->order_by('appointments.queue_number', 'ASC')
                ->get()
                ->result_array();

            $called = [];
            $pending = [];

            foreach ($appointments as $appointment) {
                $item = $this->serialize_queue_item($appointment);

                if ($item['status'] === self::STATUS_RESERVED) {
                    $pending[] = $item;
                    continue;
                }

                if ($item['status'] === self::STATUS_IN_PROCESS) {
                    $called[] = $item;
                    continue;
                }
            }

            usort($called, static function (array $first, array $second): int {
                $first_called_at = (string) (($first['call_datetime'] ?? null) ?: ($first['update_datetime'] ?? ''));
                $second_called_at = (string) (($second['call_datetime'] ?? null) ?: ($second['update_datetime'] ?? ''));

                return strcmp($second_called_at, $first_called_at);
            });

            $called = array_slice($called, 0, 8);
            $pending = array_slice($pending, 0, 6);

            json_response([
                'date' => $today,
                'provider' => [
                    'id' => (int) $provider['id'],
                    'name' => trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? '')),
                ],
                'called' => $called,
                'pending' => $pending,
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
            'call_datetime' => $appointment['call_datetime'] ?? null,
            'desk' => $appointment['location'] ?: ($appointment['service_location'] ?? ''),
            'caller_desk_number' => trim((string) ($appointment['caller_desk_number'] ?? '')),
            'caller_name' => trim(($appointment['caller_first_name'] ?? '') . ' ' . ($appointment['caller_last_name'] ?? '')),
        ];
    }
}
