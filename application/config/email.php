<?php defined('BASEPATH') or exit('No direct script access allowed');

// Add custom values by settings them to the $config array.
// Example: $config['smtp_host'] = 'smtp.gmail.com';
// @link https://codeigniter.com/user_guide/libraries/email.html

$config['useragent'] = 'Ayuntamiento de Aranda de Duero';
$config['protocol'] = 'smtp';
$config['mailtype'] = 'html';

$config['smtp_auth'] = true;
$config['smtp_host'] = 'mail.viavox.com';
$config['smtp_user'] = 'citaprevia@arandadeduero.es';
$config['smtp_pass'] = '';
$config['smtp_crypto'] = 'tls';
$config['smtp_port'] = 587;

$config['from_name'] = 'Ayuntamiento de Aranda de Duero';
$config['from_address'] = 'citaprevia@arandadeduero.es';
$config['reply_to'] = 'citaprevia@arandadeduero.es';
$config['crlf'] = "\r\n";
$config['newline'] = "\r\n";
