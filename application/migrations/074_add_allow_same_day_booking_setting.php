<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Migration_Add_allow_same_day_booking_setting extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'allow_same_day_booking'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'allow_same_day_booking',
                'value' => '0',
            ]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->get_where('settings', ['name' => 'allow_same_day_booking'])->num_rows()) {
            $this->db->delete('settings', ['name' => 'allow_same_day_booking']);
        }
    }
}
