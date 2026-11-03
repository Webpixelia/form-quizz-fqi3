<?php
/**
 * FQI3_Api_Settings Class
 *
 * This class manages API Rest settings for the FQI3 plugin.
 * It provides options for enabling and securing the API.
 *
 * @package    Form Quizz FQI3
 * @subpackage Settings
 * @since      1.6.0
 * @version    3.0.0
 */

namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'FQI3_Api_Settings' ) ) :
	class FQI3_Api_Settings {
		public function register_settings() {
			$this->add_settings_section();
		}

		public function add_settings_section() {
			$settings = fqi3_options_settings_sections();
			$sections = $settings['sections']['api'];
			add_settings_section(
				$sections['id'],
				$sections['title'],
				null,
				fqi3_get_options_page_slug(),
				array(
					'before_section' => '<div id="' . esc_attr( array_search( $sections, $settings['sections'] ) ) . '" class="fqi3-section-options-page">',
					'after_section'  => '</div>',
				)
			);

			$this->add_api_fields();
		}

		public function add_api_fields() {
			add_settings_field(
				'fqi3_quiz_api',
				__( 'Enable API Rest', 'form-quizz-fqi3' ),
				array( $this, 'render_enable_api' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			add_settings_field(
				'fqi3_quiz_api_test',
				__( 'API Test', 'form-quizz-fqi3' ),
				array( $this, 'render_api_test_ui' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			add_settings_field(
				'fqi3_quiz_api_token',
				__( 'API Token', 'form-quizz-fqi3' ),
				array( $this, 'render_generate_token_button' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			// Expiration mode
			add_settings_field(
				'fqi3_quiz_api_token_exp_mode',
				__( 'Token Expiration', 'form-quizz-fqi3' ),
				array( $this, 'render_token_expiration_mode' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			// Expiration days
			add_settings_field(
				'fqi3_quiz_api_token_exp_days',
				__( 'Expiration (days)', 'form-quizz-fqi3' ),
				array( $this, 'render_token_expiration_days' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			// Overlap (grace) days
			add_settings_field(
				'fqi3_quiz_api_token_overlap_days',
				__( 'Grace period after expiry (days)', 'form-quizz-fqi3' ),
				array( $this, 'render_token_overlap_days' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);

			// HTTPS only
			add_settings_field(
				'fqi3_api_https_only',
				__( 'HTTPS only', 'form-quizz-fqi3' ),
				array( $this, 'render_https_only' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			// CORS allowlist
			add_settings_field(
				'fqi3_api_cors_origins',
				__( 'CORS allowed origins', 'form-quizz-fqi3' ),
				array( $this, 'render_cors_origins' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			add_settings_field(
				'fqi3_api_cors_methods',
				__( 'CORS allowed methods', 'form-quizz-fqi3' ),
				array( $this, 'render_cors_methods' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			add_settings_field(
				'fqi3_api_cors_headers',
				__( 'CORS allowed headers', 'form-quizz-fqi3' ),
				array( $this, 'render_cors_headers' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);

			// Rate limiting
			add_settings_field(
				'fqi3_api_rate_ip',
				__( 'Rate limit per IP (req/window)', 'form-quizz-fqi3' ),
				array( $this, 'render_rate_ip' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			add_settings_field(
				'fqi3_api_rate_token',
				__( 'Rate limit per token (req/window)', 'form-quizz-fqi3' ),
				array( $this, 'render_rate_token' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
			add_settings_field(
				'fqi3_api_rate_window',
				__( 'Rate limit window (seconds)', 'form-quizz-fqi3' ),
				array( $this, 'render_rate_window' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);

			// Scopes toggles
			add_settings_field(
				'fqi3_api_scopes_allowed',
				__( 'Enabled scopes', 'form-quizz-fqi3' ),
				array( $this, 'render_scopes_allowed' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);

			// API Logger
			add_settings_field(
				'fqi3_api_logger',
				__( 'API Logger', 'form-quizz-fqi3' ),
				array( $this, 'render_api_logger' ),
				fqi3_get_options_page_slug(),
				'fqi3_api_section'
			);
		}

		public function render_enable_api() {
			$options = fqi3_get_options();
			$checked = isset( $options['fqi3_quiz_api'] ) && $options['fqi3_quiz_api'] ? 'checked' : '';

			echo '<div class="form-check form-switch mb-3">';
			echo '<input class="form-check-input" type="checkbox" name="fqi3_options[fqi3_quiz_api]" role="switch" id="fqi3_quiz_api" value="1" ' . esc_attr( $checked ) . '>';
			echo '<label class="form-check-label" for="fqi3_quiz_api">' . esc_html__( 'Allow access to plugin data via the REST API', 'form-quizz-fqi3' ) . '</label>';
			echo '</div>';
		}

		public function render_api_test_ui() {
			echo '<div class="d-flex align-items-center gap-2">';
			echo '<input type="text" id="fqi3-api-endpoint" placeholder="/wp-json/fqi3/v1/ping" class="form-control form-control-sm">';
			echo '<button type="button" id="fqi3-test-api" class="btn btn-info btn-sm text-nowrap"><i class="bi bi-check"></i>' . esc_html__( 'API Test', 'form-quizz-fqi3' ) . '</button>';
			echo '</div>';
			echo '<div id="fqi3-api-response" class="fqi3-api-response"></div>';
		}

		public function render_generate_token_button() {
			// Get existing token if any
			$options = fqi3_get_options();
			//$api_token = isset($options['fqi3_quiz_api_token']) ? $options['fqi3_quiz_api_token'] : '';

			$token_manager = new TokenManager();
			$api_token     = isset( $options['fqi3_quiz_api_token'] ) ?
			$token_manager->decrypt_token( $options['fqi3_quiz_api_token'] ) : '';

			$has_token = ! empty( $api_token );

			$admin_pages       = fqi3_get_admin_pages();
			$slugViewInfos     = $admin_pages['infos']['slug'];
			$documentation_url = esc_url( admin_url( 'admin.php?page=' . $slugViewInfos ) );

			echo '<div class="token-wrapper">';
			// Generate Token Button
			echo '<div class="button-group mb-3">';
			echo '<button type="button" class="btn btn-warning btn-sm mt-2" id="generate-api-token"><i class="bi bi-check"></i>' . __( 'Generate Token', 'form-quizz-fqi3' ) . '</button>';
			echo '<button type="button" class="btn btn-danger btn-sm mt-2' . ( $has_token ? '' : ' d-none' ) . '" id="revoke-api-token"><i class="bi bi-trash"></i>' .
				__( 'Revoke Token', 'form-quizz-fqi3' ) . '</button>';
			echo '</div>';

			echo '<p class="form-text text-muted">' . __( 'Click the button to generate a new API token.', 'form-quizz-fqi3' ) . '</p>';

			// Token Input Group
			echo '<div class="token-input-group d-flex align-items-center gap-3 mb-3">';
			echo '<input type="password" name="fqi3_options[fqi3_quiz_api_token]" id="fqi3_quiz_api_token" value="' . esc_attr( $api_token ) . '" readonly class="form-control form-control-sm wide-token-input" />';
			echo '<span class="toggle-password" style="cursor: pointer;" title="' . esc_attr__( 'Show/Hide Token', 'form-quizz-fqi3' ) . '"><i class="bi bi-eye"></i></span>';
			echo '<span class="copy-icon" data-shortcode="' . esc_attr( $api_token ) . '" style="cursor: pointer;" title="' . esc_attr__( 'Copy token', 'form-quizz-fqi3' ) . '"><i class="bi bi-copy"></i></span>';
			echo '</div>';

			echo '<p class="text-muted mb-2">' . __( 'The generated token will appear above. Click Save Settings to store it.', 'form-quizz-fqi3' ) . '</p>';
			echo '<p class="text-danger"><strong>' . __( 'Note:', 'form-quizz-fqi3' ) . '</strong> ' . __( 'Do not share your API token and ensure it is stored securely.', 'form-quizz-fqi3' ) . '</p>';
			$link = sprintf(
				wp_kses(
					/* translators: Guide page URL */
					__( 'The API documentation can be found <a href="%s#api_doc" target="_blank" rel="noopener noreferrer">here</a>.', 'form-quizz-fqi3' ),
					array( 'a' => array( 'href' => array() ) )
				),
				$documentation_url
			);
			echo '<p class="lead"><strong>' . $link . '</strong></p>';
			echo '</div>';
		}

		public function render_token_expiration_mode() {
			$options = fqi3_get_options();
			$mode    = isset( $options['fqi3_quiz_api_token_exp_mode'] ) ? $options['fqi3_quiz_api_token_exp_mode'] : 'never';
			echo '<select name="fqi3_options[fqi3_quiz_api_token_exp_mode]" class="form-select form-select-sm w-auto">';
			echo '<option value="never"' . selected( $mode, 'never', false ) . '>' . esc_html__( 'Never (no expiration)', 'form-quizz-fqi3' ) . '</option>';
			echo '<option value="days"' . selected( $mode, 'days', false ) . '>' . esc_html__( 'Expire after N days', 'form-quizz-fqi3' ) . '</option>';
			echo '</select>';
			echo '<p class="form-text text-muted">' . esc_html__( 'Choose whether the token expires automatically. Original default: "Never" (no expiration).', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_token_expiration_days() {
			$options = fqi3_get_options();
			$days    = isset( $options['fqi3_quiz_api_token_exp_days'] ) ? intval( $options['fqi3_quiz_api_token_exp_days'] ) : 0;
			echo '<input type="number" min="0" name="fqi3_options[fqi3_quiz_api_token_exp_days]" value="' . esc_attr( $days ) . '" class="form-control form-control-sm w-auto" />';
			echo '<p class="form-text text-muted">' . esc_html__( 'Effective only when expiration mode is set to "Expire after N days". Original default: 0 days.', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_token_overlap_days() {
			$options = fqi3_get_options();
			$days    = isset( $options['fqi3_quiz_api_token_overlap_days'] ) ? intval( $options['fqi3_quiz_api_token_overlap_days'] ) : 0;
			echo '<input type="number" min="0" name="fqi3_options[fqi3_quiz_api_token_overlap_days]" value="' . esc_attr( $days ) . '" class="form-control form-control-sm w-auto" />';
			echo '<p class="form-text text-muted">' . esc_html__( 'Additional grace period after expiration where the token is still accepted. Helps avoid app updates. Default: 0 (no grace).', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_https_only() {
			$options = fqi3_get_options();
			$checked = ! empty( $options['fqi3_api_https_only'] ) ? 'checked' : '';
			echo '<div class="form-check form-switch mb-2">';
			echo '<input class="form-check-input" type="checkbox" name="fqi3_options[fqi3_api_https_only]" id="fqi3_api_https_only" value="1" ' . esc_attr( $checked ) . ' />';
			echo '<label class="form-check-label" for="fqi3_api_https_only">' . esc_html__( 'Reject non-HTTPS requests', 'form-quizz-fqi3' ) . '</label>';
			echo '</div>';
			echo '<p class="form-text text-muted">' . esc_html__( 'When enabled, the API will refuse HTTP requests and only accept HTTPS. Original default: disabled (HTTP allowed).', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_cors_origins() {
			$options = fqi3_get_options();
			$val     = isset( $options['fqi3_api_cors_origins'] ) ? $options['fqi3_api_cors_origins'] : '';
			echo '<input type="text" name="fqi3_options[fqi3_api_cors_origins]" value="' . esc_attr( $val ) . '" class="form-control form-control-sm" placeholder="https://example.com, https://app.example.com" />';
			echo '<p class="form-text text-muted">' . esc_html__( 'Comma-separated list of allowed origins. Leave empty to disable CORS. Original default: empty (CORS disabled).', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_cors_methods() {
			$options = fqi3_get_options();
			$val     = isset( $options['fqi3_api_cors_methods'] ) ? $options['fqi3_api_cors_methods'] : 'GET, POST, OPTIONS';
			echo '<input type="text" name="fqi3_options[fqi3_api_cors_methods]" value="' . esc_attr( $val ) . '" class="form-control form-control-sm w-auto" />';
			echo '<p class="form-text text-muted">' . esc_html__( 'List of HTTP methods to advertise for CORS. Original default: "GET, POST, OPTIONS".', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_cors_headers() {
			$options = fqi3_get_options();
			$val     = isset( $options['fqi3_api_cors_headers'] ) ? $options['fqi3_api_cors_headers'] : 'Content-Type, X-API-Token';
			echo '<input type="text" name="fqi3_options[fqi3_api_cors_headers]" value="' . esc_attr( $val ) . '" class="form-control form-control-sm" />';
			echo '<p class="form-text text-muted">' . esc_html__( 'List of request headers allowed for CORS. Original default: "Content-Type, X-API-Token".', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_rate_ip() {
			$options = fqi3_get_options();
			$val     = isset( $options['fqi3_api_rate_ip'] ) ? intval( $options['fqi3_api_rate_ip'] ) : 120;
			echo '<input type="number" min="0" name="fqi3_options[fqi3_api_rate_ip]" value="' . esc_attr( $val ) . '" class="form-control form-control-sm w-auto" />';
			echo '<p class="form-text text-muted">' . esc_html__( 'Max requests per IP within the window. 0 disables per-IP limiting. Original default: 0 (disabled).', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_rate_token() {
			$options = fqi3_get_options();
			$val     = isset( $options['fqi3_api_rate_token'] ) ? intval( $options['fqi3_api_rate_token'] ) : 60;
			echo '<input type="number" min="0" name="fqi3_options[fqi3_api_rate_token]" value="' . esc_attr( $val ) . '" class="form-control form-control-sm w-auto" />';
			echo '<p class="form-text text-muted">' . esc_html__( 'Max requests per token within the window. 0 disables per-token limiting. Original default: 0 (disabled).', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_rate_window() {
			$options = fqi3_get_options();
			$val     = isset( $options['fqi3_api_rate_window'] ) ? intval( $options['fqi3_api_rate_window'] ) : 60;
			echo '<input type="number" min="1" name="fqi3_options[fqi3_api_rate_window]" value="' . esc_attr( $val ) . '" class="form-control form-control-sm w-auto" />';
			echo '<p class="form-text text-muted">' . esc_html__( 'Sliding window duration (seconds) used for rate limiting. Original default: 60 seconds.', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_scopes_allowed() {
			$options = fqi3_get_options();
			$allowed = isset( $options['fqi3_api_scopes_allowed'] ) && is_array( $options['fqi3_api_scopes_allowed'] ) ? $options['fqi3_api_scopes_allowed'] : array( 'quizzes', 'stats', 'badges', 'settings' );
			$scopes  = array(
				'quizzes'  => __( 'Quizzes', 'form-quizz-fqi3' ),
				'stats'    => __( 'Statistics', 'form-quizz-fqi3' ),
				'badges'   => __( 'Badges', 'form-quizz-fqi3' ),
				'settings' => __( 'Settings', 'form-quizz-fqi3' ),
			);
			echo '<div class="d-flex gap-3 flex-wrap">';
			foreach ( $scopes as $key => $label ) {
				$checked = in_array( $key, $allowed, true ) ? 'checked' : '';
				echo '<div class="form-check">';
				echo '<input class="form-check-input" type="checkbox" name="fqi3_options[fqi3_api_scopes_allowed][]" id="scope_' . esc_attr( $key ) . '" value="' . esc_attr( $key ) . '" ' . $checked . ' />';
				echo '<label class="form-check-label" for="scope_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
				echo '</div>';
			}
			echo '</div>';
			echo '<p class="form-text text-muted">' . esc_html__( 'Scopes gate access to routes. Example: stats covers performance endpoints (GET/POST). Original default: all scopes enabled.', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_api_logger() {
			$options = fqi3_get_options();
			$current = $options['fqi3_api_logger'] ?? 'off';

			echo '<select name="fqi3_options[fqi3_api_logger]" id="fqi3_api_logger" class="form-select form-select-sm">';
			echo '<option value="off" ' . selected( $current, 'off', false ) . '>' . esc_html__( 'Off', 'form-quizz-fqi3' ) . '</option>';
			echo '<option value="error" ' . selected( $current, 'error', false ) . '>' . esc_html__( 'Error', 'form-quizz-fqi3' ) . '</option>';
			echo '<option value="verbose" ' . selected( $current, 'verbose', false ) . '>' . esc_html__( 'Verbose', 'form-quizz-fqi3' ) . '</option>';
			echo '</select>';
			echo '<p class="form-text text-muted">' . esc_html__( 'Select log level for API requests. Original default: off.', 'form-quizz-fqi3' ) . '</p>';
		}

		public static function sanitize_api_options( $input ) {
			$sanitized_input                  = array();
			$sanitized_input['fqi3_quiz_api'] = ! empty( $input['fqi3_quiz_api'] ) ? 1 : 0;

			// Persist token (encrypted), and stamp created_at if changed
			$options_before = fqi3_get_options();
			if ( isset( $input['fqi3_quiz_api_token'] ) ) {
				$token_manager   = new TokenManager();
				$new_token_plain = $input['fqi3_quiz_api_token'];
				$prev_plain      = '';
				if ( ! empty( $options_before['fqi3_quiz_api_token'] ) ) {
					try {
						$prev_plain = ( new TokenManager() )->decrypt_token( $options_before['fqi3_quiz_api_token'] );
					} catch ( \Exception $e ) {
						$prev_plain = '';
					}
				}
				$sanitized_input['fqi3_quiz_api_token'] = $token_manager->encrypt_token( $new_token_plain );
				if ( $new_token_plain !== $prev_plain ) {
					// store unix timestamp when token changes
					$sanitized_input['fqi3_quiz_api_token_created_at'] = time();
				}
			} else {
				// If token field wasn't posted, carry forward existing values
				if ( isset( $options_before['fqi3_quiz_api_token'] ) ) {
					$sanitized_input['fqi3_quiz_api_token'] = $options_before['fqi3_quiz_api_token'];
				}
				if ( isset( $options_before['fqi3_quiz_api_token_created_at'] ) ) {
					$sanitized_input['fqi3_quiz_api_token_created_at'] = intval( $options_before['fqi3_quiz_api_token_created_at'] );
				}
			}

			// Expiration settings
			$mode = isset( $input['fqi3_quiz_api_token_exp_mode'] ) ? $input['fqi3_quiz_api_token_exp_mode'] : 'never';
			$sanitized_input['fqi3_quiz_api_token_exp_mode']     = in_array( $mode, array( 'never', 'days' ), true ) ? $mode : 'never';
			$sanitized_input['fqi3_quiz_api_token_exp_days']     = isset( $input['fqi3_quiz_api_token_exp_days'] ) ? max( 0, intval( $input['fqi3_quiz_api_token_exp_days'] ) ) : 0;
			$sanitized_input['fqi3_quiz_api_token_overlap_days'] = isset( $input['fqi3_quiz_api_token_overlap_days'] ) ? max( 0, intval( $input['fqi3_quiz_api_token_overlap_days'] ) ) : 0;

			// HTTPS-only and CORS
			$sanitized_input['fqi3_api_https_only']   = ! empty( $input['fqi3_api_https_only'] ) ? 1 : 0;
			$sanitized_input['fqi3_api_cors_origins'] = isset( $input['fqi3_api_cors_origins'] ) ? sanitize_text_field( $input['fqi3_api_cors_origins'] ) : '';
			$sanitized_input['fqi3_api_cors_methods'] = isset( $input['fqi3_api_cors_methods'] ) ? sanitize_text_field( $input['fqi3_api_cors_methods'] ) : '';
			$sanitized_input['fqi3_api_cors_headers'] = isset( $input['fqi3_api_cors_headers'] ) ? sanitize_text_field( $input['fqi3_api_cors_headers'] ) : '';

			// Rate limiting
			$sanitized_input['fqi3_api_rate_ip']     = isset( $input['fqi3_api_rate_ip'] ) ? max( 0, intval( $input['fqi3_api_rate_ip'] ) ) : 0;
			$sanitized_input['fqi3_api_rate_token']  = isset( $input['fqi3_api_rate_token'] ) ? max( 0, intval( $input['fqi3_api_rate_token'] ) ) : 0;
			$sanitized_input['fqi3_api_rate_window'] = isset( $input['fqi3_api_rate_window'] ) ? max( 1, intval( $input['fqi3_api_rate_window'] ) ) : 60;

			// Scopes
			$allowed = isset( $input['fqi3_api_scopes_allowed'] ) && is_array( $input['fqi3_api_scopes_allowed'] ) ? array_values( array_intersect( $input['fqi3_api_scopes_allowed'], array( 'quizzes', 'stats', 'badges', 'settings' ) ) ) : array();
			if ( empty( $allowed ) ) {
				$allowed = array( 'quizzes', 'stats', 'badges', 'settings' ); }
			$sanitized_input['fqi3_api_scopes_allowed'] = $allowed;

			// API Logger
			$logger_levels                      = array( 'off', 'error', 'verbose' );
			$sanitized_input['fqi3_api_logger'] = isset( $input['fqi3_api_logger'] ) && in_array( $input['fqi3_api_logger'], $logger_levels, true )
			? $input['fqi3_api_logger']
			: 'off';

			return $sanitized_input;
		}
	}
endif;

class TokenManager {
	/** Prefix of tokens encrypted with AES-256-GCM (authenticated). Tokens without it use the legacy format. */
	private const V2_PREFIX = 'v2:';
	private const V2_METHOD = 'aes-256-gcm';

	private $key;
	private $method = 'aes-256-cbc';

	public function __construct() {
		// Legacy key (stored in the database), only needed to read tokens created before 3.4.0.
		$this->key = $this->get_encryption_key();
	}

	private function get_encryption_key() {
		$key = get_option( 'fqi3_encryption_key' );
		if ( ! $key ) {
			$key = bin2hex( random_bytes( 32 ) );
			update_option( 'fqi3_encryption_key', $key );
		}
		return hex2bin( $key );
	}

	/**
	 * Key derived from the WordPress salts (wp-config.php): a leak of the database alone does not reveal it.
	 * Note: rotating the salts invalidates the stored token, generate a new one afterwards.
	 */
	private function get_v2_key(): string {
		return hash( 'sha256', 'fqi3-api-token|' . wp_salt( 'auth' ), true );
	}

	public function encrypt_token( $token ) {
		if ( in_array( self::V2_METHOD, openssl_get_cipher_methods(), true ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( (string) $token, self::V2_METHOD, $this->get_v2_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
			if ( $cipher !== false && strlen( $tag ) === 16 ) {
				return self::V2_PREFIX . base64_encode( $iv . $tag . $cipher );
			}
		}

		// Fallback (OpenSSL without GCM): legacy format
		$iv        = random_bytes( openssl_cipher_iv_length( $this->method ) );
		$encrypted = openssl_encrypt( $token, $this->method, $this->key, 0, $iv );
		return base64_encode( $iv . $encrypted );
	}

	public function decrypt_token( $encrypted_token ) {
		if ( ! is_string( $encrypted_token ) || $encrypted_token === '' ) {
			return false;
		}

		if ( strpos( $encrypted_token, self::V2_PREFIX ) === 0 ) {
			$raw = base64_decode( substr( $encrypted_token, strlen( self::V2_PREFIX ) ), true );
			if ( $raw === false || strlen( $raw ) < 28 ) {
				return false;
			}
			$iv     = substr( $raw, 0, 12 );
			$tag    = substr( $raw, 12, 16 );
			$cipher = substr( $raw, 28 );
			return openssl_decrypt( $cipher, self::V2_METHOD, $this->get_v2_key(), OPENSSL_RAW_DATA, $iv, $tag );
		}

		// Legacy format (AES-256-CBC)
		$decoded   = base64_decode( $encrypted_token );
		$iv_length = openssl_cipher_iv_length( $this->method );
		$iv        = substr( $decoded, 0, $iv_length );
		$encrypted = substr( $decoded, $iv_length );
		return openssl_decrypt( $encrypted, $this->method, $this->key, 0, $iv );
	}

	public function generate_token() {
		return bin2hex( random_bytes( 32 ) );
	}
}
