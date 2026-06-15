<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

/**
 * Public in-person appointments controller.
 */
class In_person_appointments extends EA_Controller
{
    private const APPOINTMENT_STATUS_COLORS = [
        'Reservado' => '#7cbae8',
        'En proceso' => '#ff9800',
        'Finalizada' => '#adb5bd',
    ];

    protected array $allowed_customer_fields = [
        'id',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'address',
        'city',
        'zip_code',
        'timezone',
        'language',
    ];

    protected array $allowed_appointment_fields = [
        'id',
        'start_datetime',
        'end_datetime',
        'location',
        'meeting_link',
        'notes',
        'color',
        'status',
        'appointment_type',
        'queue_number',
        'is_unavailability',
        'id_users_provider',
        'id_users_customer',
        'id_services',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');

        $this->load->library('availability');
        $this->load->library('notifications');
        $this->load->library('synchronization');
        $this->load->library('webhooks_client');
    }

    /**
     * Resolve the default appointment status from settings.
     */
    protected function get_default_appointment_status(): ?string
    {
        $raw_options = setting('appointment_status_options', '[]');
        $options = is_string($raw_options) ? json_decode($raw_options, true) ?? [] : (array) $raw_options;
        $first_option = $options[0] ?? null;

        if (is_array($first_option)) {
            $first_option =
                $first_option['value'] ??
                $first_option['label'] ??
                $first_option['name'] ??
                reset($first_option);
        }

        if (is_scalar($first_option) && $first_option !== '' && $first_option !== '[object Object]') {
            return (string) $first_option;
        }

        return 'Reservado';
    }

    /**
     * Get the color assigned to an appointment status.
     */
    protected function get_appointment_status_color(?string $status): ?string
    {
        return $status !== null ? self::APPOINTMENT_STATUS_COLORS[$status] ?? null : null;
    }

