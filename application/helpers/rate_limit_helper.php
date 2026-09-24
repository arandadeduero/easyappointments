<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.1.0
 * ---------------------------------------------------------------------------- */

if (!function_exists('rate_limit')) {
    /**
     * Rate-limit the application requests.
     *
     * Example:
     *
     * rate_limit($CI->input->ip_address(), 20, 120, 'booking_register');
     *
     * @param string $ip Client IP address.
     * @param int $max_requests Number of allowed requests, defaults to 100.
     * @param int $duration In seconds, defaults to 2 minutes.
     * @param string $scope Independent limiter scope, normally the protected operation name.
     */
    function rate_limit(string $ip, int $max_requests = 100, int $duration = 120, string $scope = 'general'): void
    {
        /** @var EA_Controller $CI */
        $CI = &get_instance();

        $rate_limiting = $CI->config->item('rate_limiting');

        if (!$rate_limiting || is_cli()) {
            return;
        }

        if ($max_requests < 1 || $duration < 1) {
            throw new InvalidArgumentException('Rate limit values must be positive integers.');
        }

        $scope = preg_replace('/[^a-zA-Z0-9_-]/', '_', $scope) ?: 'general';
        $cache_path = APPPATH . '../storage/cache';
        $identifier = hash('sha256', $scope . "\0" . $ip);
        $file_path = $cache_path . '/rate_limit_' . $identifier;
        $is_limited = false;
        $retry_after = $duration;

        try {
            if (!is_dir($cache_path) && !@mkdir($cache_path, 0750, true) && !is_dir($cache_path)) {
                throw new RuntimeException('Unable to create the rate-limit storage directory.');
            }

            $handle = @fopen($file_path, 'c+b');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the rate-limit state file.');
            }

            @chmod($file_path, 0640);

            if (!flock($handle, LOCK_EX)) {
                fclose($handle);
                throw new RuntimeException('Unable to lock the rate-limit state file.');
            }

            try {
                rewind($handle);

                $contents = stream_get_contents($handle);
                $state = $contents !== false && $contents !== '' ? json_decode($contents, true) : null;
                $now = time();

                $has_valid_state =
                    is_array($state) &&
                    isset($state['requests'], $state['expires_at']) &&
                    is_int($state['requests']) &&
                    is_int($state['expires_at']) &&
                    $state['requests'] > 0;

                if (!$has_valid_state || $now >= $state['expires_at']) {
                    if (!$has_valid_state && $contents !== false && $contents !== '') {
                        log_message('error', 'Invalid rate limiter state was reset for scope "' . $scope . '".');
                    }

                    $state = [
                        'requests' => 1,
                        'expires_at' => $now + $duration,
                    ];
                    $must_write = true;
                } else {
                    // Once blocked, keep the counter capped to avoid unnecessary writes under abusive traffic.
                    $next_count =
                        $state['requests'] > $max_requests ? $state['requests'] : $state['requests'] + 1;
                    $must_write = $next_count !== $state['requests'];
                    $state['requests'] = $next_count;
                }

                $is_limited = $state['requests'] > $max_requests;
                $retry_after = max(1, $state['expires_at'] - $now);

                if ($must_write) {
                    $payload = json_encode($state, JSON_THROW_ON_ERROR);

                    rewind($handle);

                    if (!ftruncate($handle, 0)) {
                        throw new RuntimeException('Unable to truncate the rate-limit state file.');
                    }

                    $written = 0;
                    $payload_length = strlen($payload);

                    while ($written < $payload_length) {
                        $result = fwrite($handle, substr($payload, $written));

                        if ($result === false || $result === 0) {
                            throw new RuntimeException('Unable to write the rate-limit state file.');
                        }

                        $written += $result;
                    }

                    if (!fflush($handle)) {
                        throw new RuntimeException('Unable to flush the rate-limit state file.');
                    }
                }
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (Throwable $e) {
            log_message('error', 'Rate limiter storage failure for scope "' . $scope . '": ' . $e->getMessage());
            return;
        }

        if ($is_limited) {
            http_response_code(429);
            header('Content-Type: application/json; charset=UTF-8');
            header('Retry-After: ' . $retry_after);
            header('Cache-Control: no-store');

            echo json_encode([
                'success' => false,
                'code' => 'rate_limit_exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retry_after,
            ]);

            exit();
        }
    }
}
