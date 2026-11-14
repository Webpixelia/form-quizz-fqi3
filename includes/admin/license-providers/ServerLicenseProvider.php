<?php
namespace Form_Quizz_FQI3\LicenseProviders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fournisseur LUS : interroge le serveur de licences et de mises à jour.
 *
 * Endpoints utilisés :
 * - POST /v1/wp/check         (vérification groupée, plugins = map slug => basename)
 * - GET  /v1/wp/info/{slug}   (détails, public)
 * - POST /v1/licenses/activate, /v1/licenses/validate (écran de licence, activation automatique)
 *
 * Règles de robustesse :
 * - le `package` renvoyé par /wp/check est un jeton à usage unique valable 10 min : il n'est JAMAIS mis en
 *   cache ; checkUpdates() renvoie un marqueur, resolveDownload() obtient un jeton frais au téléchargement ;
 * - les échecs sont mis en cache (403 : 12 h, 409 : 1 h, réseau/5xx : 15 min) pour ne pas marteler LUS
 *   (blocage serveur à 10 échecs/h/IP) ni bloquer l'admin pendant le timeout ;
 * - aucune exception, aucun fatal : null + getLastError().
 *
 * @package Form_Quizz_FQI3
 * @since 3.7.0
 */
class ServerLicenseProvider implements LicenseProviderInterface
{
    public const STATUS_OPTION = 'fqi3_license_status';

    private const CACHE_TTL       = 12 * HOUR_IN_SECONDS;
    private const INFO_TTL        = HOUR_IN_SECONDS;
    private const SOFT_FAIL_TTL   = 15 * MINUTE_IN_SECONDS;
    private const EPOCH_OPTION    = 'fqi3_lus_cache_epoch';
    private const TIMEOUT         = 6;
    private const MAX_PRODUCTS    = 20;
    /** Même motif que VersionComparator::PATTERN côté LUS (un seul produit invalide ferait rejeter tout le lot en 422). */
    private const VERSION_PATTERN = '/^v?\d+(\.\d+){0,3}(-[0-9A-Za-z.\-]+)?(\+[0-9A-Za-z.\-]+)?$/';

    private string $serverUrl;

    /** @var array{code: string, message: string, http: int}|null */
    private ?array $lastError = null;

    public function __construct(string $serverUrl)
    {
        $this->serverUrl = rtrim($serverUrl, '/');
    }

    public function getId(): string
    {
        return 'server';
    }

    /**
     * @inheritDoc
     */
    public function checkUpdates(array $plugins): ?array
    {
        $this->lastError = null;

        $licenseKey = LicenseProviderFactory::getLicenseKey();
        if ($licenseKey === '') {
            $this->lastError = self::err('license_missing');
            return null;
        }

        $instanceId = $this->getInstanceId();
        $plugins    = $this->sanitizePlugins($plugins);
        if ($instanceId === '' || $plugins === []) {
            return null;
        }

        // La clé de cache contient les VERSIONS installées : après une mise à jour, l'ancien résultat n'est plus servi.
        $cache_key = $this->cacheKey('check', $licenseKey . '|' . $instanceId . '|' . wp_json_encode($plugins));
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            if (isset($cached['error']) && is_array($cached['error'])) {
                $this->lastError = $cached['error'];
                return null;
            }
            if (isset($cached['updates']) && is_array($cached['updates'])) {
                return $cached['updates'];
            }
        }

        $entries = $this->fetch($plugins);
        if ($entries === null) {
            $error = $this->lastError ?? self::err('unreachable');
            set_transient($cache_key, ['error' => $error], self::errorTtl($error));
            return null;
        }

        $updates = [];
        foreach ($entries as $basename => $entry) {
            // Jamais le jeton à usage unique dans le cache ni dans le transient `update_plugins`.
            $entry['package']   = $this->packagePlaceholder();
            $updates[$basename] = $entry;
        }

