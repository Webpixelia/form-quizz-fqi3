<?php
namespace Form_Quizz_FQI3;
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

if (!defined('ABSPATH')) {
    exit;
}

if ( !class_exists( 'FQI3_Addons_Page' ) ) :
    class FQI3_Addons_Page {
        private array $available_addons;
        private array $updaters = [];
        private bool $updater_class_available = false;

        public function __construct() {
            // Initialize properties to avoid "must not be accessed before initialization" error
            $this->available_addons = [];

            // Include the Plugin_Updater class
            $this->load_updater_class();
            
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts'], 5);
            add_action('init', [$this, 'init_available_addons']);
            add_action('wp_ajax_search_addons', [$this, 'search_addons']);
            add_action('wp_ajax_filter_addons', [$this, 'filter_addons']);
            add_action('wp_ajax_fqi3_addon_action', [$this, 'handle_addon_action']);
            add_action('wp_ajax_fqi3_suggest_addon', [$this, 'handle_suggest_addon']);
            add_action('wp_ajax_fqi3_report_issue', [$this, 'handle_report_issue']);

            // Initialize updaters for installed addons (only if class is available)
            if ($this->updater_class_available) {
                add_action('init', [$this, 'init_updaters'], 20);
                add_action('wp_ajax_refresh_addon_cache', [$this, 'refresh_addon_cache']);
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
            wp_enqueue_script('popper', 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js', [], '2.11.8', true);
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
        private function get_addon_status(string $addon_slug): string {
            $installed_plugins = get_plugins();   
            $plugin_file = $addon_slug . '/' . $addon_slug . '.php';
            
            return isset($installed_plugins[$plugin_file]) ? 'installed' : 'available';
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
            $this->available_addons = [
                [
                    'title' => 'Subscription Add-on',
                    'description' => __('The solution to create and manage a member area and subscriptions', 'form-quizz-fqi3'),
                    'features' => [
                        __('Create and manage customizable subscription plans: recurring, one time, customizable duration', 'form-quizz-fqi3'),
                        __('Seamless integration with Stripe for secure payments (PayPal coming Soon)', 'form-quizz-fqi3'),
                        __('Intuitive user interface to manage subscriptions and profil', 'form-quizz-fqi3'),
                        __('Easy integration via shortcodes or by configuring pages', 'form-quizz-fqi3')
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Subscription_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => 'form-quizz-fqi3-subscription-addon',
                    'branch'      => 'main',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-subscription-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-subscription-addon'),
                    'dependance' => '',
                    'available' => true,
                ],
                [
                    'title' => 'Affiliate Add-on',
                    'description' => __('Allow users to promote your quizzes and earn commissions on subscriptions or paid quizzes.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Referral system with unique tracking links (last-click attribution).','form-quizz-fqi3'),
                        __('Affiliate dashboard with commission tracking.','form-quizz-fqi3'),
                        __('Payouts management: approve eligible conversions, schedule payouts, mark as paid.','form-quizz-fqi3'),
                        __('Reports and analytics: KPIs, Top Affiliates, Click Anomalies with CSV export.','form-quizz-fqi3'),
                        __('Integration with third-party affiliate platforms (Refersion, Tapfiliate) with Test Connection.','form-quizz-fqi3')
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Affiliate_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => 'form-quizz-fqi3-affiliate-addon',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-affiliate-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-affiliate-addon'),
                    'dependance' => [
                        'slug' => 'form-quizz-fqi3-subscription-addon',
                        'name' => 'Subscription Add-on',
                    ],
                    'available' => true,
                ],
                [
                    'title' => 'Social Login Add-On',
                    'description' => __('Allow users to quickly and securely log in using their favorite social media accounts.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Support for Facebook, Google, LinkedIn.','form-quizz-fqi3'),
                        __('Synchronization of profile data with user dashboard.','form-quizz-fqi3'),
                        __('Simplify the registration process for new users.','form-quizz-fqi3'),
                        __('Increase user retention and engagement with simplified login and registration options.','form-quizz-fqi3'),
                        __('Leverage the security of social media platforms for authentication.','form-quizz-fqi3')
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Social_Login_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => 'form-quizz-fqi3-social-login-addon',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-social-login-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-social-login-addon'),
                    'dependance' => [
                        'slug' => 'form-quizz-fqi3-subscription-addon',
                        'name' => 'Subscription Add-on',
                    ],
                    'available' => true,
                ],
                [
                    'title' => 'Email Marketing Integrations Add-On',
                    'description' => __('Boost User Engagement with Automated Email Marketing! Seamlessly integrate your quizzes with leading email marketing services to nurture leads and keep users engaged.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Automatic Quiz Follow-Ups: Send emails based on quiz completion, scores, or inactivity.', 'form-quizz-fqi3'),
                        __('Personalized Campaigns: Use dynamic variables (e.g., user name, quiz results) for tailored messaging.', 'form-quizz-fqi3'),
                        __('Seamless Integrations: Connect with Mailchimp, Brevo (ex-Sendinblue), and ActiveCampaign effortlessly.', 'form-quizz-fqi3'),
                        __('Grow Your Audience: Capture leads and automate email sequences for better retention.', 'form-quizz-fqi3')
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Email_Marketing_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => 'form-quizz-fqi3-email-marketing-addon',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-email-marketing-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-email-marketing-addon'),
                    'dependance' => '',
                    'available' => true,
                ],
                [
                    'title' => 'Notifications: Email, WhatsApp & SMS',
                    'description' => __('Send automated notifications for Form Quizz FQI3 events via Email, WhatsApp (Cloud API) and SMS (Twilio). Respect per-user preferences (channels per event) and integrate seamlessly with the Subscription addon\'s user dashboard, or expose a front-end preferences form via shortcode.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Notifications per user preferences across Email, WhatsApp and SMS (Twilio).', 'form-quizz-fqi3'),
                        __('Reminders for incomplete quizzes (scheduled follow-ups).', 'form-quizz-fqi3'),
                        __('Alerts for badges and certificates earned.', 'form-quizz-fqi3'),
                        __('Front-end preferences page via shortcode [fqi3_notification_preferences].', 'form-quizz-fqi3'),
                        __('Optional integration with the Subscription addon (user dashboard).', 'form-quizz-fqi3'),
                        __('SMTP support for reliable email deliverability.', 'form-quizz-fqi3'),
                        __('Integration with APIs: WhatsApp Cloud API and Twilio (SMS).', 'form-quizz-fqi3'),
                        //__('Personalization of messages with dynamic variables (e.g. user name, score earned).', 'form-quizz-fqi3')
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Notification_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => 'form-quizz-fqi3-notification-addon',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-notification-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-notification-addon'),
                    'dependance' => '',
                    'available' => true,
                ],
                [
                    'title' => 'Certificates Add-on',
                    'description' => __('Issue verifiable PDF certificates after quiz completion with templates and public verification.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Certificate templates with variables and backgrounds.', 'form-quizz-fqi3'),
                        __('Automatic issuance on passing criteria with unique serials.', 'form-quizz-fqi3'),
                        __('Public verification page and QR support.', 'form-quizz-fqi3'),
                        __('Admin listings, revoke, and CSV export.', 'form-quizz-fqi3')
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Certificates_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => 'form-quizz-fqi3-certificates-addon',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-certificates-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-certificates-addon'),
                    'dependance' => '',
                    'available' => true,
                ],
                [
                    'title' => 'Question Bank Pro Add-on',
                    'description' => __('Advanced question banks with Pools/Sections, weighted randomization, per-attempt snapshots, and optional AI assistance for tagging and curation.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Banks and sections with tags/difficulty weighting.', 'form-quizz-fqi3'),
                        __('Randomized draws per attempt with snapshot for consistency.', 'form-quizz-fqi3'),
                        __('Admin UI for Pools/Sections, import/export CSV/JSON.', 'form-quizz-fqi3'),
                        __('Integration in quiz builder panel.', 'form-quizz-fqi3'),
                        __('AI-assisted tagging, difficulty suggestions, and pool curation.', 'form-quizz-fqi3')
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_QB_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => 'form-quizz-fqi3-question-bank-addon',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-question-bank-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-question-bank-addon'),
                    'dependance' => '',
                    'available' => true,
                ],
                [
                    'title' => 'WooCommerce Integration',
                    'description' => __('Sell quizzes, certificates or bundles via WooCommerce and map access rules.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Product mapping to quizzes/certificates.', 'form-quizz-fqi3'),
                        __('Score-based access and post-quiz upsells.', 'form-quizz-fqi3'),
                        __('Order webhooks and rule-based access.', 'form-quizz-fqi3'),
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_WooCommerce_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => '',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-woocommerce-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-woocommerce-addon'),
                    'dependance' => '',
                    'available' => false,
                ],
                [
                    'title' => 'Webhooks & Automations',
                    'description' => __('No-code triggers for quiz, subscriptions, and certificates with outbound webhooks, custom headers, JSON payloads, and HMAC signatures.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Trigger flows on quiz/subscription events.', 'form-quizz-fqi3'),
                        __('HTTP actions with custom headers, JSON body, and HMAC signatures.', 'form-quizz-fqi3'),
                        __('Replay, rate limits, and logs.', 'form-quizz-fqi3'),
                        __('Also supports badges and certificate issuance events.', 'form-quizz-fqi3'),
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Webhooks_Automations_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => 'form-quizz-fqi3-webhooks-addon',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-webhooks-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-webhooks-addon'),
                    'dependance' => '',
                    'available' => true,
                ],
                [
                    'title' => 'Lead Scoring & CRM',
                    'description' => __('Score leads based on answers and engagement, then sync to your CRM: HubSpot, Salesforce (OAuth), Pipedrive, or via Webhook.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Scoring rules and segments.', 'form-quizz-fqi3'),
                        __('Connectors: HubSpot, Salesforce (OAuth), Pipedrive, Webhook.', 'form-quizz-fqi3'),
                        __('Quick tools: Test connection and Manual upsert from settings.', 'form-quizz-fqi3'),
                        __('Dashboards and logs.', 'form-quizz-fqi3'),
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_LeadScoring_CRM_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => 'form-quizz-fqi3-leads-crm-addon',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-leads-crm-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-leads-crm-addon'),
                    'dependance' => '',
                    'available' => true,
                ],
                [
                    'title' => 'Multilingual & Localization',
                    'description' => __('Add multilingual capabilities focused on Form Quizz FQI3: define default/available/fallback languages (auto-detected from site locale), display a language switcher, and enable SEO best practices (URL mode + hreflang) without the complexity of a full site-wide framework.', 'form-quizz-fqi3'),
                    'features' => [
                        __('Language settings: default, available and fallback languages (defaults from site locale).', 'form-quizz-fqi3'),
                        __('Language switcher: header/menu/footer or manual via shortcode/PHP, with optional flags.', 'form-quizz-fqi3'),
                        __('SEO helpers: language in URL (subdirectory or query param) and hreflang tags.', 'form-quizz-fqi3'),
                        __('Integrations: auto-create translation placeholders, optional ACF fields sync.', 'form-quizz-fqi3'),
                        __('Developer hooks: filter languages, positions, and URL modes to extend behavior.', 'form-quizz-fqi3'),
                    ], 
                    'image' => FQI3_ASSETS . '/img/addons/FormQuizzFQI3_Multilingual_Addon_square.webp',
                    'more_info' => '',
                    'github_repo' => '',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-multilingual-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-multilingual-addon'),
                    'dependance' => '',
                    'available' => false,
                ]
            ];
        }

        /**
         * Vérifie si un add-on peut être désactivé (s'il n'y a pas d'autres add-ons actifs qui en dépendent)
         *
         * @since 3.0.0
         * @param string $addon_slug Le slug de l'add-on à vérifier
         * @return bool True si l'add-on peut être désactivé, false sinon
         */
        private function can_deactivate_addon(string $addon_slug): bool {
            $dependent_addons = $this->get_active_dependent_addons($addon_slug);
            return empty($dependent_addons);
        }

        /**
         * Check if an add-on has an active dependency
         *
         * @since 3.0.0
         * @param string $addon_slug
         * @return bool
         */
        private function has_active_dependency(string $addon_slug): bool {
            foreach ($this->available_addons as $addon) {
                if (($addon['slug'] ?? '') === $addon_slug) {
                    if (!empty($addon['dependance']['slug'])) {
                        return $this->is_addon_active($addon['dependance']['slug']);
                    }
                    break;
                }
            }
            return false;
        }

        /**
         * Get the list of active add-ons that depend on a given add-on
         *
         * @since 3.0.0
         * @param string $addon_slug The slug of the add-on to check dependencies for
         * @return array List of active add-ons that depend on this add-on
         */
        private function get_active_dependent_addons(string $addon_slug): array {
            $dependent_addons = [];
            
            foreach ($this->available_addons as $addon) {
                // Check if this add-on depends on the given slug and is active
                if (isset($addon['dependance']['slug']) && 
                    $addon['dependance']['slug'] === $addon_slug &&
                    $this->is_addon_active($addon['slug'])) {
                    
                    $dependent_addons[] = [
                        'slug' => $addon['slug'],
                        'title' => $addon['title']
                    ];
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
        private function is_addon_active(string $addon_slug): bool {
            $plugin_file = $addon_slug . '/' . $addon_slug . '.php';
            return is_plugin_active($plugin_file);
        }

        /**
         * Check if all dependencies of an add-on are satisfied
         *
         * @since 3.0.0
         * @param array $addon The add-on to check dependencies for
         * @return bool True if all dependencies are satisfied, false otherwise
         */
        private function are_dependencies_satisfied(array $addon): bool {
            if (empty($addon['dependance'])) {
                return true;
            }
            
            // Check if the dependency is active
            $dependency_slug = $addon['dependance']['slug'];
            return $this->is_addon_active($dependency_slug);
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
            check_ajax_referer('fqi3_addons_search', 'nonce');

            // Ensure addons are initialized
            if (empty($this->available_addons)) {
                $this->init_available_addons();
            }

            $search_term = sanitize_text_field($_POST['search'] ?? '');
            $filter = sanitize_text_field($_POST['filter'] ?? 'all');

            $filtered_addons = array_filter($this->available_addons, function($addon) use ($search_term, $filter) {
                $matches_search = empty($search_term) || 
                    stripos($addon['title'], $search_term) !== false || 
                    stripos($addon['description'], $search_term) !== false;

                $matches_filter = $filter === 'all' || $addon['status'] === $filter;

                return $matches_search && $matches_filter;
            });

            if (empty($filtered_addons)) {
                wp_send_json_error(['message' => __('No add-ons found.', 'form-quizz-fqi3')]);
                return;
            }

            ob_start();
            $this->render_addons_grid($filtered_addons);
            $html = ob_get_clean();

            wp_send_json_success(['html' => $html]);
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
        private function render_addons_grid(array $addons): void {
            foreach ($addons as $addon): 
                $is_installed = $addon['status'] === 'installed';
                $is_active = $is_installed && is_plugin_active($addon['slug'] . '/' . $addon['slug'] . '.php');
                $can_deactivate = $this->can_deactivate_addon($addon['slug']);
                $has_active_dependency = $this->has_active_dependency($addon['slug']);
                $dependencies_satisfied = $this->are_dependencies_satisfied($addon);
                $dependent_addons = $this->get_active_dependent_addons($addon['slug']);
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm" role="article" aria-labelledby="addon-title-<?php echo esc_attr($addon['slug']); ?>">
                    <div class="overlay"></div>
                        <?php if ($addon['status'] === 'installed'): ?>
                            <div class="position-absolute top-0 end-0 m-3 z-3">
                                <span class="badge bg-success"><?php _e('Installed', 'form-quizz-fqi3'); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-img-wrap position-relative">
                            <img src="<?php echo esc_url($addon['image']); ?>" 
                                 data-src="<?php echo esc_url($addon['image']); ?>"
                                 class="card-img-top fqi3-lazy-load" 
                                 alt="<?php echo esc_attr($addon['title']); ?>"
                                 style="height: 200px;"
                                 loading="lazy">
                        </div>

                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0" id="addon-title-<?php echo esc_attr($addon['slug']); ?>"><?php echo esc_html($addon['title']); ?></h5>
                                <!--<span class="badge bg-primary">
                                    <?php 
                                        echo !empty($addon['price']) ? esc_html($addon['price']) . '€' : __('Free', 'form-quizz-fqi3');
                                        if (!empty($addon['period'])) {
                                            echo ' / ' . esc_html($addon['period']);
                                        }
                                    ?>
                                </span>-->
                            </div>
                            <p class="card-text"><?php echo esc_html($addon['description']); ?></p>
                            
                            <!-- Affichage des dépendances -->
                            <?php if (!empty($addon['dependance'])): ?>
                                <div class="alert alert-info alert-sm mb-2">
                                    <small>
                                        <i class="bi bi-info-circle me-1"></i>
                                        <?php 
                                        printf(
                                            /* translators: %s name of the plugin/addon */
                                            __('Requires: %s', 'form-quizz-fqi3'), 
                                            '<strong>' . esc_html($addon['dependance']['name']) . '</strong>'
                                        ); 
                                        ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                    
                            <!-- Affichage des add-ons dépendants (si il y en a) -->
                            <?php if (!empty($dependent_addons)): ?>
                                <div class="alert alert-warning alert-sm mb-2">
                                    <small>
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        <?php _e('Required by:', 'form-quizz-fqi3'); ?>
                                        <?php foreach ($dependent_addons as $i => $dep): ?>
                                            <strong><?php echo esc_html($dep['title']); ?></strong><?php echo $i < count($dependent_addons) - 1 ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    </small>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($addon['features'])): ?>
                                <?php $features_id = 'features-' . sanitize_html_class($addon['slug']); ?>
                                <button 
                                    class="btn btn-link btn-sm p-0 mb-2 d-flex" 
                                    type="button"
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#<?php echo esc_attr($features_id); ?>" 
                                    aria-expanded="false" 
                                    aria-controls="<?php echo esc_attr($features_id); ?>">
                                    <i class="bi bi-list me-1"></i><?php _e('Show features', 'form-quizz-fqi3'); ?>
                                </button>
                                <div id="<?php echo esc_attr($features_id); ?>" class="collapse">
                                    <ul class="list-unstyled mb-3">
                                        <?php foreach ($addon['features'] as $feature): ?>
                                            <li><i class="bi bi-check-circle-fill text-success me-2"></i><?php echo esc_html($feature); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer bg-transparent border-top-0">
                            <div class="d-grid gap-2">
                                <?php if (!$addon['available']) : ?>
                                    <button class="btn btn-secondary disabled" disabled>
                                        <?php _e('Not yet available', 'form-quizz-fqi3'); ?>
                                    </button>
                                <?php else: ?>
                                    <?php if (!$is_installed): ?>
                                        <?php if ($dependencies_satisfied): ?>
                                            <?php if (!empty($addon['github_repo'])): ?>
                                                <button 
                                                    class="btn btn-secondary disabled" 
                                                    disabled>
                                                    <?php _e('Coming Soon', 'form-quizz-fqi3'); ?>
                                                </button>
                                            <?php else: ?>
                                                <button 
                                                    class="btn btn-primary fqi3-addon-action" 
                                                    data-action="install" 
                                                    data-slug="<?php echo esc_attr($addon['slug']); ?>" 
                                                >
                                                    <?php _e('Install', 'form-quizz-fqi3'); ?>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button 
                                                class="btn btn-secondary disabled" 
                                                disabled
                                                title="<?php 
                                                    /* translators: %s is the name of the required add-on */
                                                    printf(__('Requires %s to be installed and activated first', 'form-quizz-fqi3'), $addon['dependance']['name']); ?>">
                                                <?php 
                                                    /* translators: %s is the name of the required add-on */
                                                    printf(__('Requires %s', 'form-quizz-fqi3'), $addon['dependance']['name']); ?>
                                            </button>
                                        <?php endif; ?>
                                    <?php elseif ($is_active && !$can_deactivate): ?>
                                        <button 
                                            class="btn btn-secondary disabled" 
                                            disabled
                                            title="<?php echo $has_active_dependency
                                                ? esc_attr(__('Cannot deactivate because a required dependency is active', 'form-quizz-fqi3'))
                                                : esc_attr(__('Cannot deactivate because other active add-ons depend on it', 'form-quizz-fqi3')); ?>">
                                            <?php _e('Cannot deactivate', 'form-quizz-fqi3'); ?>
                                            <i class="bi bi-info-circle ms-1" 
                                            data-bs-toggle="tooltip" 
                                            data-bs-placement="top" 
                                            title="<?php
                                                if ($has_active_dependency) {
                                                    // Find the dependency name for display
                                                    $dep_name = '';
                                                    if (!empty($addon['dependance']['name'])) {
                                                        $dep_name = $addon['dependance']['name'];
                                                    }
                                                    if ($dep_name) {
                                                        /* translators: %s is the name of the dependency */
                                                        printf(__('Depends on: %s', 'form-quizz-fqi3'), esc_html($dep_name));
                                                    } else {
                                                        echo esc_attr__('Has active dependency', 'form-quizz-fqi3');
                                                    }
                                                } else {
                                                    printf(
                                                        /* translators: %s is the list of dependent add-ons */
                                                        __('Required by: %s', 'form-quizz-fqi3'), 
                                                        implode(', ', array_column($dependent_addons, 'title'))
                                                    );
                                                }
                                            ?>"></i>
                                        </button>
                                    <?php elseif ($is_active): ?>
                                        <button 
                                            class="btn btn-warning fqi3-addon-action" 
                                            data-action="deactivate" 
                                            data-slug="<?php echo esc_attr($addon['slug']); ?>">
                                            <?php _e('Deactivate', 'form-quizz-fqi3'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button 
                                            class="btn btn-success fqi3-addon-action" 
                                            data-action="activate" 
                                            data-slug="<?php echo esc_attr($addon['slug']); ?>">
                                            <?php _e('Activate', 'form-quizz-fqi3'); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <!--<a href="<?php echo esc_url($addon['more_info']); ?>" 
                                    class="btn btn-link text-center" 
                                    target="_blank">
                                    <?php _e('Learn more', 'form-quizz-fqi3'); ?>
                                </a>-->
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach;
        }

        /**
         * Format a row for the email template
         * 
         * @param string $label The label for the row
         * @param string $value The value for the row
         * @return string HTML for the row
         */
        private function format_email_row($label, $value) {
            if (empty($value)) return '';
            return sprintf(
                '<tr><td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; vertical-align: top; width: 150px;">%s:</td><td style="padding: 10px; border-bottom: 1px solid #eee;">%s</td></tr>',
                esc_html($label),
                $value
            );
        }

        /**
         * Get the display name of an add-on by its slug
         * 
         * @param string $addon_slug The slug of the add-on
         * @return string The add-on name or the slug if not found
         */
        private function get_addon_name($addon_slug) {
            if ($addon_slug === 'general') {
                return __('General', 'form-quizz-fqi3');
            }
            
            foreach ($this->available_addons as $addon) {
                if (isset($addon['slug']) && $addon['slug'] === $addon_slug) {
                    return isset($addon['title']) ? $addon['title'] : $addon_slug;
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
        private function save_issue_report($report_data) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'fqi3_issue_reports';
            
            // Create the table if it doesn't exist
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Insert the report
            return $wpdb->insert(
                $table_name,
                [
                    'addon_slug' => $report_data['addon'],
                    'issue_type' => $report_data['issue_type'],
                    'description' => $report_data['description'],
                    'steps' => $report_data['steps'],
                    'url' => $report_data['url'],
                    'user_id' => $report_data['user_id'],
                    'user_email' => $report_data['user_email'],
                    'user_agent' => $report_data['user_agent'],
                    'site_name' => $report_data['site_name'],
                    'site_url' => $report_data['site_url'],
                    'wp_version' => get_bloginfo('version'),
                    'plugin_version' => FQI3_VERSION,
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
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
            if (!check_ajax_referer('fqi3_report_issue', 'security', false)) {
                wp_send_json_error(__('Security check failed.', 'form-quizz-fqi3'), 403);
            }

            // Vérifier les permissions de l'utilisateur
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('You do not have permission to perform this action.', 'form-quizz-fqi3'), 403);
            }

            // Récupérer les données du formulaire
            $addon = isset($_POST['addon']) ? sanitize_text_field($_POST['addon']) : 'general';
            $issue_type = isset($_POST['issue_type']) ? sanitize_text_field($_POST['issue_type']) : 'other';
            $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
            $steps = isset($_POST['steps']) ? sanitize_textarea_field($_POST['steps']) : '';
            $current_url = isset($_POST['current_url']) ? esc_url_raw($_POST['current_url']) : '';
            $user_agent = isset($_POST['user_agent']) ? sanitize_text_field($_POST['user_agent']) : '';

            // Valider les champs obligatoires
            if (empty($description)) {
                wp_send_json_error(__('Please provide a description of the issue.', 'form-quizz-fqi3'), 400);
            }

            // Récupérer les informations sur l'utilisateur et le site
            $current_user = wp_get_current_user();
            $site_name = get_bloginfo('name');
            $site_url = get_bloginfo('url');
            
            // Préparer les données du rapport
            $report_data = [
                'addon' => $addon,
                'issue_type' => $issue_type,
                'description' => $description,
                'steps' => $steps,
                'url' => $current_url,
                'user_agent' => $user_agent,
                'user_id' => $current_user->ID,
                'user_email' => $current_user->user_email,
                'user_display_name' => $current_user->display_name,
                'site_name' => $site_name,
                'site_url' => $site_url,
                'timestamp' => current_time('mysql'),
                'wp_version' => get_bloginfo('version'),
                'plugin_version' => FQI3_VERSION
            ];

            // Envoyer un email à l'administrateur
            $to = get_bloginfo('admin_email');
            $subject = sprintf(
                /* translators: %1$s: Site name, %2$s: Add-on name, %3$s: Issue type */
                __('[%1$s] New Issue Report - %2$s - %3$s', 'form-quizz-fqi3'),
                $site_name,
                $addon === 'general' ? __('General', 'form-quizz-fqi3') : $this->get_addon_name($addon),
                ucfirst($issue_type)
            );

            // Construire le message au format HTML
            $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto;">';
            $message .= '<div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; border-left: 4px solid #dc3545;">';
            $message .= '<h2 style="color: #dc3545; margin-top: 0;">' . __('New Issue Report', 'form-quizz-fqi3') . '</h2>';
            $message .= '<div style="background-color: white; border-radius: 5px; padding: 15px; margin-bottom: 20px;">';
            $message .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';
            
            // Ajouter les informations de base
            $message .= $this->format_email_row(__('Type of issue', 'form-quizz-fqi3'), ucfirst($issue_type));
            $message .= $this->format_email_row(__('Extension concerned', 'form-quizz-fqi3'), $addon === 'general' ? __('General', 'form-quizz-fqi3') : $this->get_addon_name($addon));
            $message .= $this->format_email_row(__('Description', 'form-quizz-fqi3'), nl2br(esc_html($description)));
            
            if (!empty($steps)) {
                $message .= $this->format_email_row(__('Steps to reproduce', 'form-quizz-fqi3'), nl2br(esc_html($steps)));
            }
            
            // Ajouter les informations techniques
            $message .= $this->format_email_row('', '<strong style="color: #6c757d;">' . __('Technical Informations', 'form-quizz-fqi3') . '</strong>');
            $message .= $this->format_email_row(__('URL', 'form-quizz-fqi3'), '<a href="' . esc_url($current_url) . '" style="color: #0073aa; text-decoration: none;">' . esc_html($current_url) . '</a>');
            $message .= $this->format_email_row(__('User', 'form-quizz-fqi3'), sprintf('%s (%s)', $current_user->display_name, $current_user->user_email));
            $message .= $this->format_email_row(__('Site', 'form-quizz-fqi3'), $site_name . ' (<a href="' . esc_url($site_url) . '" style="color: #0073aa; text-decoration: none;">' . esc_html($site_url) . '</a>)');
            $message .= $this->format_email_row(__('Browser', 'form-quizz-fqi3'), $user_agent);
            $message .= $this->format_email_row(__('WordPress Version', 'form-quizz-fqi3'), get_bloginfo('version'));
            $message .= $this->format_email_row(__('Plugin Version', 'form-quizz-fqi3'), FQI3_VERSION);
            
            $message .= '</table>';
            $message .= '</div>';
            $message .= '<p style="font-size: 0.9em; color: #6c757d; margin: 0; padding: 10px 0 0; border-top: 1px solid #eee;">';
            $message .= __('This is an automated message. Please do not reply directly.', 'form-quizz-fqi3');
            $message .= '</p>';
            $message .= '</div>';
            $message .= '</body></html>';

            // En-têtes pour l'email HTML
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $site_name . ' <' . $to . '>',
                'Reply-To: ' . $current_user->display_name . ' <' . $current_user->user_email . '>'
            ];

            // Envoyer l'email
            $mail_sent = wp_mail($to, $subject, $message, $headers);

            // Enregistrer le rapport dans la base de données
            $report_saved = $this->save_issue_report($report_data);

            // Retourner la réponse
            if ($mail_sent || $report_saved) {
                wp_send_json_success([
                    'message' => __('Thank you for your report. Our team will investigate the issue as soon as possible.', 'form-quizz-fqi3')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Your report has been recorded, but an error occurred while sending the email. Our team has been notified.', 'form-quizz-fqi3')
                ]);
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
            if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'fqi3_suggest_addon')) {
                wp_send_json_error(__('Security check failed.', 'form-quizz-fqi3'), 403);
                return;
            }

            // Check required fields
            $required_fields = [
                'addon_name' => __('Add-on name', 'form-quizz-fqi3'),
                'addon_description' => __('Description', 'form-quizz-fqi3'),
                'addon_email' => __('Email', 'form-quizz-fqi3'),
            ];

            $errors = [];
            $data = [];

            foreach ($required_fields as $field => $label) {
                if (empty($_POST[$field])) {
                    $errors[] = sprintf(
                        /* translators: %s: Field label */
                        __('%s is a required field.', 'form-quizz-fqi3'), $label);
                } else {
                    $data[$field] = sanitize_text_field($_POST[$field]);
                }
            }

            // Validate email
            if (!empty($data['addon_email']) && !is_email($data['addon_email'])) {
                $errors[] = __('Please enter a valid email address.', 'form-quizz-fqi3');
            }

            // Check terms agreement
            if (empty($_POST['terms_agreement'])) {
                $errors[] = __('You must agree to the terms to submit a suggestion.', 'form-quizz-fqi3');
            }

            // Process website if provided
            $data['addon_website'] = !empty($_POST['addon_website']) ? esc_url_raw($_POST['addon_website']) : '';

            if (!empty($errors)) {
                wp_send_json_error(implode('<br>', $errors), 400);
                return;
            }

            // Get current user info
            $current_user = wp_get_current_user();
            $user_info = [
                'user_id' => $current_user->ID,
                'username' => $current_user->user_login,
                'display_name' => $current_user->display_name,
                'email' => $current_user->user_email,
            ];

            // Prepare the suggestion data
            $suggestion = [
                'addon_name' => $data['addon_name'],
                'addon_description' => $data['addon_description'],
                'addon_website' => $data['addon_website'],
                'user_email' => $data['addon_email'],
                'user_info' => $user_info,
                'site_url' => get_site_url(),
                'timestamp' => current_time('mysql'),
            ];

            // Save the suggestion (you can modify this to save to database or send via email)
            $saved = $this->save_addon_suggestion($suggestion);

            if (is_wp_error($saved)) {
                wp_send_json_error($saved->get_error_message(), 500);
                return;
            }

            // Send email notification (optional)
            $email_sent = $this->send_addon_suggestion_email($suggestion);

            if (is_wp_error($email_sent)) {
                // Log the error but don't fail the request
                error_log('Failed to send add-on suggestion email: ' . $email_sent->get_error_message());
            }

            wp_send_json_success([
                'message' => __('Thank you for your suggestion! Our team will review it shortly.', 'form-quizz-fqi3'),
                'suggestion' => $suggestion
            ]);
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
                            <h5 class="modal-title" id="reportIssueModalLabel"><?php _e('Report an Issue', 'form-quizz-fqi3'); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php _e('Close', 'form-quizz-fqi3'); ?>"></button>
                        </div>
                        <div class="modal-body">
                            <form id="reportIssueForm">
                                <div class="mb-3">
                                    <label for="issueAddon" class="form-label"><?php _e('Related Add-on', 'form-quizz-fqi3'); ?></label>
                                    <select class="form-select" id="issueAddon" name="addon" required>
                                        <option value="general"><?php _e('General Issue', 'form-quizz-fqi3'); ?></option>
                                        <?php foreach ($this->available_addons as $addon): ?>
                                            <option value="<?php echo esc_attr($addon['slug']); ?>">
                                                <?php echo esc_html($addon['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="issueType" class="form-label"><?php _e('Issue Type', 'form-quizz-fqi3'); ?></label>
                                    <select class="form-select" id="issueType" name="issue_type" required>
                                        <option value="bug"><?php _e('Bug', 'form-quizz-fqi3'); ?></option>
                                        <option value="suggestion"><?php _e('Suggestion', 'form-quizz-fqi3'); ?></option>
                                        <option value="other"><?php _e('Other', 'form-quizz-fqi3'); ?></option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="issueDescription" class="form-label"><?php _e('Description', 'form-quizz-fqi3'); ?> *</label>
                                    <textarea class="form-control" id="issueDescription" name="description" rows="4" required 
                                        placeholder="<?php esc_attr_e('Please describe the issue in detail...', 'form-quizz-fqi3'); ?>"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="issueSteps" class="form-label"><?php _e('Steps to Reproduce', 'form-quizz-fqi3'); ?></label>
                                    <textarea class="form-control" id="issueSteps" name="steps" rows="3" 
                                        placeholder="<?php esc_attr_e('1. Go to...\n2. Click on...\n3. Error occurs when...', 'form-quizz-fqi3'); ?>"></textarea>
                                </div>
                                <input type="hidden" name="current_url" value="<?php echo esc_url(admin_url('admin.php?page=fqi3-add-ons')); ?>">
                                <?php wp_nonce_field('fqi3_report_issue', 'report_issue_nonce'); ?>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php _e('Cancel', 'form-quizz-fqi3'); ?></button>
                            <button type="button" class="btn btn-danger" id="submitIssueReport">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                <?php _e('Submit Report', 'form-quizz-fqi3'); ?>
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
        private function save_addon_suggestion($suggestion) {
            // Get existing suggestions
            $suggestions = get_option('fqi3_addon_suggestions', []);
            
            // Add new suggestion
            $suggestions[] = [
                'id' => uniqid('sug_'),
                'data' => $suggestion,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ];
            
            // Save back to database
            $updated = update_option('fqi3_addon_suggestions', $suggestions, false);
            
            if (!$updated) {
                return new WP_Error('save_failed', __('Failed to save your suggestion. Please try again.', 'form-quizz-fqi3'));
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
        private function send_addon_suggestion_email($suggestion) {
            $to = get_bloginfo('admin_email');
            $subject = sprintf(
                /* translators: %1$s: Site name, %2$s: Add-on name */
                __('[%1$s] New Add-on Suggestion: %2$s', 'form-quizz-fqi3'),
                get_bloginfo('name'),
                $suggestion['addon_name']
            );
            
            $message = sprintf(
                /* translators: %s: Site name */
                __('A new add-on has been suggested on %s:', 'form-quizz-fqi3'),
                get_bloginfo('name')
            ) . "\n\n";
            
            $message .= sprintf(
                /* translators: %s: Add-on name */
                __('Add-on Name: %s', 'form-quizz-fqi3'), $suggestion['addon_name']) . "\n";
            $message .= sprintf(
                /* translators: %s: Add-on description */
                __('Description: %s', 'form-quizz-fqi3'), $suggestion['addon_description']) . "\n";
            
            if (!empty($suggestion['addon_website'])) {
                $message .= sprintf(
                    /* translators: %s: Add-on website */
                    __('Website: %s', 'form-quizz-fqi3'), $suggestion['addon_website']) . "\n";
            }
            
            $message .= "\n" . __('Submitted by:', 'form-quizz-fqi3') . "\n";
            $message .= sprintf(
                /* translators: %s: User name */
                __('Name: %s', 'form-quizz-fqi3'), $suggestion['user_info']['display_name']) . "\n";
            $message .= sprintf(
                /* translators: %s: User email */
                __('Email: %s', 'form-quizz-fqi3'), $suggestion['user_email']) . "\n";
            $message .= sprintf(
                /* translators: %s: Site URL */
                __('Site: %s', 'form-quizz-fqi3'), $suggestion['site_url']) . "\n";
            
            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                sprintf('From: %s <%s>', $suggestion['user_info']['display_name'], $suggestion['user_email']),
                sprintf('Reply-To: %s <%s>', $suggestion['user_info']['display_name'], $suggestion['user_email']),
            ];
            
            return wp_mail($to, $subject, $message, $headers);
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
            if (empty($this->available_addons)) {
                $this->init_available_addons();
            }
            if ($this->updater_class_available && empty($this->updaters)) {
                $this->init_updaters();
            }

            ?>
            <div class="wrap">
                <div class="container-fluid px-4 py-5">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-6">
                            <h2 class="h3 mb-3 mb-md-0"><?php _e('Available Add-Ons', 'form-quizz-fqi3'); ?></h2>
                            <p><?php _e('Add-Ons allow you to extend the functionality of Form Quizz FQI3.', 'form-quizz-fqi3'); ?></p>
                            <p class="text-muted">
                                <?php 
                                $total_count = count($this->available_addons);
                                $available_count = count(array_filter($this->available_addons, function($addon) {
                                    return !empty($addon['available']);
                                }));
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
                                        <?php echo !$this->updater_class_available ? 'disabled title="' . esc_attr__('Plugin updater not available', 'form-quizz-fqi3') . '"' : ''; ?>>
                                        <i class="bi bi-arrow-clockwise"></i>
                                    <?php _e('Refresh Cache', 'form-quizz-fqi3'); ?>
                                </button>
                                <button type="button" 
                                        class="btn btn-primary btn-sm" 
                                        id="submitAddonSuggestion"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#suggestAddonModal">
                                        <i class="bi bi-plus"></i>
                                    <?php _e('Suggest an Add-on', 'form-quizz-fqi3'); ?>
                                </button>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger report-issue">
                                        <i class="bi bi-flag"></i>
                                    <?php echo esc_attr__('Report Issue', 'form-quizz-fqi3'); ?>
                                </button>
                            </div>
                            <div class="input-group">
                                <input type="text" 
                                    class="form-control" 
                                    id="addon-search"
                                    placeholder="<?php _e('Search an add-on...', 'form-quizz-fqi3'); ?>">
                                <button class="btn btn-outline-secondary dropdown-toggle" 
                                        type="button" 
                                        data-bs-toggle="dropdown">
                                    <?php _e('Filter', 'form-quizz-fqi3'); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item filter-option" data-filter="all" href="#"><?php _e('All', 'form-quizz-fqi3'); ?></a></li>
                                    <li><a class="dropdown-item filter-option" data-filter="installed" href="#"><?php _e('Installed', 'form-quizz-fqi3'); ?></a></li>
                                    <li><a class="dropdown-item filter-option" data-filter="available" href="#"><?php _e('Available', 'form-quizz-fqi3'); ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- Display available updates -->
                    <?php if ($this->updater_class_available) $this->render_update_notices(); ?>

                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="addons-grid">
                        <?php $this->render_addons_grid($this->available_addons); ?>
                    </div>
                </div>
            </div>
            
            <!-- Suggest Add-on Modal -->
            <div class="modal fade" id="suggestAddonModal" tabindex="-1" aria-labelledby="suggestAddonModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="suggestAddonModalLabel">
                                <?php _e('Suggest a New Add-on', 'form-quizz-fqi3'); ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e('Close', 'form-quizz-fqi3'); ?>"></button>
                        </div>
                        <div class="modal-body">
                            <form id="suggestAddonForm" method="post">
                                <?php wp_nonce_field('fqi3_suggest_addon_nonce', 'fqi3_addon_nonce'); ?>
                                
                                <div class="mb-3">
                                    <label for="addonName" class="form-label">
                                        <?php _e('Add-on Name', 'form-quizz-fqi3'); ?> *
                                    </label>
                                    <input type="text" class="form-control" id="addonName" name="addon_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="addonDescription" class="form-label">
                                        <?php _e('Description', 'form-quizz-fqi3'); ?> *
                                    </label>
                                    <textarea class="form-control" id="addonDescription" name="addon_description" rows="3" required></textarea>
                                    <div class="form-text">
                                        <?php _e('Please describe what this add-on would do and how it would benefit users.', 'form-quizz-fqi3'); ?>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="addonWebsite" class="form-label">
                                                <?php _e('Website (optional)', 'form-quizz-fqi3'); ?>
                                            </label>
                                            <input type="url" class="form-control" id="addonWebsite" name="addon_website">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="addonEmail" class="form-label">
                                                <?php _e('Your Email', 'form-quizz-fqi3'); ?> *
                                            </label>
                                            <input type="email" class="form-control" id="addonEmail" name="addon_email" required>
                                            <div class="form-text">
                                                <?php _e('In case we need to contact you for more information.', 'form-quizz-fqi3'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="termsAgreement" name="terms_agreement" required>
                                        <label class="form-check-label" for="termsAgreement">
                                            <?php _e('I understand that this is only a suggestion and there is no guarantee it will be implemented.', 'form-quizz-fqi3'); ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="dashicons dashicons-info"></i>
                                    <?php _e('Your suggestion will be sent to our development team for review. We appreciate your input!', 'form-quizz-fqi3'); ?>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <?php _e('Cancel', 'form-quizz-fqi3'); ?>
                            </button>
                            <button type="submit" form="suggestAddonForm" class="btn btn-primary">
                                <span class="dashicons dashicons-email"></span>
                                <?php _e('Submit Suggestion', 'form-quizz-fqi3'); ?>
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
            check_ajax_referer('fqi3_addon_action', 'nonce');

            if (!current_user_can('install_plugins')) {
                wp_send_json_error(['message' => __('You are not allowed to perform this action.', 'form-quizz-fqi3')]);
            }

            if (empty($this->available_addons)) {
                $this->init_available_addons();
            }

            $action = sanitize_text_field($_POST['action_type'] ?? '');
            $slug   = sanitize_text_field($_POST['slug'] ?? '');

            if (empty($action) || empty($slug)) {
                wp_send_json_error(['message' => __('Missing parameters.', 'form-quizz-fqi3')]);
            }

            // Find the addon only once
            $addon = null;
            foreach ($this->available_addons as $a) {
                if ($a['slug'] === $slug) {
                    $addon = $a;
                    break;
                }
            }

            if (!$addon) {
                wp_send_json_error(['message' => __('Addon not found.', 'form-quizz-fqi3')]);
            }

            if ($action === 'install') {                
                $result = $this->install_addon($addon);
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                }
                
                wp_send_json_success(['message' => $result['message']]);
            }                     

            if ($action === 'activate') {
                $result = activate_plugin($slug . '/' . $slug . '.php');
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                }
                wp_send_json_success(['message' => __('Activated.', 'form-quizz-fqi3')]);
            }

            if ($action === 'deactivate') {
                // Enforce dependency constraints server-side too
                if (!$this->can_deactivate_addon($slug)) {
                    // Prefer specific reason
                    $has_active_dependency = $this->has_active_dependency($slug);
                    if ($has_active_dependency) {
                        wp_send_json_error(['message' => __('Cannot deactivate because a required dependency is active.', 'form-quizz-fqi3')]);
                    }
                    $dependent_addons = $this->get_active_dependent_addons($slug);
                    if (!empty($dependent_addons)) {
                        wp_send_json_error([
                            'message' => sprintf(
                                /* translators: %s is the list of dependent add-ons */
                                __('Cannot deactivate because it is required by: %s', 'form-quizz-fqi3'),
                                implode(', ', array_column($dependent_addons, 'title'))
                            )
                        ]);
                    }
                    wp_send_json_error(['message' => __('Cannot deactivate due to dependency constraints.', 'form-quizz-fqi3')]);
                }
                deactivate_plugins($slug . '/' . $slug . '.php');
                wp_send_json_success(['message' => __('Deactivated.', 'form-quizz-fqi3')]);
            }

            wp_send_json_error(['message' => __('Invalid action.', 'form-quizz-fqi3')]);
        }

        /**
         * Install an addon from GitHub release
         *
         * @param array $addon Addon data
         * @return array|WP_Error Success message or WP_Error on failure
         */
        private function install_addon($addon) {
            // Include the necessary files
            if (!function_exists('request_filesystem_credentials')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if (!class_exists('Plugin_Upgrader')) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            }
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $github_username = 'Webpixelia';
            $repo_parts = explode('/', $addon['github_repo']);
            $repo_name = end($repo_parts);
            
            if (!defined('GITHUB_PERSONAL_TOKEN') || empty(GITHUB_PERSONAL_TOKEN)) {
                return new WP_Error('github_token_missing', __('GitHub token is not configured.', 'form-quizz-fqi3'));
            }

            // Get the latest release
            $release_url = "https://api.github.com/repos/{$github_username}/{$repo_name}/releases/latest";
            
            $release_response = wp_remote_get($release_url, [
                'headers' => [
                    'Authorization' => 'token ' . GITHUB_PERSONAL_TOKEN,
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress-Form-Quizz-Plugin' // GitHub require User-Agent
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($release_response)) {
                return new WP_Error('github_api_error', 
                    sprintf(
                        /* translators: %s error message */
                        __('GitHub API error: %s', 'form-quizz-fqi3'), $release_response->get_error_message())
                );
            }

            $response_code = wp_remote_retrieve_response_code($release_response);
            if ($response_code !== 200) {
                return new WP_Error('github_api_error', 
                    sprintf(
                        /* translators: %s status response GitHub API */
                        __('GitHub API responded with status %d.', 'form-quizz-fqi3'), $response_code)
                );
            }

            $release_data = json_decode(wp_remote_retrieve_body($release_response), true);
            
            if (!isset($release_data['zipball_url'])) {
                return new WP_Error('no_release', __('No release found for this addon.', 'form-quizz-fqi3'));
            }

            $plugin_zip_url = $release_data['zipball_url'];

            // Télécharger le fichier
            $tmp_file = wp_tempnam($plugin_zip_url);
            $response = wp_remote_get($plugin_zip_url, [
                'headers' => [
                    'Authorization' => 'token ' . GITHUB_PERSONAL_TOKEN,
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress-Form-Quizz-Plugin'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                @unlink($tmp_file);
                return $response;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                @unlink($tmp_file);
                return new WP_Error('download_error', 
                    sprintf(
                        /* translators: %s status response GitHub API */
                        __('Download failed with status %d.', 'form-quizz-fqi3'), $response_code)
                );
            }

            $body = wp_remote_retrieve_body($response);
            if (strlen($body) < 100) {
                @unlink($tmp_file);
                return new WP_Error('empty_file', __('Downloaded file is too small or empty.', 'form-quizz-fqi3'));
            }

            file_put_contents($tmp_file, $body);

            // Install plugin
            $skin = new \WP_Ajax_Upgrader_Skin();
            $upgrader = new \Plugin_Upgrader($skin);
            $result = $upgrader->install($tmp_file);
            
            // Clean temp file
            @unlink($tmp_file);

            if (is_wp_error($result)) {
                return $result;
            }

            // Find and rename the plugin folder
            $plugin_dir = $this->find_and_rename_plugin_dir($addon['slug'], $repo_name);
            if (is_wp_error($plugin_dir)) {
                return $plugin_dir;
            }

            // Activate the plugin
            return $this->activate_plugin_in_directory($plugin_dir, $addon['slug']);
        }

        /**
         * Find and rename plugin directory if needed
         * Adapted for GitHub release format
         *
         * @param string $expected_slug Expected plugin slug
         * @param string $repo_name Repository name
         * @return string|WP_Error Plugin directory path or WP_Error
         */
        private function find_and_rename_plugin_dir($expected_slug, $repo_name) {
            $possible_names = [
                $expected_slug,
                $repo_name,
                strtolower($repo_name),
                $github_username . '-' . $repo_name,
                $github_username . '-' . $repo_name . '-*'
            ];

            // Search by exact slug first
            $expected_dir = WP_PLUGIN_DIR . '/' . $expected_slug;
            if (is_dir($expected_dir)) {
                return $expected_dir;
            }

            // Search existing folders
            $all_dirs = glob(WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR);
            foreach ($all_dirs as $dir) {
                $dir_name = basename($dir);
                
                // Check if the folder matches the repo
                if (strpos($dir_name, $repo_name) !== false || 
                    strpos($dir_name, $expected_slug) !== false) {
                    
                    // Rename to the expected slug
                    $target_dir = WP_PLUGIN_DIR . '/' . $expected_slug;
                    if (rename($dir, $target_dir)) {
                        return $target_dir;
                    }
                    return $dir;
                }
            }

            return new WP_Error('plugin_dir_not_found', 
                __('Plugin installed but folder not found.', 'form-quizz-fqi3')
            );
        }

        /**
         * Activate plugin in directory
         *
         * @param string $plugin_dir Plugin directory path
         * @param string $slug Plugin slug
         * @return array|WP_Error Success message or WP_Error
         */
        private function activate_plugin_in_directory($plugin_dir, $slug) {
            // Find the main file
            $php_files = glob($plugin_dir . '/*.php');
            
            foreach ($php_files as $file) {
                $plugin_data = get_plugin_data($file, false, false);
                if (!empty($plugin_data['Name'])) {
                    $relative_path = str_replace(WP_PLUGIN_DIR . '/', '', $file);
                    $result = activate_plugin($relative_path);
                    
                    if (is_wp_error($result)) {
                        return [
                            'message' => __('Plugin installed but activation failed.', 'form-quizz-fqi3')
                        ];
                    }
                    return [
                        'message' => __('Plugin installed and activated successfully!', 'form-quizz-fqi3')
                    ];
                }
            }

            return [
                'message' => __('Plugin installed successfully but could not auto-activate.', 'form-quizz-fqi3')
            ];
        }

        /**
         * Load the Plugin_Updater class
         *
         * @since 3.2.0
         * @return void
         */
        private function load_updater_class(): void {
            $updater_file = FQI3_INCLUDES . '/admin/class-plugin-updater.php';
            
            if (file_exists($updater_file)) {
                require_once $updater_file;
                $this->updater_class_available = class_exists('\Form_Quizz_FQI3\Plugin_Updater');
            } else {
                error_log('FQI3: Plugin_Updater class file not found at: ' . $updater_file);
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
            if (!$this->updater_class_available) {
                return;
            }
    
            if (empty($this->available_addons)) {
                $this->init_available_addons();
            }
    
            foreach ($this->available_addons as $addon) {
                $plugin_file = $addon['slug'] . '/' . $addon['slug'] . '.php';
                
                // Only initialize updater if plugin is installed
                if ($this->get_addon_status($addon['slug']) === 'installed') {
                    $github_repo = $this->extract_github_repo($addon['github_repo'] ?? '');
                    
                    if (!empty($github_repo)) {
                        try {
                            $this->updaters[$addon['slug']] = new \Form_Quizz_FQI3\Plugin_Updater(
                                $plugin_file,
                                $github_repo,
                                'Webpixelia'
                            );
                        } catch (Exception $e) {
                            error_log('FQI3: Failed to initialize updater for ' . $addon['slug'] . ': ' . $e->getMessage());
                        }
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
        private function extract_github_repo(string $github_url): string {
            if (empty($github_url)) {
                return '';
            }
            
            // Extract from URLs like https://github.com/Webpixelia/repo-name
            if (preg_match('#github\.com/[^/]+/([^/]+)#', $github_url, $matches)) {
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
            check_ajax_referer('fqi3_refresh_cache', 'nonce');
        
            if (!current_user_can('update_plugins')) {
                wp_send_json_error([
                    'message' => __('You are not allowed to perform this action.', 'form-quizz-fqi3')
                ]);
            }
        
            if (!$this->updater_class_available) {
                wp_send_json_error([
                    'message' => __('Plugin updater is not available.', 'form-quizz-fqi3')
                ]);
            }
        
            $refreshed_count = 0;
            $errors = [];
        
            // Clear update caches for all installed add-ons
            foreach ($this->updaters as $slug => $updater) {
                try {
                    if (method_exists($updater, 'clear_cache')) {
                        $updater->clear_cache();
                        $refreshed_count++;
                    }
                } catch (Exception $e) {
                    $errors[] = sprintf(
                        /* translators: 1 = add-on slug, 2 = error message */
                        __('Error refreshing %1$s: %2$s', 'form-quizz-fqi3'),
                        $slug,
                        esc_html($e->getMessage())
                    );
                }
            }
        
            // Clear WordPress update transients
            delete_site_transient('update_plugins');
            wp_clean_plugins_cache();
        
            if (empty($errors)) {
                wp_send_json_success([
                    'message' => sprintf(
                        /* translators: %d = number of refreshed add-ons */
                        _n(
                            'Successfully refreshed cache for %d add-on.',
                            'Successfully refreshed cache for %d add-ons.',
                            $refreshed_count,
                            'form-quizz-fqi3'
                        ),
                        $refreshed_count
                    )
                ]);
            } else {
                wp_send_json_error([
                    'message' => implode('<br>', $errors)
                ]);
            }
        }
        

        /**
         * Get update information for an addon
         *
         * @since 3.2.0
         * @param string $addon_slug
         * @return array|false
         */
        public function get_addon_update_info(string $addon_slug) {
            if (!$this->updater_class_available || !isset($this->updaters[$addon_slug])) {
                return false;
            }
    
            $updater = $this->updaters[$addon_slug];
            
            try {
                if (!$updater->is_update_available()) {
                    return false;
                }
    
                $latest = $updater->force_check();
                if (!$latest) {
                    return false;
                }
    
                return [
                    'current_version' => $updater->get_current_version(),
                    'latest_version' => $latest['version'] ?? '',
                    'update_available' => !empty($latest['version']),
                    'changelog' => $latest['changelog'] ?? ''
                ];
            } catch (Exception $e) {
                error_log('FQI3: Error getting update info for ' . $addon_slug . ': ' . $e->getMessage());
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
            if (!$this->updater_class_available) {
                return;
            }
    
            $updates_available = [];
            
            foreach ($this->available_addons as $addon) {
                if ($this->get_addon_status($addon['slug']) === 'installed') {
                    $update_info = $this->get_addon_update_info($addon['slug']);
                    if ($update_info) {
                        $updates_available[] = array_merge($addon, $update_info);
                    }
                }
            }
          
            if (!empty($updates_available)) {
                ?>
                <div class="alert alert-info mb-4">
                    <h5><i class="bi bi-info-circle"></i> <?php _e('Updates Available', 'form-quizz-fqi3'); ?></h5>
                    <p><?php _e('The following add-ons have updates available:', 'form-quizz-fqi3'); ?></p>
                    <ul class="mb-0">
                        <?php foreach ($updates_available as $addon): ?>
                            <li>
                                <strong><?php echo esc_html($addon['title']); ?></strong>
                                - <?php printf(
                                    /* translators: 1 = current version, 2 = latest version */
                                    __('Version %1$s → %2$s', 'form-quizz-fqi3'),
                                    $addon['current_version'],
                                    $addon['latest_version']
                                ); ?>
                                <a href="<?php echo admin_url('plugins.php'); ?>" class="btn btn-sm btn-outline-primary ms-2">
                                    <?php _e('Update Now', 'form-quizz-fqi3'); ?>
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