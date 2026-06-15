<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

class Migration_Add_caller_user_column_to_appointments_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('id_users_caller', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'id_users_caller' => [
                    'type' => 'INT',
                    'null' => true,
                    'after' => 'queue_number',
                ],
            ]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('id_users_caller', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'id_users_caller');
        }
    }
}
