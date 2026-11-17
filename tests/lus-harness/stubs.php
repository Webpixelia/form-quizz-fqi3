<?php
// Stubs WordPress + faux serveur LUS (réplique du contrat : WpCheckController, WordPressResponseFormatter,
// DownloadTokenService, TrackLicenseFailures). Horloge simulée : Env::$now.
define('ABSPATH', '/tmp/wp/'); define('WP_PLUGIN_DIR', '/tmp/wp/plugins');
define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600); define('DAY_IN_SECONDS', 86400);
define('FQI3_VERSION', '3.6.1');
$GLOBALS['wp_version'] = '6.8';

final class Env {
    public static int $now = 1_000_000;
    public static array $transients = [], $options = [], $siteOptions = [], $filters = [], $actions = [];
    public static bool $multisite = false;
    public static string $home = 'https://www.client.example.com/blog', $netHome = 'https://network.example.com';
    public static string $envType = 'production';
    public static function reset(): void {
        self::$transients = []; self::$options = []; self::$siteOptions = []; self::$multisite = false;
        self::$home = 'https://www.client.example.com/blog'; self::$now = 1_000_000;
        FakeLus::reset();
    }
}
class WP_Error {
    public function __construct(public $code = '', public $message = '') {}
    public function get_error_message() { return $this->message; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }
function __($s, $d = null) { return $s; }
function add_filter($h, $cb, $p = 10, $a = 1) { Env::$filters[$h][] = $cb; return true; }
function add_action($h, $cb, $p = 10, $a = 1) { Env::$actions[$h][] = $cb; return true; }
function apply_filters($h, $v, ...$args) { foreach (Env::$filters[$h] ?? [] as $cb) { if (is_callable($cb) && !is_array($cb)) { $v = $cb($v, ...$args); } } return $v; }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function is_multisite() { return Env::$multisite; }
function home_url() { return Env::$home; }
function network_home_url() { return Env::$netHome; }
function wp_get_environment_type() { return Env::$envType; }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function get_option($k, $d = false) { return Env::$options[$k] ?? $d; }
function update_option($k, $v, $a = null) { Env::$options[$k] = $v; return true; }
function get_site_option($k, $d = false) { return Env::$siteOptions[$k] ?? $d; }
function update_site_option($k, $v) { Env::$siteOptions[$k] = $v; return true; }
function get_transient($k) { $t = Env::$transients[$k] ?? null; return ($t && $t['exp'] > Env::$now) ? $t['v'] : false; }
function set_transient($k, $v, $ttl = 0) { Env::$transients[$k] = ['v' => $v, 'exp' => Env::$now + ($ttl ?: 10**9)]; return true; }
function delete_transient($k) { unset(Env::$transients[$k]); return true; }
function wp_tempnam($f = '') { return tempnam(sys_get_temp_dir(), 'fqi3t'); }
function wp_remote_retrieve_response_code($r) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; }
function wp_remote_retrieve_header($r, $h) { return $r['headers'][strtolower($h)] ?? ''; }
function wp_remote_post($u, $a = []) { return FakeLus::handle('POST', $u, $a); }
function wp_remote_get($u, $a = []) { return FakeLus::handle('GET', $u, $a); }
function wp_safe_remote_get($u, $a = []) { return FakeLus::handle('GET', $u, $a); }
function get_plugin_data($f) { return ['Name' => 'X', 'Version' => $GLOBALS['installed'][basename(dirname($f)) ] ?? '0', 'RequiresWP' => '6.8', 'RequiresPHP' => '8.0']; }
function wpautop($s) { return $s; }
function esc_url_raw($u) { return $u; }
function __return_true() { return true; }
function _n($single, $plural, $n, $d = null) { return 1 === (int) $n ? $single : $plural; }
function mysql2date($format, $date) { return substr((string) $date, 0, 10); }
function human_time_diff($from, $to = 0) { return max(1, (int) round(((int) ($to ?: time()) - (int) $from) / 60)) . ' mins'; }

