<?php
/**
 * Plugin Name: Form Quizz FQI3
 * Description: A plugin to create and manage free quizzes with premium features.
 * Version: 2.1.0
 * Author: Jonathan Webpixelia
 * Author URI: https://webpixelia.com
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

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

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
    private const FQI3_VERSION = '2.1.0';

    /**
     * GitHub repository details
     *
     * @var string
     */
    private const GITHUB_REPO_URL = 'https://github.com/Webpixelia/form-quizz-fqi3';

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
        $this->initHooks();
        $this->loadDependencies();
        $this->initUpdateChecker();
    }

    /**
     * Initialize GitHub update checker
     */
    private function initUpdateChecker(): void {
        $updateChecker = PucFactory::buildUpdateChecker(
            self::GITHUB_REPO_URL,
            __FILE__,
            'form-quizz-fqi3'
        );

        // Optional: Set the branch that contains the stable release
        //$updateChecker->setBranch('main');

        // Enable release assets
        //$updateChecker->getVcsApi()->enableReleaseAssets();

        // Optional: If repository is private
        // $updateChecker->setAuthentication('your-github-personal-access-token');

        // Optional: Set update checker to check for pre-release versions
        // $updateChecker->setUpdateMetadata([
        //     'version' => self::FQI3_VERSION,
        //     'requires' => '5.0', // Minimum WordPress version
        //     'tested' => '6.2', // Tested up to WordPress version
        //     'requires_php' => self::REQUIRED_PHP_VERSION,
        // ]);
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
        add_action('init', [$this, 'init']);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'adminAssets']);
            add_action('admin_init', [$this, 'restrictAdminAccess']);
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
                    niveau VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                    q VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                    q2 VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
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
            'db/PerformanceTableManager.php',
            'FQI3_Template_Manager.php',
            'FQI3_Awards.php',
            'FQI3_Frontend.php',
            'FQI3_Backend.php',
            'FQI3_Statistics.php',
            'FQI3_PeriodicStatistics.php',
            'FQI3_Emails.php',
            'FQI3_Api.php'
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
        load_plugin_textdomain('form-quizz-fqi3', false, FQI3_PATH_DIRECTORY . '/languages');

        $options = get_option('fqi3_options', []);
        if (isset($options['fqi3_quiz_api']) && $options['fqi3_quiz_api']) {
            new FQI3_Quiz_API();
        }
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
    
        // Enqueue plugin-specific assets
        $this->enqueuePluginAssets();
    
        // Localize admin script
        $this->localizeAdminScript();
    }

    /**
     * Get admin page slugs
     *
     * @return string[]
     */
    private function getAdminPageSlugs(): array {
        $pages = fqi3_get_admin_pages();
        return array_map(function ($page) {
            return 'form-quizz-fqi3_page_' . $page['slug'];
        }, $pages);
    }

    /**
     * Enqueue Bootstrap assets
     */
    private function enqueueBootstrapAssets(): void {
        wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], null, 'all');
        wp_enqueue_style('bootstrap-icon-css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', [], null, 'all');
        wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js', ['jquery'], null, true);
    }

    /**
     * Enqueue plugin-specific assets
     */
    private function enqueuePluginAssets(): void {
        wp_enqueue_style('fqi3-admin-style', FQI3_ASSETS . '/css/admin-style.css', [], self::FQI3_VERSION, 'all');
        wp_enqueue_script('fqi3-admin-script', FQI3_ASSETS . '/js/admin-script.js', ['jquery'], self::FQI3_VERSION, true);
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
}

// Initialize plugin
FormQuizzFQI3::getInstance();