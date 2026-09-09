<?php

if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir().DIRECTORY_SEPARATOR);
}

define('HOUR_IN_SECONDS', 3600);
define('DB_NAME', 'wordpress_test');

$GLOBALS['wizjo_test_state'] = [
    'database_ok' => true,
    'cron_late_hours' => 0,
    'filesystem_mode' => null,
    'token' => 'test-token',
];

class WP_REST_Response
{
    public $data;
    public $status;

    public function __construct($data, $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }
}

class WP_Error
{
    public $code;
    public $message;
    public $data;

    public function __construct($code, $message, $data = [])
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
}

class WP_REST_Request
{
    private $headers;
    private $params;

    public function __construct($headers = [], $params = [])
    {
        $this->headers = $headers;
        $this->params = $params;
    }

    public function get_header($name)
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function get_param($name)
    {
        return $this->params[$name] ?? null;
    }
}

class WizjoTestWpdb
{
    public function get_var($query)
    {
        if (strpos((string) $query, 'information_schema') !== false) {
            return 12.5;
        }

        return $GLOBALS['wizjo_test_state']['database_ok'] ? '1' : null;
    }

    public function prepare($query, ...$args)
    {
        return $query;
    }
}

class WP_Filesystem_Direct
{
    public function __construct($args = '') {}
    public function is_writable($path) { return is_writable($path); }
    public function put_contents($file, $contents, $mode = false)
    {
        $GLOBALS['wizjo_test_state']['filesystem_mode'] = $mode;

        return file_put_contents($file, $contents) !== false;
    }
    public function get_contents($file) { return file_get_contents($file); }
    public function exists($file) { return file_exists($file); }
    public function delete($file) { return ! file_exists($file) || unlink($file); }
}

$GLOBALS['wpdb'] = new WizjoTestWpdb;

function add_action(...$args) {}
function add_options_page(...$args) {}
function register_setting(...$args) {}
function register_rest_route(...$args) {}
function get_option($name, $default = false) { return $GLOBALS['wizjo_test_state']['token'] ?? $default; }
function get_bloginfo($what) { return $what === 'name' ? 'Testowa witryna' : '7.1'; }
function home_url() { return 'https://example.test'; }
function wp_get_environment_type() { return 'production'; }
function wp_upload_dir() { return ['basedir' => sys_get_temp_dir(), 'error' => false]; }
function trailingslashit($path) { return rtrim($path, '/\\').DIRECTORY_SEPARATOR; }
function wp_generate_password($length = 12) { return str_repeat('a', $length); }
function size_format($bytes, $decimals = 0) { return round($bytes / 1024 / 1024 / 1024, $decimals).' GB'; }
function _get_cron_array()
{
    $late = (int) $GLOBALS['wizjo_test_state']['cron_late_hours'];

    return [time() - $late * HOUR_IN_SECONDS => ['wizjo_test_hook' => []]];
}
function get_plugin_updates() { return []; }
function get_theme_updates() { return []; }
function get_core_updates() { return [(object) ['response' => 'latest']]; }
function wp_count_posts() { return (object) ['publish' => 42]; }
function count_users() { return ['total_users' => 7]; }
function wp_list_pluck($list, $field) { return array_column($list, $field); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return $value; }

require_once dirname(__DIR__).'/wizjo-monitor.php';

function wizjo_assert($condition, $message)
{
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$plugin_source = file_get_contents(dirname(__DIR__).'/wizjo-monitor.php');
$readme_source = file_get_contents(dirname(__DIR__).'/readme.txt');

wizjo_assert(
    strpos($plugin_source, 'Tested up to:') === false,
    'Nagłówek PHP nie deklaruje pola Tested up to.'
);
wizjo_assert(
    preg_match('/^Contributors:\s*studiowizjo\s*$/mi', $readme_source) === 1,
    'Readme wskazuje właściciela zgłoszenia jako autora.'
);

$report = wizjo_monitor_report()->data;
wizjo_assert($report['app']['agent'] === '1.1.3', 'Raport zawiera wersję 1.1.3.');
wizjo_assert($report['wizjo'] === 1, 'Kontrakt raportu pozostaje zgodny.');
wizjo_assert(in_array($report['status'], ['ok', 'warning'], true), 'Zapisywalny katalog nie zgłasza awarii.');
wizjo_assert(
    $GLOBALS['wizjo_test_state']['filesystem_mode'] === 0644,
    'Test zapisu używa bezpiecznych uprawnień, gdy FS_CHMOD_FILE nie jest zdefiniowane.'
);

$GLOBALS['wizjo_test_state']['cron_late_hours'] = 4;
$report = wizjo_monitor_report()->data;
$cron = array_values(array_filter($report['checks'], fn ($check) => $check['key'] === 'cron'))[0];
wizjo_assert($cron['status'] === 'failing', 'Czterogodzinna zaległość crona zgłasza awarię.');
wizjo_assert(strpos($cron['message'], 'wizjo_test_hook') !== false, 'Raport podaje nazwę zaległego hooka.');
wizjo_assert($report['metrics']['cron_oldest_hook'] === 'wizjo_test_hook', 'Metryki podają nazwę zaległego hooka.');

$authorised = wizjo_monitor_authorised(new WP_REST_Request(['x-wizjo-token' => 'test-token']));
wizjo_assert($authorised === true, 'Poprawny nagłówek autoryzuje żądanie.');

$queryOnly = wizjo_monitor_authorised(new WP_REST_Request([], ['token' => 'test-token']));
wizjo_assert($queryOnly instanceof WP_Error && $queryOnly->data['status'] === 403, 'Token w adresie nie jest akceptowany.');

echo "OK\n";
