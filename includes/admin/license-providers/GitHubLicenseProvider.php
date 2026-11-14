<?php
namespace Form_Quizz_FQI3\LicenseProviders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fournisseur GitHub : comportement historique de Plugin_Updater.
 *
 * Utilise GITHUB_PERSONAL_TOKEN (défini dans wp-config.php) pour accéder
 * aux dépôts privés. Cache les réponses 12 h.
 *
 * @package Form_Quizz_FQI3
 * @since 3.7.0
 */
class GitHubLicenseProvider implements LicenseProviderInterface
{
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;
    private const GITHUB_USERNAME = 'Webpixelia';

    /** @var array<string, string> Map slug => repo GitHub */
    private array $repositories;

    /** @var array{code: string, message: string, http: int}|null */
    private ?array $lastError = null;

    public function __construct(array $repositories)
    {
        $this->repositories = $repositories;
    }

    public function getId(): string
    {
        return 'github';
    }

    /**
     * Déclare le dépôt d'un add-on (FQI3_Addons_Page::init_updaters / install_addon).
     */
    public function addRepository(string $slug, string $repo): void
    {
        $this->repositories[$slug] = $repo;
    }

    /**
     * @inheritDoc
     */
    public function checkUpdates(array $plugins): ?array
    {
        $updates = [];

        foreach ($plugins as $plugin_basename => $current_version) {
            $slug = dirname($plugin_basename);

            if (! isset($this->repositories[$slug])) {
                continue;
            }

            $release = $this->getLatestRelease($slug);
            if ($release === null || ! isset($release['version'])) {
                continue;
            }

            if (! version_compare($release['version'], $current_version, '>')) {
                continue;
            }

            $updates[$plugin_basename] = [
                'slug'         => $slug,
                'plugin'       => $plugin_basename,
                'new_version'  => $release['version'],
                'package'      => $release['download_url'] ?? '',
                'url'          => $release['homepage'] ?? null,
                'tested'       => $release['tested'] ?? null,
                'requires'     => $release['requires'] ?? null,
                'requires_php' => $release['requires_php'] ?? null,
                'sha256'       => '', // GitHub ne fournit pas de sha256 ; la vérification se fait via HTTPS
            ];
        }

        return $updates;
    }

    /**
     * @inheritDoc
     */
    public function getProductInfo(string $slug): ?array
    {
        if (! isset($this->repositories[$slug])) {
            return null;
        }

        $release = $this->getLatestRelease($slug);
        if ($release === null) {
            return null;
        }

        return [
            'name'         => $release['name'] ?? $slug,
            'slug'         => $slug,
            'version'      => $release['version'] ?? '',
            'author'       => $release['author'] ?? null,
            'homepage'     => $release['homepage'] ?? null,
            'requires'     => $release['requires'] ?? null,
            'tested'       => $release['tested'] ?? null,
            'requires_php' => $release['requires_php'] ?? null,
            'last_updated' => $release['last_updated'] ?? null,
            'download_link' => $release['download_url'] ?? '',
            'sections'     => [
                'description' => $release['description'] ?? '',
                'changelog'   => $release['changelog'] ?? '',
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function resolveDownload(string $slug, string $installedVersion = '0.0.0'): ?array
    {
        $this->lastError = null;

        $release = $this->getLatestRelease($slug);
        if ($release === null || empty($release['download_url'])) {
            $this->lastError = ['code' => 'no_release', 'message' => __('No release found for this add-on.', 'form-quizz-fqi3'), 'http' => 0];
            return null;
        }

        $token   = defined('GITHUB_PERSONAL_TOKEN') ? (string) GITHUB_PERSONAL_TOKEN : '';
        $url     = (string) $release['download_url'];
        $headers = ['User-Agent' => 'WordPress-Form-Quizz-Plugin'];
        if ($token !== '') {
            $headers['Authorization'] = 'token ' . $token;
            // Une release asset demande le type binaire ; le zipball l'ignore.
            $headers['Accept'] = str_contains($url, '/releases/assets/') ? 'application/octet-stream' : 'application/vnd.github+json';
        }

        return ['url' => $url, 'sha256' => '', 'version' => (string) $release['version'], 'headers' => $headers];
    }

    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    public function clearCache(): void
    {
        foreach ($this->repositories as $repo) {
            delete_transient('fqi3_github_release_' . md5($repo));
        }
    }

    /**
     * Récupère la dernière release GitHub d'un dépôt, avec cache 12 h.
     *
     * @return array<string, mixed>|null
     */
    private function getLatestRelease(string $slug): ?array
    {
        $repo = $this->repositories[$slug] ?? null;
        if ($repo === null) {
            return null;
        }

        $cache_key = 'fqi3_github_release_' . md5($repo);
        $cached = get_transient($cache_key);

        if ($cached === 'no_release') {
            return null;
        }
        if (is_array($cached)) {
            return $cached;
        }

        $token = defined('GITHUB_PERSONAL_TOKEN') ? (string) GITHUB_PERSONAL_TOKEN : '';

        $headers = [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress-Form-Quizz-Plugin',
        ];
        if ($token !== '') {
            $headers['Authorization'] = 'token ' . $token;
        }

        $response = wp_remote_get(
            sprintf('https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_USERNAME, $repo),
            ['headers' => $headers, 'timeout' => 15]
        );

        if (is_wp_error($response)) {
            set_transient($cache_key, 'no_release', HOUR_IN_SECONDS);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 404) {
            set_transient($cache_key, 'no_release', DAY_IN_SECONDS);
            return null;
        }
        if ($code !== 200) {
            set_transient($cache_key, 'no_release', HOUR_IN_SECONDS);
            return null;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($release) || ! isset($release['tag_name'])) {
            set_transient($cache_key, 'no_release', HOUR_IN_SECONDS);
            return null;
        }

        $data = [
            'name'         => $release['name'] ?? $slug,
            'version'      => ltrim($release['tag_name'], 'v'),
            'download_url' => $this->getDownloadUrl($release, $token),
            'changelog'    => $this->parseChangelog($release['body'] ?? ''),
            'homepage'     => $release['html_url'] ?? null,
            'last_updated' => $release['published_at'] ?? null,
            'requires'     => null,
            'tested'       => null,
            'requires_php' => null,
        ];

        set_transient($cache_key, $data, self::CACHE_TTL);

        return $data;
    }

    /**
     * @param array<string, mixed> $release
     */
    private function getDownloadUrl(array $release, string $token): string
    {
        if (! empty($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (str_contains((string) $asset['name'], '.zip')) {
                    if ($token !== '' && ! empty($asset['url'])) {
                        return (string) $asset['url'];
                    }
                    return (string) $asset['browser_download_url'];
                }
            }
        }

        return (string) ($release['zipball_url'] ?? '');
    }

    private function parseChangelog(string $content): string
    {
        if ($content === '') {
            return __('No changelog available.', 'form-quizz-fqi3');
        }

        $content = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $content) ?? $content;
        $content = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $content) ?? $content;
        $content = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $content) ?? $content;
        $content = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $content) ?? $content;
        $content = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $content) ?? $content;

        return wpautop($content);
    }
}