<?php
namespace Form_Quizz_FQI3\LicenseProviders;

/**
 * Fournisseur LUS : interroge le serveur de licences et de mises à jour.
 *
 * Endpoints utilisés :
 * - POST /v1/wp/check       (vérification groupée)
 * - GET  /v1/wp/info/{slug} (détails)
 *
 * La clé de licence est lue depuis :
 * 1. la constante FQI3_LICENSE_KEY (wp-config.php, prioritaire),
 * 2. l'option `fqi3_license_key` en base (fallback).
 *
 * Si le serveur est injoignable, retourne null (pas de mise à jour, pas de fatal).
 *
 * @package Form_Quizz_FQI3
 * @since 3.7.0
 */
class ServerLicenseProvider implements LicenseProviderInterface
{
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    private string $serverUrl;

    public function __construct(string $serverUrl)
    {
        $this->serverUrl = rtrim($serverUrl, '/');
    }

    /**
     * @inheritDoc
     */
    public function checkUpdates(array $plugins): ?array
    {
        $licenseKey = $this->getLicenseKey();
        if ($licenseKey === '') {
            return null;
        }

        $instanceId = $this->getInstanceId();
        if ($instanceId === '') {
            return null;
        }

        $cache_key = 'fqi3_lus_check_' . md5($licenseKey . '|' . $instanceId . '|' . implode(',', array_keys($plugins)));
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $products = [];
        $pluginMap = [];
        foreach ($plugins as $plugin_basename => $current_version) {
            $slug = dirname($plugin_basename);
            $products[] = ['slug' => $slug, 'version' => $current_version];
            $pluginMap[$slug] = $plugin_basename;
        }

        $response = wp_remote_post(
            $this->serverUrl . '/v1/wp/check',
            [
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body'    => wp_json_encode([
                    'license_key' => $licenseKey,
                    'instance'    => ['type' => 'domain', 'id' => $instanceId],
                    'plugins'     => array_combine(array_keys($pluginMap), array_keys($pluginMap)),
                    'products'    => $products,
                ]),
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response)) {
            error_log('[FQI3/LUS] checkUpdates WP_Error: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('[FQI3/LUS] checkUpdates HTTP ' . $code);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($body) || ! isset($body['plugins']) || ! is_array($body['plugins'])) {
            return null;
        }

        $updates = [];
        foreach ($body['plugins'] as $basename => $data) {
            if (! is_array($data)) {
                continue;
            }
            $updates[$basename] = [
                'slug'         => (string) ($data['slug'] ?? ''),
                'plugin'       => (string) ($data['plugin'] ?? $basename),
                'new_version'  => (string) ($data['new_version'] ?? ''),
                'package'      => (string) ($data['package'] ?? ''),
                'url'          => $data['url'] ?? null,
                'tested'       => $data['tested'] ?? null,
                'requires'     => $data['requires'] ?? null,
                'requires_php' => $data['requires_php'] ?? null,
                'sha256'       => (string) ($data['sha256'] ?? ''),
            ];
        }

        set_transient($cache_key, $updates, self::CACHE_TTL);

        return $updates;
    }

    /**
     * @inheritDoc
     */
    public function getProductInfo(string $slug): ?array
    {
        $response = wp_remote_get(
            $this->serverUrl . '/v1/wp/info/' . rawurlencode($slug),
            [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($body)) {
            return null;
        }

        return [
            'name'         => (string) ($body['name'] ?? $slug),
            'slug'         => (string) ($body['slug'] ?? $slug),
            'version'      => (string) ($body['version'] ?? ''),
            'author'       => $body['author'] ?? null,
            'homepage'     => $body['homepage'] ?? null,
            'requires'     => $body['requires'] ?? null,
            'tested'       => $body['tested'] ?? null,
            'requires_php' => $body['requires_php'] ?? null,
            'sections'     => [
                'description' => (string) ($body['sections']['description'] ?? ''),
                'changelog'   => (string) ($body['sections']['changelog'] ?? ''),
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function downloadUrl(string $slug, string $version): ?string
    {
        $updates = $this->checkUpdates([$slug . '/' . $slug . '.php' => $version]);
        if ($updates === null) {
            return null;
        }

        $key = $slug . '/' . $slug . '.php';

        return isset($updates[$key]['package']) && $updates[$key]['package'] !== ''
            ? (string) $updates[$key]['package']
            : null;
    }

    /**
     * Lit la clé de licence : constante prioritaire, puis option en base.
     */
    private function getLicenseKey(): string
    {
        if (defined('FQI3_LICENSE_KEY') && (string) FQI3_LICENSE_KEY !== '') {
            return (string) FQI3_LICENSE_KEY;
        }

        $option = get_option('fqi3_license_key', '');
        return is_string($option) ? $option : '';
    }

    /**
     * Identifiant d'instance : domaine normalisé (sans schéma, sans www., sans chemin).
     */
    private function getInstanceId(): string
    {
        $home = home_url();
        $host = parse_url($home, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }

        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }
}