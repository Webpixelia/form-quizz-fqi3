<?php
namespace Form_Quizz_FQI3;

use Form_Quizz_FQI3\LicenseProviders\LicenseProviderFactory;
use Form_Quizz_FQI3\LicenseProviders\LicenseProviderInterface;

/**
 * Plugin Updater Class
 *
 * This class handles updates for a WordPress plugin. The release source is a "license provider"
 * (GitHub for development sites, the LUS server for production), selected by FQI3_LICENSE_PROVIDER.
 * It checks for new releases, retrieves plugin information, and manages update notifications.
 * Usage:
 * $updater = new Plugin_Updater(__FILE__, 'repository-name', 'GitHubUsername');
 * The 3-argument form is unchanged; a provider can optionally be injected as 4th argument (tests).
 * @package Form_Quizz_FQI3
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-package-signature.php';
require_once __DIR__ . '/license-providers/LicenseProviderInterface.php';
require_once __DIR__ . '/license-providers/GitHubLicenseProvider.php';
require_once __DIR__ . '/license-providers/ServerLicenseProvider.php';
require_once __DIR__ . '/license-providers/LicenseProviderFactory.php';

class Plugin_Updater {
	/** @var array<string, true> Plugin basenames managed by an updater (core + add-ons): checked in ONE grouped request. */
	private static $registry = array();

	/** @var LicenseProviderInterface|null Injected provider (tests); otherwise LicenseProviderFactory::make(). */
	private $injected_provider;

	private $github_username;
	private $github_token;
	private $plugin_data;
	private $plugin_slug;
	private $plugin_file;
	private $github_repo;
	private $cache_key;
	private $cache_expiration = 12 * HOUR_IN_SECONDS;

	public function __construct( $plugin_file, $github_repo = '', $github_username = 'Webpixelia', $provider = null ) {
		$this->plugin_file       = $plugin_file; // ex: 'fqi3-addon/fqi3-addon.php'
		$this->plugin_slug       = dirname( $plugin_file ); // ex: 'fqi3-addon'
		$this->github_username   = $github_username;
		$this->github_repo       = (string) $github_repo; // empty in server mode (LUS needs no repository)
		$this->github_token      = defined( 'GITHUB_PERSONAL_TOKEN' ) ? GITHUB_PERSONAL_TOKEN : '';
		$this->cache_key         = 'fqi3_update_' . md5( $plugin_file );
		$this->injected_provider = $provider instanceof LicenseProviderInterface ? $provider : null;

		self::$registry[ $plugin_file ] = true;
		LicenseProviderFactory::registerRepository( $this->plugin_slug, $this->github_repo );

		// Hook into the update check
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

		// Hook into the plugin information screen
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

		// Add custom update message
		add_action( "in_plugin_update_message-{$plugin_file}", array( $this, 'update_message' ), 10, 2 );

		// GitHub zipballs extract to "<owner>-<repo>-<sha>": give the package the plugin folder name
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );

		// Private repositories: the package must be downloaded with the token (WordPress sends none).
		add_filter( 'upgrader_pre_download', array( $this, 'download_private_package' ), 10, 4 );
	}

	/**
	 * Download the update package of this plugin with the GitHub token.
	 *
	 * The release check sends the token, but WordPress downloads the package itself without any header: on a
	 * private repository GitHub answers 404 ("Not Found"). The package is downloaded here instead, only for the
	 * plugin being updated and only for a URL of its own repository, so the token is never sent anywhere else.
	 *
	 * @param bool|string|\WP_Error $reply      Short-circuit value (false = let WordPress download it).
	 * @param string                $package    Package URL.
	 * @param \WP_Upgrader          $upgrader   Upgrader instance.
	 * @param array                 $hook_extra Context of the upgrade (`plugin` is the plugin basename).
	 * @return bool|string|\WP_Error Path of the downloaded file, an error, or $reply unchanged.
	 * @since 3.6.1
	 */
	public function download_private_package( $reply, $package, $upgrader = null, $hook_extra = array() ) {
		if ( false !== $reply || ! is_array( $hook_extra ) || ( $hook_extra['plugin'] ?? '' ) !== $this->plugin_file ) {
			return $reply;
		}

		// LUS: the package in the update transient is only a placeholder. A fresh single-use link is requested now,
		// then the file is downloaded and its sha256 verified before WordPress installs it.
		if ( 'server' === $this->provider()->getId() ) {
			return $this->download_from_provider();
		}

		$repo_prefix = sprintf( 'https://api.github.com/repos/%s/%s/', $this->github_username, $this->github_repo );
		if ( empty( $this->github_token ) || ! is_string( $package ) || 0 !== strpos( $package, $repo_prefix ) ) {
			return $reply;
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp_file = wp_tempnam( $package );
		if ( ! $tmp_file ) {
			return new \WP_Error( 'fqi3_download_tmp', __( 'Could not create a temporary file for the update.', 'form-quizz-fqi3' ) );
		}

		$response = wp_safe_remote_get(
			$package,
			array(
				'headers'  => array(
					'Authorization' => 'token ' . $this->github_token,
					// A release asset needs the binary media type; the zipball ignores it.
					'Accept'        => false !== strpos( $package, '/releases/assets/' ) ? 'application/octet-stream' : 'application/vnd.github+json',
					'User-Agent'    => 'WordPress-Form-Quizz-Plugin',
				),
				'timeout'  => 300,
				'stream'   => true,
				'filename' => $tmp_file,
			)
		);

		if ( is_wp_error( $response ) ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Temporary file.
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Temporary file.
			return new \WP_Error(
				'fqi3_download_failed',
				sprintf(
					/* translators: %d: HTTP status code returned by GitHub */
					__( 'The update package could not be downloaded (GitHub answered %d). Check that the GitHub token can read this repository.', 'form-quizz-fqi3' ),
					$code
				)
			);
		}

		return $tmp_file;
	}

	/**
	 * Download the package of this plugin through the license provider (LUS), sha256 verified.
	 *
	 * @return string|\WP_Error Path of the downloaded file.
	 * @since 3.7.0
	 */
	private function download_from_provider() {
		$provider = $this->provider();
		$package  = $provider->resolveDownload( $this->plugin_slug, $this->get_current_version() );

		if ( null === $package ) {
			$error = $provider->getLastError();
			return new \WP_Error(
				'fqi3_lus_' . ( $error['code'] ?? 'no_package' ),
				(string) ( $error['message'] ?? __( 'The update package could not be resolved.', 'form-quizz-fqi3' ) )
			);
		}

		return self::download_verified( $package['url'], $package['sha256'], $package['headers'], true, $package['signed'] ?? array() );
	}

	/**
	 * Download a package to a temporary file and verify its sha256 and, for LUS packages, its Ed25519 signature.
	 *
	 * Shared by the updater and FQI3_Addons_Page::install_addon().
	 *
	 * @param string               $url            Package URL.
	 * @param string               $sha256         Expected hash (hex). Empty = no verification (GitHub provider only).
	 * @param array<string,string> $headers        Extra request headers (e.g. GitHub Authorization).
	 * @param bool                 $require_sha256 Server provider: refuse the package without a valid sha256 AND without a valid signature.
	 * @param array<string,mixed>  $signed         Signature data from the provider (slug, version, channel, signature, kid).
	 * @return string|\WP_Error Path of the temporary file (the caller deletes it), or an error.
	 * @since 3.7.0
	 */
	public static function download_verified( string $url, string $sha256 = '', array $headers = array(), bool $require_sha256 = false, array $signed = array() ) {
		if ( $require_sha256 && 1 !== preg_match( '/^[a-f0-9]{64}$/i', $sha256 ) ) {
			return new \WP_Error( 'fqi3_sha256_missing', __( 'The update was refused: the server did not provide a valid checksum.', 'form-quizz-fqi3' ) );
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp_file = wp_tempnam( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		if ( ! $tmp_file ) {
			return new \WP_Error( 'fqi3_download_tmp', __( 'Could not create a temporary file for the update.', 'form-quizz-fqi3' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers'  => $headers + array( 'User-Agent' => 'WordPress-Form-Quizz-Plugin' ),
				'timeout'  => 300,
				'stream'   => true,
				'filename' => $tmp_file,
			)
		);

		if ( is_wp_error( $response ) ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Temporary file.
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Temporary file.
			return new \WP_Error(
				'fqi3_download_failed',
				sprintf(
					/* translators: %d: HTTP status code returned by the download server */
					__( 'The update package could not be downloaded (the server answered %d).', 'form-quizz-fqi3' ),
					$code
				)
			);
		}

		if ( filesize( $tmp_file ) < 100 ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Temporary file.
			return new \WP_Error( 'fqi3_download_empty', __( 'Downloaded file is too small or empty.', 'form-quizz-fqi3' ) );
		}

		if ( '' !== $sha256 && ! hash_equals( strtolower( $sha256 ), (string) hash_file( 'sha256', $tmp_file ) ) ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Temporary file.
			return new \WP_Error( 'fqi3_sha256_mismatch', __( 'The update package is corrupted (checksum mismatch) and was not installed.', 'form-quizz-fqi3' ) );
		}

		// Signature: mandatory in server mode (unless explicitly allowed on a local site); a signature that is
		// present is always verified, whatever the mode.
		if ( $require_sha256 || ! empty( $signed['signature'] ) ) {
			$verified = Package_Signature::verify( $signed, (string) hash_file( 'sha256', $tmp_file ) );
			if ( is_wp_error( $verified ) ) {
				@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Temporary file.
				return $verified;
			}
		}

		return $tmp_file;
	}

	/**
	 * Provider in use (shared across the core and add-ons updaters unless one was injected).
	 */
	private function provider(): LicenseProviderInterface {
		return $this->injected_provider ?? LicenseProviderFactory::make();
	}

	/**
	 * Rename the extracted package folder to the plugin slug when this plugin is being updated.
	 *
	 * Without it, an update served from the GitHub zipball (no .zip asset on the release) would be installed in
	 * a folder named after the repository and commit, next to the real plugin, and the plugin would be deactivated.
	 *
	 * @param string|\WP_Error $source        Extracted package folder.
	 * @param string           $remote_source Folder containing the extracted package.
	 * @param \WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array            $hook_extra    Context of the upgrade (`plugin` is the plugin basename).
	 * @return string|\WP_Error
	 * @since 3.5.0
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader = null, $hook_extra = array() ) {
		if ( is_wp_error( $source ) || ! is_array( $hook_extra ) || ( $hook_extra['plugin'] ?? '' ) !== $this->plugin_file ) {
			return $source;
		}

		global $wp_filesystem;

		$desired = trailingslashit( $remote_source ) . $this->plugin_slug . '/';
		if ( trailingslashit( $source ) === $desired ) {
			return $source;
		}

		if ( ! $wp_filesystem || ! $wp_filesystem->move( $source, $desired, true ) ) {
			return new \WP_Error( 'fqi3_rename_failed', __( 'Could not prepare the plugin folder for the update.', 'form-quizz-fqi3' ) );
		}

		return $desired;
	}

	/**
	 * Load plugin data
	 *
	 * @return void
	 */
	private function load_plugin_data() {
		if ( empty( $this->plugin_data ) ) {
			$plugin_path = WP_PLUGIN_DIR . '/' . $this->plugin_file;
			if ( file_exists( $plugin_path ) ) {
				$this->plugin_data = get_plugin_data( $plugin_path );
			} else {
				$this->plugin_data = array();
			}
		}
	}

	/**
	 * Check for plugin updates
	 *
	 * One grouped request covers every plugin registered by an updater (core + add-ons); the provider caches it.
	 * When the provider fails (server down, invalid license), the transient is left untouched: no notice, no fatal.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function check_for_updates( $transient ) {
		$this->load_plugin_data();

		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Check if our plugin is in the list of plugins checked
		if ( ! isset( $transient->checked[ $this->plugin_file ] ) ) {
			return $transient;
		}

		$current_version = (string) $transient->checked[ $this->plugin_file ];
		$plugins         = array_intersect_key( $transient->checked, self::$registry );
		$updates         = $this->provider()->checkUpdates( $plugins );

		if ( null === $updates ) {
			return $transient;
		}

		$update = $updates[ $this->plugin_file ] ?? null;

		if ( is_array( $update ) && ! empty( $update['new_version'] ) && version_compare( $update['new_version'], $current_version, '>' ) ) {
			$transient->response[ $this->plugin_file ] = (object) array(
				'slug'          => $this->plugin_slug,
				'plugin'        => $this->plugin_file,
				'new_version'   => $update['new_version'],
				'url'           => $update['url'] ?? ( $this->plugin_data['PluginURI'] ?? '' ),
				'package'       => $update['package'] ?? '',
				'requires'      => $update['requires'] ?? $this->extract_requirement( 'Requires at least' ),
				'requires_php'  => $update['requires_php'] ?? $this->extract_requirement( 'Requires PHP' ),
				'tested'        => $update['tested'] ?? $this->extract_requirement( 'Tested up to' ),
				'compatibility' => array(),
			);
		} else {
			// Plugin is up to date
			$transient->no_update[ $this->plugin_file ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $current_version,
				'url'         => $this->plugin_data['PluginURI'] ?? '',
				'package'     => '',
			);
		}

		return $transient;
	}


	/**
	 * Get plugin information for the update popup
	 *
	 * @param mixed  $res
	 * @param string $action
	 * @param object $args
	 * @return mixed
	 */
	public function plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || ( $args->slug ?? '' ) !== $this->plugin_slug ) {
			return $res;
		}

		$this->load_plugin_data();

		$remote = $this->get_latest_release();
		if ( ! $remote || ! isset( $remote['version'] ) ) {
			return $res;
		}

		$res                 = new \stdClass();
		$res->name           = $this->plugin_data['Name'] ?? $this->plugin_slug;
		$res->slug           = $this->plugin_slug;
		$res->plugin_name    = $this->plugin_file;
		$res->version        = $remote['version'] ?? '';
		$res->author         = $this->plugin_data['Author'] ?? 'Unknown';
		$res->author_profile = $this->plugin_data['AuthorURI'] ?? '';
		$res->last_updated   = $remote['last_updated'] ?? '';
		$res->homepage       = $this->plugin_data['PluginURI'] ?? '';
		$res->requires       = $remote['requires'] ?? $this->extract_requirement( 'Requires at least' );
		$res->tested         = $remote['tested'] ?? $this->extract_requirement( 'Tested up to' );
		$res->requires_php   = $remote['requires_php'] ?? $this->extract_requirement( 'Requires PHP' );
		$res->sections       = array(
			'description' => $this->plugin_data['Description'] ?? '',
			'changelog'   => '' !== ( $remote['changelog'] ?? '' ) ? $remote['changelog'] : __( 'No changelog available.', 'form-quizz-fqi3' ),
		);
		$res->download_link  = $remote['download_url'] ?? '';

		return $res;
	}

	/**
	 * Add custom update message
	 *
	 * @param array $plugin_data
	 * @param object $response
	 * @return void
	 */
	public function update_message( $plugin_data, $response ) {
		$this->load_plugin_data();

		if ( isset( $response->new_version ) ) {
			echo '<div class="update-message notice notice-warning notice-alt"><p>';
			printf(
				/* translators: 1 = plugin name, 2 = details URL, 3 = new version, 4 = update URL */
				__( 'There is a new version of %1$s available. <a href="%2$s" class="thickbox open-plugin-details-modal">View version %3$s details</a> or <a href="%4$s">update now</a>.', 'form-quizz-fqi3' ),
				$this->plugin_data['Name'],
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->plugin_slug . '&TB_iframe=true&width=600&height=550' ) ),
				$response->new_version,
				wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $this->plugin_file ), 'upgrade-plugin_' . $this->plugin_file )
			);
			echo '</p></div>';
		}
	}

	/**
	 * Get the latest release known to the provider (display data: version, changelog, requirements).
	 *
	 * The download URL is informative only: the real package is resolved at download time.
	 *
	 * @return array|false
	 */
	private function get_latest_release() {
		$info = $this->provider()->getProductInfo( $this->plugin_slug );

		if ( ! $info || empty( $info['version'] ) ) {
			return false;
		}

		return array(
			'version'      => $info['version'],
			'download_url' => $info['download_link'] ?? '',
			'last_updated' => $info['last_updated'] ?? '',
			'changelog'    => $info['sections']['changelog'] ?? '',
			'requires'     => $info['requires'] ?? null,
			'tested'       => $info['tested'] ?? null,
			'requires_php' => $info['requires_php'] ?? null,
		);
	}


	/**
	 * Extract requirement from plugin data
	 *
	 * @param string $field
	 * @return string
	 */
	private function extract_requirement( string $field ): string {
		// get_plugin_data() exposes these headers as RequiresWP / RequiresPHP; "Tested up to" is a readme.txt field only.
		$keys  = array(
			'Requires at least' => 'RequiresWP',
			'Requires PHP'      => 'RequiresPHP',
		);
		$value = $this->plugin_data[ $keys[ $field ] ?? $field ] ?? '';
		return ! empty( $value ) ? (string) $value : '';
	}

	/**
	 * Clear update cache
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( $this->cache_key );
		$this->provider()->clearCache();
	}

	/**
	 * Force check for updates
	 *
	 * @return object
	 */
	public function force_check(): object {
		$this->clear_cache();

		$release = $this->get_latest_release();

		return is_array( $release ) ? (object) $release : new \stdClass();
	}

	/**
	 * Latest release known to the provider, as an array (cached by the provider; no forced refresh).
	 *
	 * @return array|false Keys: version, download_url, last_updated, changelog, requires, tested, requires_php.
	 * @since 3.7.0
	 */
	public function get_latest() {
		return $this->get_latest_release();
	}

	/**
	 * Get current version
	 *
	 * @return string
	 */
	public function get_current_version(): string {
		$this->load_plugin_data();
		return $this->plugin_data['Version'] ?? '0.0.0';
	}

	/**
	 * Check if update is available
	 *
	 * @return bool
	 */
	public function is_update_available(): bool {
		$current = $this->get_current_version();
		$updates = $this->provider()->checkUpdates( array( $this->plugin_file => $current ) );

		return is_array( $updates ) && isset( $updates[ $this->plugin_file ] );
	}
}

/**
 * Helper function to initialize updater for a plugin
 *
 * @param string $plugin_file
 * @param string $github_repo
 * @param string $github_username
 * @return Plugin_Updater
 */
function init_plugin_updater( $plugin_file, $github_repo, $github_username = 'Webpixelia' ) {
	return new Plugin_Updater( $plugin_file, $github_repo, $github_username );
}
