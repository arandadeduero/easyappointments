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

$config['cache_busting_token'] = 'TSJ87';

$config['realtime_ws_enabled'] = defined('Config::REALTIME_WS_ENABLED') ? Config::REALTIME_WS_ENABLED : false;
$config['realtime_ws_url'] = defined('Config::REALTIME_WS_URL') ? Config::REALTIME_WS_URL : '';
$config['realtime_ws_publish_url'] = defined('Config::REALTIME_WS_PUBLISH_URL') ? Config::REALTIME_WS_PUBLISH_URL : '';
$config['realtime_ws_secret'] = defined('Config::REALTIME_WS_SECRET') ? Config::REALTIME_WS_SECRET : '';
