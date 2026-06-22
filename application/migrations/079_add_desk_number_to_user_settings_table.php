<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

class Migration_Add_desk_number_to_user_settings_table extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->field_exists('desk_number', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'desk_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => '32',
                    'null' => true,
                    'after' => 'calendar_view',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->field_exists('desk_number', 'user_settings')) {
            $this->dbforge->drop_column('user_settings', 'desk_number');
        }
    }
}