    /**
     * Check whether public in-person appointments are disabled.
     */
    protected function are_walkin_appointments_disabled(): bool
    {
        return filter_var(setting('disable_walkin_appointments', '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function index(): void
    {
        method('get');

        if (!is_app_installed()) {
            redirect('installation');

            return;
        }

        if ($this->are_walkin_appointments_disabled()) {
            html_vars([
                'show_message' => true,
                'page_title' => lang('walkin_create'),
                'message_title' => lang('walkin_appointments_are_disabled'),
                'message_text' => setting('disable_booking_message'),
                'message_icon' => base_url('assets/img/error.png'),
                'google_analytics_code' => setting('google_analytics_code'),
                'matomo_analytics_url' => setting('matomo_analytics_url'),
                'matomo_analytics_site_id' => setting('matomo_analytics_site_id'),
                'display_login_button' => setting('display_login_button'),
                'legal_notice_url' => setting('legal_notice_url'),
                'imprint_url' => setting('imprint_url'),
            ]);

            $this->load->view('pages/booking_message');

            return;
        }

        check('departamento', 'numeric');

        $department_id = (int) request('departamento');

        if ($department_id <= 0) {
            abort(404, lang('invalid_provider_selection'));
        }

        $provider = $this->providers_model->find($department_id);

        if (empty($provider)) {
            abort(404, lang('invalid_provider_selection'));
        }

        $available_services = $this->services_model->get_available_services(true);
        $provider_service_ids = array_map('intval', $provider['services'] ?? []);

        $available_services = array_values(
            array_filter($available_services, function ($service) use ($provider_service_ids) {
                return in_array((int) $service['id'], $provider_service_ids, true);
            }),
        );

        script_vars([
            'provider' => filter_sensitive_user_data($provider),
            'available_services' => $available_services,
            'default_timezone' => setting('default_timezone'),
        ]);

        html_vars([
            'page_title' => lang('walkin_create'),
            'company_color' => setting('company_color'),
            'legal_notice_url' => setting('legal_notice_url'),
            'imprint_url' => setting('imprint_url'),
            'display_login_button' => setting('display_login_button'),
        ]);

        $this->load->view('pages/in_person_appointments');
    }

    public function store(): void
    {
        try {
            method('post');

            if ($this->are_walkin_appointments_disabled()) {
                throw new RuntimeException(lang('walkin_appointments_are_disabled'));
            }

            check('post_data', 'array');

            $post_data = request('post_data');
            $appointment = $post_data['appointment'] ?? [];

            if (empty($appointment)) {
                throw new RuntimeException(lang('fields_are_required'));
            }

            $provider_id = (int) ($appointment['id_users_provider'] ?? 0);
            $service_id = (int) ($appointment['id_services'] ?? 0);

            if ($provider_id <= 0) {
                throw new RuntimeException(lang('invalid_provider_selection'));
            }

            if ($service_id <= 0) {
                throw new RuntimeException(lang('invalid_service_selection'));
            }

            $provider = $this->providers_model->find($provider_id);
            $service = $this->services_model->find($service_id);

            if (empty($provider)) {
                throw new RuntimeException(lang('invalid_provider_selection'));
            }

            if (empty($service)) {
                throw new RuntimeException(lang('invalid_service_selection'));
            }

            if (!in_array($service_id, array_map('intval', $provider['services'] ?? []), true)) {
                throw new RuntimeException(lang('invalid_service_selection'));
            }

            $walkin_slot = $this->find_next_walkin_slot_for_provider($provider, $service);

            if (empty($walkin_slot)) {
                throw new RuntimeException(lang('walkin_no_slots_available'));
            }

            $queue_number = $this->get_next_walkin_queue_number($provider_id, substr($walkin_slot['start_datetime'], 0, 10));

            $customer_reference = date('YmdHis') . '-' . $queue_number;
            $require_email = filter_var(setting('require_email'), FILTER_VALIDATE_BOOLEAN);
            $require_phone_number = filter_var(setting('require_phone_number'), FILTER_VALIDATE_BOOLEAN);
            $require_address = filter_var(setting('require_address'), FILTER_VALIDATE_BOOLEAN);
            $require_city = filter_var(setting('require_city'), FILTER_VALIDATE_BOOLEAN);
            $require_zip_code = filter_var(setting('require_zip_code'), FILTER_VALIDATE_BOOLEAN);

            $customer = [
                'first_name' => lang('appointment_type_walkin'),
                'last_name' => $customer_reference,
                'email' => $require_email ? 'walkin-' . $customer_reference . '@example.com' : '',
                'phone_number' => $require_phone_number ? $customer_reference : '',
                'address' => $require_address ? lang('appointment_type_walkin') : '',
                'city' => $require_city ? lang('appointment_type_walkin') : '',
                'zip_code' => $require_zip_code ? $customer_reference : '',
                'timezone' => setting('default_timezone'),
                'language' => session('language') ?? config('language'),
            ];

            $this->customers_model->only($customer, $this->allowed_customer_fields);
            $customer_id = $this->customers_model->save($customer);
            $customer = $this->customers_model->find($customer_id);

            $appointment_status = $this->get_default_appointment_status();

            $appointment_data = [
                'start_datetime' => $walkin_slot['start_datetime'],
                'end_datetime' => $this->appointments_model->calculate_end_datetime([
                    'start_datetime' => $walkin_slot['start_datetime'],
                    'id_services' => $service_id,
                ]),
                'notes' => lang('appointment_type_walkin') . ' #' . $queue_number,
                'status' => $appointment_status,
                'appointment_type' => APPOINTMENT_TYPE_WALKIN,
                'queue_number' => $queue_number,
                'is_unavailability' => false,
                'id_users_provider' => $provider_id,
                'id_users_customer' => $customer_id,
                'id_services' => $service_id,
                'location' => $service['location'] ?? null,
                'color' => $this->get_appointment_status_color($appointment_status),
            ];

            $this->appointments_model->only($appointment_data, $this->allowed_appointment_fields);
            $appointment_id = $this->appointments_model->save($appointment_data);
            $appointment = $this->appointments_model->find($appointment_id);

            $company_color = setting('company_color');

            $settings = [
                'company_name' => setting('company_name'),
                'company_link' => setting('company_link'),
                'company_email' => setting('company_email'),
                'company_color' =>
                    !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
                'date_format' => setting('date_format'),
                'time_format' => setting('time_format'),
            ];

            $this->synchronization->sync_appointment_saved($appointment, $service, $provider, $customer, $settings);

            $this->notifications->notify_appointment_saved(
                $appointment,
                $service,
                $provider,
                $customer,
                $settings,
                false,
            );

            $this->webhooks_client->trigger(WEBHOOK_APPOINTMENT_SAVE, $appointment);

            json_response([
                'appointment_id' => $appointment['id'],
                'appointment_hash' => $appointment['hash'],
                'queue_number' => $queue_number,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    protected function find_next_walkin_slot_for_provider(array $provider, array $service): ?array
    {
        $provider_timezone = new DateTimeZone($provider['timezone']);
        $provider_now = new DateTime('now', $provider_timezone);
        $future_booking_limit = (int) setting('future_booking_limit');

        for ($day_offset = 0; $day_offset <= $future_booking_limit; $day_offset++) {
            $date = (clone $provider_now)->modify('+' . $day_offset . ' days')->format('Y-m-d');

            $available_hours = $this->availability->get_available_hours(
                $date,
                $service,
                $provider,
                null,
                APPOINTMENT_TYPE_WALKIN,
            );

            if (empty($available_hours)) {
                continue;
            }

            foreach ($available_hours as $available_hour) {
                $slot_start = new DateTime($date . ' ' . $available_hour . ':00', $provider_timezone);

                if ($slot_start < $provider_now) {
                    continue;
                }

                return [
                    'start_datetime' => $slot_start->format('Y-m-d H:i:s'),
                ];
            }
        }

        return null;
    }

    protected function get_next_walkin_queue_number(int $provider_id, string $date): int
    {
        return $this->appointments_model->get_random_queue_number($provider_id, $date);
    }
}
