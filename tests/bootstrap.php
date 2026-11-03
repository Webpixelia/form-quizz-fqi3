<?php
/**
 * Minimal WordPress stubs so the plugin's pure logic can be unit tested without WordPress.
 * For behavior that needs a real WordPress (AJAX, REST, database), see tests/integration/.
 */
declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/');
define('FQI3_VERSION', '0.0.0-test');
define('FQI3_ASSETS', 'http://example.test/assets');
define('FQI3_TABLE_PERFORMANCE', 'fqi3_performance');
define('OBJECT', 'OBJECT');

/** In-memory state shared by the stubs (reset it in setUp). */
final class WpState {
    public static array $options = [];
    public static array $userMeta = [];
    public static array $filters = [];
    public static bool $loggedIn = false;
    public static int $userId = 0;
    public static string $today = '2026-11-03';
    public static bool $singular = true;
    public static string $postContent = '';
    public static array $server = [];

    public static function reset(): void {
        self::$options = [];
        self::$userMeta = [];
        self::$filters = [];
        self::$loggedIn = false;
        self::$userId = 0;
        self::$today = '2026-11-03';
        self::$singular = true;
        self::$postContent = '';
    }
}

class WP_Post { public string $post_content = ''; }

class FakeWpdb {
    public string $prefix = 'wp_';
    public array $queries = [];
    public bool $failNextInsert = false;
    public int $insert_id = 5;
    public function get_charset_collate(): string { return ''; }
    public function query($sql) { $this->queries[] = $sql; return true; }
    /** Records the prepared statement and its arguments instead of running it. */
    public array $prepared = [];
    public function prepare($sql, ...$args) { $this->prepared[] = ['sql' => $sql, 'args' => $args]; return $sql; }
    public function get_results($sql, $output = OBJECT) { return []; }
    public function insert() {
        if ($this->failNextInsert) { $this->failNextInsert = false; return false; }
        return 1;
    }
}
$GLOBALS['wpdb'] = new FakeWpdb();

function __($s, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function add_action(...$a) {}
function add_shortcode(...$a) {}
function add_filter($hook, $cb, $priority = 10, $args = 1) { WpState::$filters[$hook][] = $cb; return true; }
function apply_filters($hook, $value, ...$args) {
    foreach (WpState::$filters[$hook] ?? [] as $cb) { $value = $cb($value, ...$args); }
    return $value;
}
function is_user_logged_in() { return WpState::$loggedIn; }
function get_current_user_id() { return WpState::$userId; }
function current_time($type) { return $type === 'Y-m-d' ? WpState::$today : WpState::$today . ' 00:00:00'; }
function get_user_meta($u, $k, $single = false) { return WpState::$userMeta[$u][$k] ?? ''; }
function update_user_meta($u, $k, $v) { WpState::$userMeta[$u][$k] = $v; return true; }
function get_option($k, $d = false) { return WpState::$options[$k] ?? $d; }
function update_option($k, $v) { WpState::$options[$k] = $v; return true; }
function delete_option($k) { unset(WpState::$options[$k]); return true; }
function is_singular() { return WpState::$singular; }
function get_post() { $p = new WP_Post(); $p->post_content = WpState::$postContent; return $p; }
function has_shortcode($c, $t) { return (bool) preg_match('/\[' . preg_quote($t, '/') . '[\s\]\/]/', $c); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_textarea_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_key($k) { return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $k)); }
function absint($v) { return abs((int) $v); }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function wp_unslash($v) { return $v; }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function wp_salt($scheme = 'auth') { return 'unit-test-salt-' . $scheme; }
function wp_send_json_success($d = null) { throw new RuntimeException('json_success'); }
function check_ajax_referer(...$a) { return true; }
function is_ssl() { return true; }
function is_admin() { return false; }

require_once dirname(__DIR__) . '/includes/fqi3-config-functions.php';

/**
 * Load a plugin file that instantiates itself at the end (singleton) without running that line.
 */
function fqi3_test_load(string $relativePath, array $replace = []): void {
    static $loaded = [];
    if (isset($loaded[$relativePath])) { return; }
    $src = file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    $src = preg_replace('/^\s*(FQI3_[A-Za-z_]+|FormQuizzFQI3)::getInstance\(\);\s*$/m', '', $src);
    foreach ($replace as $from => $to) { $src = str_replace($from, $to, $src); }
    $tmp = tempnam(sys_get_temp_dir(), 'fqi3_') . '.php';
    file_put_contents($tmp, $src);
    require $tmp;
    $loaded[$relativePath] = true;
}

/** Call a private/protected method. */
function fqi3_call($object, string $method, ...$args) {
    $m = (new ReflectionClass($object))->getMethod($method);
    $m->setAccessible(true);
    return $m->invoke($object, ...$args);
}

function fqi3_instance(string $class) {
    return (new ReflectionClass($class))->newInstanceWithoutConstructor();
}
