<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

use GuzzleHttp\Client;

/**
 * Webhooks client library.
 *
 * Handles the webhook HTTP related functionality.
 *
 * @package Libraries
 */
class Webhooks_client
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Webhook client constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('settings_model');
        $this->CI->load->model('webhooks_model');
        $this->CI->load->library('realtime_events');
    }

    /**
     * Trigger the registered webhooks for the provided action.
     *
     * @param string $action Webhook action.
     * @param array $payload Payload data.
     *
     * @return void|null
     */
    public function trigger(string $action, array $payload)
    {
        // Realtime updates must not wait behind third-party webhook endpoints.
        $this->emit_realtime_event($action, $payload);

        $webhooks = $this->CI->webhooks_model->get();

        foreach ($webhooks as $webhook) {
            $actions = array_filter(array_map('trim', explode(',', (string) $webhook['actions'])));

            if (in_array($action, $actions, true)) {
                $this->call($webhook, $action, $payload);
            }
        }
    }

    /**
     * Publish calendar realtime event if action affects the calendar.
     */
    private function emit_realtime_event(string $action, array $payload): void
    {
        $event_name = null;
        $event_data = [];

        switch ($action) {
            case WEBHOOK_APPOINTMENT_SAVE:
                $event_name = 'calendar.appointment_saved';
                $event_data = [
                    'appointment_id' => $payload['id'] ?? null,
                    'provider_id' => $payload['id_users_provider'] ?? null,
                    'service_id' => $payload['id_services'] ?? null,
                    'status' => $payload['status'] ?? null,
                    'start_datetime' => $payload['start_datetime'] ?? null,
                    'end_datetime' => $payload['end_datetime'] ?? null,
                ];
                break;
            case WEBHOOK_APPOINTMENT_DELETE:
                $event_name = 'calendar.appointment_deleted';
                $event_data = [
                    'appointment_id' => $payload['id'] ?? null,
                    'provider_id' => $payload['id_users_provider'] ?? null,
                    'service_id' => $payload['id_services'] ?? null,
                    'start_datetime' => $payload['start_datetime'] ?? null,
                    'end_datetime' => $payload['end_datetime'] ?? null,
                ];
                break;
            case WEBHOOK_UNAVAILABILITY_SAVE:
                $event_name = 'calendar.unavailability_saved';
                $event_data = [
                    'unavailability_id' => $payload['id'] ?? null,
                    'provider_id' => $payload['id_users_provider'] ?? null,
                    'start_datetime' => $payload['start_datetime'] ?? null,
                    'end_datetime' => $payload['end_datetime'] ?? null,
                ];
                break;
            case WEBHOOK_UNAVAILABILITY_DELETE:
                $event_name = 'calendar.unavailability_deleted';
                $event_data = [
                    'unavailability_id' => $payload['id'] ?? null,
                    'provider_id' => $payload['id_users_provider'] ?? null,
                    'start_datetime' => $payload['start_datetime'] ?? null,
                    'end_datetime' => $payload['end_datetime'] ?? null,
                ];
                break;
            case WEBHOOK_BLOCKED_PERIOD_SAVE:
                $event_name = 'calendar.blocked_period_saved';
                $event_data = [
                    'blocked_period_id' => $payload['id'] ?? null,
                    'provider_id' => $payload['id_users_provider'] ?? null,
                    'start_datetime' => $payload['start_datetime'] ?? null,
                    'end_datetime' => $payload['end_datetime'] ?? null,
                ];
                break;
            case WEBHOOK_BLOCKED_PERIOD_DELETE:
                $event_name = 'calendar.blocked_period_deleted';
                $event_data = [
                    'blocked_period_id' => $payload['id'] ?? null,
                    'provider_id' => $payload['id_users_provider'] ?? null,
                    'start_datetime' => $payload['start_datetime'] ?? null,
                    'end_datetime' => $payload['end_datetime'] ?? null,
                ];
                break;
            default:
                return;
        }

        $this->CI->realtime_events->emit($event_name, $event_data);
    }

    /**
     * Call the provided webhook.
     *
     * @param array $webhook
     * @param string $action
     * @param array $payload
     */
    private function call(array $webhook, string $action, array $payload): void
    {
        try {
            $target_host = (string) parse_url($webhook['url'], PHP_URL_HOST);
            $client = new Client([
                'connect_timeout' => (float) config('external_connect_timeout', 3.0),
                'timeout' => (float) config('external_webhook_timeout', 5.0),
                'on_stats' => static function ($stats) use ($target_host): void {
                    log_external_call_duration(
                        'webhook',
                        $stats->getTransferTime(),
                        $stats->hasResponse() ? $stats->getResponse()->getStatusCode() : null,
                        $target_host,
                    );
                },
            ]);

            $headers = [];

            if (!empty($webhook['secret_header']) && !empty($webhook['secret_token'])) {
                $headers[$webhook['secret_header']] = $webhook['secret_token'];
            }

            release_session_lock();

            $client->post($webhook['url'], [
                'verify' => $webhook['is_ssl_verified'],
                'headers' => $headers,
                'json' => [
                    'action' => $action,
                    'payload' => $payload,
                ],
            ]);

            // echo $response->getBody()->getContents(); // Use this for quick debugging
        } catch (Throwable $e) {
            log_message(
                'error',
                'Webhooks Client - The webhook (' .
                    ($webhook['id'] ?? null) .
                    ') request received an unexpected exception: ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        }
    }
}
