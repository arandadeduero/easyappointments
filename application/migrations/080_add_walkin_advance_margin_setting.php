<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Migration_Add_walkin_advance_margin_setting extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'walkin_advance_margin'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'walkin_advance_margin',
                'value' => '0',
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'walkin_advance_margin']);
    }
}
