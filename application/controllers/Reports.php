<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

/**
 * Operational appointment reports.
 */
class Reports extends EA_Controller
{
    private const PERIOD_DAY = 'day';
    private const PERIOD_WEEK = 'week';
    private const PERIOD_MONTH = 'month';

    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('roles_model');
        $this->load->model('secretaries_model');
        $this->load->model('services_model');

        $this->load->library('accounts');
    }

    /**
     * Render the reports page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('reports')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_APPOINTMENTS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');
        $filters = $this->get_filters();
        $period = $this->resolve_period($filters['period'], $filters['date']);
        $available_providers = $this->get_available_providers_for_user((int) $user_id, $role_slug);
        $available_services = $this->services_model->get_available_services();
        $appointments = $this->get_report_appointments($period, $filters, $available_providers, (int) $user_id, $role_slug);
        $report = $this->build_report($appointments, $period);

        html_vars([
            'page_title' => lang('reports'),
            'active_menu' => 'reports',
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
            'filters' => $filters,
            'period' => $period,
            'report' => $report,
            'available_providers' => $available_providers,
            'available_services' => $available_services,
            'role_slug' => $role_slug,
        ]);

        $this->load->view('pages/reports');
    }

    /**
     * Resolve request filters.
     */
    private function get_filters(): array
    {
        $period = request('period', self::PERIOD_DAY);

        if (!in_array($period, [self::PERIOD_DAY, self::PERIOD_WEEK, self::PERIOD_MONTH], true)) {
            $period = self::PERIOD_DAY;
        }

        $date = request('date', date('Y-m-d'));

        $parsed_date = DateTime::createFromFormat('Y-m-d', $date);

        if (!$parsed_date || $parsed_date->format('Y-m-d') !== $date) {
            $date = date('Y-m-d');
        }

        return [
            'period' => $period,
            'date' => $date,
            'provider_id' => (int) request('provider_id', 0),
            'service_id' => (int) request('service_id', 0),
        ];
    }

    /**
     * Get the concrete period boundaries from the selected date.
     */
    private function resolve_period(string $period, string $date): array
    {
        $selected_date = new DateTime($date);
        $start = clone $selected_date;
        $end = clone $selected_date;

        if ($period === self::PERIOD_WEEK) {
            $start->modify('monday this week');
            $end = (clone $start)->modify('+7 days');
        } elseif ($period === self::PERIOD_MONTH) {
            $start->modify('first day of this month');
            $end = (clone $start)->modify('first day of next month');
        } else {
            $end->modify('+1 day');
        }

        $start->setTime(0, 0, 0);
        $end->setTime(0, 0, 0);

        return [
            'type' => $period,
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $end)->modify('-1 day')->format('Y-m-d'),
        ];
    }

    /**
     * Restrict provider options by current user role.
     */
    private function get_available_providers_for_user(int $user_id, string $role_slug): array
    {
        $providers = $this->providers_model->get_available_providers();

        if ($role_slug === DB_SLUG_PROVIDER) {
            return array_values(
                array_filter($providers, static fn(array $provider): bool => (int) $provider['id'] === $user_id),
            );
        }

        if ($role_slug === DB_SLUG_SECRETARY) {
            $secretary_providers = $this->secretaries_model->find($user_id)['providers'] ?? [];

            return array_values(
                array_filter($providers, static function (array $provider) use ($secretary_providers): bool {
                    return in_array((int) $provider['id'], array_map('intval', $secretary_providers), true);
                }),
            );
        }

        return $providers;
    }

    /**
     * Fetch report appointments with service/customer data for classification.
     */
    private function get_report_appointments(
        array $period,
        array $filters,
        array $available_providers,
        int $user_id,
        string $role_slug,
    ): array {
        $allowed_provider_ids = array_map(static fn(array $provider): int => (int) $provider['id'], $available_providers);

        $this->db
            ->select(
                '
                appointments.id,
                appointments.start_datetime,
                appointments.status,
                appointments.appointment_type,
                appointments.notes,
                appointments.id_users_provider,
                appointments.id_services,
                services.name AS service_name,
                providers.first_name AS provider_first_name,
                providers.last_name AS provider_last_name,
                customers.first_name AS customer_first_name,
                customers.email AS customer_email
            ',
                false,
            )
            ->from('appointments')
            ->join('services', 'services.id = appointments.id_services', 'left')
            ->join('users AS providers', 'providers.id = appointments.id_users_provider', 'left')
            ->join('users AS customers', 'customers.id = appointments.id_users_customer', 'left')
            ->where('appointments.is_unavailability', false)
            ->where('appointments.start_datetime >=', $period['start'])
            ->where('appointments.start_datetime <', $period['end']);

        if ($filters['service_id'] > 0) {
            $this->db->where('appointments.id_services', $filters['service_id']);
        }

        if ($role_slug === DB_SLUG_ADMIN) {
            if ($filters['provider_id'] > 0) {
                $this->db->where('appointments.id_users_provider', $filters['provider_id']);
            }
        } else {
            if (empty($allowed_provider_ids)) {
                return [];
            }

            $this->db->where_in('appointments.id_users_provider', $allowed_provider_ids);

            if ($filters['provider_id'] > 0 && in_array($filters['provider_id'], $allowed_provider_ids, true)) {
                $this->db->where('appointments.id_users_provider', $filters['provider_id']);
            }
        }

        if ($role_slug === DB_SLUG_PROVIDER) {
            $this->db->where('appointments.id_users_provider', $user_id);
        }

        return $this->db
            ->order_by('appointments.start_datetime', 'ASC')
            ->order_by('appointments.id', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Convert appointment rows into displayable counters.
     */
    private function build_report(array $appointments, array $period): array
    {
        $summary = [
            'total' => count($appointments),
            'booking' => 0,
            'walkin' => 0,
        ];
        $services = [];
        $statuses = [];
        $providers = [];
        $days = [];

        foreach ($appointments as $appointment) {
            $type = $this->is_walkin_appointment($appointment) ? APPOINTMENT_TYPE_WALKIN : APPOINTMENT_TYPE_BOOKING;
            $summary[$type]++;

            $service_name = trim((string) ($appointment['service_name'] ?? '')) ?: lang('service');
            $status = trim((string) ($appointment['status'] ?? '')) ?: lang('no_status');
            $provider_name = trim(
                (string) ($appointment['provider_first_name'] ?? '') . ' ' . (string) ($appointment['provider_last_name'] ?? ''),
            ) ?: lang('provider');
            $day = substr((string) $appointment['start_datetime'], 0, 10);

            $services[$service_name] = ($services[$service_name] ?? 0) + 1;
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            $providers[$provider_name] = ($providers[$provider_name] ?? 0) + 1;

            if (!isset($days[$day])) {
                $days[$day] = [
                    'date' => $day,
                    'total' => 0,
                    'booking' => 0,
                    'walkin' => 0,
                ];
            }

            $days[$day]['total']++;
            $days[$day][$type]++;
        }

        arsort($services);
        arsort($statuses);
        arsort($providers);
        ksort($days);

        return [
            'summary' => $summary,
            'services' => $services,
            'statuses' => $statuses,
            'providers' => $providers,
            'days' => array_values($days),
            'period' => $period,
        ];
    }

    /**
     * Detect walk-in appointments, including older records.
     */
    private function is_walkin_appointment(array $appointment): bool
    {
        if (($appointment['appointment_type'] ?? null) === APPOINTMENT_TYPE_WALKIN) {
            return true;
        }

        $walkin_label = lang('appointment_type_walkin');
        $notes = trim((string) ($appointment['notes'] ?? ''));

        if ($walkin_label !== '' && stripos($notes, $walkin_label) === 0) {
            return true;
        }

        $first_name = trim((string) ($appointment['customer_first_name'] ?? ''));
        $email = trim((string) ($appointment['customer_email'] ?? ''));

        if ($walkin_label !== '' && strcasecmp($first_name, $walkin_label) === 0) {
            return true;
        }

        return strpos(strtolower($email), 'walkin-') === 0;
    }
}
