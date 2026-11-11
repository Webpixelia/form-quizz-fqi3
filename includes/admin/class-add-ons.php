<?php
namespace Form_Quizz_FQI3;

use WP_Error;

/**
 * Class FQI3_Addons_Page
 *
 * Manages the "Available Add-Ons" page in the WordPress admin for Form Quizz FQI3 plugin.
 *
 * This class provides functionality for:
 * 1. Displaying available add-ons in a responsive grid layout
 * 2. Real-time AJAX filtering and searching of add-ons
 * 3. Status management for installed vs available add-ons
 *
 * @package Form_Quizz_FQI3
 * @subpackage Admin Pages
 * @since      3.0.0
 * @version    3.2.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FQI3_Addons_Page' ) ) :
	class FQI3_Addons_Page {
		private array $available_addons;
		private array $updaters               = array();
		private bool $updater_class_available = false;

		public function __construct() {
			// Initialize properties to avoid "must not be accessed before initialization" error
			$this->available_addons = array();

			// Include the Plugin_Updater class
			$this->load_updater_class();

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 5 );
			add_action( 'init', array( $this, 'init_available_addons' ) );
			add_action( 'wp_ajax_search_addons', array( $this, 'search_addons' ) );
			add_action( 'wp_ajax_filter_addons', array( $this, 'filter_addons' ) );
			add_action( 'wp_ajax_fqi3_addon_action', array( $this, 'handle_addon_action' ) );
			add_action( 'wp_ajax_fqi3_suggest_addon', array( $this, 'handle_suggest_addon' ) );
			add_action( 'wp_ajax_fqi3_report_issue', array( $this, 'handle_report_issue' ) );

			// Initialize updaters for installed addons (only if class is available)
			if ( $this->updater_class_available ) {
				add_action( 'init', array( $this, 'init_updaters' ), 20 );
				add_action( 'wp_ajax_refresh_addon_cache', array( $this, 'refresh_addon_cache' ) );
			}
		}

		/**
		 * Enqueues required JavaScript libraries for the admin page.
		 *
		 * This method loads the Popper.js library, which is used for positioning tooltips
		 * and dropdown elements.
		 *
		 * @since 3.0.0
		 * @return void
		 */
		public function enqueue_scripts(): void {
			wp_enqueue_script( 'popper', 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js', array(), '2.11.8', true );
		}

		/**
		 * Retrieves the installation status of a given add-on.
		 *
		 * Checks if the specified add-on is installed by looking for its plugin file
		 * in the WordPress plugin directory.
		 *
		 * @since 3.0.0
		 *
		 * @param string $addon_slug The slug of the add-on to check.
		 * @return string 'installed' if the add-on is installed, 'available' otherwise.
		 */
		private function get_addon_status( string $addon_slug ): string {
			$installed_plugins = get_plugins();
			$plugin_file       = $addon_slug . '/' . $addon_slug . '.php';

			return isset( $installed_plugins[ $plugin_file ] ) ? 'installed' : 'available';
		}

		/**
		 * Initializes the list of available add-ons.
		 *
		 * Populates an array of add-ons, each containing metadata such as title, description,
		 * features, images, and purchase links. It also determines whether an add-on is installed or available.
		 *
		 * @since 3.0.0
		 * @return void
		 */
		public function init_available_addons(): void {
			$this->available_addons = array(
				array(
					'title'       => 'Subscription Add-on',
					'description' => __( 'Create and manage membership plans with one-time or recurring payments, secure payment processing, and controlled access to premium quiz content.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Flexible subscription plans with configurable names, descriptions, prices, durations, and recurring or one-time billing.', 'form-quizz-fqi3' ),
						__( 'Stripe and PayPal payment integrations with secure webhook handling and server-side payment verification.', 'form-quizz-fqi3' ),
						__( 'Automatic subscription management, including payment confirmation, cancellation, refunds, and payment status tracking.', 'form-quizz-fqi3' ),
						__( 'Stripe 3D Secure support, with membership access granted only after payment confirmation.', 'form-quizz-fqi3' ),
						__( 'Premium content access control based on subscription status and plan.', 'form-quizz-fqi3' ),
						__( 'User registration, login, profile management, and subscription dashboard with billing information and history.', 'form-quizz-fqi3' ),
						__( 'Customizable email notification templates for subscription-related events.', 'form-quizz-fqi3' ),
						__( 'Shortcodes and Gutenberg blocks for displaying subscription plans, login and registration forms, account dashboards, and payment success pages.', 'form-quizz-fqi3' ),
						__( 'Admin management of plans and subscriptions, with CSV export capabilities.', 'form-quizz-fqi3' ),
						__( 'REST API endpoints and developer hooks for integrating and extending subscription functionality.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Subscription_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => 'form-quizz-fqi3-subscription-addon',
					'branch'      => 'main',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-subscription-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-subscription-addon' ),
					'dependance'  => '',
					'available'   => true,
				),
				array(
					'title'       => 'Affiliate Add-on',
					'description' => __( 'Run an affiliate program for your quizzes: track referrals, attribute subscription conversions, calculate commissions, and manage affiliate payouts.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Affiliate referral links with unique tokens, click tracking, last-click attribution, and a configurable attribution window.', 'form-quizz-fqi3' ),
						__( 'Automatic conversion tracking and percentage-based commission calculation, with support for subscription lifecycle events, refunds, and commission tiers.', 'form-quizz-fqi3' ),
						__( 'Affiliate dashboard and signup form, available through Gutenberg blocks and shortcodes.', 'form-quizz-fqi3' ),
						__( 'Payout management: generate and schedule payouts, approve eligible conversions, mark payouts as paid, and export filtered payout data to CSV.', 'form-quizz-fqi3' ),
						__( 'Optional PayPal automatic payouts with scheduled processing, configurable payout limits, and batch history.', 'form-quizz-fqi3' ),
						__( 'Admin reports and analytics: Overview, Top Affiliates, and Click Anomalies, with filters and CSV export.', 'form-quizz-fqi3' ),
						__( 'Optional integrations with Refersion and Tapfiliate, including API configuration and connection testing.', 'form-quizz-fqi3' ),
						__( 'Privacy and GDPR tools: audit logs, configurable data retention, IP anonymization, and WordPress data export and erasure support.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Affiliate_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => 'form-quizz-fqi3-affiliate-addon',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-affiliate-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-affiliate-addon' ),
					'dependance'  => array(
						'slug' => 'form-quizz-fqi3-subscription-addon',
						'name' => 'Subscription Add-on',
					),
					'available'   => true,
				),
				array(
					'title'       => 'Social Login Add-On',
					'description' => __( 'Allow users to register and log in to Form Quizz FQI3 using their Google, Facebook, or LinkedIn accounts, with secure OAuth authentication.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Social authentication through Google, Facebook, and LinkedIn using OAuth 2.0 / OpenID Connect.', 'form-quizz-fqi3' ),
						__( 'Simplified registration with automatic WordPress account creation and profile information synchronization.', 'form-quizz-fqi3' ),
						__( 'Secure account linking with provider identity storage and email confirmation when required.', 'form-quizz-fqi3' ),
						__( 'Configurable login buttons with customizable styles, text, and CSS classes.', 'form-quizz-fqi3' ),
						__( 'Configurable landing pages after login and registration.', 'form-quizz-fqi3' ),
						__( 'Individual provider activation and API credentials management through dedicated settings.', 'form-quizz-fqi3' ),
						__( 'OAuth security features including state validation, IP-based rate limiting, and protection against unauthorized account access.', 'form-quizz-fqi3' ),
						__( 'Developer hooks and actions to customize provider configuration, redirects, user data, login buttons, and account events.', 'form-quizz-fqi3' ),
						__( 'Optional data cleanup on uninstall, including social identities and plugin transients, without deleting WordPress accounts.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Social_Login_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => 'form-quizz-fqi3-social-login-addon',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-social-login-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-social-login-addon' ),
					'dependance'  => array(
						'slug' => 'form-quizz-fqi3-subscription-addon',
						'name' => 'Subscription Add-on',
					),
					'available'   => true,
				),
				array(
					'title'       => 'Email Marketing Integrations Add-On',
					'description' => __( 'Grow your email list with every quiz! Connect your quizzes to your email marketing platform: collect consent, send each person\'s results, and let your provider handle the campaigns.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Quiz Results in Your Provider: Send each contact\'s score and level to custom fields, and add tags (quiz completed, score thresholds for logged-in users, user role) to segment your audience.', 'form-quizz-fqi3' ),
						__( 'Consent Done Right: GDPR consent checkbox with proof of consent, double opt-in (Mailchimp, MailerLite, Brevo) and real unsubscription when consent is withdrawn.', 'form-quizz-fqi3' ),
						__( 'Seamless Integrations: Connect with Mailchimp, Brevo (ex-Sendinblue), ActiveCampaign, and MailerLite effortlessly.', 'form-quizz-fqi3' ),
						__( 'Always in Sync: Unsubscribes and bounces made in your provider are applied on your site through a secure webhook, and temporary API failures are retried automatically.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Email_Marketing_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => 'form-quizz-fqi3-email-marketing-addon',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-email-marketing-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-email-marketing-addon' ),
					'dependance'  => '',
					'available'   => true,
				),
				array(
					'title'       => 'Notification Add-on',
					'description' => __( 'Keep users informed and engaged with automated email, WhatsApp, and SMS notifications triggered by their quiz activity.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Email notifications with bundled HTML templates, SMTP configuration with SSL/TLS, and test email functionality.', 'form-quizz-fqi3' ),
						__( 'WhatsApp Business API integration with configurable sender settings and test messages.', 'form-quizz-fqi3' ),
						__( 'SMS notifications through Twilio, with configurable settings and test messages.', 'form-quizz-fqi3' ),
						__( 'Automated quiz reminders, badge notifications, and certificate notifications.', 'form-quizz-fqi3' ),
						__( 'Opt-in notification preferences allowing premium members to choose their preferred channels for each notification type.', 'form-quizz-fqi3' ),
						__( 'Notification preferences management through a dedicated shortcode, Gutenberg block, and WordPress profile screen.', 'form-quizz-fqi3' ),
						__( 'Background delivery of badge and certificate notifications through WP-Cron.', 'form-quizz-fqi3' ),
						__( 'Optional integration with the Subscription Add-on to display notification preferences in the member dashboard.', 'form-quizz-fqi3' ),
						__( 'WordPress privacy tools integration for personal data export and erasure, including notification preferences and phone numbers.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Notification_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => 'form-quizz-fqi3-notification-addon',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-notification-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-notification-addon' ),
					'dependance'  => '',
					'available'   => true,
				),
				array(
					'title'       => 'Certificates Add-on',
					'description' => __( 'Issue and manage certificates for passed quizzes, with customizable templates, public verification, and optional PDF downloads.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Automatic certificate issuance when users pass a quiz, with configurable passing score thresholds.', 'form-quizz-fqi3' ),
						__( 'Customizable certificate templates with Gutenberg editing, block patterns, preview, and per-quiz or per-level template mapping.', 'form-quizz-fqi3' ),
						__( 'Personalized certificates with dynamic variables for user details, quiz information, scores, instructors, and issuance dates.', 'form-quizz-fqi3' ),
						__( 'Public certificate verification and token-protected HTML viewing and PDF downloads.', 'form-quizz-fqi3' ),
						__( 'My Certificates page with filtering, scores, and certificate actions, available via shortcode or Gutenberg block.', 'form-quizz-fqi3' ),
						__( 'Optional integration with the Subscription Add-on to display certificates in the user dashboard.', 'form-quizz-fqi3' ),
						__( 'PDF generation with Dompdf, configurable paper size and orientation, and optional remote image loading.', 'form-quizz-fqi3' ),
						__( 'Admin certificate management: search, status filtering, revoke, restore, manual issuance, and CSV export.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Certificates_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => 'form-quizz-fqi3-certificates-addon',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-certificates-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-certificates-addon' ),
					'dependance'  => '',
					'available'   => true,
				),
				array(
					'title'       => 'Question Bank Add-on',
					'description' => __( 'Create and organize question pools, manage sections, and generate randomized quiz draws with configurable rules and draw snapshots.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Question pools with search, sorting, bulk actions, and CSV export.', 'form-quizz-fqi3' ),
						__( 'Question sections with configurable weights to control their share of randomized quiz draws.', 'form-quizz-fqi3' ),
						__( 'Create and edit quiz questions, assigning them to specific pools and sections.', 'form-quizz-fqi3' ),
						__( 'Randomized draws with balanced, strict, and no-randomization modes, configurable question limits, and optional per-level overrides.', 'form-quizz-fqi3' ),
						__( 'Draw snapshots recording each randomized selection, with filtering, CSV export, and configurable automatic retention.', 'form-quizz-fqi3' ),
						__( 'Optional AI assistant to generate draft questions using OpenAI, Mistral, or Anthropic, with your own API keys.', 'form-quizz-fqi3' ),
						__( 'Configurable access control for administrators and editors, with separate administrator-only settings.', 'form-quizz-fqi3' ),
						__( 'Optional clean uninstall that removes only the add-on data, without affecting the parent plugin.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_QB_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => 'form-quizz-fqi3-question-bank-addon',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-question-bank-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-question-bank-addon' ),
					'dependance'  => '',
					'available'   => true,
				),
				array(
					'title'       => 'WooCommerce Integration',
					'description' => __( 'Sell quizzes, certificates or bundles via WooCommerce and map access rules.', 'form-quizz-fqi3' ),
					'features' => array(
						__( 'Sell quiz levels, level packs or full access as WooCommerce products, with lifetime or time-limited access.', 'form-quizz-fqi3' ),
						__( 'Automatic access grant on paid orders and automatic removal on refunds, cancellations and failed payments, safe to replay.', 'form-quizz-fqi3' ),
						__( 'Guest checkout support: buyers link their order to an account through a secure e-mail link, and a My Account page lists purchased levels and expiry dates.', 'form-quizz-fqi3' ),
						__( 'Score-based rules using verified results only: prerequisites to unlock a level or product, and auto-generated single-use coupons.', 'form-quizz-fqi3' ),
						__( 'Post-quiz upsells on the results screen, with conversion tracking.', 'form-quizz-fqi3' ),
						__( 'Admin tools: entitlements list, order meta box, manual revoke and event log with retention.', 'form-quizz-fqi3' ),
						__( 'Use the full WooCommerce ecosystem (payment gateways, customer accounts) instead of the Subscription Add-on; compatible with HPOS and Cart/Checkout blocks.', 'form-quizz-fqi3' ),
						__( 'Developer hooks and filters, and optional clean uninstall that removes only the add-on data.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_WooCommerce_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => '',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-woocommerce-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-woocommerce-addon' ),
					'dependance'  => '',
					'requires_plugins' => array(
						array(
							'file' => 'woocommerce/woocommerce.php',
							'name' => 'WooCommerce',
						),
					),
					'available'   => false,
				),
				array(
					'title'       => 'Webhooks & Automations Add-on',
					'description' => __( 'Connect Form Quizz FQI3 to external services and automation platforms by sending quiz, badge, certificate, and subscription events through configurable outgoing webhooks.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Multiple webhook endpoints with configurable HTTP methods, URLs, custom headers, and JSON payload templates.', 'form-quizz-fqi3' ),
						__( 'Event triggers for quiz attempts, drawn questions, badges, certificates, and Subscription Add-on events.', 'form-quizz-fqi3' ),
						__( 'Asynchronous delivery through WP-Cron, with configurable timeouts, automatic retries, exponential backoff, and per-endpoint rate limits.', 'form-quizz-fqi3' ),
						__( 'HMAC request signing with SHA256 or SHA1, optional timestamp signatures, and replay protection support.', 'form-quizz-fqi3' ),
						__( 'SSRF protection against private and local network destinations, with an optional setting to allow trusted private endpoints.', 'form-quizz-fqi3' ),
						__( 'Delivery logs with configurable retention, privacy controls, failed delivery retries, replay, and CSV export.', 'form-quizz-fqi3' ),
						__( 'Dedicated administration pages for settings, endpoint management, and delivery monitoring.', 'form-quizz-fqi3' ),
						__( 'Fine-grained access control through a dedicated WordPress capability.', 'form-quizz-fqi3' ),
						__( 'Developer hooks and filters to customize endpoints, event payloads, headers, delivery behavior, retry policies, and access permissions.', 'form-quizz-fqi3' ),
						__( 'WordPress privacy tools integration for personal data export and erasure, with automatic cleanup of plugin data on uninstall.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Webhooks_Automations_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => 'form-quizz-fqi3-webhooks-addon',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-webhooks-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-webhooks-addon' ),
					'dependance'  => '',
					'available'   => true,
				),
				array(
					'title'       => 'Lead Scoring & CRM Add-on',
					'description' => __( 'Score leads based on quiz activity and engagement events, build dynamic segments, and synchronize contacts with HubSpot, Salesforce, Pipedrive, or custom webhook endpoints.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Configurable scoring rules based on quiz answers and events, with automatic score updates and detailed scoring history.', 'form-quizz-fqi3' ),
						__( 'Configurable score decay for inactive leads, with automatic periodic recalculation.', 'form-quizz-fqi3' ),
						__( 'Dynamic segments defined through JSON rules, with conditions based on lead scores, tags, dates, and events.', 'form-quizz-fqi3' ),
						__( 'CRM integrations with HubSpot, Salesforce (OAuth 2.0), and Pipedrive, plus support for custom webhook endpoints.', 'form-quizz-fqi3' ),
						__( 'Configurable field mapping for CRM synchronization, including lead scores and segment membership.', 'form-quizz-fqi3' ),
						__( 'Manual, event-based, and scheduled synchronization with queued processing, automatic retries, and backoff.', 'form-quizz-fqi3' ),
						__( 'Admin dashboard with lead scoring metrics, segment insights, and synchronization status.', 'form-quizz-fqi3' ),
						__( 'Synchronization and event logs with filtering, CSV/JSON export, and configurable data retention.', 'form-quizz-fqi3' ),
						__( 'Developer hooks and filters to customize score adjustments, segment handling, and administrative capabilities.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_LeadScoring_CRM_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => 'form-quizz-fqi3-leads-crm-addon',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-leads-crm-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-leads-crm-addon' ),
					'dependance'  => '',
					'available'   => true,
				),
				array(
					'title'       => 'Multilingual & Localization Add-on',
					'description' => __( 'Add multilingual capabilities focused on Form Quizz FQI3: define default/available/fallback languages (auto-detected from site locale), display a language switcher, and enable SEO best practices (URL mode + hreflang) without the complexity of a full site-wide framework.', 'form-quizz-fqi3' ),
					'features'    => array(
						__( 'Language settings: default, available and fallback languages (defaults from site locale).', 'form-quizz-fqi3' ),
						__( 'Language switcher: header/menu/footer or manual via shortcode/PHP, with optional flags.', 'form-quizz-fqi3' ),
						__( 'SEO helpers: language in URL (subdirectory or query param) and hreflang tags.', 'form-quizz-fqi3' ),
						__( 'Integrations: auto-create translation placeholders, optional ACF fields sync.', 'form-quizz-fqi3' ),
						__( 'Developer hooks: filter languages, positions, and URL modes to extend behavior.', 'form-quizz-fqi3' ),
					),
					'image'       => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Multilingual_Addon_square.webp',
					'more_info'   => '',
					'github_repo' => '',
					'price'       => '',
					'period'      => '',
					'slug'        => 'form-quizz-fqi3-multilingual-addon',
					'status'      => $this->get_addon_status( 'form-quizz-fqi3-multilingual-addon' ),
					'dependance'  => '',
					'available'   => false,
				),
			);
		}

		/**
		 * Vérifie si un add-on peut être désactivé (s'il n'y a pas d'autres add-ons actifs qui en dépendent)
		 *
		 * @since 3.0.0
		 * @param string $addon_slug Le slug de l'add-on à vérifier
		 * @return bool True si l'add-on peut être désactivé, false sinon
		 */
		private function can_deactivate_addon( string $addon_slug ): bool {
			$dependent_addons = $this->get_active_dependent_addons( $addon_slug );
			return empty( $dependent_addons );
		}

		/**
		 * Check if an add-on has an active dependency
		 *
		 * @since 3.0.0
		 * @param string $addon_slug
		 * @return bool
		 */
		private function has_active_dependency( string $addon_slug ): bool {
			foreach ( $this->available_addons as $addon ) {
				if ( ( $addon['slug'] ?? '' ) === $addon_slug ) {
					foreach ( $this->get_addon_dependencies( $addon ) as $dependency ) {
						if ( $this->is_addon_active( $dependency['slug'] ) ) {
							return true;
						}
					}
					break;
				}
			}
			return false;
		}

		/**
		 * Normalized list of the add-ons another add-on depends on.
		 *
		 * `dependance` accepts a single array( 'slug' =>, 'name' => ) (historical format) or a list of them.
		 *
		 * @since 3.6.0
		 * @param array $addon Add-on definition.
		 * @return array<int,array{slug:string,name:string}>
		 */
		private function get_addon_dependencies( array $addon ): array {
			$raw = $addon['dependance'] ?? array();
			if ( empty( $raw ) || ! is_array( $raw ) ) {
				return array();
			}
			if ( isset( $raw['slug'] ) ) {
				$raw = array( $raw );
			}

			$dependencies = array();
			foreach ( $raw as $dependency ) {
				if ( is_array( $dependency ) && ! empty( $dependency['slug'] ) ) {
					$dependencies[] = array(
						'slug' => (string) $dependency['slug'],
						'name' => (string) ( $dependency['name'] ?? $dependency['slug'] ),
					);
				}
			}
			return $dependencies;
		}

		/**
		 * Third-party WordPress plugins an add-on needs (for example WooCommerce).
		 *
		 * @since 3.6.0
		 * @param array $addon Add-on definition.
		 * @return array<int,array{file:string,name:string}> `file` is the plugin basename (folder/file.php).
		 */
		private function get_required_plugins( array $addon ): array {
			$plugins = array();
			foreach ( (array) ( $addon['requires_plugins'] ?? array() ) as $plugin ) {
				if ( is_array( $plugin ) && ! empty( $plugin['file'] ) ) {
					$plugins[] = array(
						'file' => (string) $plugin['file'],
						'name' => (string) ( $plugin['name'] ?? $plugin['file'] ),
					);
				}
			}
			return $plugins;
		}

		/**
		 * Display names of everything an add-on requires (add-ons first, then plugins).
		 *
		 * @since 3.6.0
		 * @param array $addon Add-on definition.
		 * @return string[]
		 */
		private function get_requirement_names( array $addon ): array {
			return array_merge(
				array_column( $this->get_addon_dependencies( $addon ), 'name' ),
				array_column( $this->get_required_plugins( $addon ), 'name' )
			);
		}

		/**
		 * Get the list of active add-ons that depend on a given add-on
		 *
		 * @since 3.0.0
		 * @param string $addon_slug The slug of the add-on to check dependencies for
		 * @return array List of active add-ons that depend on this add-on
		 */
		private function get_active_dependent_addons( string $addon_slug ): array {
			$dependent_addons = array();

			foreach ( $this->available_addons as $addon ) {
				// Check if this add-on depends on the given slug and is active
				if ( ! $this->is_addon_active( $addon['slug'] ) ) {
					continue;
				}
				foreach ( $this->get_addon_dependencies( $addon ) as $dependency ) {
					if ( $dependency['slug'] === $addon_slug ) {
						$dependent_addons[] = array(
							'slug'  => $addon['slug'],
							'title' => $addon['title'],
						);
						break;
					}
				}
			}

			return $dependent_addons;
		}

		/**
		 * Check if an add-on is active
		 *
		 * @since 3.0.0
		 * @param string $addon_slug The slug of the add-on to check
		 * @return bool True if the add-on is active, false otherwise
		 */
		private function is_addon_active( string $addon_slug ): bool {
			$plugin_file = $addon_slug . '/' . $addon_slug . '.php';
			return is_plugin_active( $plugin_file );
		}

		/**
		 * Check if all dependencies of an add-on are satisfied
		 *
		 * @since 3.0.0
		 * @param array $addon The add-on to check dependencies for
		 * @return bool True if all dependencies are satisfied, false otherwise
		 */
		private function are_dependencies_satisfied( array $addon ): bool {
			foreach ( $this->get_addon_dependencies( $addon ) as $dependency ) {
				if ( ! $this->is_addon_active( $dependency['slug'] ) ) {
					return false;
				}
			}
			foreach ( $this->get_required_plugins( $addon ) as $plugin ) {
				if ( ! is_plugin_active( $plugin['file'] ) ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * Handles AJAX requests for searching add-ons.
		 *
		 * Filters the list of available add-ons based on the search term and selected filter.
		 * Responds with a JSON object containing filtered add-ons or an error message if none are found.
		 *
		 * @since 3.0.0
		 * @return void
		 */
		public function search_addons(): void {
			check_ajax_referer( 'fqi3_addons_search', 'nonce' );

			// Ensure addons are initialized
			if ( empty( $this->available_addons ) ) {
				$this->init_available_addons();
			}

			$search_term = sanitize_text_field( $_POST['search'] ?? '' );
			$filter      = sanitize_text_field( $_POST['filter'] ?? 'all' );

			$filtered_addons = array_filter(
				$this->available_addons,
				function ( $addon ) use ( $search_term, $filter ) {
					$matches_search = empty( $search_term ) ||
					stripos( $addon['title'], $search_term ) !== false ||
					stripos( $addon['description'], $search_term ) !== false;

					$matches_filter = $filter === 'all' || $addon['status'] === $filter;

					return $matches_search && $matches_filter;
				}
			);

			if ( empty( $filtered_addons ) ) {
				wp_send_json_error( array( 'message' => __( 'No add-ons found.', 'form-quizz-fqi3' ) ) );
				return;
			}

			ob_start();
			$this->render_addons_grid( $filtered_addons );
			$html = ob_get_clean();

			wp_send_json_success( array( 'html' => $html ) );
		}

		/**
		 * Renders the add-ons grid.
		 *
		 * Outputs the HTML structure for displaying add-ons in a grid format.
		 * Each add-on includes its image, title, description, and purchase link.
		 *
		 * @since 3.0.0
		 *
		 * @param array $addons An array of add-ons to be displayed.
		 * @return void
		 */
		private function render_addons_grid( array $addons ): void {
			foreach ( $addons as $addon ) :
				$is_installed           = $addon['status'] === 'installed';
				$is_active              = $is_installed && is_plugin_active( $addon['slug'] . '/' . $addon['slug'] . '.php' );
				$can_deactivate         = $this->can_deactivate_addon( $addon['slug'] );
				$has_active_dependency  = $this->has_active_dependency( $addon['slug'] );
				$dependencies_satisfied = $this->are_dependencies_satisfied( $addon );
				$dependent_addons       = $this->get_active_dependent_addons( $addon['slug'] );
				$requirement_names      = $this->get_requirement_names( $addon );
				?>
				<div class="col">
					<div class="card h-100 shadow-sm" role="article" aria-labelledby="addon-title-<?php echo esc_attr( $addon['slug'] ); ?>">
					<div class="overlay"></div>
						<?php
						if ( $addon['status'] === 'installed' ) :
							$plugin_file = $addon['slug'] . '/' . $addon['slug'] . '.php';
							$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
							$version     = $plugin_data['Version'] ?? '';
							?>
							<div class="position-absolute top-0 end-0 m-3 z-3">
								<span class="badge bg-success">
									<?php
									_e( 'Installed', 'form-quizz-fqi3' );
									if ( $version ) {
										echo ' - v' . esc_html( $version );
									}
									?>
								</span>
							</div>
						<?php endif; ?>
						
						<div class="card-img-wrap position-relative">
							<img src="<?php echo esc_url( $addon['image'] ); ?>" 
								data-src="<?php echo esc_url( $addon['image'] ); ?>"
								class="card-img-top fqi3-lazy-load" 
								alt="<?php echo esc_attr( $addon['title'] ); ?>"
								style="height: 200px;"
								loading="lazy">
						</div>

						<div class="card-body d-flex flex-column">
							<div class="d-flex justify-content-between align-items-start mb-2">
								<h5 class="card-title mb-0" id="addon-title-<?php echo esc_attr( $addon['slug'] ); ?>"><?php echo esc_html( $addon['title'] ); ?></h5>
								<!--<span class="badge bg-primary">
									<?php
										echo ! empty( $addon['price'] ) ? esc_html( $addon['price'] ) . '€' : __( 'Free', 'form-quizz-fqi3' );
									if ( ! empty( $addon['period'] ) ) {
										echo ' / ' . esc_html( $addon['period'] );
									}
									?>
								</span>-->
							</div>
							<p class="card-text"><?php echo esc_html( $addon['description'] ); ?></p>
							
							<!-- Affichage des dépendances -->
							<?php if ( ! empty( $requirement_names ) ) : ?>
								<div class="alert alert-info alert-sm mb-2">
									<small>
										<i class="bi bi-info-circle me-1"></i>
										<?php
										printf(
											/* translators: %s name of the plugin/addon */
											__( 'Requires: %s', 'form-quizz-fqi3' ),
											'<strong>' . esc_html( implode( ', ', $requirement_names ) ) . '</strong>'
										);
										?>
									</small>
								</div>
							<?php endif; ?>
					
							<!-- Affichage des add-ons dépendants (si il y en a) -->
							<?php if ( ! empty( $dependent_addons ) ) : ?>
								<div class="alert alert-warning alert-sm mb-2">
									<small>
										<i class="bi bi-exclamation-triangle me-1"></i>
										<?php esc_html_e( 'Required by:', 'form-quizz-fqi3' ); ?>
										<?php foreach ( $dependent_addons as $i => $dep ) : ?>
											<strong><?php echo esc_html( $dep['title'] ); ?></strong><?php echo $i < count( $dependent_addons ) - 1 ? ', ' : ''; ?>
										<?php endforeach; ?>
									</small>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $addon['features'] ) ) : ?>
								<?php $features_id = 'features-' . sanitize_html_class( $addon['slug'] ); ?>
								<button 
									class="btn btn-link btn-sm p-0 mb-2 d-flex" 
									type="button"
									data-bs-toggle="collapse" 
									data-bs-target="#<?php echo esc_attr( $features_id ); ?>" 
									aria-expanded="false" 
									aria-controls="<?php echo esc_attr( $features_id ); ?>">
									<i class="bi bi-list me-1"></i><?php esc_html_e( 'Show features', 'form-quizz-fqi3' ); ?>
								</button>
								<div id="<?php echo esc_attr( $features_id ); ?>" class="collapse">
									<ul class="list-unstyled mb-3">
										<?php foreach ( $addon['features'] as $feature ) : ?>
											<li><i class="bi bi-check-circle-fill text-success me-2"></i><?php echo esc_html( $feature ); ?></li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
						</div>

						<div class="card-footer bg-transparent border-top-0">
							<div class="d-grid gap-2">
								<?php if ( ! $addon['available'] ) : ?>
									<button class="btn btn-secondary disabled" disabled>
										<?php esc_html_e( 'Not yet available', 'form-quizz-fqi3' ); ?>
									</button>
								<?php else : ?>
									<?php if ( ! $is_installed ) : ?>
										<?php if ( $dependencies_satisfied ) : ?>
											<?php if ( empty( $addon['github_repo'] ) ) : ?>
												<button 
													class="btn btn-secondary disabled" 
													disabled>
													<?php esc_html_e( 'Coming Soon', 'form-quizz-fqi3' ); ?>
												</button>
											<?php else : ?>
												<button 
													class="btn btn-primary fqi3-addon-action" 
													data-action="install" 
													data-slug="<?php echo esc_attr( $addon['slug'] ); ?>" 
												>
													<?php esc_html_e( 'Install', 'form-quizz-fqi3' ); ?>
												</button>
											<?php endif; ?>
										<?php else : ?>
											<button 
												class="btn btn-secondary disabled" 
												disabled
												title="
												<?php
													/* translators: %s is the name of the required add-on */
													printf( __( 'Requires %s to be installed and activated first', 'form-quizz-fqi3' ), implode( ', ', $requirement_names ) );
												?>
													">
												<?php
													/* translators: %s is the name of the required add-on */
													printf( __( 'Requires %s', 'form-quizz-fqi3' ), implode( ', ', $requirement_names ) );
												?>
											</button>
										<?php endif; ?>
									<?php elseif ( $is_active && ! $can_deactivate ) : ?>
										<button 
											class="btn btn-secondary disabled" 
											disabled
											title="
											<?php
											echo $has_active_dependency
												? esc_attr( __( 'Cannot deactivate because a required dependency is active', 'form-quizz-fqi3' ) )
												: esc_attr( __( 'Cannot deactivate because other active add-ons depend on it', 'form-quizz-fqi3' ) );
											?>
												">
											<?php esc_html_e( 'Cannot deactivate', 'form-quizz-fqi3' ); ?>
											<i class="bi bi-info-circle ms-1" 
											data-bs-toggle="tooltip" 
											data-bs-placement="top" 
											title="
											<?php
											if ( $has_active_dependency ) {
												// Find the dependency name for display
												$dep_name = implode( ', ', array_column( $this->get_addon_dependencies( $addon ), 'name' ) );
												if ( $dep_name ) {
													/* translators: %s is the name of the dependency */
													printf( __( 'Depends on: %s', 'form-quizz-fqi3' ), esc_html( $dep_name ) );
												} else {
													echo esc_attr__( 'Has active dependency', 'form-quizz-fqi3' );
												}
											} else {
												printf(
													/* translators: %s is the list of dependent add-ons */
													__( 'Required by: %s', 'form-quizz-fqi3' ),
													implode( ', ', array_column( $dependent_addons, 'title' ) )
												);
											}
											?>
											"></i>
										</button>
									<?php elseif ( $is_active ) : ?>
										<button 
											class="btn btn-warning fqi3-addon-action" 
											data-action="deactivate" 
											data-slug="<?php echo esc_attr( $addon['slug'] ); ?>">
											<?php esc_html_e( 'Deactivate', 'form-quizz-fqi3' ); ?>
										</button>
									<?php else : ?>
										<button 
											class="btn btn-success fqi3-addon-action" 
											data-action="activate" 
											data-slug="<?php echo esc_attr( $addon['slug'] ); ?>">
											<?php esc_html_e( 'Activate', 'form-quizz-fqi3' ); ?>
										</button>
									<?php endif; ?>
								<?php endif; ?>
								<!--<a href="<?php echo esc_url( $addon['more_info'] ); ?>" 
									class="btn btn-link text-center" 
									target="_blank">
									<?php esc_html_e( 'Learn more', 'form-quizz-fqi3' ); ?>
								</a>-->
							</div>
						</div>
					</div>
				</div>
				<?php
			endforeach;
		}

		/**
		 * Format a row for the email template
		 *
		 * @param string $label The label for the row
		 * @param string $value The value for the row
		 * @return string HTML for the row
		 */
		private function format_email_row( $label, $value ) {
			if ( empty( $value ) ) {
				return '';
			}
			return sprintf(
				'<tr><td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; vertical-align: top; width: 150px;">%s:</td><td style="padding: 10px; border-bottom: 1px solid #eee;">%s</td></tr>',
				esc_html( $label ),
				$value
			);
		}

		/**
		 * Get the display name of an add-on by its slug
		 *
		 * @param string $addon_slug The slug of the add-on
		 * @return string The add-on name or the slug if not found
		 */
		private function get_addon_name( $addon_slug ) {
			if ( $addon_slug === 'general' ) {
				return __( 'General', 'form-quizz-fqi3' );
			}

			foreach ( $this->available_addons as $addon ) {
				if ( isset( $addon['slug'] ) && $addon['slug'] === $addon_slug ) {
					return isset( $addon['title'] ) ? $addon['title'] : $addon_slug;
				}
			}

			return $addon_slug;
		}

		/**
		 * Save issue report to the database
		 *
		 * @param array $report_data The report data to save
		 * @return bool|int The report ID on success, false on failure
		 */
		private function save_issue_report( $report_data ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'fqi3_issue_reports';

			// Create the table if it doesn't exist
			$charset_collate = $wpdb->get_charset_collate();
			$sql             = "CREATE TABLE IF NOT EXISTS $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                addon_slug varchar(100) NOT NULL,
                issue_type varchar(50) NOT NULL,
                description text NOT NULL,
                steps text,
                url varchar(255) NOT NULL,
                user_id bigint(20) NOT NULL,
                user_email varchar(100) NOT NULL,
                user_agent text NOT NULL,
                site_name varchar(255) NOT NULL,
                site_url varchar(255) NOT NULL,
                wp_version varchar(20) NOT NULL,
                plugin_version varchar(20) NOT NULL,
                status varchar(20) DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                KEY addon_slug (addon_slug),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			// Insert the report
			return $wpdb->insert(
				$table_name,
				array(
					'addon_slug'     => $report_data['addon'],
					'issue_type'     => $report_data['issue_type'],
					'description'    => $report_data['description'],
					'steps'          => $report_data['steps'],
					'url'            => $report_data['url'],
					'user_id'        => $report_data['user_id'],
					'user_email'     => $report_data['user_email'],
					'user_agent'     => $report_data['user_agent'],
					'site_name'      => $report_data['site_name'],
					'site_url'       => $report_data['site_url'],
					'wp_version'     => get_bloginfo( 'version' ),
					'plugin_version' => FQI3_VERSION,
					'status'         => 'pending',
					'created_at'     => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		/**
		 * Handle report issue submission
		 *
		 * @since 3.2.0
		 * @return void
		 */
		public function handle_report_issue() {
			// Vérifier le nonce pour la sécurité
			if ( ! check_ajax_referer( 'fqi3_report_issue', 'security', false ) ) {
				wp_send_json_error( __( 'Security check failed.', 'form-quizz-fqi3' ), 403 );
			}

			// Vérifier les permissions de l'utilisateur
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'form-quizz-fqi3' ), 403 );
			}

			// Récupérer les données du formulaire
			$addon       = isset( $_POST['addon'] ) ? sanitize_text_field( $_POST['addon'] ) : 'general';
			$issue_type  = isset( $_POST['issue_type'] ) ? sanitize_text_field( $_POST['issue_type'] ) : 'other';
			$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
			$steps       = isset( $_POST['steps'] ) ? sanitize_textarea_field( $_POST['steps'] ) : '';
			$current_url = isset( $_POST['current_url'] ) ? esc_url_raw( $_POST['current_url'] ) : '';
			$user_agent  = isset( $_POST['user_agent'] ) ? sanitize_text_field( $_POST['user_agent'] ) : '';

			// Valider les champs obligatoires
			if ( empty( $description ) ) {
				wp_send_json_error( __( 'Please provide a description of the issue.', 'form-quizz-fqi3' ), 400 );
			}

			// Récupérer les informations sur l'utilisateur et le site
			$current_user = wp_get_current_user();
			$site_name    = get_bloginfo( 'name' );
			$site_url     = get_bloginfo( 'url' );

			// Préparer les données du rapport
			$report_data = array(
				'addon'             => $addon,
				'issue_type'        => $issue_type,
				'description'       => $description,
				'steps'             => $steps,
				'url'               => $current_url,
				'user_agent'        => $user_agent,
				'user_id'           => $current_user->ID,
				'user_email'        => $current_user->user_email,
				'user_display_name' => $current_user->display_name,
				'site_name'         => $site_name,
				'site_url'          => $site_url,
				'timestamp'         => current_time( 'mysql' ),
				'wp_version'        => get_bloginfo( 'version' ),
				'plugin_version'    => FQI3_VERSION,
			);

			// Envoyer un email à l'administrateur
			$to      = get_bloginfo( 'admin_email' );
			$subject = sprintf(
				/* translators: %1$s: Site name, %2$s: Add-on name, %3$s: Issue type */
				__( '[%1$s] New Issue Report - %2$s - %3$s', 'form-quizz-fqi3' ),
				$site_name,
				$addon === 'general' ? __( 'General', 'form-quizz-fqi3' ) : $this->get_addon_name( $addon ),
				ucfirst( $issue_type )
			);

			// Construire le message au format HTML
			$message  = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto;">';
			$message .= '<div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; border-left: 4px solid #dc3545;">';
			$message .= '<h2 style="color: #dc3545; margin-top: 0;">' . __( 'New Issue Report', 'form-quizz-fqi3' ) . '</h2>';
			$message .= '<div style="background-color: white; border-radius: 5px; padding: 15px; margin-bottom: 20px;">';
			$message .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';

			// Ajouter les informations de base
			$message .= $this->format_email_row( __( 'Type of issue', 'form-quizz-fqi3' ), ucfirst( $issue_type ) );
			$message .= $this->format_email_row( __( 'Extension concerned', 'form-quizz-fqi3' ), $addon === 'general' ? __( 'General', 'form-quizz-fqi3' ) : $this->get_addon_name( $addon ) );
			$message .= $this->format_email_row( __( 'Description', 'form-quizz-fqi3' ), nl2br( esc_html( $description ) ) );

			if ( ! empty( $steps ) ) {
				$message .= $this->format_email_row( __( 'Steps to reproduce', 'form-quizz-fqi3' ), nl2br( esc_html( $steps ) ) );
			}

			// Ajouter les informations techniques
			$message .= $this->format_email_row( '', '<strong style="color: #6c757d;">' . __( 'Technical Informations', 'form-quizz-fqi3' ) . '</strong>' );
			$message .= $this->format_email_row( __( 'URL', 'form-quizz-fqi3' ), '<a href="' . esc_url( $current_url ) . '" style="color: #0073aa; text-decoration: none;">' . esc_html( $current_url ) . '</a>' );
			$message .= $this->format_email_row( __( 'User', 'form-quizz-fqi3' ), sprintf( '%s (%s)', $current_user->display_name, $current_user->user_email ) );
			$message .= $this->format_email_row( __( 'Site', 'form-quizz-fqi3' ), $site_name . ' (<a href="' . esc_url( $site_url ) . '" style="color: #0073aa; text-decoration: none;">' . esc_html( $site_url ) . '</a>)' );
			$message .= $this->format_email_row( __( 'Browser', 'form-quizz-fqi3' ), $user_agent );
			$message .= $this->format_email_row( __( 'WordPress Version', 'form-quizz-fqi3' ), get_bloginfo( 'version' ) );
			$message .= $this->format_email_row( __( 'Plugin Version', 'form-quizz-fqi3' ), FQI3_VERSION );

			$message .= '</table>';
			$message .= '</div>';
			$message .= '<p style="font-size: 0.9em; color: #6c757d; margin: 0; padding: 10px 0 0; border-top: 1px solid #eee;">';
			$message .= __( 'This is an automated message. Please do not reply directly.', 'form-quizz-fqi3' );
			$message .= '</p>';
			$message .= '</div>';
			$message .= '</body></html>';

			// En-têtes pour l'email HTML
			$headers = array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $site_name . ' <' . $to . '>',
				'Reply-To: ' . $current_user->display_name . ' <' . $current_user->user_email . '>',
			);

			// Envoyer l'email
			$mail_sent = wp_mail( $to, $subject, $message, $headers );

			// Enregistrer le rapport dans la base de données
			$report_saved = $this->save_issue_report( $report_data );

			// Retourner la réponse
			if ( $mail_sent || $report_saved ) {
				wp_send_json_success(
					array(
						'message' => __( 'Thank you for your report. Our team will investigate the issue as soon as possible.', 'form-quizz-fqi3' ),
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => __( 'Your report has been recorded, but an error occurred while sending the email. Our team has been notified.', 'form-quizz-fqi3' ),
					)
				);
			}
		}

		/**
		 * Handle add-on suggestion submission
		 *
		 * @since 3.2.0
		 * @return void
		 */
		public function handle_suggest_addon() {
			// Verify nonce
			if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'fqi3_suggest_addon' ) ) {
				wp_send_json_error( __( 'Security check failed.', 'form-quizz-fqi3' ), 403 );
				return;
			}

			// Check required fields
			$required_fields = array(
				'addon_name'        => __( 'Add-on name', 'form-quizz-fqi3' ),
				'addon_description' => __( 'Description', 'form-quizz-fqi3' ),
				'addon_email'       => __( 'Email', 'form-quizz-fqi3' ),
			);

			$errors = array();
			$data   = array();

			foreach ( $required_fields as $field => $label ) {
				if ( empty( $_POST[ $field ] ) ) {
					$errors[] = sprintf(
						/* translators: %s: Field label */
						__( '%s is a required field.', 'form-quizz-fqi3' ),
						$label
					);
				} else {
					$data[ $field ] = sanitize_text_field( $_POST[ $field ] );
				}
			}

			// Validate email
			if ( ! empty( $data['addon_email'] ) && ! is_email( $data['addon_email'] ) ) {
				$errors[] = __( 'Please enter a valid email address.', 'form-quizz-fqi3' );
			}

			// Check terms agreement
			if ( empty( $_POST['terms_agreement'] ) ) {
				$errors[] = __( 'You must agree to the terms to submit a suggestion.', 'form-quizz-fqi3' );
			}

			// Process website if provided
			$data['addon_website'] = ! empty( $_POST['addon_website'] ) ? esc_url_raw( $_POST['addon_website'] ) : '';

			if ( ! empty( $errors ) ) {
				wp_send_json_error( implode( '<br>', $errors ), 400 );
				return;
			}

			// Get current user info
			$current_user = wp_get_current_user();
			$user_info    = array(
				'user_id'      => $current_user->ID,
				'username'     => $current_user->user_login,
				'display_name' => $current_user->display_name,
				'email'        => $current_user->user_email,
			);

			// Prepare the suggestion data
			$suggestion = array(
				'addon_name'        => $data['addon_name'],
				'addon_description' => $data['addon_description'],
				'addon_website'     => $data['addon_website'],
				'user_email'        => $data['addon_email'],
				'user_info'         => $user_info,
				'site_url'          => get_site_url(),
				'timestamp'         => current_time( 'mysql' ),
			);

			// Save the suggestion (you can modify this to save to database or send via email)
			$saved = $this->save_addon_suggestion( $suggestion );

			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( $saved->get_error_message(), 500 );
				return;
			}

			// Send email notification (optional)
			$email_sent = $this->send_addon_suggestion_email( $suggestion );

			if ( is_wp_error( $email_sent ) ) {
				// Log the error but don't fail the request
				error_log( 'Failed to send add-on suggestion email: ' . $email_sent->get_error_message() );
			}

			wp_send_json_success(
				array(
					'message'    => __( 'Thank you for your suggestion! Our team will review it shortly.', 'form-quizz-fqi3' ),
					'suggestion' => $suggestion,
				)
			);
		}

		/**
		 * Renders the report issue modal
		 */
		private function render_report_issue_modal() {
			?>
			<div class="modal fade" id="reportIssueModal" tabindex="-1" aria-labelledby="reportIssueModalLabel" aria-hidden="true">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="reportIssueModalLabel"><?php esc_html_e( 'Report an Issue', 'form-quizz-fqi3' ); ?></h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_html_e( 'Close', 'form-quizz-fqi3' ); ?>"></button>
						</div>
						<div class="modal-body">
							<form id="reportIssueForm">
								<div class="mb-3">
									<label for="issueAddon" class="form-label"><?php esc_html_e( 'Related Add-on', 'form-quizz-fqi3' ); ?></label>
									<select class="form-select" id="issueAddon" name="addon" required>
										<option value="general"><?php esc_html_e( 'General Issue', 'form-quizz-fqi3' ); ?></option>
										<?php foreach ( $this->available_addons as $addon ) : ?>
											<option value="<?php echo esc_attr( $addon['slug'] ); ?>">
												<?php echo esc_html( $addon['title'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="mb-3">
									<label for="issueType" class="form-label"><?php esc_html_e( 'Issue Type', 'form-quizz-fqi3' ); ?></label>
									<select class="form-select" id="issueType" name="issue_type" required>
										<option value="bug"><?php esc_html_e( 'Bug', 'form-quizz-fqi3' ); ?></option>
										<option value="suggestion"><?php esc_html_e( 'Suggestion', 'form-quizz-fqi3' ); ?></option>
										<option value="other"><?php esc_html_e( 'Other', 'form-quizz-fqi3' ); ?></option>
									</select>
								</div>
								<div class="mb-3">
									<label for="issueDescription" class="form-label"><?php esc_html_e( 'Description', 'form-quizz-fqi3' ); ?> *</label>
									<textarea class="form-control" id="issueDescription" name="description" rows="4" required 
										placeholder="<?php esc_attr_e( 'Please describe the issue in detail...', 'form-quizz-fqi3' ); ?>"></textarea>
								</div>
								<div class="mb-3">
									<label for="issueSteps" class="form-label"><?php esc_html_e( 'Steps to Reproduce', 'form-quizz-fqi3' ); ?></label>
									<textarea class="form-control" id="issueSteps" name="steps" rows="3" 
										placeholder="<?php esc_attr_e( '1. Go to...\n2. Click on...\n3. Error occurs when...', 'form-quizz-fqi3' ); ?>"></textarea>
								</div>
								<input type="hidden" name="current_url" value="<?php echo esc_url( admin_url( 'admin.php?page=fqi3-add-ons' ) ); ?>">
								<?php wp_nonce_field( 'fqi3_report_issue', 'report_issue_nonce' ); ?>
							</form>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancel', 'form-quizz-fqi3' ); ?></button>
							<button type="button" class="btn btn-danger" id="submitIssueReport">
								<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
								<?php esc_html_e( 'Submit Report', 'form-quizz-fqi3' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Saves the add-on suggestion to the database.
		 *
		 * @since 3.2.0
		 * @param array $suggestion The suggestion data.
		 * @return bool|WP_Error True on success, WP_Error on failure.
		 */
		private function save_addon_suggestion( $suggestion ) {
			// Get existing suggestions
			$suggestions = get_option( 'fqi3_addon_suggestions', array() );

			// Add new suggestion
			$suggestions[] = array(
				'id'         => uniqid( 'sug_' ),
				'data'       => $suggestion,
				'status'     => 'pending',
				'created_at' => current_time( 'mysql' ),
			);

			// Save back to database
			$updated = update_option( 'fqi3_addon_suggestions', $suggestions, false );

			if ( ! $updated ) {
				return new WP_Error( 'save_failed', __( 'Failed to save your suggestion. Please try again.', 'form-quizz-fqi3' ) );
			}

			return true;
		}

		/**
		 * Sends an email notification about the new add-on suggestion.
		 *
		 * @since 3.2.0
		 * @param array $suggestion The suggestion data.
		 * @return bool|WP_Error True on success, WP_Error on failure.
		 */
		private function send_addon_suggestion_email( $suggestion ) {
			$to      = get_bloginfo( 'admin_email' );
			$subject = sprintf(
				/* translators: %1$s: Site name, %2$s: Add-on name */
				__( '[%1$s] New Add-on Suggestion: %2$s', 'form-quizz-fqi3' ),
				get_bloginfo( 'name' ),
				$suggestion['addon_name']
			);

			$message = sprintf(
				/* translators: %s: Site name */
				__( 'A new add-on has been suggested on %s:', 'form-quizz-fqi3' ),
				get_bloginfo( 'name' )
			) . "\n\n";

			$message .= sprintf(
				/* translators: %s: Add-on name */
				__( 'Add-on Name: %s', 'form-quizz-fqi3' ),
				$suggestion['addon_name']
			) . "\n";
			$message .= sprintf(
				/* translators: %s: Add-on description */
				__( 'Description: %s', 'form-quizz-fqi3' ),
				$suggestion['addon_description']
			) . "\n";

			if ( ! empty( $suggestion['addon_website'] ) ) {
				$message .= sprintf(
					/* translators: %s: Add-on website */
					__( 'Website: %s', 'form-quizz-fqi3' ),
					$suggestion['addon_website']
				) . "\n";
			}

			$message .= "\n" . __( 'Submitted by:', 'form-quizz-fqi3' ) . "\n";
			$message .= sprintf(
				/* translators: %s: User name */
				__( 'Name: %s', 'form-quizz-fqi3' ),
				$suggestion['user_info']['display_name']
			) . "\n";
			$message .= sprintf(
				/* translators: %s: User email */
				__( 'Email: %s', 'form-quizz-fqi3' ),
				$suggestion['user_email']
			) . "\n";
			$message .= sprintf(
				/* translators: %s: Site URL */
				__( 'Site: %s', 'form-quizz-fqi3' ),
				$suggestion['site_url']
			) . "\n";

			$headers = array(
				'Content-Type: text/plain; charset=UTF-8',
				sprintf( 'From: %s <%s>', $suggestion['user_info']['display_name'], $suggestion['user_email'] ),
				sprintf( 'Reply-To: %s <%s>', $suggestion['user_info']['display_name'], $suggestion['user_email'] ),
			);

			return wp_mail( $to, $subject, $message, $headers );
		}

		/**
		 * Renders the Add-Ons page in the WordPress admin.
		 *
		 * This method generates a settings page displaying available add-ons with a search bar
		 * and filter options. It dynamically renders the add-ons grid using the `render_addons_grid` method.
		 *
		 * @return void
		 */
		public function render_add_ons_page(): void {
			// Ensure addons are initialized
			if ( empty( $this->available_addons ) ) {
				$this->init_available_addons();
			}
			if ( $this->updater_class_available && empty( $this->updaters ) ) {
				$this->init_updaters();
			}

			?>
			<div class="wrap">
				<div class="container-fluid px-4 py-5">
					<div class="row align-items-center mb-4">
						<div class="col-md-6">
							<h2 class="h3 mb-3 mb-md-0"><?php esc_html_e( 'Available Add-Ons', 'form-quizz-fqi3' ); ?></h2>
							<p><?php esc_html_e( 'Add-Ons allow you to extend the functionality of Form Quizz FQI3.', 'form-quizz-fqi3' ); ?></p>
							<p class="text-muted">
								<?php
								$total_count     = count( $this->available_addons );
								$available_count = count(
									array_filter(
										$this->available_addons,
										function ( $addon ) {
											return ! empty( $addon['available'] );
										}
									)
								);
								printf(
									/* translators: 1 = singular, %d = number of add-ons */
									_n(
										'Actually %1$d add-on is available on %2$d total.',
										'Actually %1$d add-ons are available on %2$d total.',
										$available_count,
										'form-quizz-fqi3'
									),
									$available_count,
									$total_count
								);
								?>
						</p>
						</div>
						<div class="col-md-6">
							<div class="btn-group float-end mb-3 gap-2">
								<button type="button" 
										class="btn btn-outline-secondary btn-sm" 
										id="refresh-cache"
										<?php echo ! $this->updater_class_available ? 'disabled title="' . esc_attr__( 'Plugin updater not available', 'form-quizz-fqi3' ) . '"' : ''; ?>>
										<i class="bi bi-arrow-clockwise"></i>
									<?php esc_html_e( 'Refresh Cache', 'form-quizz-fqi3' ); ?>
								</button>
								<button type="button" 
										class="btn btn-primary btn-sm" 
										id="submitAddonSuggestion"
										data-bs-toggle="modal" 
										data-bs-target="#suggestAddonModal">
										<i class="bi bi-plus"></i>
									<?php esc_html_e( 'Suggest an Add-on', 'form-quizz-fqi3' ); ?>
								</button>
								<button type="button"
										class="btn btn-sm btn-outline-danger report-issue">
										<i class="bi bi-flag"></i>
									<?php echo esc_attr__( 'Report Issue', 'form-quizz-fqi3' ); ?>
								</button>
							</div>
							<div class="input-group">
								<input type="text" 
									class="form-control" 
									id="addon-search"
									placeholder="<?php esc_attr_e( 'Search an add-on...', 'form-quizz-fqi3' ); ?>">
								<button class="btn btn-outline-secondary dropdown-toggle" 
										type="button" 
										data-bs-toggle="dropdown">
									<?php esc_html_e( 'Filter', 'form-quizz-fqi3' ); ?>
								</button>
								<ul class="dropdown-menu dropdown-menu-end">
									<li><a class="dropdown-item filter-option" data-filter="all" href="#"><?php esc_html_e( 'All', 'form-quizz-fqi3' ); ?></a></li>
									<li><a class="dropdown-item filter-option" data-filter="installed" href="#"><?php esc_html_e( 'Installed', 'form-quizz-fqi3' ); ?></a></li>
									<li><a class="dropdown-item filter-option" data-filter="available" href="#"><?php esc_html_e( 'Available', 'form-quizz-fqi3' ); ?></a></li>
								</ul>
							</div>
						</div>
					</div>
					<!-- Display available updates -->
					<?php
					if ( $this->updater_class_available ) {
						$this->render_update_notices();}
					?>

					<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="addons-grid">
						<?php $this->render_addons_grid( $this->available_addons ); ?>
					</div>
				</div>
			</div>
			
			<!-- Suggest Add-on Modal -->
			<div class="modal fade" id="suggestAddonModal" tabindex="-1" aria-labelledby="suggestAddonModalLabel" aria-hidden="true">
				<div class="modal-dialog modal-lg">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="suggestAddonModalLabel">
								<?php esc_html_e( 'Suggest a New Add-on', 'form-quizz-fqi3' ); ?>
							</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'form-quizz-fqi3' ); ?>"></button>
						</div>
						<div class="modal-body">
							<form id="suggestAddonForm" method="post">
								<?php wp_nonce_field( 'fqi3_suggest_addon_nonce', 'fqi3_addon_nonce' ); ?>
								
								<div class="mb-3">
									<label for="addonName" class="form-label">
										<?php esc_html_e( 'Add-on Name', 'form-quizz-fqi3' ); ?> *
									</label>
									<input type="text" class="form-control" id="addonName" name="addon_name" required>
								</div>
								
								<div class="mb-3">
									<label for="addonDescription" class="form-label">
										<?php esc_html_e( 'Description', 'form-quizz-fqi3' ); ?> *
									</label>
									<textarea class="form-control" id="addonDescription" name="addon_description" rows="3" required></textarea>
									<div class="form-text">
										<?php esc_html_e( 'Please describe what this add-on would do and how it would benefit users.', 'form-quizz-fqi3' ); ?>
									</div>
								</div>
								
								<div class="row">
									<div class="col-md-6">
										<div class="mb-3">
											<label for="addonWebsite" class="form-label">
												<?php esc_html_e( 'Website (optional)', 'form-quizz-fqi3' ); ?>
											</label>
											<input type="url" class="form-control" id="addonWebsite" name="addon_website">
										</div>
									</div>
									<div class="col-md-6">
										<div class="mb-3">
											<label for="addonEmail" class="form-label">
												<?php esc_html_e( 'Your Email', 'form-quizz-fqi3' ); ?> *
											</label>
											<input type="email" class="form-control" id="addonEmail" name="addon_email" required>
											<div class="form-text">
												<?php esc_html_e( 'In case we need to contact you for more information.', 'form-quizz-fqi3' ); ?>
											</div>
										</div>
									</div>
								</div>
								
								<div class="mb-3">
									<div class="form-check">
										<input class="form-check-input" type="checkbox" id="termsAgreement" name="terms_agreement" required>
										<label class="form-check-label" for="termsAgreement">
											<?php esc_html_e( 'I understand that this is only a suggestion and there is no guarantee it will be implemented.', 'form-quizz-fqi3' ); ?>
										</label>
									</div>
								</div>
								
								<div class="alert alert-info">
									<i class="dashicons dashicons-info"></i>
									<?php esc_html_e( 'Your suggestion will be sent to our development team for review. We appreciate your input!', 'form-quizz-fqi3' ); ?>
								</div>
							</form>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
								<?php esc_html_e( 'Cancel', 'form-quizz-fqi3' ); ?>
							</button>
							<button type="submit" form="suggestAddonForm" class="btn btn-primary">
								<span class="dashicons dashicons-email"></span>
								<?php esc_html_e( 'Submit Suggestion', 'form-quizz-fqi3' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
				
			<?php
			// Render the report issue modal
			$this->render_report_issue_modal();
		}

		/**
		 * Handles AJAX requests for filtering add-ons.
		 *
		 * Filters the list of available add-ons based on the selected filter type (installed, available).
		 * Responds with a JSON object containing filtered add-ons or an error message if none are found.
		 *
		 * @since 3.0.0
		 * @return void
		 */
		public function handle_addon_action() {
			check_ajax_referer( 'fqi3_addon_action', 'nonce' );

			if ( ! current_user_can( 'install_plugins' ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'form-quizz-fqi3' ) ) );
			}

			if ( empty( $this->available_addons ) ) {
				$this->init_available_addons();
			}

			$action = sanitize_text_field( $_POST['action_type'] ?? '' );
			$slug   = sanitize_text_field( $_POST['slug'] ?? '' );

			if ( empty( $action ) || empty( $slug ) ) {
				wp_send_json_error( array( 'message' => __( 'Missing parameters.', 'form-quizz-fqi3' ) ) );
			}

			// Find the addon only once
			$addon = null;
			foreach ( $this->available_addons as $a ) {
				if ( $a['slug'] === $slug ) {
					$addon = $a;
					break;
				}
			}

			if ( ! $addon ) {
				wp_send_json_error( array( 'message' => __( 'Addon not found.', 'form-quizz-fqi3' ) ) );
			}

			if ( $action === 'install' ) {
				$result = $this->install_addon( $addon );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				wp_send_json_success( array( 'message' => $result['message'] ) );
			}

			if ( $action === 'activate' ) {
				$result = activate_plugin( $slug . '/' . $slug . '.php' );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}
				wp_send_json_success( array( 'message' => __( 'Activated.', 'form-quizz-fqi3' ) ) );
			}

			if ( $action === 'deactivate' ) {
				// Enforce dependency constraints server-side too
				if ( ! $this->can_deactivate_addon( $slug ) ) {
					// Prefer specific reason
					$has_active_dependency = $this->has_active_dependency( $slug );
					if ( $has_active_dependency ) {
						wp_send_json_error( array( 'message' => __( 'Cannot deactivate because a required dependency is active.', 'form-quizz-fqi3' ) ) );
					}
					$dependent_addons = $this->get_active_dependent_addons( $slug );
					if ( ! empty( $dependent_addons ) ) {
						wp_send_json_error(
							array(
								'message' => sprintf(
									/* translators: %s is the list of dependent add-ons */
									__( 'Cannot deactivate because it is required by: %s', 'form-quizz-fqi3' ),
									implode( ', ', array_column( $dependent_addons, 'title' ) )
								),
							)
						);
					}
					wp_send_json_error( array( 'message' => __( 'Cannot deactivate due to dependency constraints.', 'form-quizz-fqi3' ) ) );
				}
				deactivate_plugins( $slug . '/' . $slug . '.php' );
				wp_send_json_success( array( 'message' => __( 'Deactivated.', 'form-quizz-fqi3' ) ) );
			}

			wp_send_json_error( array( 'message' => __( 'Invalid action.', 'form-quizz-fqi3' ) ) );
		}

		/**
		 * Install an addon from GitHub release
		 *
		 * @param array $addon Addon data
		 * @return array|WP_Error Success message or WP_Error on failure
		 */
		private function install_addon( $addon ) {
			// Include the necessary files
			if ( ! function_exists( 'request_filesystem_credentials' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! class_exists( 'Plugin_Upgrader' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$github_username = 'Webpixelia';
			$repo_parts      = explode( '/', $addon['github_repo'] );
			$repo_name       = end( $repo_parts );

			if ( ! defined( 'GITHUB_PERSONAL_TOKEN' ) || empty( GITHUB_PERSONAL_TOKEN ) ) {
				return new WP_Error( 'github_token_missing', __( 'GitHub token is not configured.', 'form-quizz-fqi3' ) );
			}

			// Get the latest release
			$release_url = "https://api.github.com/repos/{$github_username}/{$repo_name}/releases/latest";

			$release_response = wp_remote_get(
				$release_url,
				array(
					'headers' => array(
						'Authorization' => 'token ' . GITHUB_PERSONAL_TOKEN,
						'Accept'        => 'application/vnd.github.v3+json',
						'User-Agent'    => 'WordPress-Form-Quizz-Plugin', // GitHub require User-Agent
					),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $release_response ) ) {
				return new WP_Error(
					'github_api_error',
					sprintf(
						/* translators: %s error message */
						__( 'GitHub API error: %s', 'form-quizz-fqi3' ),
						$release_response->get_error_message()
					)
				);
			}

			$response_code = wp_remote_retrieve_response_code( $release_response );
			if ( $response_code !== 200 ) {
				return new WP_Error(
					'github_api_error',
					sprintf(
						/* translators: %s status response GitHub API */
						__( 'GitHub API responded with status %d.', 'form-quizz-fqi3' ),
						$response_code
					)
				);
			}

			$release_data = json_decode( wp_remote_retrieve_body( $release_response ), true );

			if ( ! isset( $release_data['zipball_url'] ) ) {
				return new WP_Error( 'no_release', __( 'No release found for this addon.', 'form-quizz-fqi3' ) );
			}

			$plugin_zip_url = $release_data['zipball_url'];

			// Télécharger le fichier
			$tmp_file = wp_tempnam( $plugin_zip_url );
			$response = wp_remote_get(
				$plugin_zip_url,
				array(
					'headers' => array(
						'Authorization' => 'token ' . GITHUB_PERSONAL_TOKEN,
						'Accept'        => 'application/vnd.github.v3+json',
						'User-Agent'    => 'WordPress-Form-Quizz-Plugin',
					),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				@unlink( $tmp_file );
				return $response;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				@unlink( $tmp_file );
				return new WP_Error(
					'download_error',
					sprintf(
						/* translators: %s status response GitHub API */
						__( 'Download failed with status %d.', 'form-quizz-fqi3' ),
						$response_code
					)
				);
			}

			$body = wp_remote_retrieve_body( $response );
			if ( strlen( $body ) < 100 ) {
				@unlink( $tmp_file );
				return new WP_Error( 'empty_file', __( 'Downloaded file is too small or empty.', 'form-quizz-fqi3' ) );
			}

			file_put_contents( $tmp_file, $body );

			// GitHub archives extract to "<owner>-<repo>-<sha>": rename the package folder to the add-on slug
			// before it is copied to wp-content/plugins (instead of renaming an arbitrary folder afterwards).
			$slug_for_package = (string) $addon['slug'];
			$rename_source    = function ( $source, $remote_source ) use ( $slug_for_package ) {
				return $this->rename_package_source( $source, $remote_source, $slug_for_package );
			};

			// Install plugin
			$skin     = new \WP_Ajax_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );
			add_filter( 'upgrader_source_selection', $rename_source, 10, 2 );
			try {
				$result = $upgrader->install( $tmp_file );
			} finally {
				remove_filter( 'upgrader_source_selection', $rename_source, 10 );
				// Clean temp file
				@unlink( $tmp_file );
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$plugin_dir = WP_PLUGIN_DIR . '/' . $addon['slug'];
			if ( ! is_dir( $plugin_dir ) ) {
				return new WP_Error(
					'plugin_dir_not_found',
					__( 'Plugin installed but folder not found.', 'form-quizz-fqi3' )
				);
			}

			// Activate the plugin
			return $this->activate_plugin_in_directory( $plugin_dir, $addon['slug'] );
		}

		/**
		 * Rename the extracted package folder to the add-on slug (upgrader_source_selection callback).
		 *
		 * @param string $source        Extracted package folder.
		 * @param string $remote_source Folder containing the extracted package.
		 * @param string $slug          Expected add-on slug.
		 * @return string|WP_Error New source folder.
		 * @since 3.4.0
		 */
		private function rename_package_source( $source, $remote_source, string $slug ) {
			global $wp_filesystem;

			$desired = trailingslashit( $remote_source ) . $slug . '/';
			if ( trailingslashit( $source ) === $desired ) {
				return $source;
			}

			if ( ! $wp_filesystem || ! $wp_filesystem->move( $source, $desired, true ) ) {
				return new WP_Error( 'rename_failed', __( 'Could not prepare the add-on folder.', 'form-quizz-fqi3' ) );
			}

			return $desired;
		}

		/**
		 * Activate plugin in directory
		 *
		 * @param string $plugin_dir Plugin directory path
		 * @param string $slug Plugin slug
		 * @return array|WP_Error Success message or WP_Error
		 */
		private function activate_plugin_in_directory( $plugin_dir, $slug ) {
			// Find the main file
			$php_files = glob( $plugin_dir . '/*.php' );

			foreach ( $php_files as $file ) {
				$plugin_data = get_plugin_data( $file, false, false );
				if ( ! empty( $plugin_data['Name'] ) ) {
					$relative_path = str_replace( WP_PLUGIN_DIR . '/', '', $file );
					$result        = activate_plugin( $relative_path );

					if ( is_wp_error( $result ) ) {
						return array(
							'message' => __( 'Plugin installed but activation failed.', 'form-quizz-fqi3' ),
						);
					}
					return array(
						'message' => __( 'Plugin installed and activated successfully!', 'form-quizz-fqi3' ),
					);
				}
			}

			return array(
				'message' => __( 'Plugin installed successfully but could not auto-activate.', 'form-quizz-fqi3' ),
			);
		}

		/**
		 * Load the Plugin_Updater class
		 *
		 * @since 3.2.0
		 * @return void
		 */
		private function load_updater_class(): void {
			$updater_file = FQI3_INCLUDES . '/admin/class-plugin-updater.php';

			if ( file_exists( $updater_file ) ) {
				require_once $updater_file;
				$this->updater_class_available = class_exists( '\Form_Quizz_FQI3\Plugin_Updater' );
			} else {
				error_log( 'FQI3: Plugin_Updater class file not found at: ' . $updater_file );
				$this->updater_class_available = false;
			}
		}

		/**
		 * Initialize updaters for installed add-ons
		 *
		 * @since 3.2.0
		 * @return void
		 */
		public function init_updaters(): void {
			if ( ! $this->updater_class_available ) {
				return;
			}

			if ( empty( $this->available_addons ) ) {
				$this->init_available_addons();
			}

			foreach ( $this->available_addons as $addon ) {
				$plugin_file = $addon['slug'] . '/' . $addon['slug'] . '.php';

				// Only initialize updater if plugin is installed and has a github repo
				if ( $this->get_addon_status( $addon['slug'] ) === 'installed' && ! empty( $addon['github_repo'] ) ) {
					$github_repo = $this->extract_github_repo( $addon['github_repo'] );

					// Skip if no github repo is set
					if ( empty( $github_repo ) ) {
						continue;
					}

					try {
						$this->updaters[ $addon['slug'] ] = new \Form_Quizz_FQI3\Plugin_Updater(
							$plugin_file,
							$github_repo,
							'Webpixelia'
						);
					} catch ( \Exception $e ) {
						error_log( 'FQI3: Failed to initialize updater for ' . $addon['slug'] . ': ' . $e->getMessage() );
					}
				}
			}
		}

		/**
		 * Extract repository name from GitHub URL
		 *
		 * @since 3.2.0
		 *
		 * @param string $github_url
		 * @return string
		 */
		private function extract_github_repo( string $github_url ): string {
			if ( empty( $github_url ) ) {
				return '';
			}

			// Extract from URLs like https://github.com/Webpixelia/repo-name
			if ( preg_match( '#github\.com/[^/]+/([^/]+)#', $github_url, $matches ) ) {
				return $matches[1];
			}

			// If it's just the repo name
			return $github_url;
		}

		/**
		 * Handle AJAX request to refresh addon cache
		 *
		 * @since 3.2.0
		 * @return void
		 */
		public function refresh_addon_cache(): void {
			check_ajax_referer( 'fqi3_refresh_cache', 'nonce' );

			if ( ! current_user_can( 'update_plugins' ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'You are not allowed to perform this action.', 'form-quizz-fqi3' ),
					)
				);
			}

			if ( ! $this->updater_class_available ) {
				wp_send_json_error(
					array(
						'message' => __( 'Plugin updater is not available.', 'form-quizz-fqi3' ),
					)
				);
			}

			$refreshed_count = 0;
			$errors          = array();

			// Clear update caches for all installed add-ons
			foreach ( $this->updaters as $slug => $updater ) {
				try {
					if ( method_exists( $updater, 'clear_cache' ) ) {
						$updater->clear_cache();
						++$refreshed_count;
					}
				} catch ( \Exception $e ) {
					$errors[] = sprintf(
						/* translators: 1 = add-on slug, 2 = error message */
						__( 'Error refreshing %1$s: %2$s', 'form-quizz-fqi3' ),
						$slug,
						esc_html( $e->getMessage() )
					);
				}
			}

			// Clear WordPress update transients
			delete_site_transient( 'update_plugins' );
			wp_clean_plugins_cache();

			if ( empty( $errors ) ) {
				wp_send_json_success(
					array(
						'message' => sprintf(
							/* translators: %d = number of refreshed add-ons */
							_n(
								'Successfully refreshed cache for %d add-on.',
								'Successfully refreshed cache for %d add-ons.',
								$refreshed_count,
								'form-quizz-fqi3'
							),
							$refreshed_count
						),
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => implode( '<br>', $errors ),
					)
				);
			}
		}


		/**
		 * Get update information for an addon
		 *
		 * @since 3.2.0
		 * @param string $addon_slug
		 * @return array|false
		 */
		public function get_addon_update_info( string $addon_slug ) {
			if ( ! $this->updater_class_available || ! isset( $this->updaters[ $addon_slug ] ) ) {
				return false;
			}

			$updater = $this->updaters[ $addon_slug ];

			try {
				if ( ! $updater->is_update_available() ) {
					return false;
				}

				$latest = $updater->force_check();
				if ( ! $latest ) {
					return false;
				}

				return array(
					'current_version'  => $updater->get_current_version(),
					'latest_version'   => $latest['version'],
					'update_available' => true,
					'changelog'        => $latest['changelog'] ?? '',
				);
			} catch ( \Exception $e ) {
				error_log( 'FQI3: Error getting update info for ' . $addon_slug . ': ' . $e->getMessage() );
				return false;
			}
		}

		/**
		 * Render update notices for installed addons
		 *
		 * @since 3.2.0
		 * @return void
		 */
		private function render_update_notices(): void {
			if ( ! $this->updater_class_available ) {
				return;
			}

			$updates_available = array();

			foreach ( $this->available_addons as $addon ) {
				if ( $this->get_addon_status( $addon['slug'] ) === 'installed' ) {
					$update_info = $this->get_addon_update_info( $addon['slug'] );
					if ( $update_info ) {
						$updates_available[] = array_merge( $addon, $update_info );
					}
				}
			}

			if ( ! empty( $updates_available ) ) {
				?>
				<div class="alert alert-info mb-4">
					<h5><i class="bi bi-info-circle"></i> <?php esc_html_e( 'Updates Available', 'form-quizz-fqi3' ); ?></h5>
					<p><?php esc_html_e( 'The following add-ons have updates available:', 'form-quizz-fqi3' ); ?></p>
					<ul class="mb-0">
						<?php foreach ( $updates_available as $addon ) : ?>
							<li>
								<strong><?php echo esc_html( $addon['title'] ); ?></strong>
								- 
								<?php
								printf(
									/* translators: 1 = current version, 2 = latest version */
									__( 'Version %1$s → %2$s', 'form-quizz-fqi3' ),
									esc_html( $addon['current_version'] ),
									esc_html( $addon['latest_version'] )
								);
								?>
								<a href="<?php echo admin_url( 'plugins.php' ); ?>" class="btn btn-sm btn-outline-primary ms-2">
									<?php esc_html_e( 'Update Now', 'form-quizz-fqi3' ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php
			}
		}
	}
endif;