        set_transient($cache_key, ['updates' => $updates], self::CACHE_TTL);

        return $updates;
    }

    /**
     * @inheritDoc
     */
    public function getProductInfo(string $slug): ?array
    {
        $cache_key = $this->cacheKey('info', $slug);
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return isset($cached['none']) ? null : $cached;
        }

        $res = $this->request('GET', '/v1/wp/info/' . rawurlencode($slug));
        if ($res['error'] !== null || ! is_array($res['body'])) {
            set_transient($cache_key, ['none' => true], 5 * MINUTE_IN_SECONDS);
            return null;
        }

        $body = $res['body'];
        $info = [
            'name'          => (string) ($body['name'] ?? $slug),
            'slug'          => (string) ($body['slug'] ?? $slug),
            'version'       => (string) ($body['version'] ?? ''),
            'author'        => self::str($body, 'author'),
            'homepage'      => self::str($body, 'homepage'),
            'requires'      => self::str($body, 'requires'),
            'tested'        => self::str($body, 'tested'),
            'requires_php'  => self::str($body, 'requires_php'),
            'last_updated'  => null,
            'download_link' => '', // /wp/info renvoie un lien factice : le paquet se résout via resolveDownload()
            'sections'      => [
                'description' => (string) ($body['sections']['description'] ?? ''),
                'changelog'   => (string) ($body['sections']['changelog'] ?? ''),
            ],
        ];

        set_transient($cache_key, $info, self::INFO_TTL);

        return $info;
    }

    /**
     * @inheritDoc
     */
    public function resolveDownload(string $slug, string $installedVersion = '0.0.0'): ?array
    {
        $this->lastError = null;

        if (LicenseProviderFactory::getLicenseKey() === '' || $this->getInstanceId() === '') {
            $this->lastError = self::err('license_missing');
            return null;
        }

        if (preg_match(self::VERSION_PATTERN, $installedVersion) !== 1) {
            $installedVersion = '0.0.0';
        }

        $basename = $slug . '/' . $slug . '.php';

        // Jamais de cache ici : chaque appel crée un jeton à usage unique côté LUS.
        $entries = $this->fetch([$basename => $installedVersion]);
        if ($entries === null) {
            return null;
        }
        if (! isset($entries[$basename])) {
            $this->lastError = self::err('no_update');
            return null;
        }

        $entry = $entries[$basename];
        if (! $this->isTrustedPackageUrl($entry['package'])) {
            $this->lastError = self::err('bad_package');
            return null;
        }

        return [
            'url'     => $entry['package'],
            'sha256'  => $entry['sha256'],
            'version' => $entry['new_version'],
            'headers' => [],
        ];
    }

    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    public function clearCache(): void
    {
        $epoch = (string) time();
        if (is_multisite()) {
            update_site_option(self::EPOCH_OPTION, $epoch);
        } else {
            update_option(self::EPOCH_OPTION, $epoch, false);
        }
    }

    /**
     * Active ce site auprès de LUS (POST /v1/licenses/activate). Idempotent côté serveur.
     */
    public function activate(): bool
    {
        $this->lastError = null;

        $licenseKey = LicenseProviderFactory::getLicenseKey();
        $instanceId = $this->getInstanceId();
        if ($licenseKey === '' || $instanceId === '') {
            $this->lastError = self::err('license_missing');
            return false;
        }

        $res = $this->request('POST', '/v1/licenses/activate', [
            'license_key' => $licenseKey,
            'instance'    => ['type' => 'domain', 'id' => $instanceId, 'label' => $instanceId],
            'client'      => [
                'name'    => 'fqi3-core',
                'version' => defined('FQI3_VERSION') ? (string) FQI3_VERSION : '',
                'env'     => ['wp' => (string) ($GLOBALS['wp_version'] ?? ''), 'php' => PHP_VERSION],
            ],
        ]);

        if ($res['error'] !== null) {
            $this->lastError = $res['error'];
            $this->recordStatus($res['error']);
            return false;
        }

        $license = is_array($res['body']['license'] ?? null) ? $res['body']['license'] : [];
        $this->saveSnapshot($license, true);
        $this->clearCache();

        return true;
    }

    /**
     * Libère l'activation de ce site (POST /v1/licenses/deactivate), au mieux : un échec réseau ne bloque pas la suppression locale.
     */
    public function deactivate(): bool
    {
        $this->lastError = null;

        $licenseKey = LicenseProviderFactory::getLicenseKey();
        $instanceId = $this->getInstanceId();
        if ($licenseKey === '' || $instanceId === '') {
            $this->lastError = self::err('license_missing');
            return false;
        }

        $res = $this->request('POST', '/v1/licenses/deactivate', [
            'license_key' => $licenseKey,
            'instance'    => ['type' => 'domain', 'id' => $instanceId],
        ]);

        if ($res['error'] !== null) {
            $this->lastError = $res['error'];
            return false;
        }

        return true;
    }

    /**
     * Interroge l'état de la licence (POST /v1/licenses/validate) et met à jour le statut enregistré.
     *
     * @return array<string, mixed>|null Corps de la réponse LUS, ou null (voir getLastError()).
     */
    public function validate(string $productSlug = 'form-quizz-fqi3'): ?array
    {
        $this->lastError = null;

        $licenseKey = LicenseProviderFactory::getLicenseKey();
        $instanceId = $this->getInstanceId();
        if ($licenseKey === '' || $instanceId === '') {
            $this->lastError = self::err('license_missing');
            return null;
        }

        $res = $this->request('POST', '/v1/licenses/validate', [
            'license_key' => $licenseKey,
            'product'     => $productSlug,
            'instance'    => ['type' => 'domain', 'id' => $instanceId],
        ]);

        if ($res['error'] !== null || ! is_array($res['body'])) {
            $this->lastError = $res['error'] ?? self::err('bad_response');
            $this->recordStatus($this->lastError);
            return null;
        }

        $license = is_array($res['body']['license'] ?? null) ? $res['body']['license'] : [];
        $this->saveSnapshot($license, ! empty($res['body']['activated']));

        return $res['body'];
    }

    /**
     * Identifiant d'instance : domaine du site (du réseau en multisite), sans schéma, sans www., sans port.
     * Surchargeable par la constante FQI3_LICENSE_INSTANCE (ex. un site Local qui ne doit pas consommer d'activation).
     */
    public function getInstanceId(): string
    {
        if (defined('FQI3_LICENSE_INSTANCE') && (string) FQI3_LICENSE_INSTANCE !== '') {
            $url = (string) FQI3_LICENSE_INSTANCE;
            if (strpos($url, '://') === false) {
                $url = 'http://' . $url;
            }
        } else {
            $url = is_multisite() ? network_home_url() : home_url();
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }

        $host = rtrim(strtolower($host), '.');
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    /**
     * Message lisible pour un code d'erreur LUS (partagé avec l'écran de licence et install_addon()).
     */
    public static function messageForCode(string $code): string
    {
        switch ($code) {
            case 'license_missing':
                return __('No license key is defined.', 'form-quizz-fqi3');
            case 'license_invalid':
                return __('The license key is invalid, revoked, or does not cover this product.', 'form-quizz-fqi3');
            case 'license_expired':
                return __('The license has expired. Renew it to receive updates.', 'form-quizz-fqi3');
            case 'instance_not_activated':
                return __('This site is not activated for this license. Activate it from the License settings.', 'form-quizz-fqi3');
            case 'instance_limit_reached':
                return __('The maximum number of sites for this license has been reached.', 'form-quizz-fqi3');
            case 'rate_limited':
                return __('Too many requests to the license server. Try again later.', 'form-quizz-fqi3');
            case 'validation_failed':
                return __('The license server rejected the request.', 'form-quizz-fqi3');
            case 'insecure_url':
                return __('The license server URL must use HTTPS.', 'form-quizz-fqi3');
            case 'no_update':
                return __('No downloadable version is available for this plugin.', 'form-quizz-fqi3');
            case 'bad_package':
                return __('The license server returned an untrusted download address.', 'form-quizz-fqi3');
            case 'unreachable':
            case 'server_error':
            case 'bad_response':
            default:
                return __('The license server could not be reached. Try again later.', 'form-quizz-fqi3');
        }
    }

    /**
     * État du site déduit d'un code d'erreur LUS ; null = incident transitoire (on garde l'état connu).
     */
    public static function stateForCode(?string $code): ?string
    {
        switch ($code) {
            case null:
                return 'active';
            case 'license_invalid':
                return 'invalid';
            case 'license_expired':
                return 'expired';
            case 'instance_not_activated':
            case 'instance_limit_reached':
                return 'inactive';
            default:
                return null;
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Internes
    // ---------------------------------------------------------------------------------------------

    /**
     * Appel groupé /v1/wp/check, sans cache. Retourne les entrées avec le VRAI `package` (jeton à usage unique).
     *
     * @param array<string, string> $plugins basename => version installée (déjà assainie)
     * @return array<string, array<string, mixed>>|null
     */
    private function fetch(array $plugins, bool $allowAutoActivate = true): ?array
    {
        $pluginMap = []; // slug => basename : c'est ce que WordPressResponseFormatter attend (et renvoie comme clé).
        $products  = [];
        foreach ($plugins as $basename => $version) {
            $slug             = dirname($basename);
            $pluginMap[$slug] = $basename;
            $products[]       = ['slug' => $slug, 'version' => $version];
        }

        $res = $this->request('POST', '/v1/wp/check', [
            'license_key' => LicenseProviderFactory::getLicenseKey(),
            'instance'    => ['type' => 'domain', 'id' => $this->getInstanceId()],
            'plugins'     => $pluginMap,
            'products'    => $products,
        ]);

        if ($res['error'] !== null) {
            if ($allowAutoActivate && $res['error']['code'] === 'instance_not_activated' && $this->shouldAutoActivate()) {
                if ($this->activate()) {
                    return $this->fetch($plugins, false);
                }
                return null;
            }

            $this->lastError = $res['error'];
            $this->recordStatus($res['error']);
            return null;
        }

        $body = $res['body'];
        if (! is_array($body) || ! isset($body['plugins']) || ! is_array($body['plugins'])) {
            $this->lastError = self::err('bad_response', (int) $res['http']);
            return null;
        }

        $entries = [];
        foreach ($body['plugins'] as $data) {
            if (! is_array($data)) {
                continue;
            }

            // On ne fait pas confiance aux clés renvoyées : on ré-associe par slug à NOTRE table.
            $slug = (string) ($data['slug'] ?? '');
            if (! isset($pluginMap[$slug])) {
                continue;
            }

            $basename   = $pluginMap[$slug];
            $newVersion = (string) ($data['new_version'] ?? '');
            if (preg_match(self::VERSION_PATTERN, $newVersion) !== 1 || ! version_compare($newVersion, $plugins[$basename], '>')) {
                continue;
            }

            $entries[$basename] = [
                'slug'         => $slug,
                'plugin'       => $basename,
                'new_version'  => $newVersion,
                'package'      => (string) ($data['package'] ?? ''),
                'url'          => self::str($data, 'url'),
                'tested'       => self::str($data, 'tested'),
                'requires'     => self::str($data, 'requires'),
                'requires_php' => self::str($data, 'requires_php'),
                'sha256'       => strtolower((string) ($data['sha256'] ?? '')),
            ];
        }

        $this->recordStatus(null);

        return $entries;
    }

    /**
     * Requête HTTP vers LUS. Ne lève jamais d'exception.
     *
     * @param array<string, mixed>|null $payload
     * @return array{http: int, body: array<string, mixed>|null, error: array{code: string, message: string, http: int}|null}
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (strpos($this->serverUrl, 'https://') !== 0 && ! $this->allowInsecure()) {
            return ['http' => 0, 'body' => null, 'error' => self::err('insecure_url')];
        }

        $ua   = 'fqi3-core/' . (defined('FQI3_VERSION') ? FQI3_VERSION : '0') . ' wp/' . ($GLOBALS['wp_version'] ?? '0') . ' php/' . PHP_VERSION;
        $args = [
            'timeout'     => self::TIMEOUT,
            // Pas de redirection : un 307/308 rejouerait le corps (donc la clé de licence) vers un autre hôte.
            'redirection' => 0,
            'headers'     => ['Accept' => 'application/json', 'User-Agent' => $ua, 'X-Client' => $ua],
        ];

        if ($method === 'POST') {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body']                    = wp_json_encode($payload ?? []);
            $response                        = wp_remote_post($this->serverUrl . $path, $args);
        } else {
            $response = wp_remote_get($this->serverUrl . $path, $args);
        }

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FQI3/LUS] ' . $method . ' ' . $path . ' WP_Error: ' . $response->get_error_message()); // phpcs:ignore
            }
            return ['http' => 0, 'body' => null, 'error' => self::err('unreachable')];
        }

        $http = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $body = is_array($body) ? $body : null;

        if ($http === 200) {
            return ['http' => $http, 'body' => $body, 'error' => null];
        }

        $code = is_string($body['error']['code'] ?? null) ? $body['error']['code'] : ($http >= 500 ? 'server_error' : 'http_' . $http);
        $extra = [];
        if ($http === 429) {
            $extra['retry_after'] = max(0, (int) wp_remote_retrieve_header($response, 'retry-after'));
        }

        return ['http' => $http, 'body' => $body, 'error' => self::err($code, $http, $extra)];
    }

    /**
     * Activation automatique : uniquement si la clé vient de wp-config.php (le propriétaire l'a posée volontairement,
     * il n'y a alors aucun écran où cliquer « Activer »), et au plus une tentative par heure.
     */
    private function shouldAutoActivate(): bool
    {
        $default = LicenseProviderFactory::licenseKeySource() === 'constant';
        if (! apply_filters('fqi3_lus_auto_activate', $default)) {
            return false;
        }

        $lock = $this->cacheKey('autoact', LicenseProviderFactory::getLicenseKey() . '|' . $this->getInstanceId());
        if (get_transient($lock)) {
            return false;
        }
        set_transient($lock, 1, HOUR_IN_SECONDS);

        return true;
    }

    /**
     * @param array<string, mixed> $license Bloc `license` d'une réponse activate/validate.
     */
    private function saveSnapshot(array $license, bool $activated): void
    {
        $status = LicenseProviderFactory::getStatus();

        $status['state']      = $activated ? 'active' : 'inactive';
        $status['code']       = 'ok';
        $status['checked_at'] = time();
        $status['last_error'] = '';
        if (isset($license['status'])) {
            $status['license_status'] = (string) $license['status'];
        }
        if (array_key_exists('expires_at', $license)) {
            $status['expires_at'] = $license['expires_at'] === null ? null : (string) $license['expires_at'];
        }
        if (isset($license['instances']) && is_array($license['instances'])) {
            $status['instances'] = [
                'used' => (int) ($license['instances']['used'] ?? 0),
                'max'  => isset($license['instances']['max']) ? (int) $license['instances']['max'] : null,
            ];
        }
        if (isset($license['products']) && is_array($license['products'])) {
            $status['products'] = array_values(array_map('strval', $license['products']));
        }

        LicenseProviderFactory::saveStatus($status);
    }

    /**
     * Enregistre l'état pour l'écran de licence (jamais de requête HTTP au chargement de l'écran).
     * Écriture limitée : seulement si l'état change ou si la dernière écriture date de plus d'une heure.
     *
     * @param array{code: string, message: string, http: int}|null $error
     */
    private function recordStatus(?array $error): void
    {
        $previous = LicenseProviderFactory::getStatus();
        $state    = self::stateForCode($error['code'] ?? null);
        $next     = $previous;

        if ($state === null) {
            // Réseau / 5xx / 429 : on garde l'état de licence connu et on note l'incident.
            $next['last_error']    = (string) ($error['code'] ?? 'unreachable');
            $next['last_error_at'] = time();
        } else {
            $next['state']      = $state;
            $next['code']       = $error['code'] ?? 'ok';
            $next['checked_at'] = time();
            $next['last_error'] = '';
        }

        $unchanged = ($previous['state'] ?? '') === ($next['state'] ?? '')
            && ($previous['last_error'] ?? '') === ($next['last_error'] ?? '')
            && (time() - (int) ($previous['checked_at'] ?? 0)) < HOUR_IN_SECONDS;

        if (! $unchanged) {
            LicenseProviderFactory::saveStatus($next);
        }
    }

    /**
     * Garde uniquement des couples basename => version exploitables par LUS.
     *
     * @param array<string, string> $plugins
     * @return array<string, string>
     */
    private function sanitizePlugins(array $plugins): array
    {
        $clean = [];
        foreach ($plugins as $basename => $version) {
            $basename = (string) $basename;
            $version  = (string) $version;
            if (strpos($basename, '/') === false || preg_match(self::VERSION_PATTERN, $version) !== 1) {
                continue; // un seul produit invalide ferait rejeter tout le lot (422)
            }
            $clean[$basename] = $version;
        }
        ksort($clean);

        return array_slice($clean, 0, self::MAX_PRODUCTS, true);
    }

    private function packagePlaceholder(): string
    {
        return $this->serverUrl . '/v1/download/pending';
    }

    private function isTrustedPackageUrl(string $url): bool
    {
        $host    = strtolower((string) parse_url($url, PHP_URL_HOST));
        $trusted = (array) apply_filters('fqi3_lus_trusted_package_hosts', [strtolower((string) parse_url($this->serverUrl, PHP_URL_HOST))]);

        if ($host === '' || ! in_array($host, array_map('strtolower', $trusted), true)) {
            return false;
        }

        return strpos($url, 'https://') === 0 || $this->allowInsecure();
    }

    private function allowInsecure(): bool
    {
        return (defined('FQI3_LUS_ALLOW_INSECURE') && FQI3_LUS_ALLOW_INSECURE)
            || in_array(wp_get_environment_type(), ['local', 'development'], true);
    }

    private function cacheKey(string $type, string $material): string
    {
        $epoch = (string) (is_multisite() ? get_site_option(self::EPOCH_OPTION, '') : get_option(self::EPOCH_OPTION, ''));

        return 'fqi3_lus_' . $type . '_' . md5($epoch . '|' . $material);
    }

    /**
     * @param array{code: string, message: string, http: int, retry_after?: int} $error
     */
    private static function errorTtl(array $error): int
    {
        switch ($error['code']) {
            case 'license_invalid':
            case 'license_expired':
                return self::CACHE_TTL;
            case 'instance_not_activated':
            case 'instance_limit_reached':
            case 'validation_failed':
                return HOUR_IN_SECONDS;
            case 'rate_limited':
                return max(MINUTE_IN_SECONDS, min(HOUR_IN_SECONDS, (int) ($error['retry_after'] ?? HOUR_IN_SECONDS)));
            default:
                return self::SOFT_FAIL_TTL;
        }
    }

    /**
     * @param array<string, mixed> $extra
     * @return array{code: string, message: string, http: int}
     */
    private static function err(string $code, int $http = 0, array $extra = []): array
    {
        return array_merge(['code' => $code, 'message' => self::messageForCode($code), 'http' => $http], $extra);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function str(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '' ? $data[$key] : null;
    }
}
