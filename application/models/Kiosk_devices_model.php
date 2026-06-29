<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

class Kiosk_devices_model extends EA_Model
{
    private const MAX_PAIRING_ATTEMPTS = 5;

    public function get_devices(): array
    {
        return $this->db
            ->select(
                'kiosk_devices.id, kiosk_devices.id_users_provider, kiosk_devices.name, ' .
                'kiosk_devices.created_datetime, kiosk_devices.last_used_datetime, ' .
                'kiosk_devices.last_ip_address, kiosk_devices.revoked_datetime, ' .
                'users.first_name, users.last_name',
            )
            ->from('kiosk_devices')
            ->join('users', 'users.id = kiosk_devices.id_users_provider')
            ->order_by('kiosk_devices.created_datetime', 'DESC')
            ->get()
            ->result_array();
    }

    public function create_pairing_code(int $provider_id, string $device_name, ?int $created_by): string
    {
        $this->db->delete('kiosk_pairing_codes', [
            'id_users_provider' => $provider_id,
            'used_datetime' => null,
        ]);

        $code = (string) random_int(100000, 999999);

        $this->db->insert('kiosk_pairing_codes', [
            'id_users_provider' => $provider_id,
            'device_name' => $device_name,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires_datetime' => date('Y-m-d H:i:s', time() + 300),
            'attempts' => 0,
            'created_datetime' => date('Y-m-d H:i:s'),
            'id_users_created_by' => $created_by,
        ]);

        return $code;
    }

    public function pair_device(string $code, string $ip_address, int $requested_provider_id): ?array
    {
        $now = date('Y-m-d H:i:s');
        $table = $this->db->dbprefix('kiosk_pairing_codes');

        $this->db->trans_begin();

        $pairing_codes = $this->db
            ->query(
                "SELECT * FROM {$table} " .
                    'WHERE used_datetime IS NULL AND expires_datetime >= ? ' .
                    'AND attempts < ? ORDER BY created_datetime DESC FOR UPDATE',
                [$now, self::MAX_PAIRING_ATTEMPTS],
            )
            ->result_array();

        foreach ($pairing_codes as $pairing_code) {
            if (!password_verify($code, $pairing_code['code_hash'])) {
                continue;
            }

            $token = bin2hex(random_bytes(32));
            $this->db->insert('kiosk_devices', [
                'id_users_provider' => (int) $pairing_code['id_users_provider'],
                'name' => $pairing_code['device_name'],
                'token_hash' => hash('sha256', $token),
                'created_datetime' => $now,
                'last_used_datetime' => $now,
                'last_ip_address' => $ip_address,
            ]);
            $this->db->update(
                'kiosk_pairing_codes',
                ['used_datetime' => $now],
                ['id' => $pairing_code['id']],
            );

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return null;
            }

            $this->db->trans_commit();
            return [
                'token' => $token,
                'provider_id' => (int) $pairing_code['id_users_provider'],
            ];
        }

        if (!empty($pairing_codes)) {
            $ids = array_column(
                array_filter(
                    $pairing_codes,
                    fn(array $pairing_code): bool =>
                        (int) $pairing_code['id_users_provider'] === $requested_provider_id,
                ),
                'id',
            );

            if (!empty($ids)) {
                $this->db
                    ->set('attempts', 'attempts + 1', false)
                    ->where_in('id', $ids)
                    ->update('kiosk_pairing_codes');
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        }

        return null;
    }

    public function authorize(string $token, int $provider_id, string $ip_address): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $device = $this->db
            ->get_where('kiosk_devices', [
                'token_hash' => hash('sha256', $token),
                'id_users_provider' => $provider_id,
                'revoked_datetime' => null,
            ])
            ->row_array();

        if (!$device) {
            return null;
        }

        $this->db->update(
            'kiosk_devices',
            [
                'last_used_datetime' => date('Y-m-d H:i:s'),
                'last_ip_address' => $ip_address,
            ],
            ['id' => $device['id']],
        );

        return $device;
    }

    public function authorize_by_ip(int $provider_id, string $ip_address): ?array
    {
        if ($ip_address === '') {
            return null;
        }

        $devices = $this->db
            ->get_where('kiosk_devices', [
                'id_users_provider' => $provider_id,
                'last_ip_address' => $ip_address,
                'revoked_datetime' => null,
            ])
            ->result_array();

        if (count($devices) !== 1) {
            return null;
        }

        $device = $devices[0];

        $this->db->update(
            'kiosk_devices',
            [
                'last_used_datetime' => date('Y-m-d H:i:s'),
                'last_ip_address' => $ip_address,
            ],
            ['id' => $device['id']],
        );

        return $device;
    }

    public function revoke(int $device_id): void
    {
        $this->db->update(
            'kiosk_devices',
            ['revoked_datetime' => date('Y-m-d H:i:s')],
            ['id' => $device_id, 'revoked_datetime' => null],
        );
    }
}
