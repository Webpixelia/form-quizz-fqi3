<?php
namespace Form_Quizz_FQI3;

/**
 * Plugin Updater Class
 *
 * This class handles updates for a WordPress plugin hosted on GitHub.
 * It checks for new releases, retrieves plugin information, and manages update notifications.
 * Usage:
 * $updater = new Plugin_Updater(__FILE__, 'repository-name', 'GitHubUsername');
 * @package Form_Quizz_FQI3
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin_Updater {
	private $github_username;
	private $github_token;
	private $plugin_data;
	private $plugin_slug;
	private $plugin_file;
	private $github_repo;
	private $cache_key;
	private $cache_expiration = 12 * HOUR_IN_SECONDS;

	public function __construct( $plugin_file, $github_repo, $github_username = 'Webpixelia' ) {
		$this->plugin_file     = $plugin_file; // ex: 'fqi3-addon/fqi3-addon.php'
		$this->plugin_slug     = dirname( $plugin_file ); // ex: 'fqi3-addon'
		$this->github_username = $github_username;
		$this->github_repo     = $github_repo;
		$this->github_token    = defined( 'GITHUB_PERSONAL_TOKEN' ) ? GITHUB_PERSONAL_TOKEN : '';
		$this->cache_key       = 'fqi3_update_' . md5( $plugin_file );

		// Hook into the update check
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

		// Hook into the plugin information screen
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

		// Add custom update message
		add_action( "in_plugin_update_message-{$plugin_file}", array( $this, 'update_message' ), 10, 2 );

		// GitHub zipballs extract to "<owner>-<repo>-<sha>": give the package the plugin folder name
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
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
	 * @since 3.4.1
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

		// Get the latest release from GitHub
		$latest_release = $this->get_latest_release();

		$current_version = $this->plugin_data['Version'] ?? '0.0.0';
		$latest_version  = $latest_release['version'] ?? null;

		if ( $latest_version && version_compare( $latest_version, $current_version, '>' ) ) {
			$plugin_data = array(
				'slug'          => $this->plugin_slug,
				'plugin'        => $this->plugin_file,
				'new_version'   => $latest_version,
				'url'           => $this->plugin_data['PluginURI'] ?? '',
				'package'       => $latest_release['download_url'] ?? '',
				'requires'      => $this->extract_requirement( 'Requires at least' ),
				'requires_php'  => $this->extract_requirement( 'Requires PHP' ),
				'tested'        => $this->extract_requirement( 'Tested up to' ),
				'compatibility' => array(),
			);

			$transient->response[ $this->plugin_file ] = (object) $plugin_data;
		} else {
			// Plugin is up to date or no version available
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
		$this->load_plugin_data();

		if ( 'plugin_information' !== $action || $args->slug !== $this->plugin_slug ) {
			return $res;
		}

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
		$res->requires       = $this->extract_requirement( 'Requires at least' ) ?? '';
		$res->tested         = $this->extract_requirement( 'Tested up to' ) ?? '';
		$res->requires_php   = $this->extract_requirement( 'Requires PHP' ) ?? '';
		$res->sections       = array(
			'description' => $this->plugin_data['Description'] ?? '',
			'changelog'   => $remote['changelog'] ?? __( 'No changelog available.', 'form-quizz-fqi3' ),
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
	 * Get the latest release from GitHub
	 * @return array|false
	 */
	private function get_latest_release() {
		$transient_key = 'fqi3_release_check_' . md5( $this->github_username . '/' . $this->github_repo );
		$cached        = get_transient( $transient_key );

		// If we have a valid cache and no release, return false
		if ( $cached === 'no_release' ) {
			return false;
		}

		// If we have a cached *formatted* response, return it
		if ( is_array( $cached ) && isset( $cached['version'] ) ) {
			return $cached;
		}

		$url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $this->github_username, $this->github_repo );

		$args = array(
			'headers' => array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress-Form-Quizz-Plugin',
			),
			'timeout' => 15,
		);

		if ( ! empty( $this->github_token ) ) {
			$args['headers']['Authorization'] = 'token ' . $this->github_token;
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			set_transient( $transient_key, 'no_release', HOUR_IN_SECONDS );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code === 404 ) {
			set_transient( $transient_key, 'no_release', DAY_IN_SECONDS );
			return false;
		}

		if ( $response_code !== 200 ) {
			set_transient( $transient_key, 'no_release', HOUR_IN_SECONDS );
			return false;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $release ) || ! isset( $release['tag_name'] ) ) {
			set_transient( $transient_key, 'no_release', HOUR_IN_SECONDS );
			return false;
		}

		// Format the release data
		$release_data = array(
			'version'      => ltrim( $release['tag_name'], 'v' ),
			'download_url' => $this->get_download_url( $release ),
			'last_updated' => $release['published_at'] ?? '',
			'changelog'    => $this->parse_changelog( $release['body'] ?? '' ),
		);

		// Cache the *formatted* result for 12 hours
		set_transient( $transient_key, $release_data, 12 * HOUR_IN_SECONDS );

		return $release_data;
	}


	/**
	 * Get the download URL from release data
	 *
	 * @param array $release
	 * @return string
	 */
	private function get_download_url( array $release ): string {
		// First, try to get a .zip asset
		if ( ! empty( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( strpos( $asset['name'], '.zip' ) !== false ) {
					return $asset['browser_download_url'];
				}
			}
		}

		// Fallback to zipball_url (GitHub auto-generated zip)
		return $release['zipball_url'] ?? '';
	}

	/**
	 * Parse changelog from release body
	 *
	 * @param string $content
	 * @return string
	 */
	private function parse_changelog( string $content ): string {
		if ( empty( $content ) ) {
			return __( 'No changelog available.', 'form-quizz-fqi3' );
		}

		// Convert Markdown-style headers to HTML
		$content = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $content );
		$content = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $content );
		$content = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $content );

		// Convert bullet points
		$content = preg_replace( '/^\* (.+)$/m', '<li>$1</li>', $content );
		$content = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $content );

		// Convert line breaks
		$content = wpautop( $content );

		return $content;
	}

	/**
	 * Extract requirement from plugin data
	 *
	 * @param string $field
	 * @return string
	 */
	private function extract_requirement( string $field ): string {
		$value = $this->plugin_data[ $field ] ?? '';
		return ! empty( $value ) ? $value : '';
	}

	/**
	 * Clear update cache
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( $this->cache_key );
	}

	/**
	 * Force check for updates
	 *
	 * @return object
	 */
	public function force_check(): object {
		$this->clear_cache();
		$transient_key = 'fqi3_release_check_' . md5( $this->github_username . '/' . $this->github_repo );
		delete_transient( $transient_key );

		$release = $this->get_latest_release();

		return is_array( $release ) ? (object) $release : ( $release ?? new \stdClass() );
	}

	/**
	 * Get current version
	 *
	 * @return string
	 */
	public function get_current_version(): string {
		return $this->plugin_data['Version'] ?? '0.0.0';
	}

	/**
	 * Check if update is available
	 *
	 * @return bool
	 */
	public function is_update_available(): bool {
		$this->load_plugin_data();
		$latest = $this->get_latest_release();
		if ( ! $latest || empty( $latest['version'] ) ) {
			return false;
		}

		$current_version = $this->get_current_version();
		$is_update       = version_compare( $latest['version'], $current_version, '>' );

		return $is_update;
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
