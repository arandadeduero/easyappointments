<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Migration_Add_documentation_requirements_to_services_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('documentation_requirements', 'services')) {
            $this->dbforge->add_column('services', [
                'documentation_requirements' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'description',
                ],
            ]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('documentation_requirements', 'services')) {
            $this->dbforge->drop_column('services', 'documentation_requirements');
        }
    }
}