final class FakeLus {
    public static array $licenses, $activations, $latest, $tokens, $log;
    public static int $failures = 0;
    public static string $zip = '';
    public static bool $corrupt = false, $down = false, $emptySha = false, $badHost = false, $rate429NoBody = false;
    public static int $retryAfter = 120;
    public static function reset(): void {
        self::$licenses = [
            'LUS-GOOD'    => ['status' => 'active', 'max' => 3, 'products' => ['form-quizz-fqi3', 'form-quizz-fqi3-subscription-addon']],
            'LUS-GOOD2'   => ['status' => 'active', 'max' => 3, 'products' => ['form-quizz-fqi3']],
            'LUS-REVOKED' => ['status' => 'revoked', 'max' => 3, 'products' => ['form-quizz-fqi3']],
        ];
        self::$activations = ['LUS-GOOD' => ['client.example.com', 'network.example.com'], 'LUS-GOOD2' => [], 'LUS-REVOKED' => ['client.example.com']];
        self::$latest = ['form-quizz-fqi3' => '3.7.0', 'form-quizz-fqi3-subscription-addon' => '1.4.0', 'form-quizz-fqi3-social-login-addon' => '2.0.0'];
        self::$tokens = []; self::$log = []; self::$failures = 0;
        self::$zip = str_repeat('PK-fake-zip-', 40);
        self::$corrupt = self::$down = self::$emptySha = self::$badHost = self::$rate429NoBody = false;
        self::$retryAfter = 120;
    }
    public static function calls(string $path = ''): int {
        return count(array_filter(self::$log, fn($l) => $path === '' || $l === $path));
    }
    private static function res(int $code, array $body, array $headers = []): array {
        return ['response' => ['code' => $code], 'body' => json_encode($body), 'headers' => $headers];
    }
    private static function err(string $code, int $http): array {
        if (in_array($code, ['license_invalid', 'license_expired'], true)) { self::$failures++; }
        return self::res($http, ['error' => ['code' => $code, 'message' => $code]]);
    }
    public static function handle(string $method, string $url, array $args): array|WP_Error {
        $p = parse_url($url);
        $path = $p['path'];
        self::$log[] = $path;
        if (self::$down) { return new WP_Error('http_request_failed', 'cURL error 28: timeout'); }
        // 429 sans corps d'erreur LUS (proxy, throttle générique), avec Retry-After
        if (self::$rate429NoBody && in_array($path, ['/v1/wp/check', '/v1/licenses/activate', '/v1/licenses/validate'], true)) {
            return ['response' => ['code' => 429], 'body' => 'Too Many Attempts.', 'headers' => ['retry-after' => (string) self::$retryAfter]];
        }
        // TrackLicenseFailures (10 échecs/h/IP)
        if (in_array($path, ['/v1/wp/check', '/v1/licenses/activate', '/v1/licenses/validate'], true) && self::$failures >= 10) {
            return self::res(429, ['error' => ['code' => 'rate_limited']], ['retry-after' => '3600']);
        }
        if (str_starts_with($path, '/v1/download/')) { return self::download(substr($path, 13), $args); }
        $body = json_decode($args['body'] ?? '[]', true) ?: [];
        $norm = fn($h) => preg_replace('/^www\./', '', strtolower($h));

        if ($path === '/v1/wp/check') {
            // WpCheckRequest
            $ok = is_string($body['license_key'] ?? null) && is_array($body['plugins'] ?? null) && $body['plugins'] !== []
                && is_array($body['products'] ?? null) && count($body['products']) >= 1 && count($body['products']) <= 20;
            foreach ($body['products'] ?? [] as $pr) {
                if (!preg_match('/^v?\d+(\.\d+){0,3}(-[0-9A-Za-z.\-]+)?(\+[0-9A-Za-z.\-]+)?$/', (string) ($pr['version'] ?? ''))) { $ok = false; }
            }
            foreach ($body['plugins'] ?? [] as $v) { if (!is_string($v)) { $ok = false; } }
            if (!$ok) { return self::err('validation_failed', 422); }
            $lic = self::$licenses[$body['license_key']] ?? null;
            if (!$lic || $lic['status'] !== 'active') { return self::err('license_invalid', 403); }
            if (!empty($lic['expired'])) { return self::err('license_expired', 403); }
            $inst = $norm($body['instance']['id']);
            if (!in_array($inst, self::$activations[$body['license_key']], true)) { return self::err('instance_not_activated', 409); }
            $plugins = [];
            foreach ($body['products'] as $pr) {
                $slug = $pr['slug'];
                if (!in_array($slug, $lic['products'], true)) { continue; }                       // not_covered
                $latest = self::$latest[$slug] ?? null;
                if (!$latest || !version_compare($latest, $pr['version'], '>')) { continue; }     // up_to_date
                $jti = bin2hex(random_bytes(6)); self::$tokens[$jti] = ['exp' => Env::$now + 600, 'used' => false, 'slug' => $slug];
                $sha = self::$emptySha ? '' : hash('sha256', self::$zip);
                $host = self::$badHost ? 'evil.example.net' : $p['host'];
                // WordPressResponseFormatter : `if (! isset($pluginBasenames[$slug])) continue;` puis `$plugins[$basename]`
                if (!isset($body['plugins'][$slug])) { continue; }
                $plugins[$body['plugins'][$slug]] = ['slug' => $slug, 'plugin' => $body['plugins'][$slug], 'new_version' => $latest,
                    'package' => "https://$host/v1/download/$jti", 'url' => null, 'tested' => '6.8', 'requires' => '6.4', 'requires_php' => '8.0', 'sha256' => $sha];
            }
            return self::res(200, ['checked_at' => 'now', 'next_check_after' => 43200, 'plugins' => $plugins]);
        }
        if ($path === '/v1/licenses/activate') {
            $lic = self::$licenses[$body['license_key'] ?? ''] ?? null;
            if (!$lic || $lic['status'] !== 'active') { return self::err('license_invalid', 403); }
            if (!empty($lic['expired'])) { return self::err('license_expired', 403); }
            $inst = $norm($body['instance']['id']);
            if (!in_array($inst, self::$activations[$body['license_key']], true)) { self::$activations[$body['license_key']][] = $inst; }
            return self::res(200, ['activated' => true, 'license' => ['status' => 'active', 'expires_at' => null, 'instances' => ['used' => count(self::$activations[$body['license_key']]), 'max' => $lic['max']]]]);
        }
        if ($path === '/v1/licenses/deactivate') {
            $k = $body['license_key'] ?? '';
            $inst = $norm($body['instance']['id'] ?? '');
            self::$activations[$k] = array_values(array_diff(self::$activations[$k] ?? [], [$inst]));
            return self::res(200, ['deactivated' => true]);
        }
        if ($path === '/v1/licenses/validate') {
            $lic = self::$licenses[$body['license_key'] ?? ''] ?? null;
            if (!$lic || $lic['status'] !== 'active') { return self::err('license_invalid', 403); }
            if (!empty($lic['expired'])) { return self::err('license_expired', 403); }
            $inst = $norm($body['instance']['id']);
            return self::res(200, ['valid' => true, 'activated' => in_array($inst, self::$activations[$body['license_key']], true),
                'license' => ['status' => 'active', 'expires_at' => '2027-11-14T00:00:00+00:00', 'products' => $lic['products'], 'instances' => ['used' => 1, 'max' => $lic['max']]]]);
        }
        if (str_starts_with($path, '/v1/wp/info/')) {
            $slug = substr($path, 12);
            return self::res(200, ['name' => 'Form Quizz FQI3', 'slug' => $slug, 'version' => self::$latest[$slug] ?? null, 'sections' => ['description' => 'd', 'changelog' => '<h2>3.7.0</h2>'], 'download_link' => 'https://updates.webpixelia.com/v1/download/placeholder']);
        }
        return self::res(404, ['error' => ['code' => 'not_found']]);
    }
    private static function download(string $token, array $args): array {
        // DownloadTokenService : usage unique, 600 s
        $t = self::$tokens[$token] ?? null;
        if (!$t) { return self::res(403, ['error' => ['code' => 'token_invalid']]); }
        if ($t['used'] || $t['exp'] <= Env::$now) { return self::res(410, ['error' => ['code' => 'token_expired']]); }
        self::$tokens[$token]['used'] = true;
        file_put_contents($args['filename'], self::$corrupt ? strrev(self::$zip) : self::$zip);
        return self::res(200, []);
    }
}
