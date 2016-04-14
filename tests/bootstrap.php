<?php
define('ROOT_PATH', __DIR__);
ini_set('display_errors', true);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Prague');

set_error_handler('exceptions_error_handler');
function exceptions_error_handler($severity, $message, $filename, $lineno)
{
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}

defined('KBGDC_APP_NAME')
|| define('KBGDC_APP_NAME', getenv('KBGDC_APP_NAME') ? getenv('KBGDC_APP_NAME') : 'gooddata-php-client');

defined('KBGDC_USERNAME')
|| define('KBGDC_USERNAME', getenv('KBGDC_USERNAME') ? getenv('KBGDC_USERNAME') : 'username');

defined('KBGDC_PASSWORD')
|| define('KBGDC_PASSWORD', getenv('KBGDC_PASSWORD') ? getenv('KBGDC_PASSWORD') : 'password');

defined('KBGDC_API_URL')
|| define('KBGDC_API_URL', getenv('KBGDC_API_URL') ? getenv('KBGDC_API_URL') : 'https://secure.gooddata.com');

defined('KBGDC_DOMAIN')
|| define('KBGDC_DOMAIN', getenv('KBGDC_DOMAIN') ? getenv('KBGDC_DOMAIN') : 'domain');

defined('KBGDC_AUTH_TOKEN')
|| define('KBGDC_AUTH_TOKEN', getenv('KBGDC_AUTH_TOKEN') ? getenv('KBGDC_AUTH_TOKEN') : 'auth_token');

defined('KBGDC_PAPERTRAIL_PORT')
|| define('KBGDC_PAPERTRAIL_PORT', getenv('KBGDC_PAPERTRAIL_PORT') ? getenv('KBGDC_PAPERTRAIL_PORT') : null);

defined('KBGDC_USERS_DOMAIN')
|| define('KBGDC_USERS_DOMAIN', getenv('KBGDC_USERS_DOMAIN') ? getenv('KBGDC_USERS_DOMAIN') : 'gooddata.test.com');

defined('KBGDC_SSO_PROVIDER')
|| define('KBGDC_SSO_PROVIDER', getenv('KBGDC_SSO_PROVIDER') ? getenv('KBGDC_SSO_PROVIDER') : null);

defined('KBGDC_SSO_KEY')
|| define('KBGDC_SSO_KEY', getenv('KBGDC_SSO_KEY') ? base64_decode(getenv('KBGDC_SSO_KEY')) : null);

defined('KBGDC_OTHER_USERS_DOMAIN')
|| define('KBGDC_OTHER_USERS_DOMAIN', getenv('KBGDC_OTHER_USERS_DOMAIN')
    ? getenv('KBGDC_OTHER_USERS_DOMAIN') : 'gooddata');


define('KBGDC_PROJECTS_PREFIX', '[test-'.uniqid('', true).'] ');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Helper.php';
