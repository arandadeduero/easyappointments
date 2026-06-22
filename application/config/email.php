<?php defined('BASEPATH') or exit('No direct script access allowed');

// Add custom values by settings them to the $config array.
// Example: $config['smtp_host'] = 'smtp.gmail.com';
// @link https://codeigniter.com/user_guide/libraries/email.html

$config['useragent'] = 'Ayuntamiento de Aranda de Duero';
$config['protocol'] = 'smtp';
$config['mailtype'] = 'html';

$config['smtp_auth'] = true;
$config['smtp_host'] = 'smtp.mail.ovh.net';
$config['smtp_user'] = 'noreply@sanchainformatica.com';
$config['smtp_pass'] = '';
$config['smtp_crypto'] = 'tls';
$config['smtp_port'] = 587;

$config['from_name'] = 'Ayuntamiento de Aranda de Duero';
$config['from_address'] = 'noreply@sanchainformatica.com';
$config['reply_to'] = 'noreply@sanchainformatica.com';
$config['crlf'] = "\r\n";
$config['newline'] = "\r\n";
