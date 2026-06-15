<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Migration_Add_disable_appointment_type_settings extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $disable_booking = $this->db->get_where('settings', ['name' => 'disable_booking'])->row_array();

        if (!$this->db->get_where('settings', ['name' => 'disable_booking_appointments'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'disable_booking_appointments',
                'value' => $disable_booking['value'] ?? '0',
            ]);
        }

        if (!$this->db->get_where('settings', ['name' => 'disable_walkin_appointments'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'disable_walkin_appointments',
                'value' => '0',
            ]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        foreach (['disable_booking_appointments', 'disable_walkin_appointments'] as $setting_name) {
            if ($this->db->get_where('settings', ['name' => $setting_name])->num_rows()) {
                $this->db->delete('settings', ['name' => $setting_name]);
            }
        }
    }
}
