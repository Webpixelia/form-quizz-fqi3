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
 * @version    3.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( !class_exists( 'FQI3_Addons_Page' ) ) :
    class FQI3_Addons_Page {
        private array $available_addons;

        public function __construct() {
            // Initialize properties to avoid "must not be accessed before initialization" error
            $this->available_addons = [];
            
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts'], 5);
            add_action('init', [$this, 'init_available_addons']);
            add_action('wp_ajax_search_addons', [$this, 'search_addons']);
            add_action('wp_ajax_filter_addons', [$this, 'filter_addons']);
            add_action('wp_ajax_fqi3_addon_action', [$this, 'handle_addon_action']);
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
                    'purchase_link' => '',
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
                    'purchase_link' => '',
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
                    'purchase_link' => '',
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
                    'purchase_link' => '',
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
                    'purchase_link' => '',
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
                    'purchase_link' => '',
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
                    'purchase_link' => '',
                    'price' => '',
                    'period' => '',
                    'slug' => 'form-quizz-fqi3-question-bank-addon',
                    'status' => $this->get_addon_status('form-quizz-fqi3-question-bank-addon'),
                    'dependance' => '',
                    'available' => true,
                ],
                // Planned add-ons (placeholders)
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
                    'purchase_link' => '',
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
                    'purchase_link' => '',
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
                    'purchase_link' => '',
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
                    'purchase_link' => '',
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
                    <div class="card h-100 shadow-sm">
                    <div class="overlay"></div>
                        <?php if ($addon['status'] === 'installed'): ?>
                            <div class="position-absolute top-0 end-0 m-3 z-3">
                                <span class="badge bg-success"><?php _e('Installed', 'form-quizz-fqi3'); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-img-wrap position-relative">
                            <img src="<?php echo esc_url($addon['image']); ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo esc_attr($addon['title']); ?>"
                                 style="height: 200px;">
                        </div>

                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?php echo esc_html($addon['title']); ?></h5>
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
                                            <?php if (empty($addon['purchase_link'])): ?>
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
                                                    data-zip="<?php echo esc_url($addon['purchase_link']); ?>">
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
            
            ?>
            <div class="wrap">
                <div class="container-fluid px-4 py-5">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-6">
                            <h2 class="h3 mb-3 mb-md-0"><?php _e('Available Add-Ons', 'form-quizz-fqi3'); ?></h2>
                            <p><?php _e('Add-Ons allow you to extend the functionality of Form Quizz FQI3.', 'form-quizz-fqi3'); ?></p>
                            <p class="text-muted">
                                <?php 
                                $count = count($this->available_addons);
                                printf(
                                    /* translators: 1 = singular, %d = number of add-ons */
                                    _n(
                                        'Actually %d add-on is available.',
                                        'Actually %d add-ons are available.',
                                        $count,
                                        'form-quizz-fqi3'
                                    ),
                                    $count
                                ); 
                            ?>
                        </p>
                        </div>
                        <div class="col-md-6">
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
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="addons-grid">
                        <?php $this->render_addons_grid($this->available_addons); ?>
                    </div>
                </div>
            </div>
            <?php
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

            $action = sanitize_text_field($_POST['action_type'] ?? '');
            $slug   = sanitize_text_field($_POST['slug'] ?? '');
            $zip    = esc_url_raw($_POST['zip'] ?? '');

            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            include_once ABSPATH . 'wp-admin/includes/file.php';

            if ($action === 'install' && $zip) {
                $upgrader = new \Plugin_Upgrader();
                $result = $upgrader->install($zip);
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                }
                activate_plugin($slug . '/' . $slug . '.php');
                wp_send_json_success(['message' => __('Installed and activated.', 'form-quizz-fqi3')]);
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
    }
endif;