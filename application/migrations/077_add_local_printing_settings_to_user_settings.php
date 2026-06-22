<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Migration_Add_local_printing_settings_to_user_settings extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->field_exists('local_printing_enabled', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'local_printing_enabled' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'null' => false,
                    'default' => 0,
                    'after' => 'attendants_number',
                ],
            ]);
        }

        if (!$this->db->field_exists('local_printing_endpoint', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'local_printing_endpoint' => [
                    'type' => 'VARCHAR',
                    'constraint' => 512,
                    'null' => false,
                    'default' => 'http://127.0.0.1:8787/print',
                    'after' => 'local_printing_enabled',
                ],
            ]);
        }

        if (!$this->db->field_exists('local_printing_token', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'local_printing_token' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => false,
                    'default' => '',
                    'after' => 'local_printing_endpoint',
                ],
            ]);
        }
    }

    public function down(): void
    {
        foreach (['local_printing_token', 'local_printing_endpoint', 'local_printing_enabled'] as $field) {
            if ($this->db->field_exists($field, 'user_settings')) {
                $this->dbforge->drop_column('user_settings', $field);
            }
        }
    }
}
