<?php
/**
 * Plugin Name: Form Quizz FQI3
 * Description: A plugin to create and manage free quizzes with premium features.
 * Version: 3.3.0
 * Author: Jonathan Webpixelia
 * Author URI: https://webpixelia.com
 * Update URI: https://github.com/Webpixelia/form-quizz-fqi3
 * Requires PHP: 8.0
 * Requires at least: 5.0
 * Tested up to: 6.7.1
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: form-quizz-fqi3
 * Domain Path: /languages
 *
 * @package Form_Quizz_FQI3
*/

declare(strict_types=1);

namespace Form_Quizz_FQI3;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Composer autoload
require_once __DIR__ . '/vendor/autoload.php';

// Desactivate update check on wordpress.org
add_filter('http_request_args', function($args, $url) {
    // Desactivate update check for this plugin
    if (str_contains($url, '//api.wordpress.org/plugins/update-check/1.1/') && isset($args['body']['plugins'])) {
        $plugins = json_decode($args['body']['plugins'], true);
        if (isset($plugins['plugins']['form-quizz-fqi3/form-quizz-fqi3.php'])) {
            unset($plugins['plugins']['form-quizz-fqi3/form-quizz-fqi3.php']);
            $args['body']['plugins'] = wp_json_encode($plugins);
        }
    }
    return $args;
}, 5, 2);

// Desactivate infos get on wordpress.org
add_filter('plugins_api', function($res, $action, $args) {
    if (isset($args->slug) && $args->slug === 'form-quizz-fqi3') {
        return new WP_Error(
            'plugins_api_disabled',
            __('This plugin is not listed in the WordPress.org directory.', 'form-quizz-fqi3')
        );
    }
    return $res;
}, 10, 3);

/**
 * Main plugin class
 */
final class FormQuizzFQI3 {
    /**
     * Plugin instance
     *
     * @var FormQuizzFQI3|null
     */
    private static ?FormQuizzFQI3 $instance = null;

    /**
     * Plugin version
     *
     * @var string
     */
    private const FQI3_VERSION = '3.3.0';

    /**
     * Required PHP version
     *
     * @var string
     */
    private const REQUIRED_PHP_VERSION = '8.0.0';

    /**
     * Plugin constants
     *
     * @var array
     */
    private const CONSTANTS = [
        'FQI3_NAME' => 'Form Quizz FQI3',
        'FQI3_VERSION' => self::FQI3_VERSION,
        'ROLE_PREMIUM' => 'premium_member',
        'FQI3_TABLE_QUIZZES' => 'fqi3_quizzes',
        'FQI3_TABLE_PERFORMANCE' => 'fqi3_performance',
        'FQI3_TABLE_AWARDS' => 'fqi3_awards',
        'FQI3_TABLE_PERIODIC_STATISTICS' => 'fqi3_periodic_statistics',
        'DEFAULT_ANSWERS_COUNT' => 4,
        'MAX_ANSWERS_COUNT' => 8,
    ];

    /**
     * Get plugin instance
     *
     * @return FormQuizzFQI3
     */
    public static function getInstance(): FormQuizzFQI3 {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->defineConstants();
        $this->checkRequirements();
        $this->loadDependencies();
        $this->initHooks();
    }

    /**
     * Define plugin constants
     */
    private function defineConstants(): void {
        foreach (self::CONSTANTS as $constant => $value) {
            if (!defined($constant)) {
                define($constant, $value);
            }
        }

        // Path-based constants
        if (!defined('FQI3_FILE')) {
            define('FQI3_FILE', __FILE__);
            define('FQI3_PATH_DIRECTORY', basename(dirname(FQI3_FILE)));
            define('FQI3_PATH', dirname(FQI3_FILE));
            define('FQI3_INCLUDES', FQI3_PATH . '/includes');
            define('FQI3_URL', plugin_dir_url(FQI3_FILE));
            define('FQI3_ASSETS', FQI3_URL . 'assets');
        }
    }

    /**
     * Check system requirements
     */
    private function checkRequirements(): void {
        if (version_compare(PHP_VERSION, self::REQUIRED_PHP_VERSION, '<')) {
            add_action('admin_init', [$this, 'deactivatePlugin']);
            add_action('admin_notices', [$this, 'requirementsNotice']);
            return;
        }
    }

