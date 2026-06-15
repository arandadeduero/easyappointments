<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.6.0
 * ---------------------------------------------------------------------------- */

class Migration_Add_walkin_appointment_type_and_working_plan extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('appointment_type', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'appointment_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 32,
                    'default' => APPOINTMENT_TYPE_BOOKING,
                    'after' => 'status',
                ],
            ]);
        }

        if (!$this->db->field_exists('queue_number', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'queue_number' => [
                    'type' => 'INT',
                    'null' => true,
                    'after' => 'appointment_type',
                ],
            ]);
        }

        if (!$this->db->field_exists('working_plan_walkin', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'working_plan_walkin' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'working_plan',
                ],
            ]);
        }

        if (!$this->db->field_exists('working_plan_walkin_exceptions', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'working_plan_walkin_exceptions' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'working_plan_walkin',
                ],
            ]);
        }

        // Use the existing online-booking plan as initial value for walk-in planning.
        if (
            $this->db->field_exists('working_plan', 'user_settings') &&
            $this->db->field_exists('working_plan_walkin', 'user_settings')
        ) {
            $this->db->query(
                'UPDATE ' .
                    $this->db->dbprefix('user_settings') .
                    " SET working_plan_walkin = working_plan WHERE working_plan_walkin IS NULL OR working_plan_walkin = ''",
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('working_plan_walkin_exceptions', 'user_settings')) {
            $this->dbforge->drop_column('user_settings', 'working_plan_walkin_exceptions');
        }

        if ($this->db->field_exists('working_plan_walkin', 'user_settings')) {
            $this->dbforge->drop_column('user_settings', 'working_plan_walkin');
        }

        if ($this->db->field_exists('queue_number', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'queue_number');
        }

        if ($this->db->field_exists('appointment_type', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'appointment_type');
        }
    }
}
