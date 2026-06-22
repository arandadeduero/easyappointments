<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

class Migration_Add_call_datetime_to_appointments_table extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->field_exists('call_datetime', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'call_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'id_users_caller',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->field_exists('call_datetime', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'call_datetime');
        }
    }
}
