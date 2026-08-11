<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Migration_Change_book_advance_timeout_to_days extends EA_Migration
{
    public function up(): void
    {
        if ($this->db->get_where('settings', ['name' => 'book_advance_timeout'])->num_rows()) {
            // The previous value was expressed in minutes. Use one calendar day
            // as the safe equivalent for the new calendar-day rule.
            $this->db->update('settings', ['value' => '1'], ['name' => 'book_advance_timeout']);
        }
    }

    public function down(): void
    {
        if ($this->db->get_where('settings', ['name' => 'book_advance_timeout'])->num_rows()) {
            $this->db->update('settings', ['value' => '30'], ['name' => 'book_advance_timeout']);
        }
    }
}
