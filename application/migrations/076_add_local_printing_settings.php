<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Migration_Add_local_printing_settings extends EA_Migration
{
    public function up(): void
    {
        $settings = [
            'local_printing_enabled' => '0',
            'local_printing_endpoint' => 'http://127.0.0.1:8787/print',
            'local_printing_token' => 'change-this-print-token',
        ];

        foreach ($settings as $name => $value) {
            if (!$this->db->get_where('settings', ['name' => $name])->num_rows()) {
                $this->db->insert('settings', [
                    'name' => $name,
                    'value' => $value,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['local_printing_enabled', 'local_printing_endpoint', 'local_printing_token'] as $name) {
            $this->db->delete('settings', ['name' => $name]);
        }
    }
}
