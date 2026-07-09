<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

/**
 * Availability library.
 *
 * Handles availability related functionality.
 *
 * @package Libraries
 */
class Availability
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Availability constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('settings_model');
        $this->CI->load->model('unavailabilities_model');
        $this->CI->load->model('blocked_periods_model');
        $this->CI->load->model('working_plan_exceptions_model');

        $this->CI->load->library('ics_file');
    }

    /**
     * Get the available hours of a provider.
     *
     * @param string $date Selected date (Y-m-d).
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param int|null $exclude_appointment_id Exclude an appointment from the availability generation.
     *
     * @return array
     *
     * @throws Exception
     */
    public function get_available_hours(
        string $date,
        array $service,
        array $provider,
        ?int $exclude_appointment_id = null,
        string $appointment_type = APPOINTMENT_TYPE_BOOKING,
    ): array {
        if ($this->CI->blocked_periods_model->is_entire_date_blocked($date)) {
            return [];
        }

        if (
            $appointment_type === APPOINTMENT_TYPE_BOOKING &&
            !filter_var(setting('allow_same_day_booking', '0'), FILTER_VALIDATE_BOOLEAN)
        ) {
            $provider_timezone = new DateTimeZone($provider['timezone']);
            $selected_date = new DateTime($date, $provider_timezone);
            $today = new DateTime('today', $provider_timezone);

            if ($selected_date <= $today) {
                return [];
            }
        }

        if (!empty($provider['settings']['attendants_number'])) {
            $available_hours = $this->consider_shared_provider_capacity(
                $date,
                $service,
                $provider,
                $exclude_appointment_id,
                $appointment_type,
            );
        } elseif ($service['attendants_number'] > 1) {
            $available_hours = $this->consider_multiple_attendants(
                $date,
                $service,
                $provider,
                $exclude_appointment_id,
                $appointment_type,
            );
        } else {
            $available_periods = $this->get_available_periods(
                $date,
                $provider,
                $exclude_appointment_id,
                $appointment_type,
            );

            $available_hours = $this->generate_available_hours($date, $service, $available_periods);
        }

        $available_hours = $this->consider_appointment_type_advance_margin(
            $date,
            $available_hours,
            $provider,
            $appointment_type,
        );

        return $this->consider_future_booking_limit($date, $available_hours, $provider);
    }

    /**
     * Get multiple attendants hours.
     *
     * This method will add the additional appointment hours whenever a service accepts multiple attendants.
     *
     * @param string $date Selected date (Y-m-d).
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param int|null $exclude_appointment_id Exclude an appointment from the availability generation.
     *
     * @return array Returns the available hours array.
     *
     * @throws Exception
     */
    protected function consider_multiple_attendants(
        string $date,
        array $service,
        array $provider,
        ?int $exclude_appointment_id = null,
        string $appointment_type = APPOINTMENT_TYPE_BOOKING,
    ): array {
        $unavailability_events = $this->CI->unavailabilities_model->get([
            'is_unavailability' => true,
            'DATE(start_datetime) <=' => $date,
            'DATE(end_datetime) >=' => $date,
            'id_users_provider' => $provider['id'],
        ]);

        $working_plan = $this->get_working_plan_by_type($provider, $appointment_type);

        $working_plan_exceptions = $this->get_working_plan_exceptions_by_type($provider, $appointment_type);

        $working_day = strtolower(date('l', strtotime($date)));

        $date_working_plan = $working_plan[$working_day] ?? null;

        // Search if the $date is a custom availability period added outside the normal working plan.
        if (array_key_exists($date, $working_plan_exceptions)) {
            $date_working_plan = $working_plan_exceptions[$date];
        }

        if (!$date_working_plan) {
            return [];
        }

        $periods = [
            [
                'start' => new DateTime($date . ' ' . $date_working_plan['start']),
                'end' => new DateTime($date . ' ' . $date_working_plan['end']),
            ],
        ];

        $blocked_periods = $this->CI->blocked_periods_model->get_for_period($date, $date);

        $periods = $this->remove_breaks($date, $periods, $date_working_plan['breaks']);
        $periods = $this->remove_unavailability_events($periods, $unavailability_events);
        $periods = $this->remove_unavailability_events($periods, $blocked_periods);

        $hours = [];

        $interval_value = !empty($service['slot_interval']) ? $service['slot_interval'] : 15;
        $interval = new DateInterval('PT' . (int) $interval_value . 'M');
        $duration = new DateInterval('PT' . (int) $service['duration'] . 'M');

        foreach ($periods as $period) {
            $slot_start = clone $period['start'];
            $slot_end = clone $slot_start;
            $slot_end->add($duration);

            while ($slot_end <= $period['end']) {
                // Make sure there is no other service appointment for this time slot.
                $other_service_attendants_number = $this->CI->appointments_model->get_other_service_attendants_number(
                    $slot_start,
                    $slot_end,
                    $service['id'],
                    $provider['id'],
                    $exclude_appointment_id,
                );

                if ($other_service_attendants_number > 0) {
                    $slot_start->add($interval);
                    $slot_end->add($interval);
                    continue;
                }

                // Check reserved attendants for this time slot and see if current attendants fit.
                $appointment_attendants_number = $this->CI->appointments_model->get_attendants_number_for_period(
                    $slot_start,
                    $slot_end,
                    $service['id'],
                    $provider['id'],
                    $exclude_appointment_id,
                );

                if ($appointment_attendants_number < $service['attendants_number']) {
                    $hours[] = $slot_start->format('H:i');
                }

                $slot_start->add($interval);
                $slot_end->add($interval);
            }
        }

        return $hours;
    }

    /**
     * Get hours that satisfy both the service limit and the shared department capacity.
     */
    protected function consider_shared_provider_capacity(
        string $date,
        array $service,
        array $provider,
        ?int $exclude_appointment_id = null,
        string $appointment_type = APPOINTMENT_TYPE_BOOKING,
    ): array {
        $unavailability_events = $this->CI->unavailabilities_model->get([
            'is_unavailability' => true,
            'DATE(start_datetime) <=' => $date,
            'DATE(end_datetime) >=' => $date,
            'id_users_provider' => $provider['id'],
        ]);

        $working_plan = $this->get_working_plan_by_type($provider, $appointment_type);
        $working_plan_exceptions = $this->get_working_plan_exceptions_by_type($provider, $appointment_type);
        $working_day = strtolower(date('l', strtotime($date)));
        $date_working_plan = $working_plan[$working_day] ?? null;

        if (array_key_exists($date, $working_plan_exceptions)) {
            $date_working_plan = $working_plan_exceptions[$date];
        }

        if (!$date_working_plan) {
            return [];
        }

        $periods = [
            [
                'start' => new DateTime($date . ' ' . $date_working_plan['start']),
                'end' => new DateTime($date . ' ' . $date_working_plan['end']),
            ],
        ];

        $blocked_periods = $this->CI->blocked_periods_model->get_for_period($date, $date);
        $periods = $this->remove_breaks($date, $periods, $date_working_plan['breaks'] ?? []);
        $periods = $this->remove_unavailability_events($periods, $unavailability_events);
        $periods = $this->remove_unavailability_events($periods, $blocked_periods);

        $hours = [];
        $interval_value = !empty($service['slot_interval']) ? $service['slot_interval'] : 15;
        $interval = new DateInterval('PT' . (int) $interval_value . 'M');
        $duration = new DateInterval('PT' . (int) $service['duration'] . 'M');

        foreach ($periods as $period) {
            $slot_start = clone $period['start'];
            $slot_end = (clone $slot_start)->add($duration);

            while ($slot_end <= $period['end']) {
                $service_usage = $this->CI->appointments_model->get_service_attendants_number_for_period(
                    $slot_start,
                    $slot_end,
                    (int) $service['id'],
                    (int) $provider['id'],
                    $exclude_appointment_id,
                );

                $provider_usage = $this->CI->appointments_model->get_provider_attendants_number_for_period(
                    $slot_start,
                    $slot_end,
                    (int) $provider['id'],
                    $exclude_appointment_id,
                );

                if (
                    $service_usage < (int) $service['attendants_number'] &&
                    $provider_usage < (int) $provider['settings']['attendants_number']
                ) {
                    $hours[] = $slot_start->format('H:i');
                }

                $slot_start->add($interval);
                $slot_end->add($interval);
            }
        }

        return $hours;
    }

    /**
     * Remove breaks from available time periods.
     *
     * @param string $date Selected date (Y-m-d).
     * @param array $periods Empty periods.
     * @param array $breaks Array of breaks.
     *
     * @return array Returns the available time periods without the breaks.
     *
     * @throws Exception
     */
    public function remove_breaks(string $date, array $periods, array $breaks): array
    {
        if (!$breaks) {
            return $periods;
        }

        foreach ($breaks as $break) {
            $break_start = new DateTime($date . ' ' . $break['start']);

            $break_end = new DateTime($date . ' ' . $break['end']);

            foreach ($periods as &$period) {
                $period_start = $period['start'];

                $period_end = $period['end'];

                if ($break_start <= $period_start && $break_end >= $period_start && $break_end <= $period_end) {
                    // left
                    $period['start'] = $break_end;
                    continue;
                }

                if (
                    $break_start >= $period_start &&
                    $break_start <= $period_end &&
                    $break_end >= $period_start &&
                    $break_end <= $period_end
                ) {
                    // middle
                    $period['end'] = $break_start;
                    $periods[] = [
                        'start' => $break_end,
                        'end' => $period_end,
                    ];
                    continue;
                }

                if ($break_start >= $period_start && $break_start <= $period_end && $break_end >= $period_end) {
                    // right
                    $period['end'] = $break_start;
                    continue;
                }

                if ($break_start <= $period_start && $break_end >= $period_end) {
                    // break contains period
                    $period['start'] = $break_end;
                }
            }
        }

        return $periods;
    }

    /**
     * Remove the unavailability entries from the available time periods of the selected date.
     *
     * @param array $periods Available time periods.
     * @param array $unavailability_events Unavailability events of the current date.
     *
     * @return array Returns the available time periods without the unavailability events.
     *
     * @throws Exception
     */
    public function remove_unavailability_events(array $periods, array $unavailability_events): array
    {
        foreach ($unavailability_events as $unavailability_event) {
            $unavailability_start = new DateTime($unavailability_event['start_datetime']);

            $unavailability_end = new DateTime($unavailability_event['end_datetime']);

            foreach ($periods as &$period) {
                $period_start = $period['start'];

                $period_end = $period['end'];

                if (
                    $unavailability_start <= $period_start &&
                    $unavailability_end >= $period_start &&
                    $unavailability_end <= $period_end
                ) {
                    // Left
                    $period['start'] = $unavailability_end;
                    continue;
                }

                if (
                    $unavailability_start >= $period_start &&
                    $unavailability_start <= $period_end &&
                    $unavailability_end >= $period_start &&
                    $unavailability_end <= $period_end
                ) {
                    // Middle
                    $period['end'] = $unavailability_start;
                    $periods[] = [
                        'start' => $unavailability_end,
                        'end' => $period_end,
                    ];
                    continue;
                }

                if (
                    $unavailability_start >= $period_start &&
                    $unavailability_start <= $period_end &&
                    $unavailability_end >= $period_end
                ) {
                    // Right
                    $period['end'] = $unavailability_start;
                    continue;
                }

                if ($unavailability_start <= $period_start && $unavailability_end >= $period_end) {
                    // Unavailability contains period
                    $period['start'] = $unavailability_end;
                }
            }
        }

        return $periods;
    }

    /**
     * Get an array containing the free time periods (start - end) of a selected date.
     *
     * This method is very important because there are many cases where the system needs to know when a provider is
     * available for an appointment. It will return an array that belongs to the selected date and contains values that
     * have the start and the end time of an available time period.
     *
     * @param string $date Selected date (Y-m-d).
     * @param array $provider Provider data.
     * @param int|null $exclude_appointment_id Exclude an appointment from the availability generation.
     *
     * @return array Returns an array with the available time periods of the provider.
     *
     * @throws Exception
     */
    public function get_available_periods(
        string $date,
        array $provider,
        ?int $exclude_appointment_id = null,
        string $appointment_type = APPOINTMENT_TYPE_BOOKING,
    ): array
    {
        // Get the service, provider's working plan and provider appointments.
        $working_plan = $this->get_working_plan_by_type($provider, $appointment_type);

        $working_plan_exceptions = $this->get_working_plan_exceptions_by_type($provider, $appointment_type);

        // Validate and sanitize inputs before building query
        $provider_id = (int) $provider['id'];

        // Validate date format to prevent SQL injection
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Invalid date format provided.');
        }

        // Build query using array-based where clause for safety
        $where = [
            'id_users_provider' => $provider_id,
            'DATE(start_datetime) <=' => $date,
            'DATE(end_datetime) >=' => $date,
        ];

        // Sometimes it might be necessary to exclude an appointment from the calculation (e.g. when editing an
        // existing appointment).
        if ($exclude_appointment_id) {
            $where['id !='] = (int) $exclude_appointment_id;
        }

        $appointments = array_values(
            array_merge(
                $this->CI->appointments_model->get($where),
                $this->CI->unavailabilities_model->get($where),
                $this->CI->blocked_periods_model->get_for_period($date, $date),
            ),
        );

        // Find the empty spaces on the plan. The first split between the plan is due to a break (if any). After that
        // every reserved appointment is considered to be a taken space in the plan.
        $working_day = strtolower(date('l', strtotime($date)));

        $date_working_plan = $working_plan[$working_day] ?? null;

        // Search if the $date is a custom availability period added outside the normal working plan.
        if (array_key_exists($date, $working_plan_exceptions)) {
            $date_working_plan = $working_plan_exceptions[$date];
        }

        if (!$date_working_plan) {
            return [];
        }

        $periods = [];

        if (isset($date_working_plan['breaks'])) {
            $periods[] = [
                'start' => $date_working_plan['start'],
                'end' => $date_working_plan['end'],
            ];

            $day_start = new DateTime($date_working_plan['start']);
            $day_end = new DateTime($date_working_plan['end']);

            // Split the working plan to available time periods that do not contain the breaks in them.
            foreach ($date_working_plan['breaks'] as $break) {
                $break_start = new DateTime($break['start']);
                $break_end = new DateTime($break['end']);

                if ($break_start < $day_start) {
                    $break_start = $day_start;
                }

                if ($break_end > $day_end) {
                    $break_end = $day_end;
                }

                if ($break_start >= $break_end) {
                    continue;
                }

                foreach ($periods as $key => $period) {
                    $period_start = new DateTime($period['start']);
                    $period_end = new DateTime($period['end']);

                    $remove_current_period = false;

                    if ($break_start > $period_start && $break_start < $period_end && $break_end > $period_start) {
                        $periods[] = [
                            'start' => $period_start->format('H:i'),
                            'end' => $break_start->format('H:i'),
                        ];

                        $remove_current_period = true;
                    }

                    if ($break_start < $period_end && $break_end > $period_start && $break_end < $period_end) {
                        $periods[] = [
                            'start' => $break_end->format('H:i'),
                            'end' => $period_end->format('H:i'),
                        ];

                        $remove_current_period = true;
                    }

                    if ($break_start == $period_start && $break_end == $period_end) {
                        $remove_current_period = true;
                    }

                    if ($remove_current_period) {
                        unset($periods[$key]);
                    }
                }
            }
        }

        // Break the empty periods with the reserved appointments.
        foreach ($appointments as $appointment) {
            foreach ($periods as $index => &$period) {
                $appointment_start = new DateTime($appointment['start_datetime']);
                $appointment_end = new DateTime($appointment['end_datetime']);

                if ($appointment_start >= $appointment_end) {
                    continue;
                }

                $period_start = new DateTime($date . ' ' . $period['start']);
                $period_end = new DateTime($date . ' ' . $period['end']);

                if (
                    $appointment_start <= $period_start &&
                    $appointment_end <= $period_end &&
                    $appointment_end <= $period_start
                ) {
                    // The appointment does not belong in this time period, so we  will not change anything.
                    continue;
                } else {
                    if (
                        $appointment_start <= $period_start &&
                        $appointment_end <= $period_end &&
                        $appointment_end >= $period_start
                    ) {
                        // The appointment starts before the period and finishes somewhere inside. We will need to break
                        // this period and leave the available part.
                        $period['start'] = $appointment_end->format('H:i');
                    } else {
                        if ($appointment_start >= $period_start && $appointment_end < $period_end) {
                            // The appointment is inside the time period, so we will split the period into two new
                            // others.
                            unset($periods[$index]);

                            $periods[] = [
                                'start' => $period_start->format('H:i'),
                                'end' => $appointment_start->format('H:i'),
                            ];

                            $periods[] = [
                                'start' => $appointment_end->format('H:i'),
                                'end' => $period_end->format('H:i'),
                            ];
                        } elseif ($appointment_start == $period_start && $appointment_end == $period_end) {
                            unset($periods[$index]); // The whole period is blocked so remove it from the available periods array.
                        } else {
                            if (
                                $appointment_start >= $period_start &&
                                $appointment_end >= $period_start &&
                                $appointment_start <= $period_end
                            ) {
                                // The appointment starts in the period and finishes out of it. We will need to remove
                                // the time that is taken from the appointment.
                                $period['end'] = $appointment_start->format('H:i');
                            } else {
                                if (
                                    $appointment_start >= $period_start &&
                                    $appointment_end >= $period_end &&
                                    $appointment_start >= $period_end
                                ) {
                                    // The appointment does not belong in the period so do not change anything.
                                    continue;
                                } else {
                                    if (
                                        $appointment_start <= $period_start &&
                                        $appointment_end >= $period_end &&
                                        $appointment_start <= $period_end
                                    ) {
                                        // The appointment is bigger than the period, so this period needs to be removed.
                                        unset($periods[$index]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return array_values($periods);
    }

    /**
     * Calculate the available appointment hours.
     *
     * Calculate the available appointment hours for the given date. The empty spaces are broken down to 15 min and if
     * the service fit in each quarter then a new available hour is added to the "$available_hours" array.
     *
     * @param string $date Selected date (Y-m-d).
     * @param array $service Service data.
     * @param array $empty_periods Empty periods array.
     *
     * @return array Returns an array with the available hours for the appointment.
     *
     * @throws Exception
     */
    protected function generate_available_hours(string $date, array $service, array $empty_periods): array
    {
        $available_hours = [];

        foreach ($empty_periods as $period) {
            $start_hour = new DateTime($date . ' ' . $period['start']);

            $end_hour = new DateTime($date . ' ' . $period['end']);

            $interval = !empty($service['slot_interval']) ? (int) $service['slot_interval'] : 15;

            $current_hour = $start_hour;

            $diff = $current_hour->diff($end_hour);

            while ($diff->h * 60 + $diff->i >= (int) $service['duration'] && $diff->invert === 0) {
                $available_hours[] = $current_hour->format('H:i');

                $current_hour->add(new DateInterval('PT' . $interval . 'M'));

                $diff = $current_hour->diff($end_hour);
            }
        }

        return $available_hours;
    }

    /**
     * Consider the appointment-type advance margin and remove available hours that have passed the threshold.
     *
     * If the selected date is today, remove past hours. In-person appointments can keep a short operational margin
     * so the next ticket is not assigned for the same minute it is requested.
     *
     * @param string $date The selected date.
     * @param array $available_hours Already generated available hours.
     * @param array $provider Provider information.
     *
     * @return array Returns the updated available hours.
     *
     * @throws Exception
     */
    protected function consider_appointment_type_advance_margin(
        string $date,
        array $available_hours,
        array $provider,
        string $appointment_type,
    ): array
    {
        $provider_timezone = new DateTimeZone($provider['timezone']);

        $advance_margin = $appointment_type === APPOINTMENT_TYPE_WALKIN ? setting('walkin_advance_margin', 0) : 0;
        $advance_margin = is_numeric($advance_margin) ? max(0, (int) $advance_margin) : 0;

        $threshold = new DateTime('now', $provider_timezone);

        $threshold->modify('+' . $advance_margin . ' minutes');

        foreach ($available_hours as $index => $value) {
            $available_hour = new DateTime($date . ' ' . $value, $provider_timezone);

            if ($available_hour->getTimestamp() <= $threshold->getTimestamp()) {
                unset($available_hours[$index]);
            }
        }

        $available_hours = array_values($available_hours);

        sort($available_hours, SORT_STRING);

        return array_values($available_hours);
    }

    /**
     * Remove times if succeed the future booking limit.
     *
     * @param string $selected_date
     * @param array $available_hours
     * @param array $provider
     *
     * @return array
     *
     * @throws Exception
     */
    protected function consider_future_booking_limit(
        string $selected_date,
        array $available_hours,
        array $provider,
    ): array {
        $provider_timezone = new DateTimeZone($provider['timezone']);

        $future_booking_limit = setting('future_booking_limit', 90); // in days
        $future_booking_limit = is_numeric($future_booking_limit) ? max(0, (int) $future_booking_limit) : 90;

        $threshold = new DateTime('now', $provider_timezone);

        $threshold->modify('+' . $future_booking_limit . ' days');

        $selected_date_time = new DateTime($selected_date);

        if ($threshold < $selected_date_time) {
            return [];
        }

        return $threshold > $selected_date_time ? $available_hours : [];
    }

    /**
     * Get provider working plan by appointment type.
     */
    protected function get_working_plan_by_type(array $provider, string $appointment_type): array
    {
        $settings = $provider['settings'] ?? [];

        if ($appointment_type === APPOINTMENT_TYPE_WALKIN) {
            $raw_working_plan = $settings['working_plan_walkin'] ?? ($settings['working_plan'] ?? '{}');
        } else {
            $raw_working_plan = $settings['working_plan'] ?? '{}';
        }

        $working_plan = json_decode($raw_working_plan, true);

        return is_array($working_plan) ? $working_plan : [];
    }

    /**
     * Get provider working plan exceptions by appointment type.
     */
    protected function get_working_plan_exceptions_by_type(array $provider, string $appointment_type): array
    {
        if ($appointment_type === APPOINTMENT_TYPE_WALKIN) {
            $settings = $provider['settings'] ?? [];
            $raw_exceptions = $settings['working_plan_walkin_exceptions'] ?? '[]';
            $exceptions = json_decode($raw_exceptions, true);

            if (!is_array($exceptions)) {
                return [];
            }

            $map = [];

            foreach ($exceptions as $exception) {
                if (empty($exception['startDate']) || empty($exception['endDate'])) {
                    continue;
                }

                $start_date = new DateTime($exception['startDate']);
                $end_date = new DateTime($exception['endDate']);

                while ($start_date <= $end_date) {
                    $has_time_window = !empty($exception['startTime']) && !empty($exception['endTime']);

                    $map[$start_date->format('Y-m-d')] = $has_time_window
                        ? [
                            'start' => $exception['startTime'],
                            'end' => $exception['endTime'],
                            'breaks' => $exception['breaks'] ?? [],
                        ]
                        : null;

                    $start_date->modify('+1 day');
                }
            }

            return $map;
        }

        return $this->CI->working_plan_exceptions_model->get_by_provider($provider['id']);
    }
}