    /**
     * Deactivate plugin
     */
    public function deactivatePlugin(): void {
        if (current_user_can('activate_plugins')) {
            deactivate_plugins(plugin_basename(FQI3_FILE));
        }
    }

    /**
     * Display requirements notice
     */
    public function requirementsNotice(): void {
        $message = sprintf(
            /* translators: 1: Current PHP version 2: Required PHP version */
            esc_html__('Form Quizz FQI3 requires PHP version %2$s or higher. Your current version is %1$s.', 'form-quizz-fqi3'),
            PHP_VERSION,
            self::REQUIRED_PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void {
        // Activation/Deactivation hooks
        register_activation_hook(FQI3_FILE, [$this, 'activate']);
        register_deactivation_hook(FQI3_FILE, [$this, 'deactivate']);

        // Init hook for loading translations and API
        add_action('init', [$this, 'init'], 20);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'adminAssets']);
            add_action('admin_init', [$this, 'restrictAdminAccess']);
            // Run schema upgrade once to widen q/q2 to TEXT (utf8mb4)
            add_action('admin_init', [$this, 'maybeMigrateSchema300']);
            add_filter('plugin_action_links_' . plugin_basename(FQI3_FILE), [$this, 'pluginLinks']);
        } else {
            add_action('wp_enqueue_scripts', [$this, 'frontendAssets']);
        }

