<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Migration_Add_attendants_number_to_user_settings extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('attendants_number', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'attendants_number' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                    'default' => 1,
                    'after' => 'calendar_view',
                ],
            ]);
        }

        // Remove the temporary category capacity column from early development versions.
        if ($this->db->field_exists('attendants_number', 'service_categories')) {
            $this->dbforge->drop_column('service_categories', 'attendants_number');
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('attendants_number', 'user_settings')) {
            $this->dbforge->drop_column('user_settings', 'attendants_number');
        }
    }
}
