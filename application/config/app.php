<?php defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| App Configuration
|--------------------------------------------------------------------------
|
| Declare some of the global config values of Easy!Appointments.
|
*/

$config['version'] = '1.6.0'; // This must be changed manually.

$config['url'] = Config::BASE_URL;

$config['debug'] = Config::DEBUG_MODE;

$config['cache_busting_token'] = 'TSJ97';

$config['realtime_ws_enabled'] = defined('Config::REALTIME_WS_ENABLED') ? Config::REALTIME_WS_ENABLED : false;
$config['realtime_ws_url'] = defined('Config::REALTIME_WS_URL') ? Config::REALTIME_WS_URL : '';
$config['realtime_ws_publish_url'] = defined('Config::REALTIME_WS_PUBLISH_URL') ? Config::REALTIME_WS_PUBLISH_URL : '';
$config['realtime_ws_secret'] = defined('Config::REALTIME_WS_SECRET') ? Config::REALTIME_WS_SECRET : '';

// External service safeguards. All values are expressed in seconds.
$config['external_connect_timeout'] = 3.0;
$config['external_google_timeout'] = 10.0;
$config['external_caldav_timeout'] = 15.0;
$config['external_webhook_timeout'] = 5.0;
$config['external_smtp_timeout'] = 15;
$config['external_slow_log_threshold'] = 2.0;