        // User-related hooks
        add_filter('show_admin_bar', [$this, 'handleAdminBar']);
    }

    /**
     * Plugin activation
     */
    public function activate(): void {
        $this->createTables();
        $this->createPremiumRole();
        $this->initializeOptions();
        $this->initializeSettings();
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function createTables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            FQI3_TABLE_QUIZZES => "
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . FQI3_TABLE_QUIZZES . " (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    niveau VARCHAR(255) NOT NULL,
                    q TEXT NOT NULL,
                    q2 TEXT NOT NULL,
                    options JSON NOT NULL,
                    answer INT(11) NOT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;
            "
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    /**
     * Create premium role
     */
    private function createPremiumRole(): void {
        $subscriber = get_role('subscriber');
        if ($subscriber && !get_role(ROLE_PREMIUM)) {
            add_role(ROLE_PREMIUM, 'Premium Member', $subscriber->capabilities);
        }
    }

    /**
     * Initializes the plugin's options
     * 
     * @since 2.1.0 Added automatic initialization of plugin options on activation.
    */
    private function initializeOptions(): void {    
        if (false === get_option('fqi3_options')) {
            fqi3_set_default_options(fqi3_default_options(), 'fqi3_options');
        }
    }

    /**
     * Initialize plugin settings and statistics
     *
     * Prepares the performance tracking database and sets up
     * the default quiz levels configuration.
    */
    private function initializeSettings(): void {
        (new \Form_Quizz_FQI3\FQI3_Statistics())->init_performance_database();
        (new \Form_Quizz_FQI3\FQI3_Levels_Settings())->set_default_options_levels();
    }

    /**
     * Load dependencies
     */
    private function loadDependencies(): void {
        $files = [
            'fqi3-config-functions.php',
            'admin/class-abstract-settings.php',
            'Utils/BlocksUtils.php',       
            'FQI3_Template_Manager.php',
            'FQI3_Awards.php',
            'FQI3_Frontend.php',
            'FQI3_Backend.php',
            'FQI3_Statistics.php',
            'FQI3_PeriodicStatistics.php',
            'FQI3_Blocks.php',
            'FQI3_Emails.php',
            'FQI3_Api.php',
            'admin/class-api-logger.php',
        ];

        foreach ($files as $file) {
            $path = FQI3_INCLUDES . '/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    /**
     * Initialize plugin
     * 
     * @since 1.0.0 Initial release
     * @since 1.6.0 Modified to include quiz API initialization based on settings
    */
    public function init(): void {
        $this->loadTextdomain();

        // Instance Gutenberg blocks
        $frontend = FQI3_Frontend::getInstance();
        $statistics = FQI3_Statistics::getInstance();
        $periodicStatistics = FQI3_PeriodicStatistics::getInstance();
        if (class_exists('Form_Quizz_FQI3\FQI3_Blocks')) {
            new \Form_Quizz_FQI3\FQI3_Blocks($frontend, $statistics, $periodicStatistics);
        }

        $options = get_option('fqi3_options', []);
        // Ensure default API settings whitelists are registered
        $this->registerApiSettingsWhitelists();
        if (isset($options['fqi3_quiz_api']) && $options['fqi3_quiz_api']) {
            new FQI3_Quiz_API();
        }
    }

    /**
     * Register default whitelists for API-exposed settings groups.
     *
     * These filters define which option keys from 'fqi3_options' can be exposed
     * through the read-only REST endpoints implemented in FQI3_Api.
     *
     * Addons or site code can extend or restrict these lists via the same filters.
     */
    private function registerApiSettingsWhitelists(): void {
        // General group: only requested fields
        add_filter('fqi3_api_settings_whitelist_general', function(array $keys) {
            $defaults = [
                'fqi3_free_trials_per_day',
                'fqi3_number_questions_per_quiz',
            ];
            return array_values(array_unique(array_merge($keys, $defaults)));
        });

        // Emails group: only requested fields
        add_filter('fqi3_api_settings_whitelist_emails', function(array $keys) {
            $defaults = [
                'fqi3_disable_emails',
            ];
            return array_values(array_unique(array_merge($keys, $defaults)));
        });

        // Tools group: only requested fields
        add_filter('fqi3_api_settings_whitelist_tools', function(array $keys) {
            $defaults = [
                'fqi3_disable_sharing',
                'fqi3_disable_statistics',
            ];
            return array_values(array_unique(array_merge($keys, $defaults)));
        });

        // Badges group: only requested fields
        add_filter('fqi3_api_settings_whitelist_badges', function(array $keys) {
            $defaults = [
                'fqi3_disable_badges',
                'fqi3_disable_badges_legend',
            ];
            return array_values(array_unique(array_merge($keys, $defaults)));
        });
    }

    /**
     * Load plugin textdomain early for both admin and frontend.
     */
    public function loadTextdomain(): void {
        load_plugin_textdomain(
            'form-quizz-fqi3',
            false,
            dirname(plugin_basename(FQI3_FILE)) . '/languages'
        );
    }

    /**
     * Enqueue admin styles and scripts
     *
     * @param string $hook The current admin page hook
     */
    public function adminAssets(string $hook): void {
        $plugin_pages = $this->getAdminPageSlugs();
    
        // Check if the current page is a plugin page
        if (!in_array($hook, $plugin_pages, true)) {
            return;
        }

        // Enqueue media assets if needed
        if ($hook === 'form-quizz-fqi3_page_fqi3-options-quiz-questions') {
            wp_enqueue_media();
        }

        // Enqueue Bootstrap assets
        $this->enqueueBootstrapAssets();

        // Enqueue Driver JS assets
        $this->enqueueDriverJsAssets();
    
        // Enqueue plugin-specific assets
        $this->enqueuePluginAssets();
    
        // Localize admin script
        $this->localizeAdminScript();
    }

    /**
     * Retrieve and filter admin page slugs for the plugin
     *
     * This method generates page slugs for the plugin and allows addons to extend 
     * the list of admin page slugs via a filter.
     *
     * @return string[] Array of admin page slugs
     * @since 3.0.0 Added support for extending page slugs via filter
    */
    private function getAdminPageSlugs(): array {
        $pages = fqi3_get_admin_pages();
    
        $plugin_pages = array_map(function ($page) {
            return $page['slug'] ?? null;
        }, $pages);
    
        $plugin_pages = array_filter($plugin_pages); // supprime les null
    
        $plugin_pages = array_map(function ($slug) {
            return 'form-quizz-fqi3_page_' . $slug;
        }, $plugin_pages);
    
        return apply_filters('fqi3_admin_page_slugs', $plugin_pages);
    }

    /**
     * Enqueue Bootstrap assets
     */
    private function enqueueBootstrapAssets(): void {
        wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], null, 'all');
        wp_enqueue_style('bootstrap-icon-css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', [], null, 'all');
        wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js', ['jquery'], null, true);
    }

    public function enqueueDriverJsAssets(): void {
        wp_enqueue_style('driver-css', 'https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css', [], null, 'all');
        wp_enqueue_script('driver-js', 'https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js', [], null, true);
    }

    /**
     * Enqueue plugin-specific assets
     */
    private function enqueuePluginAssets(): void {        
        wp_enqueue_style('fqi3-admin-style', FQI3_ASSETS . '/css/admin-style.css', [], self::FQI3_VERSION, 'all');
        // Load translatable Driver.js steps first (exposes window.fqi3GetDriverSteps)
        wp_enqueue_script('fqi3-driver-strings', FQI3_ASSETS . '/js/driverjs-strings.js', ['wp-i18n'], self::FQI3_VERSION, true);
        wp_set_script_translations('fqi3-driver-strings', 'form-quizz-fqi3', FQI3_PATH . '/languages/');

        // Main admin script depends on jQuery, wp-i18n and our driver strings
        wp_enqueue_script('fqi3-admin-script', FQI3_ASSETS . '/js/admin-script.js', ['jquery', 'wp-i18n', 'fqi3-driver-strings'], self::FQI3_VERSION, true);
        wp_set_script_translations('fqi3-admin-script', 'form-quizz-fqi3', FQI3_PATH . '/languages/');
    }

    private function localizeAdminScript(): void {
        $translations = [
            'revoke_confirmation' => __('Are you sure you want to revoke the API token?', 'form-quizz-fqi3'),
            'copy_error' => __('Unable to copy the shortcode. Please try again.', 'form-quizz-fqi3'),
            'media_title' => __('Insert image', 'form-quizz-fqi3'),
            'media_button' => __('Use this image', 'form-quizz-fqi3'),
            'upload_button' => __('Upload image', 'form-quizz-fqi3'),
            'answer_choice' => __('Answer choice', 'form-quizz-fqi3'),
            'remove_answer_option' => __('Remove Answer Option', 'form-quizz-fqi3'),
            'min_answers' => __('You must have at least 4 answer options.', 'form-quizz-fqi3'),
        ];
    
        wp_localize_script('fqi3-admin-script', 'admin_vars', [
            'admin_url' => admin_url('admin.php'),
            'translations' => $translations,
            'max_answers_count' => defined('MAX_ANSWERS_COUNT') ? MAX_ANSWERS_COUNT : 10,
            'default_answers_count' => defined('DEFAULT_ANSWERS_COUNT') ? DEFAULT_ANSWERS_COUNT : 4,         
        ]);
    
        wp_localize_script('fqi3-admin-script', 'fqi3_admin_cookies_ajax_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fqi3_admin_cookies_nonce'),
            'test_email_nonce' => wp_create_nonce('fqi3_test_email_nonce'),
        ]);

        wp_localize_script('fqi3-admin-script', 'fqi3Stats', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fqi3_refresh_stats')
        ]);

        wp_localize_script('fqi3-admin-script', 'fqi3AddonsAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce_search' => wp_create_nonce('fqi3_addons_search'),
            'nonce_action' => wp_create_nonce('fqi3_addon_action'),
            'nonce_issue' => wp_create_nonce('fqi3_report_issue'),
            'nonce_suggest' => wp_create_nonce('fqi3_suggest_addon'),
            'strings' => [
                'noResults' => __('No add-ons found.', 'form-quizz-fqi3'),
                'loading'   => __('Please wait...', 'form-quizz-fqi3'),
                'error'     => __('An unexpected error occurred.', 'form-quizz-fqi3'),
                'installing'=> __('Installing add-on...', 'form-quizz-fqi3'),
                'activating'=> __('Activating add-on...', 'form-quizz-fqi3'),
                'deactivating' => __('Deactivating add-on...', 'form-quizz-fqi3'),
                'notAllowed'   => __('You are not allowed to perform this action.', 'form-quizz-fqi3'),
                'invalidNonce' => __('Invalid security token.', 'form-quizz-fqi3'),
                'submitting' => __('Submitting...', 'form-quizz-fqi3'),
            ]
        ]);

        // AJAX validation for Add Question form
        wp_localize_script('fqi3-admin-script', 'fqi3QuestionsAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce_validate_add' => wp_create_nonce('fqi3_validate_add_question'),
        ]);

        // GitHub Token AJAX
        wp_localize_script('fqi3-admin-script', 'fqi3GitHubAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fqi3_save_github_token_nonce'),
            'nonce_refresh_cache' => wp_create_nonce('fqi3_refresh_cache'),
            'strings' => [
                'empty_token' => __('Please enter a GitHub token.', 'form-quizz-fqi3'),
                'invalid_length' => __('GitHub token must contain at least 20 characters.', 'form-quizz-fqi3'),
                'error' => __('An unexpected error occurred.', 'form-quizz-fqi3'),
                'ajax_error' => __('Server communication error.', 'form-quizz-fqi3'),
                'current_token' => __('Current token', 'form-quizz-fqi3'),
                'update_placeholder' => __('Enter new token to update', 'form-quizz-fqi3'),
                'success' => __('GitHub token saved successfully!', 'form-quizz-fqi3'),
            ]
        ]);
    }

    /**
     * Restrict admin access for Premium Members.
     *
     * This method checks if the current user has the 'Premium Member' role. If they do,
     * it redirects them to the homepage, preventing access to the WordPress admin dashboard.
     * This excludes AJAX requests to avoid breaking backend functionality.
     *
     * @return void
     * 
     * @since 1.3.2
     */
    public function restrictAdminAccess() {
        if (current_user_can(ROLE_PREMIUM) && !defined('DOING_AJAX')) {
            wp_redirect(home_url());
            exit;
        }
    }

    /**
     * Adds custom links to the plugin action links on the plugins page.
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links.
     * 
     * @since 1.5.0
     */
    public function pluginLinks($links): array {
        // Create settings link
        $settings_link = '<a href="admin.php?page=' . fqi3_get_options_page_slug() . '">' . __('Settings', 'form-quizz-fqi3') . '</a>';
        
        // Create support link
        $support_link = '<a href="https://webpixelia.com/en/contact" target="_blank">' . __('Support', 'form-quizz-fqi3') . '</a>';
        
        // Add the new links at the beginning of the links array
        array_unshift($links, $settings_link, $support_link);
        
        return $links;
    }

    /**
     * Enqueue frontend assets
     */
    public function frontendAssets(): void {
        // Core styles
        wp_enqueue_style('fqi3-style', FQI3_ASSETS . '/css/front-style.css', [], self::FQI3_VERSION, 'all');

        // Scripts
        wp_enqueue_script('confetti-js', 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js', [], null, true);
        wp_enqueue_script('fqi3-script', FQI3_ASSETS . '/js/front-script.js', ['jquery'], self::FQI3_VERSION, true);

        // Localize script
        wp_localize_script('fqi3-script', 'fqi3Data', $this->getFrontendLocalization());
    }

    /**
     * Get frontend localization data
     *
     * @return array
     */
    private function getFrontendLocalization(): array {
        $options = fqi3_get_options();
        $free_trials = (int) ($options['fqi3_free_trials_per_day'] ?? 3);
        $disable_statistics = isset($options['fqi3_disable_statistics']) && ($options['fqi3_disable_statistics'] === '1' || !empty($options['fqi3_disable_statistics']));
        $levels = fqi3_get_free_quiz_levels();
    
        // Organize levels for JavaScript
        $levels_for_js = [];
        foreach ($levels as $key => $level) {
            $levels_for_js[$key] = $level['label'];
        }
    
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fqi3_frontend_nonce'),
            'user_id' => get_current_user_id(),
            'isUserLoggedIn' => is_user_logged_in(),
            
            // Translations organized as 'translations' subkey
            'translations' => $this->getFrontendTranslations($free_trials),
    
            // Configurations and other variables under 'config' subkey
            'config' => [
                'disable_statistics' => $disable_statistics,
                'free_trials_per_day' => $free_trials,
                'levels' => $levels_for_js,
            ],
    
            // Cookie-related nonce and AJAX URL under 'cookies' subkey
            'cookies' => [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fqi3_cookies_nonce'),
            ],
    
            // Session handling nonce and AJAX URL under 'session' subkey
            'session' => [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fqi3_session_nonce'),
            ]
        ];
    }

    /**
     * Get frontend translations
     *
     * @param int $free_trials
     * @return array
     */
    private function getFrontendTranslations(int $free_trials): array {
        return [
            'select_level' => __('Please select a level before starting the quiz.', 'form-quizz-fqi3'),
            // Translators: %s is the limit number of quiz per day
            'loading_error' => sprintf(__('You have reached the limit of %s quizzes per day as a free logged in user.', 'form-quizz-fqi3'), $free_trials),
            // Translators: %1$s is the number of correct answer if singular, %2$s is the total number of questions, %3$s is the percentage of correct answers, %4$s is the finished level
            'result_text_singular' => __('You have %1$s correct answer on %2$s. (%3$s%) for the %4$s level', 'form-quizz-fqi3'),
            // Translators: %1$s is the number of correct answers if plural, %2$s is the total number of questions, %3$s is the percentage of correct answers, %4$s is the finished level
            'result_text_plural' => __('You have %1$s correct answers on %2$s. (%3$s%) for the %4$s level', 'form-quizz-fqi3'),
            'select_answer' => __('Select an answer before moving on to the next question.', 'form-quizz-fqi3'),
            'question' => __('Question', 'form-quizz-fqi3'),
            'on' => __('on', 'form-quizz-fqi3'),
            'messageTimer' => __('Oops! Your time is up! Feel free to try again.', 'form-quizz-fqi3'),
            // Translators: %1$s is the URL site, %2$s is  the number of correct answers, %3$s is the total number of questions, %3$s is the percentage of correct answers
            'share_post_text' => __('I just finished the quiz on %1$s and I answered %2$s of %3$s questions correctly; that\'s %4$s%. Try it too; it\'s free!', 'form-quizz-fqi3'),
        ];
    }

    /**
     * Disable the admin bar in frontend for Premium Members.
     *
     * This method disables the admin bar for users with the 'Premium Member' role
     * when they are viewing the frontend of the site.
     *
     * @param bool $show_admin_bar Whether to display the admin bar.
     * @return bool False if the user is a Premium Member, otherwise the original value.
     *
     * @since 1.3.2
     */
    public function handleAdminBar($show_admin_bar): bool {
        if (current_user_can(ROLE_PREMIUM)) {
            return false;
        }
        return $show_admin_bar;
    }

    /**
     * Handle plugin deactivation
     */
    public function deactivate(): void {
        remove_role(ROLE_PREMIUM);
        flush_rewrite_rules();
    }

    /**
     * Upgrade DB schema for 3.0.0 to allow longer question texts.
     * - Change columns q and q2 in core quizzes table to TEXT with site charset/collation.
     * - Idempotent: checks current column types and skips if already TEXT.
     * - Executes once and records completion in an option.
     */
    public function maybeMigrateSchema300(): void {
        // Ensure only privileged users trigger migration to avoid unnecessary writes
        if (!current_user_can('manage_options')) {
            return;
        }

        $flag = get_option('fqi3_core_schema_qtext_300', '0');
        if ($flag === '1') {
            return; // already migrated
        }

        global $wpdb;
        $table = $wpdb->prefix . FQI3_TABLE_QUIZZES;

        // Verify table exists
        $table_exists = (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
            DB_NAME,
            $table
        ));
        if (!$table_exists) {
            // Create tables if missing (fresh installs)
            $this->createTables();
            update_option('fqi3_core_schema_qtext_300', '1');
            return;
        }

        // Describe current columns
        $cols = $wpdb->get_results('DESCRIBE ' . $table, ARRAY_A);
        if (!is_array($cols)) {
            return;
        }
        $types = [];
        foreach ($cols as $c) {
            if (!empty($c['Field']) && !empty($c['Type'])) {
                $types[strtolower($c['Field'])] = strtolower($c['Type']);
            }
        }

        $alters = [];
        $charset = $wpdb->charset ? (' CHARACTER SET ' . $wpdb->charset) : '';
        $collate = $wpdb->collate ? (' COLLATE ' . $wpdb->collate) : '';

        // Only widen if not already text
        if (isset($types['q']) && strpos($types['q'], 'text') === false) {
            $alters[] = 'MODIFY `q` TEXT' . $charset . $collate;
        }
        if (isset($types['q2']) && strpos($types['q2'], 'text') === false) {
            $alters[] = 'MODIFY `q2` TEXT' . $charset . $collate;
        }

        if ($alters) {
            $sql = 'ALTER TABLE `' . esc_sql($table) . '` ' . implode(', ', $alters);
            $wpdb->query($sql);
            if (defined('WP_DEBUG') && WP_DEBUG && !empty($wpdb->last_error)) {
                error_log('[FQI3] Schema migration 3.0.0 notice: ' . $wpdb->last_error);
            }
        }

        update_option('fqi3_core_schema_qtext_300', '1');
    }
}

// Initialize plugin
FormQuizzFQI3::getInstance();

// Initialize GitHub updater for the main plugin
if (is_admin()) {
    require_once __DIR__ . '/includes/admin/class-plugin-updater.php';
    new \Form_Quizz_FQI3\Plugin_Updater(
        'form-quizz-fqi3/form-quizz-fqi3.php',
        'form-quizz-fqi3',
        'Webpixelia'
    );
}