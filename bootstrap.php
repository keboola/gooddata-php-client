<?php
define('ROOT_PATH', __DIR__);
ini_set('display_errors', true);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Prague');

defined('GOODDATA_USERNAME')
|| define('GOODDATA_USERNAME', getenv('GOODDATA_USERNAME') ? getenv('GOODDATA_USERNAME') : 'username');
defined('GOODDATA_PASSWORD')
|| define('GOODDATA_PASSWORD', getenv('GOODDATA_PASSWORD') ? getenv('GOODDATA_PASSWORD') : 'password');
defined('GOODDATA_URL')
|| define('GOODDATA_URL', getenv('GOODDATA_URL') ? getenv('GOODDATA_URL') : 'https://secure.gooddata.com');
defined('GOODDATA_DOMAIN')
|| define('GOODDATA_DOMAIN', getenv('GOODDATA_DOMAIN') ? getenv('GOODDATA_DOMAIN') : 'domain');
defined('GOODDATA_AUTH_TOKEN')
|| define('GOODDATA_AUTH_TOKEN', getenv('GOODDATA_AUTH_TOKEN') ? getenv('GOODDATA_AUTH_TOKEN') : 'auth_token');

require_once ROOT_PATH . '/vendor/autoload.php';