<?php
namespace Form_Quizz_FQI3;

use Form_Quizz_FQI3\LicenseProviders\LicenseProviderFactory;
use Form_Quizz_FQI3\LicenseProviders\ServerLicenseProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FQI3_License_Settings {
	private const NONCE_ACTION = 'fqi3_license_action';

	public function __construct() {
		// The GitHub token is read from the GITHUB_PERSONAL_TOKEN constant defined by the site owner in wp-config.php.
		// The plugin no longer edits wp-config.php itself.

		// This class is instantiated on admin_init (also for admin-ajax.php): the handlers are registered here.
		add_action( 'wp_ajax_fqi3_license_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_fqi3_license_validate', array( $this, 'ajax_validate' ) );
		add_action( 'wp_ajax_fqi3_license_remove', array( $this, 'ajax_remove' ) );
	}

	/**
	 * Check if GitHub token is defined and not empty
	 *
	 * @return bool True if token is defined and not empty
	 */
	public static function has_github_token() {
		$token = self::get_current_github_token();
		return ! empty( $token );
	}

	public function register_settings() {
		$this->add_settings_section();
	}

	private function add_settings_section() {
		$settings = fqi3_options_settings_sections();
		$sections = $settings['sections']['license_settings'];

		add_settings_section(
			$sections['id'],
			$sections['title'],
			array( $this, 'license_section_callback' ),
			fqi3_get_options_page_slug(),
			array(
				'before_section' => '<div id="' . esc_attr( array_search( $sections, $settings['sections'] ) ) . '" class="fqi3-section-options-page">',
				'after_section'  => '</div>',
			)
		);

		$this->add_license_settings_fields();
	}

	private function add_license_settings_fields() {
		add_settings_field(
			'fqi3_license_key',
			__( 'License key', 'form-quizz-fqi3' ),
			array( $this, 'license_key_callback' ),
			fqi3_get_options_page_slug(),
			'fqi3_license_section'
		);

		// The GitHub token is a development-site setting: hidden on production sites that use a license.
		if ( ! LicenseProviderFactory::isServerMode() || self::has_github_token() ) {
			add_settings_field(
				'fqi3_github_token',
				__( 'GitHub Personal Token', 'form-quizz-fqi3' ),
				array( $this, 'github_token_callback' ),
				fqi3_get_options_page_slug(),
				'fqi3_license_section'
			);
		}
	}

	/**
	 * License section callback function.
	 */
	public function license_section_callback() {
		echo '<p>' . esc_html__( 'Enter your license key to install add-ons and receive updates for the core plugin and installed add-ons. Development sites can use a GitHub token instead.', 'form-quizz-fqi3' ) . '</p>';
	}


	/**
	 * Capability required to change the license (multisite: the license belongs to the network).
	 */
	private static function capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Mask a license key: only the last 4 characters are shown.
	 */
	public static function mask_key( string $key ): string {
		return '' === $key ? '' : str_repeat( '•', 8 ) . substr( $key, -4 );
	}

	/**
	 * Expiry line of the license screen: label and value (both empty = nothing to show).
	 *
	 * LUS does not send the expiry date inside a `license_expired` error: the date stored in the status is then the one
	 * of the last successful check. It is shown only if it really is in the past; otherwise (date unknown or still in the
	 * future, e.g. the license was shortened on the server) the screen says when the expiry was detected.
	 *
	 * @param array $status Value of LicenseProviderFactory::getStatus().
	 * @return array{label: string, value: string}
	 */
	public static function expires_info( array $status ): array {
		$expires = $status['expires_at'] ?? null;
		$date    = '';
		$ts      = 0;

		if ( is_string( $expires ) && '' !== $expires ) {
			$parsed = strtotime( $expires );
			$ts     = false === $parsed ? 0 : $parsed;
			$date   = (string) mysql2date( get_option( 'date_format' ), $expires );
		}

		if ( 'expired' === ( $status['state'] ?? '' ) ) {
			if ( $ts > 0 && $ts <= time() ) {
				return array(
					'label' => __( 'Expired on:', 'form-quizz-fqi3' ),
					'value' => $date,
				);
			}

			$detected = (int) ( $status['expired_detected_at'] ?? 0 );

			return array(
				'label' => __( 'Expired:', 'form-quizz-fqi3' ),
				'value' => $detected > 0
					/* translators: %s: time elapsed since the expiry was detected, e.g. "2 hours" */
					? sprintf( __( 'detected %s ago', 'form-quizz-fqi3' ), human_time_diff( $detected ) )
					: __( 'renew the license to receive updates', 'form-quizz-fqi3' ),
			);
		}

		if ( '' !== $date ) {
			return array(
				'label' => __( 'Expires:', 'form-quizz-fqi3' ),
				'value' => $date,
			);
		}

		return array(
			'label' => '',
			'value' => '',
		);
	}

	/**
	 * "used / max" text of the sites counter ('' when unknown).
	 *
	 * @param array $status Value of LicenseProviderFactory::getStatus().
	 */
	public static function instances_label( array $status ): string {
		$instances = $status['instances'] ?? null;

		if ( ! is_array( $instances ) ) {
			return '';
		}

		return (int) ( $instances['used'] ?? 0 ) . ' / ' . ( isset( $instances['max'] ) ? (int) $instances['max'] : '∞' );
	}

	/**
	 * Human-readable license state from the stored status (no HTTP request).
	 *
	 * @param array $status Value of LicenseProviderFactory::getStatus().
	 */
	public static function state_label( array $status ): string {
		switch ( $status['state'] ?? '' ) {
			case 'active':
				return __( 'Active', 'form-quizz-fqi3' );
			case 'inactive':
				return __( 'Not activated on this site', 'form-quizz-fqi3' );
			case 'expired':
				return __( 'Expired', 'form-quizz-fqi3' );
			case 'invalid':
				return __( 'Invalid', 'form-quizz-fqi3' );
			default:
				return __( 'Unknown (not checked yet)', 'form-quizz-fqi3' );
		}
	}

	/**
	 * License key field. It has no `name` attribute on purpose: the "Save settings" button of the options form
	 * never submits it; the key is sent by AJAX (nonce + capability) and activated at once.
	 */
	public function license_key_callback() {
		$key       = LicenseProviderFactory::getLicenseKey();
		$source    = LicenseProviderFactory::licenseKeySource(); // constant | option | none.
		$status    = LicenseProviderFactory::getStatus();
		$can_edit  = current_user_can( self::capability() );
		$checked   = (int) ( $status['checked_at'] ?? 0 );
		$instance  = LicenseProviderFactory::makeServer()->getInstanceId();
		$expires   = self::expires_info( $status );
		$instances = self::instances_label( $status );
		$retry_min = ServerLicenseProvider::retryAfterMinutes( $status );
		?>
		<div id="fqi3-license"
			data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>"
			data-network-error="<?php esc_attr_e( 'Network error. Try again.', 'form-quizz-fqi3' ); ?>">
			<p class="mb-2">
				<strong><?php esc_html_e( 'Key:', 'form-quizz-fqi3' ); ?></strong>
				<code id="fqi3-license-key"><?php echo esc_html( '' !== $key ? self::mask_key( $key ) : '—' ); ?></code>
				<?php if ( 'constant' === $source ) : ?>
					<em class="text-muted"><?php esc_html_e( 'defined in wp-config.php', 'form-quizz-fqi3' ); ?></em>
				<?php endif; ?>
			</p>
			<p class="mb-2">
				<strong><?php esc_html_e( 'Status:', 'form-quizz-fqi3' ); ?></strong>
				<span id="fqi3-license-state"><?php echo esc_html( self::state_label( $status ) ); ?></span>
				<?php if ( $checked > 0 ) : ?>
					<small class="text-muted" id="fqi3-license-checked">
						<?php
						/* translators: %s: time elapsed since the last license check, e.g. "2 hours" */
						printf( esc_html__( '(checked %s ago)', 'form-quizz-fqi3' ), esc_html( human_time_diff( $checked ) ) );
						?>
					</small>
				<?php endif; ?>
			</p>
			<p class="mb-2" id="fqi3-license-expires-row"<?php echo '' === $expires['value'] ? ' hidden' : ''; ?>>
				<strong id="fqi3-license-expires-label"><?php echo esc_html( $expires['label'] ); ?></strong>
				<span id="fqi3-license-expires"><?php echo esc_html( $expires['value'] ); ?></span>
			</p>
			<p class="mb-2" id="fqi3-license-instances-row"<?php echo '' === $instances ? ' hidden' : ''; ?>>
				<strong><?php esc_html_e( 'Sites:', 'form-quizz-fqi3' ); ?></strong>
				<span id="fqi3-license-instances"><?php echo esc_html( $instances ); ?></span>
			</p>
			<p class="text-warning mb-2" id="fqi3-license-retry"<?php echo $retry_min > 0 ? '' : ' hidden'; ?>>
				<?php
				if ( $retry_min > 0 ) {
					echo esc_html( ServerLicenseProvider::messageForRetry( $retry_min ) );
				}
				?>
			</p>
			<p class="text-muted mb-2">
				<?php
				/* translators: %s: domain name used to identify this site to the license server */
				printf( esc_html__( 'This site identifies itself as %s.', 'form-quizz-fqi3' ), '<code>' . esc_html( $instance ) . '</code>' );
				?>
			</p>

			<?php if ( 'constant' !== $source && $can_edit ) : ?>
				<div class="d-flex gap-2 mb-2">
					<input type="text" id="fqi3-license-input" class="form-control form-control-sm" style="max-width:26rem"
						autocomplete="off" spellcheck="false" maxlength="64" placeholder="LUS-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX">
					<button type="button" class="btn btn-primary btn-sm" data-fqi3-license="save"><?php esc_html_e( 'Activate', 'form-quizz-fqi3' ); ?></button>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $key && $can_edit ) : ?>
				<button type="button" class="btn btn-outline-secondary btn-sm" data-fqi3-license="validate"><?php esc_html_e( 'Check status', 'form-quizz-fqi3' ); ?></button>
				<?php if ( 'option' === $source ) : ?>
					<button type="button" class="btn btn-outline-danger btn-sm" data-fqi3-license="remove"><?php esc_html_e( 'Remove key', 'form-quizz-fqi3' ); ?></button>
				<?php endif; ?>
			<?php endif; ?>
			<p id="fqi3-license-msg" class="mt-2 mb-0" role="status" aria-live="polite"></p>
		</div>
		<?php
	}

	/**
	 * Light throttle: 5 attempts / 10 minutes / user. LUS blocks an IP after 10 failed key checks per hour,
	 * which would also block every other site behind the same address.
	 */
	private function throttle(): bool {
		$key   = 'fqi3_lic_try_' . get_current_user_id();
		$count = (int) get_transient( $key );
		if ( $count >= 5 ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	private function guard(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'form-quizz-fqi3' ) ), 403 );
		}

		if ( ! $this->throttle() ) {
			// Local limit of this screen, not an answer of the license server: the message says so.
			wp_send_json_error( array( 'message' => __( 'Too many attempts from this account. Wait a few minutes before trying again.', 'form-quizz-fqi3' ) ), 429 );
		}
	}

	/**
	 * Answer the AJAX call from the stored status (never leaks the key: masked only).
	 *
	 * @param array|null $error Provider error ({code, message, http}) or null on success.
	 */
	private function reply( ?array $error ): void {
		$status = LicenseProviderFactory::getStatus();
		$expires = self::expires_info( $status );
		$data    = array(
			'state'           => self::state_label( $status ),
			'key'             => self::mask_key( LicenseProviderFactory::getLicenseKey() ),
			'expires_at'      => $status['expires_at'] ?? null,
			'instances'       => $status['instances'] ?? null,
			'expires_label'   => $expires['label'],
			'expires_value'   => $expires['value'],
			'instances_label' => self::instances_label( $status ),
			'retry_message'   => '',
		);

		if ( null !== $error ) {
			// The provider message already carries the wait time for a 429 (Retry-After).
			$data['message'] = '' !== (string) ( $error['message'] ?? '' )
				? (string) $error['message']
				: ServerLicenseProvider::messageForCode( (string) $error['code'] );

			$retry_min = ServerLicenseProvider::retryAfterMinutes( $status );
			if ( 'rate_limited' === (string) $error['code'] && $retry_min > 0 ) {
				$data['retry_message'] = ServerLicenseProvider::messageForRetry( $retry_min );
			}

			wp_send_json_error( $data );
		}

		$data['message'] = __( 'License verified.', 'form-quizz-fqi3' );
		wp_send_json_success( $data );
	}

	/**
	 * AJAX fqi3_license_save: validate the format, store the key, call POST /v1/licenses/activate.
	 * On failure the previous key is restored (a typo must not erase a working license).
	 */
	public function ajax_save(): void {
		$this->guard();

		if ( 'constant' === LicenseProviderFactory::licenseKeySource() ) {
			wp_send_json_error( array( 'message' => __( 'The key is defined in wp-config.php.', 'form-quizz-fqi3' ) ) );
		}

		$key = strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in guard().
		if ( 1 !== preg_match( '/^[A-Z0-9]{2,10}(?:-[A-Z0-9]{5}){5}$/', $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid license key format.', 'form-quizz-fqi3' ) ) );
		}

		$previous = LicenseProviderFactory::getLicenseKey();
		self::store_key( $key );
		LicenseProviderFactory::reset();

		$server = LicenseProviderFactory::makeServer();
		$server->clearCache();

		if ( ! $server->activate() ) {
			self::store_key( $previous );
			LicenseProviderFactory::reset();
			$this->reply( $server->getLastError() );
		}

		$this->reply( null );
	}

	/**
	 * AJAX fqi3_license_validate: POST /v1/licenses/validate, updates the stored status.
	 */
	public function ajax_validate(): void {
		$this->guard();

		$server = LicenseProviderFactory::makeServer();
		$server->clearCache();
		$server->validate();

		$this->reply( $server->getLastError() );
	}

	/**
	 * AJAX fqi3_license_remove: release the activation (best effort), then delete the stored key.
	 */
	public function ajax_remove(): void {
		$this->guard();

		if ( 'option' !== LicenseProviderFactory::licenseKeySource() ) {
			wp_send_json_error( array( 'message' => __( 'The key is defined in wp-config.php.', 'form-quizz-fqi3' ) ) );
		}

		LicenseProviderFactory::makeServer()->deactivate();
		self::store_key( '' );
		LicenseProviderFactory::saveStatus( array() );
		LicenseProviderFactory::makeServer()->clearCache();
		LicenseProviderFactory::reset();

		$this->reply( null );
	}

	/**
	 * autoload = false: the key is not loaded on every request. Kept out of `fqi3_options`, which feeds the REST API whitelists.
	 */
	private static function store_key( string $key ): void {
		if ( is_multisite() ) {
			update_site_option( LicenseProviderFactory::OPTION_KEY, $key );
		} else {
			update_option( LicenseProviderFactory::OPTION_KEY, $key, false );
		}
	}

	/**
	 * GitHub token field callback function.
	 *
	 * Read-only: the token is defined as a constant in wp-config.php by the site owner.
	 */
	public function github_token_callback() {
		$token      = self::get_current_github_token();
		$is_defined = self::has_github_token();
		?>
		<div class="github-token-wrapper">
			<?php if ( $is_defined ) : ?>
				<p class="mb-2">
					<strong><?php esc_html_e( 'Current token:', 'form-quizz-fqi3' ); ?></strong>
					<code class="bg-light p-1 rounded"><?php echo esc_html( '••••••••••' . substr( $token, -4 ) ); ?></code>
				</p>
			<?php else : ?>
				<p class="text-danger mb-2">
					<?php esc_html_e( 'No GitHub token is defined.', 'form-quizz-fqi3' ); ?>
				</p>
			<?php endif; ?>

			<p class="text-muted mb-2">
				<?php esc_html_e( 'To set or change the token, add this line to your wp-config.php file (above the line "That\'s all, stop editing!"), then reload this page:', 'form-quizz-fqi3' ); ?>
			</p>
			<pre class="bg-light p-2 rounded"><code>define( 'GITHUB_PERSONAL_TOKEN', 'your_token_here' );</code></pre>

			<p class="text-muted mb-0">
				<a href="https://github.com/settings/tokens" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
					<?php esc_html_e( 'Create a new token on GitHub', 'form-quizz-fqi3' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Get the GitHub token defined by the GITHUB_PERSONAL_TOKEN constant.
	 *
	 * @return string Empty string when the constant is not defined.
	 */
	private static function get_current_github_token() {
		return defined( 'GITHUB_PERSONAL_TOKEN' ) ? (string) GITHUB_PERSONAL_TOKEN : '';
	}

	/**
	 * Validate a GitHub token
	 *
	 * @param string $token
	 * @return bool|WP_Error
	 */
	public static function validate_github_token( $token ) {
		if ( empty( $token ) ) {
			return new \WP_Error( 'empty_token', __( 'Token cannot be empty', 'form-quizz-fqi3' ) );
		}

		// Test API GitHub to validate the token
		$response = wp_remote_get(
			'https://api.github.com/user',
			array(
				'headers' => array(
					'Authorization' => 'token ' . $token,
					'User-Agent'    => 'WordPress-Plugin',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_error', __( 'Failed to validate token with GitHub API.', 'form-quizz-fqi3' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			return new \WP_Error( 'invalid_token', __( 'Invalid GitHub token.', 'form-quizz-fqi3' ) );
		}

		return true;
	}
}