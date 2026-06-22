<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Migration_Create_kiosk_devices_tables extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->table_exists('kiosk_devices')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'id_users_provider' => [
                    'type' => 'INT',
                    'constraint' => 11,
                ],
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'token_hash' => [
                    'type' => 'CHAR',
                    'constraint' => 64,
                ],
                'created_datetime' => [
                    'type' => 'DATETIME',
                ],
                'last_used_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'last_ip_address' => [
                    'type' => 'VARCHAR',
                    'constraint' => 45,
                    'null' => true,
                ],
                'revoked_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('id_users_provider');
            $this->dbforge->add_key('token_hash');
            $this->dbforge->create_table('kiosk_devices', true, ['engine' => 'InnoDB']);
        }

        if (!$this->db->table_exists('kiosk_pairing_codes')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'id_users_provider' => [
                    'type' => 'INT',
                    'constraint' => 11,
                ],
                'device_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'code_hash' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'expires_datetime' => [
                    'type' => 'DATETIME',
                ],
                'attempts' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                ],
                'used_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_datetime' => [
                    'type' => 'DATETIME',
                ],
                'id_users_created_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('id_users_provider');
            $this->dbforge->add_key('expires_datetime');
            $this->dbforge->create_table('kiosk_pairing_codes', true, ['engine' => 'InnoDB']);
        }
    }

    public function down(): void
    {
        $this->dbforge->drop_table('kiosk_pairing_codes', true);
        $this->dbforge->drop_table('kiosk_devices', true);
    }
}
