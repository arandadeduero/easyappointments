<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * Appointments model.
 *
 * @package Models
 */
class Appointments_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'is_unavailability' => 'boolean',
        'id_users_provider' => 'integer',
        'id_users_customer' => 'integer',
        'id_services' => 'integer',
        'queue_number' => 'integer',
        'id_users_caller' => 'integer',
    ];

    /**
     * @var array
     */
    protected array $api_resource = [
        'id' => 'id',
        'book' => 'book_datetime',
        'start' => 'start_datetime',
        'end' => 'end_datetime',
        'location' => 'location',
        'meetingLink' => 'meeting_link',
        'color' => 'color',
        'status' => 'status',
        'type' => 'appointment_type',
        'queueNumber' => 'queue_number',
        'callerUserId' => 'id_users_caller',
        'callDateTime' => 'call_datetime',
        'notes' => 'notes',
        'hash' => 'hash',
        'serviceId' => 'id_services',
        'providerId' => 'id_users_provider',
        'customerId' => 'id_users_customer',
        'googleCalendarId' => 'id_google_calendar',
        'caldavCalendarId' => 'id_caldav_calendar',
    ];

    /**
     * Save (insert or update) an appointment.
     *
     * @param array $appointment Associative array with the appointment data.
     *
     * @return int Returns the appointment ID.
     *
     * @throws InvalidArgumentException
     */
    public function save(array $appointment): int
    {
        $capacity_lock = $this->acquire_provider_capacity_lock($appointment);

        try {
            $this->validate($appointment);

            if (empty($appointment['id'])) {
                return $this->insert($appointment);
            }

            return $this->update($appointment);
        } finally {
            if ($capacity_lock) {
                $this->db->query('SELECT RELEASE_LOCK(?)', [$capacity_lock]);
            }
        }
    }

    /**
     * Serialize appointment writes for departments with shared capacity.
     */
    protected function acquire_provider_capacity_lock(array $appointment): ?string
    {
        if (
            filter_var($appointment['is_unavailability'] ?? false, FILTER_VALIDATE_BOOLEAN) ||
            empty($appointment['id_services'])
        ) {
            return null;
        }

        if (empty($appointment['id_users_provider'])) {
            return null;
        }

        $provider_settings = $this->db
            ->select('attendants_number')
            ->get_where('user_settings', ['id_users' => $appointment['id_users_provider']])
            ->row_array();

        if (empty($provider_settings['attendants_number'])) {
            return null;
        }

        $lock_name = 'ea_department_capacity_' . (int) $appointment['id_users_provider'];
        $lock_result = $this->db->query('SELECT GET_LOCK(?, 5) AS acquired', [$lock_name])->row_array();

        if ((int) ($lock_result['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the department capacity lock.');
        }

        return $lock_name;
    }

    /**
     * Check whether a daily queue number is available for a provider.
     */
    private function is_queue_number_available(
        int $provider_id,
        string $date,
        int $queue_number,
        ?int $exclude_appointment_id = null,
    ): bool {
        $this->db
            ->select('id')
            ->from('appointments')
            ->where('id_users_provider', $provider_id)
            ->where('DATE(start_datetime)', $date)
            ->where('queue_number', $queue_number)
            ->where('is_unavailability', false);

        if ($exclude_appointment_id) {
            $this->db->where('id !=', $exclude_appointment_id);
        }

        return $this->db->get()->num_rows() === 0;
    }

    /**
     * Generate the next available daily queue number for a provider.
     */
    public function get_next_queue_number(
        int $provider_id,
        string $date,
        ?int $exclude_appointment_id = null,
    ): int {
        for ($queue_number = 1; $queue_number <= 999; $queue_number++) {
            if ($this->is_queue_number_available($provider_id, $date, $queue_number, $exclude_appointment_id)) {
                return $queue_number;
            }
        }

        throw new RuntimeException('There are no available queue numbers for this provider and date.');
    }

    /**
     * Generate a random daily queue number for an online booking.
     */
    public function get_random_queue_number(
        int $provider_id,
        string $date,
        ?int $exclude_appointment_id = null,
    ): int {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $queue_number = random_int(1, 999);

            if ($this->is_queue_number_available($provider_id, $date, $queue_number, $exclude_appointment_id)) {
                return $queue_number;
            }
        }

        return $this->get_next_queue_number($provider_id, $date, $exclude_appointment_id);
    }

    /**
     * Assign a stable queue number once; never refresh it during normal edits.
     */
    public function ensure_queue_number(array &$appointment): void
    {
        if (filter_var($appointment['is_unavailability'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (empty($appointment['start_datetime']) || empty($appointment['id_users_provider'])) {
            return;
        }

        $appointment_type = $appointment['appointment_type'] ?? APPOINTMENT_TYPE_BOOKING;

        if (!in_array($appointment_type, [APPOINTMENT_TYPE_BOOKING, APPOINTMENT_TYPE_WALKIN], true)) {
            $appointment_type = APPOINTMENT_TYPE_BOOKING;
        }

        $appointment['appointment_type'] = $appointment_type;

        if (!empty($appointment['id'])) {
            $current_appointment = $this->find((int) $appointment['id']);

            if (!empty($current_appointment['queue_number'])) {
                $appointment['queue_number'] = $current_appointment['queue_number'];
                return;
            }
        }

        $appointment_date = substr((string) $appointment['start_datetime'], 0, 10);

        if (!empty($appointment['queue_number'])) {
            return;
        }

        $exclude_appointment_id = isset($appointment['id']) ? (int) $appointment['id'] : null;

        $appointment['queue_number'] = $this->get_random_queue_number(
            (int) $appointment['id_users_provider'],
            $appointment_date,
            $exclude_appointment_id,
        );
    }

    /**
     * Backfill today's missing queue numbers for the display screen.
     */
    public function ensure_daily_queue_numbers(int $provider_id, string $date): void
    {
        foreach ([APPOINTMENT_TYPE_BOOKING, APPOINTMENT_TYPE_WALKIN] as $appointment_type) {
            $this->db
                ->select('*')
                ->from('appointments')
                ->where('id_users_provider', $provider_id)
                ->where('DATE(start_datetime)', $date)
                ->where('is_unavailability', false);

            if ($appointment_type === APPOINTMENT_TYPE_BOOKING) {
                $this->db
                    ->group_start()
                    ->where('appointment_type', APPOINTMENT_TYPE_BOOKING)
                    ->or_where('appointment_type IS NULL')
                    ->or_where('appointment_type', '')
                    ->group_end();
            } else {
                $this->db->where('appointment_type', $appointment_type);
            }

            $appointments = $this->db
                ->group_start()
                ->where('queue_number IS NULL')
                ->or_where('queue_number <=', 0)
                ->group_end()
                ->order_by('start_datetime', 'ASC')
                ->order_by('id', 'ASC')
                ->get()
                ->result_array();

            foreach ($appointments as $appointment) {
                $queue_number = $this->get_random_queue_number($provider_id, $date, (int) $appointment['id']);

                $this->db->update(
                    'appointments',
                    [
                        'appointment_type' => $appointment_type,
                        'queue_number' => $queue_number,
                        'update_datetime' => date('Y-m-d H:i:s'),
                    ],
                    [
                        'id' => $appointment['id'],
                    ],
                );
            }
        }
    }

    /**
     * Validate the appointment data.
     *
     * @param array $appointment Associative array with the appointment data.
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $appointment): void
    {
        // If an appointment ID is provided then check whether the record really exists in the database.
        if (!empty($appointment['id'])) {
            $count = $this->db->get_where('appointments', ['id' => $appointment['id']])->num_rows();

            if (!$count) {
                throw new InvalidArgumentException(
                    'The provided appointment ID does not exist in the database: ' . $appointment['id'],
                );
            }
        }

        // Make sure all required fields are provided.

        $require_notes = filter_var(setting('require_notes'), FILTER_VALIDATE_BOOLEAN);

        if (
            empty($appointment['start_datetime']) ||
            empty($appointment['end_datetime']) ||
            empty($appointment['id_services']) ||
            empty($appointment['id_users_provider']) ||
            empty($appointment['id_users_customer']) ||
            (empty($appointment['notes']) && $require_notes)
        ) {
            throw new InvalidArgumentException('Not all required fields are provided: ' . print_r($appointment, true));
        }

        // Make sure that the provided appointment date time values are valid.
        if (!validate_datetime($appointment['start_datetime'])) {
            throw new InvalidArgumentException('The appointment start date time is invalid.');
        }

        if (!validate_datetime($appointment['end_datetime'])) {
            throw new InvalidArgumentException('The appointment end date time is invalid.');
        }

        // Make the appointment lasts longer than the minimum duration (in minutes).
        $diff = (strtotime($appointment['end_datetime']) - strtotime($appointment['start_datetime'])) / 60;

        if ($diff < EVENT_MINIMUM_DURATION) {
            throw new InvalidArgumentException(
                'The appointment duration cannot be less than ' . EVENT_MINIMUM_DURATION . ' minutes.',
            );
        }

        // Make sure the provider ID really exists in the database.
        $count = $this->db
            ->select()
            ->from('users')
            ->join('roles', 'roles.id = users.id_roles', 'inner')
            ->where('users.id', $appointment['id_users_provider'])
            ->where('roles.slug', DB_SLUG_PROVIDER)
            ->get()
            ->num_rows();

        if (!$count) {
            throw new InvalidArgumentException(
                'The appointment provider ID was not found in the database: ' . $appointment['id_users_provider'],
            );
        }

        if (!filter_var($appointment['is_unavailability'], FILTER_VALIDATE_BOOLEAN)) {
            // Make sure the customer ID really exists in the database.
            $count = $this->db
                ->select()
                ->from('users')
                ->join('roles', 'roles.id = users.id_roles', 'inner')
                ->where('users.id', $appointment['id_users_customer'])
                ->where('roles.slug', DB_SLUG_CUSTOMER)
                ->get()
                ->num_rows();

            if (!$count) {
                throw new InvalidArgumentException(
                    'The appointment customer ID was not found in the database: ' . $appointment['id_users_customer'],
                );
            }

            // Make sure the service ID really exists in the database.
            $count = $this->db->get_where('services', ['id' => $appointment['id_services']])->num_rows();

            if (!$count) {
                throw new InvalidArgumentException('Appointment service id is invalid.');
            }

            $this->validate_provider_capacity($appointment);
        }
    }

    /**
     * Validate the shared department capacity and the selected service limit.
     */
    protected function validate_provider_capacity(array $appointment): void
    {
        if (!empty($appointment['id'])) {
            $original_appointment = $this->db
                ->select('start_datetime, end_datetime, id_services, id_users_provider')
                ->get_where('appointments', ['id' => $appointment['id']])
                ->row_array();

            if (
                $original_appointment &&
                $original_appointment['start_datetime'] === $appointment['start_datetime'] &&
                $original_appointment['end_datetime'] === $appointment['end_datetime'] &&
                (int) $original_appointment['id_services'] === (int) $appointment['id_services'] &&
                (int) $original_appointment['id_users_provider'] === (int) $appointment['id_users_provider']
            ) {
                return;
            }
        }

        $service = $this->db
            ->select('attendants_number')
            ->get_where('services', ['id' => $appointment['id_services']])
            ->row_array();

        $provider_settings = $this->db
            ->select('attendants_number')
            ->get_where('user_settings', ['id_users' => $appointment['id_users_provider']])
            ->row_array();

        $provider_limit = (int) ($provider_settings['attendants_number'] ?? 0);

        // A null capacity keeps legacy categories working until an administrator configures their tables.
        if ($provider_limit < 1) {
            return;
        }

        $start = new DateTime($appointment['start_datetime']);
        $end = new DateTime($appointment['end_datetime']);
        $exclude_appointment_id = !empty($appointment['id']) ? (int) $appointment['id'] : null;

        $service_usage = $this->get_service_attendants_number_for_period(
            $start,
            $end,
            (int) $appointment['id_services'],
            (int) $appointment['id_users_provider'],
            $exclude_appointment_id,
        );

        if ($service_usage >= (int) $service['attendants_number']) {
            throw new RuntimeException(lang('requested_hour_is_unavailable'));
        }

        $provider_usage = $this->get_provider_attendants_number_for_period(
            $start,
            $end,
            (int) $appointment['id_users_provider'],
            $exclude_appointment_id,
        );

        if ($provider_usage >= $provider_limit) {
            throw new RuntimeException(lang('requested_hour_is_unavailable'));
        }
    }

    /**
     * Get all appointments that match the provided criteria.
     *
     * @param array|string|null $where Where conditions.
     * @param int|null $limit Record limit.
     * @param int|null $offset Record offset.
     * @param string|null $order_by Order by.
     *
     * @return array Returns an array of appointments.
     */
    public function get(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $order_by = null,
    ): array {
        if ($where !== null) {
            $this->db->where($where);
        }

        if ($order_by) {
            $this->db->order_by($this->quote_order_by($order_by));
        }

        $appointments = $this->db
            ->get_where('appointments', ['is_unavailability' => false], $limit, $offset)
            ->result_array();

        foreach ($appointments as &$appointment) {
            $this->cast($appointment);
        }

        return $appointments;
    }

    /**
     * Insert a new appointment into the database.
     *
     * @param array $appointment Associative array with the appointment data.
     *
     * @return int Returns the appointment ID.
     *
     * @throws RuntimeException
     */
    protected function insert(array $appointment): int
    {
        $appointment['book_datetime'] = date('Y-m-d H:i:s');
        $appointment['create_datetime'] = date('Y-m-d H:i:s');
        $appointment['update_datetime'] = date('Y-m-d H:i:s');
        $appointment['hash'] = random_string('alnum', 12);

        if (!$this->db->insert('appointments', $appointment)) {
            throw new RuntimeException('Could not insert appointment.');
        }

        return $this->db->insert_id();
    }

    /**
     * Update an existing appointment.
     *
     * @param array $appointment Associative array with the appointment data.
     *
     * @return int Returns the appointment ID.
     *
     * @throws RuntimeException
     */
    protected function update(array $appointment): int
    {
        $appointment['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->update('appointments', $appointment, ['id' => $appointment['id']])) {
            throw new RuntimeException('Could not update appointment record.');
        }

        return $appointment['id'];
    }

    /**
     * Get a specific appointment from the database.
     *
     * @param int $appointment_id The ID of the record to be returned.
     *
     * @return array Returns an array with the appointment data.
     *
     * @throws InvalidArgumentException
     */
    public function find(int $appointment_id): array
    {
        $appointment = $this->db->get_where('appointments', ['id' => $appointment_id])->row_array();

        if (!$appointment) {
            throw new InvalidArgumentException(
                'The provided appointment ID was not found in the database: ' . $appointment_id,
            );
        }

        $this->cast($appointment);

        return $appointment;
    }

    /**
     * Get a specific field value from the database.
     *
     * @param int $appointment_id Appointment ID.
     * @param string $field Name of the value to be returned.
     *
     * @return mixed Returns the selected appointment value from the database.
     *
     * @throws InvalidArgumentException
     */
    public function value(int $appointment_id, string $field): mixed
    {
        if (empty($field)) {
            throw new InvalidArgumentException('The field argument is cannot be empty.');
        }

        if (empty($appointment_id)) {
            throw new InvalidArgumentException('The appointment ID argument cannot be empty.');
        }

        // Check whether the appointment exists.
        $query = $this->db->get_where('appointments', ['id' => $appointment_id]);

        if (!$query->num_rows()) {
            throw new InvalidArgumentException(
                'The provided appointment ID was not found in the database: ' . $appointment_id,
            );
        }

        // Check if the required field is part of the appointment data.
        $appointment = $query->row_array();

        $this->cast($appointment);

        if (!array_key_exists($field, $appointment)) {
            throw new InvalidArgumentException('The requested field was not found in the appointment data: ' . $field);
        }

        return $appointment[$field];
    }

    /**
     * Remove all the Google Calendar event IDs from appointment records.
     *
     * @param int $provider_id Matching provider ID.
     */
    public function clear_google_sync_ids(int $provider_id): void
    {
        $this->db->update('appointments', ['id_google_calendar' => null], ['id_users_provider' => $provider_id]);
    }

    /**
     * Remove all the Google Calendar event IDs from appointment records.
     *
     * @param int $provider_id Matching provider ID.
     */
    public function clear_caldav_sync_ids(int $provider_id): void
    {
        $this->db->update('appointments', ['id_caldav_calendar' => null], ['id_users_provider' => $provider_id]);
    }

    /**
     * Deletes recurring CalDAV events for the provided date period.
     *
     * @param string $start_date_time
     * @param string $end_date_time
     *
     * @return void
     */
    public function delete_caldav_recurring_events(string $start_date_time, string $end_date_time): void
    {
        $this->db
            ->where('start_datetime >=', $start_date_time)
            ->where('end_datetime <=', $end_date_time)
            ->where('is_unavailability', true)
            ->like('id_caldav_calendar', 'RECURRENCE')
            ->delete('appointments');
    }

    /**
     * Remove an existing appointment from the database.
     *
     * @param int $appointment_id Appointment ID.
     *
     * @throws RuntimeException
     */
    public function delete(int $appointment_id): void
    {
        $this->db->delete('appointments', ['id' => $appointment_id]);
    }

    /**
     * Get the attendants number for the requested period.
     *
     * @param DateTime $start Period start.
     * @param DateTime $end Period end.
     * @param int $service_id Service ID.
     * @param int $provider_id Provider ID.
     * @param int|null $exclude_appointment_id Exclude an appointment from the result set.
     *
     * @return int Returns the number of appointments that match the provided criteria.
     */
    public function get_attendants_number_for_period(
        DateTime $start,
        DateTime $end,
        int $service_id,
        int $provider_id,
        ?int $exclude_appointment_id = null,
    ): int {
        if ($exclude_appointment_id) {
            $this->db->where('id !=', $exclude_appointment_id);
        }

        $result = $this->db
            ->select('count(*) AS attendants_number')
            ->from('appointments')
            ->group_start()
            ->group_start()
            ->where('start_datetime <=', $start->format('Y-m-d H:i:s'))
            ->where('end_datetime >', $start->format('Y-m-d H:i:s'))
            ->group_end()
            ->or_group_start()
            ->where('start_datetime <', $end->format('Y-m-d H:i:s'))
            ->where('end_datetime >=', $end->format('Y-m-d H:i:s'))
            ->group_end()
            ->group_end()
            ->where('id_services', $service_id)
            ->where('id_users_provider', $provider_id)
            ->get()
            ->row_array();

        return $result['attendants_number'];
    }

    /**
     *
     * Returns the number of the other service attendants number for the provided time slot.
     *
     * @param DateTime $start Period start.
     * @param DateTime $end Period end.
     * @param int $service_id Service ID.
     * @param int $provider_id Provider ID.
     * @param int|null $exclude_appointment_id Exclude an appointment from the result set.
     *
     * @return int Returns the number of appointments that match the provided criteria.
     */
    public function get_other_service_attendants_number(
        DateTime $start,
        DateTime $end,
        int $service_id,
        int $provider_id,
        ?int $exclude_appointment_id = null,
    ): int {
        if ($exclude_appointment_id) {
            $this->db->where('id !=', $exclude_appointment_id);
        }

        $result = $this->db
            ->select('count(*) AS attendants_number')
            ->from('appointments')
            ->group_start()
            ->group_start()
            ->where('start_datetime <=', $start->format('Y-m-d H:i:s'))
            ->where('end_datetime >', $start->format('Y-m-d H:i:s'))
            ->group_end()
            ->or_group_start()
            ->where('start_datetime <', $end->format('Y-m-d H:i:s'))
            ->where('end_datetime >=', $end->format('Y-m-d H:i:s'))
            ->group_end()
            ->group_end()
            ->where('id_services !=', $service_id)
            ->where('id_users_provider', $provider_id)
            ->get()
            ->row_array();

        return $result['attendants_number'];
    }

    /**
     * Return the number of overlapping appointments for a service and provider, across all appointment types.
     */
    public function get_service_attendants_number_for_period(
        DateTime $start,
        DateTime $end,
        int $service_id,
        int $provider_id,
        ?int $exclude_appointment_id = null,
    ): int {
        $this->db
            ->from('appointments')
            ->where('is_unavailability', false)
            ->where('id_services', $service_id)
            ->where('id_users_provider', $provider_id)
            ->where('start_datetime <', $end->format('Y-m-d H:i:s'))
            ->where('end_datetime >', $start->format('Y-m-d H:i:s'));

        if ($exclude_appointment_id) {
            $this->db->where('id !=', $exclude_appointment_id);
        }

        return $this->db->count_all_results();
    }

    /**
     * Return the number of overlapping appointments that use tables from a provider/department.
     */
    public function get_provider_attendants_number_for_period(
        DateTime $start,
        DateTime $end,
        int $provider_id,
        ?int $exclude_appointment_id = null,
    ): int {
        $this->db
            ->from('appointments')
            ->where('appointments.is_unavailability', false)
            ->where('appointments.id_users_provider', $provider_id)
            ->where('appointments.start_datetime <', $end->format('Y-m-d H:i:s'))
            ->where('appointments.end_datetime >', $start->format('Y-m-d H:i:s'));

        if ($exclude_appointment_id) {
            $this->db->where('appointments.id !=', $exclude_appointment_id);
        }

        return $this->db->count_all_results();
    }

    /**
     * Get the query builder interface, configured for use with the appointments table.
     *
     * @return CI_DB_query_builder
     */
    public function query(): CI_DB_query_builder
    {
        return $this->db->from('appointments');
    }

    /**
     * Search appointments by the provided keyword.
     *
     * @param string $keyword Search keyword.
     * @param int|null $limit Record limit.
     * @param int|null $offset Record offset.
     * @param string|null $order_by Order by.
     *
     * @return array Returns an array of appointments.
     */
    public function search(string $keyword, ?int $limit = null, ?int $offset = null, ?string $order_by = null): array
    {
        $appointments = $this->db
            ->select('appointments.*')
            ->from('appointments')
            ->join('services', 'services.id = appointments.id_services', 'left')
            ->join('users AS providers', 'providers.id = appointments.id_users_provider', 'inner')
            ->join('users AS customers', 'customers.id = appointments.id_users_customer', 'left')
            ->where('is_unavailability', false)
            ->group_start()
            ->like('appointments.start_datetime', $keyword)
            ->or_like('appointments.end_datetime', $keyword)
            ->or_like('appointments.location', $keyword)
            ->or_like('appointments.hash', $keyword)
            ->or_like('appointments.notes', $keyword)
            ->or_like('services.name', $keyword)
            ->or_like('services.description', $keyword)
            ->or_like('providers.first_name', $keyword)
            ->or_like('providers.last_name', $keyword)
            ->or_like('providers.email', $keyword)
            ->or_like('providers.phone_number', $keyword)
            ->or_like('customers.first_name', $keyword)
            ->or_like('customers.last_name', $keyword)
            ->or_like('customers.email', $keyword)
            ->or_like('customers.phone_number', $keyword)
            ->group_end()
            ->limit($limit)
            ->offset($offset)
            ->order_by($this->quote_order_by($order_by))
            ->get()
            ->result_array();

        foreach ($appointments as &$appointment) {
            $this->cast($appointment);
        }

        return $appointments;
    }

    /**
     * Get appointments as options for dropdowns.
     *
     * @param array|string|null $where Where conditions.
     *
     * @return array Returns an array of options with 'value' and 'label' keys.
     */
    public function to_options(array|string|null $where = null): array
    {
        if ($where !== null) {
            $this->db->where($where);
        }

        $appointments = $this->db
            ->select('appointments.id, appointments.start_datetime, services.name AS service_name')
            ->from('appointments')
            ->join('services', 'services.id = appointments.id_services', 'left')
            ->where('is_unavailability', false)
            ->order_by('start_datetime', 'DESC')
            ->get()
            ->result_array();

        $options = [];

        foreach ($appointments as $appointment) {
            $options[] = [
                'value' => (int) $appointment['id'],
                'label' => $appointment['start_datetime'] . ' - ' . ($appointment['service_name'] ?? 'N/A'),
            ];
        }

        return $options;
    }

    /**
     * Load related resources to an appointment.
     *
     * @param array $appointment Associative array with the appointment data.
     * @param array $resources Resource names to be attached ("service", "provider", "customer" supported).
     *
     * @throws InvalidArgumentException
     */
    public function load(array &$appointment, array $resources): void
    {
        if (empty($appointment) || empty($resources)) {
            return;
        }

        foreach ($resources as $resource) {
            switch ($resource) {
                case 'service':
                    $appointment['service'] = $this->db
                        ->get_where('services', [
                            'id' => $appointment['id_services'] ?? ($appointment['serviceId'] ?? null),
                        ])
                        ->row_array();
                    break;

                case 'provider':
                    $appointment['provider'] = $this->db
                        ->get_where('users', [
                            'id' => $appointment['id_users_provider'] ?? ($appointment['providerId'] ?? null),
                        ])
                        ->row_array();
                    break;

                case 'customer':
                    $appointment['customer'] = $this->db
                        ->get_where('users', [
                            'id' => $appointment['id_users_customer'] ?? ($appointment['customerId'] ?? null),
                        ])
                        ->row_array();
                    break;

                default:
                    throw new InvalidArgumentException(
                        'The requested appointment relation is not supported: ' . $resource,
                    );
            }
        }
    }

    /**
     * Convert the database appointment record to the equivalent API resource.
     *
     * @param array $appointment Appointment data.
     */
    public function api_encode(array &$appointment): void
    {
        $encoded_resource = [
            'id' => array_key_exists('id', $appointment) ? (int) $appointment['id'] : null,
            'book' => $appointment['book_datetime'],
            'start' => $appointment['start_datetime'],
            'end' => $appointment['end_datetime'],
            'hash' => $appointment['hash'],
            'color' => $appointment['color'],
            'status' => $appointment['status'],
            'callDateTime' => $appointment['call_datetime'] ?? null,
            'location' => $appointment['location'],
            'notes' => $appointment['notes'],
            'customerId' => $appointment['id_users_customer'] !== null ? (int) $appointment['id_users_customer'] : null,
            'providerId' => $appointment['id_users_provider'] !== null ? (int) $appointment['id_users_provider'] : null,
            'serviceId' => $appointment['id_services'] !== null ? (int) $appointment['id_services'] : null,
            'meetingLink' => $appointment['meeting_link'],
            'googleCalendarId' =>
                $appointment['id_google_calendar'] !== null ? $appointment['id_google_calendar'] : null,
            'caldavCalendarId' =>
                $appointment['id_caldav_calendar'] !== null ? $appointment['id_caldav_calendar'] : null,
        ];

        $appointment = $encoded_resource;
    }

    /**
     * Convert the API resource to the equivalent database appointment record.
     *
     * @param array $appointment API resource.
     * @param array|null $base Base appointment data to be overwritten with the provided values (useful for updates).
     */
    public function api_decode(array &$appointment, ?array $base = null): void
    {
        $decoded_resource = $base ?: [];

        if (array_key_exists('id', $appointment)) {
            $decoded_resource['id'] = $appointment['id'];
        }

        if (array_key_exists('book', $appointment)) {
            $decoded_resource['book_datetime'] = $appointment['book'];
        }

        if (array_key_exists('start', $appointment)) {
            $decoded_resource['start_datetime'] = $appointment['start'];
        }

        if (array_key_exists('end', $appointment)) {
            $decoded_resource['end_datetime'] = $appointment['end'];
        }

        if (array_key_exists('hash', $appointment)) {
            $decoded_resource['hash'] = $appointment['hash'];
        }

        if (array_key_exists('color', $appointment)) {
            $decoded_resource['color'] = $appointment['color'];
        }

        if (array_key_exists('location', $appointment)) {
            $decoded_resource['location'] = $appointment['location'];
        }

        if (array_key_exists('status', $appointment)) {
            $decoded_resource['status'] = $appointment['status'];
        }

        if (array_key_exists('callDateTime', $appointment)) {
            $decoded_resource['call_datetime'] = $appointment['callDateTime'];
        }

        if (array_key_exists('notes', $appointment)) {
            $decoded_resource['notes'] = $appointment['notes'];
        }

        if (array_key_exists('customerId', $appointment)) {
            $decoded_resource['id_users_customer'] = $appointment['customerId'];
        }

        if (array_key_exists('providerId', $appointment)) {
            $decoded_resource['id_users_provider'] = $appointment['providerId'];
        }

        if (array_key_exists('serviceId', $appointment)) {
            $decoded_resource['id_services'] = $appointment['serviceId'];
        }

        if (array_key_exists('googleCalendarId', $appointment)) {
            $decoded_resource['id_google_calendar'] = $appointment['googleCalendarId'];
        }

        if (array_key_exists('caldavCalendarId', $appointment)) {
            $decoded_resource['id_caldav_calendar'] = $appointment['caldavCalendarId'];
        }

        if (array_key_exists('meetingLink', $appointment)) {
            $decoded_resource['meeting_link'] = $appointment['meetingLink'];
        }

        $decoded_resource['is_unavailability'] = false;

        $appointment = $decoded_resource;
    }

    /**
     * Calculate the end date time of an appointment based on the selected service.
     *
     * @param array $appointment Appointment data.
     *
     * @return string Returns the end date time value.
     *
     * @throws Exception
     */
    public function calculate_end_datetime(array $appointment): string
    {
        $duration = $this->db->get_where('services', ['id' => $appointment['id_services']])?->row()?->duration;

        $end_date_time_object = new DateTime($appointment['start_datetime']);

        $end_date_time_object->add(new DateInterval('PT' . $duration . 'M'));

        return $end_date_time_object->format('Y-m-d H:i:s');
    }

    /**
     * Check if the provider has a conflicting appointment at the given time period.
     *
     * @param int $provider_id Provider ID.
     * @param string $start_datetime Start date time of the appointment.
     * @param string $end_datetime End date time of the appointment.
     * @param int|null $exclude_appointment_id Exclude an appointment from the conflict check (useful for updates).
     *
     * @return bool Returns true if there is a conflict, false otherwise.
     */
    public function has_provider_conflict(
        int $provider_id,
        string $start_datetime,
        string $end_datetime,
        ?int $exclude_appointment_id = null,
    ): bool {
        $this->db->select('id')->from('appointments')->where('id_users_provider', $provider_id);

        if ($exclude_appointment_id) {
            $this->db->where('id !=', $exclude_appointment_id);
        }

        // Check for overlapping appointments:
        // An overlap occurs when:  (existing_start < new_end) AND (existing_end > new_start)

        return $this->db
            ->group_start()
            ->where('start_datetime <', $end_datetime)
            ->where('end_datetime >', $start_datetime)
            ->group_end()
            ->get()
            ->num_rows() > 0;
    }
}
