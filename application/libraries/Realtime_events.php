<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ----------------------------------------------------------------------------
 */

use GuzzleHttp\Client;

/**
 * Realtime events publisher.
 *
 * Publishes internal app events to a websocket gateway over HTTP.
 */
class Realtime_events
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Publish an event to the realtime gateway.
     */
    public function emit(string $event_name, array $data = []): void
    {
        $is_enabled = filter_var(config('realtime_ws_enabled', false), FILTER_VALIDATE_BOOLEAN);

        if (!$is_enabled) {
            return;
        }

        $publish_url = (string) config('realtime_ws_publish_url', '');

        if (empty($publish_url)) {
            return;
        }

        $secret = (string) config('realtime_ws_secret', '');

        try {
            $client = new Client([
                'timeout' => 1.0,
                'connect_timeout' => 0.5,
            ]);

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if (!empty($secret)) {
                $headers['X-Realtime-Secret'] = $secret;
            }

            $client->post($publish_url, [
                'headers' => $headers,
                'json' => [
                    'event' => $event_name,
                    'data' => $data,
                    'sent_at' => date('c'),
                ],
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Realtime Events - Failed to publish event: ' . $e->getMessage());
        }
    }
}
