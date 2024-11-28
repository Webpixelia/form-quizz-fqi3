<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3 Backend Main Class
 *
 * This class handles the core backend functionality for the Form Quizz FQI3 plugin.
 * It initializes and manages the admin pages, settings, hooks, and configurations
 * for the quiz plugin, providing the foundation for displaying and managing quizzes
 * in the WordPress admin panel.
 *
 * @package Form Quizz FQI3
 * @since 1.0.0
 * @since 2.0.0
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('FQI3_Backend')) :

class FQI3_Backend {

    // Static singleton instance
    private static ?self $instance = null;

    // Private properties for pages and quiz levels
    private FQI3_View_Questions_Page $view_questions_page;
    private FQI3_Add_Questions_Page $add_questions_page;
    private FQI3_Edit_Questions_Page $edit_questions_page;
    private FQI3_Statistics_Page $statistics_page;
    private FQI3_Option_Page $options_page;
    private FQI3_Infos_Page $infos_page;
    private FQI3_Import_Export_Page $import_export_page;
    private array $levelsQuiz;
    private array $plugin_page_slugs;

    /**
     * Class constructor.
     * Initializes settings, adds menu pages, and sets up hooks for handling actions and filters.
     */
    public function __construct() {
        // Load pages slugs
        $this->plugin_page_slugs = fqi3_get_admin_pages();

        // Load admin class
        $this->loadAdminClasses();

        // Initialize admin pages
        $this->add_questions_page = new FQI3_Add_Questions_Page($this);
        $this->edit_questions_page = new FQI3_Edit_Questions_Page($this);
        $this->statistics_page = new FQI3_Statistics_Page($this);
        $this->options_page = new FQI3_Option_Page($this);
        $this->infos_page = new FQI3_Infos_Page($this);
        $this->import_export_page = new FQI3_Import_Export_Page($this);

        // Hooks
        $this->setup_hooks();
    }

    /**
     * Returns the single instance of the class.
     * 
     * @return FQI3_Backend
     * 
     * @since 1.3.0
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadAdminClasses(): void {
        $adminClasses = [
            // Settings Options Page
            'class-general-settings',
            'class-content-settings',
            'class-emails-settings',
            'class-access-control-settings',
            'class-badges-settings',
            'class-levels-settings',
            'class-api-settings',
            // Pages Admin
            'class-view-questions',
            'class-add-questions',
            'class-edit-questions',
            'class-options',
            'class-infos',
            'class-statistics',
            'class-import-export',
        ];

        foreach ($adminClasses as $class) {
            require_once FQI3_INCLUDES . '/admin/' . $class . '.php';
        }
    }

     /**
     * Sets up hooks for the plugin.
     */
    private function setup_hooks(): void
    {
        add_action('wp_loaded', [$this, 'initialize_levels']);
        add_action('wp_loaded', [$this, 'initialize_view_questions_page']);
        add_action('admin_init', [$this, 'initialize_settings']);
        //add_action('wp_ajax_fqi3_save_options', [$this, 'handle_save_options']);
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_notices', [$this, 'display_admin_header']);
        add_action('in_admin_footer', [$this, 'display_admin_footer']);
        add_action('admin_post_add_question', [$this->add_questions_page, 'handle_add_question']);
        add_action('admin_post_update_question', [$this->edit_questions_page, 'handle_update_question']);
        add_filter('set_screen_option_fqi3_items_per_page', [$this, 'save_screen_options'], 10, 3);
        add_action('load-form-quizz-fqi3_page_' . $this->get_view_questions_slug(), [$this, 'screen_options']);
    }

    // Test ajax 
    /*public function handle_save_options() {
        try {
            // Vérification du nonce
            if (!check_ajax_referer('fqi3_settings_nonce', 'security', false)) {
                wp_send_json_error(['message' => 'Nonce invalide']);
                return;
            }
    
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permissions insuffisantes']);
                return;
            }
    
            // Récupérer les données organisées
            $options = $_POST['options'] ?? [];
            
            // Vérifier que les données sont dans le bon format
            if (!is_array($options)) {
                wp_send_json_error(['message' => 'Format de données invalide']);
                return;
            }
    
            $updated = false;
    
            // Traiter et sauvegarder les options générales
            if (isset($options['fqi3_options']) && is_array($options['fqi3_options'])) {
                $sanitized_options = $this->sanitize_fqi3_settings($options['fqi3_options']);
                $updated = update_option('fqi3_options', $sanitized_options) || $updated;
            }
    
            // Traiter et sauvegarder les badges
            if (isset($options['fqi3_badges']) && is_array($options['fqi3_badges'])) {
                $sanitized_badges = FQI3_Badges_Settings::sanitize_badges_options($options['fqi3_badges']);
                $updated = update_option('fqi3_badges', $sanitized_badges) || $updated;
            }
    
            // Traiter et sauvegarder les niveaux
            if (isset($options['fqi3_quiz_levels']) && is_array($options['fqi3_quiz_levels'])) {
                $sanitized_levels = FQI3_Levels_Settings::sanitize_levels_options($options['fqi3_quiz_levels']);
                $updated = update_option('fqi3_quiz_levels', $sanitized_levels) || $updated;
            }
    
            // Traiter et sauvegarder les rôles
            if (isset($options['fqi3_access_roles']) && is_array($options['fqi3_access_roles'])) {
                $sanitized_roles = FQI3_Access_Control_Settings::sanitize_roles($options['fqi3_access_roles']);
                $updated = update_option('fqi3_access_roles', $sanitized_roles) || $updated;
            }
    
            if ($updated) {
                wp_send_json_success([
                    'message' => 'Options sauvegardées avec succès',
                    'updated' => true
                ]);
            } else {
                wp_send_json_success([
                    'message' => 'Aucune modification n\'a été nécessaire',
                    'updated' => false
                ]);
            }
    
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ]);
        }
    }*/

    /**
     * Initializes the quiz levels by retrieving them from the configuration function.
     * 
     * This method assigns the result of the `fqi3_get_free_quiz_levels` function to the `$levelsQuiz` property.
     * It is called during the `wp_loaded` hook to ensure that all WordPress components are fully loaded
     * before initializing the levels.
     * 
     * @since 1.2.2
     */
    public function initialize_levels(): void
    {
        $this->levelsQuiz = fqi3_get_free_quiz_levels();
    }

    /**
     * Initializes the quiz levels by retrieving them from the configuration function.
     * 
     * This method assigns the result of the `fqi3_get_free_quiz_levels` function to the `$levelsQuiz` property.
     * It is called during the `wp_loaded` hook to ensure that all WordPress components are fully loaded
     * before initializing the levels.
     * 
     * @since 1.2.2
     */
    public function initialize_view_questions_page(): void
    {
        $this->view_questions_page = new FQI3_View_Questions_Page($this, $this->get_levels_quiz());
    }

    /**
    * Initializes plugin settings and handles the test email submission.
    * 
    * This method registers the plugin settings and creates an instance
    * of the FQI3_Emails_Settings class to handle the submission of 
    * the test email.
    *
    * @since 1.4.0
    */
    public function initialize_settings(): void
    {
        $this->init_register_settings();
        $email_settings_instance = new \Form_Quizz_FQI3\FQI3_Emails_Settings();
    }

    /**
     * Registers the plugin settings by calling specific methods for each group 
     * of settings. This method ensures that the sections and fields for the 
     * settings are correctly registered and configured for display on the plugin's 
     * options page.
     *
     * Note: This method reflects the logic changes introduced in version 1.3.1.
     */
    public function init_register_settings() {
        register_setting('fqi3_options_group', 'fqi3_options', [$this, 'sanitize_fqi3_settings']);
        register_setting('fqi3_options_group', 'fqi3_access_roles',['FQI3_Access_Control_Settings', 'sanitize_roles']);
        //register_setting('fqi3_options_group', 'fqi3_badges',['FQI3_Badges_Settings', 'sanitize_badges_options']);
        //register_setting('fqi3_options_group', 'fqi3_quiz_levels',['FQI3_Levels_Settings', 'sanitize_levels_options']);

        $config = fqi3_options_settings_sections();
        foreach ($config['settings_classes'] as $class) {
            if (class_exists("Form_Quizz_FQI3\\" . $class)) {
                $className = "Form_Quizz_FQI3\\" . $class;
                $settings_instance = new $className();
                if (method_exists($settings_instance, 'register_settings')) {
                    $settings_instance->register_settings();
                }
            }
        }
    }

     /**
     * Sanitize the settings input for the FQI3 plugin.
     *
     * This method takes raw input data and applies specific sanitization processes
     * for different sections of the settings. It merges the sanitized options 
     * from the general settings, email settings, and content settings into a 
     * single array before returning it. This ensures that all user inputs 
     * are clean and safe to use within the application.
     *
     * @param array $input The raw input settings data.
     * @return array The sanitized settings data.
     */
    public function sanitize_fqi3_settings(array $input): array {
        $sanitized_input = [];

        $sanitized_input = array_merge($sanitized_input, FQI3_General_Settings::sanitize_general_settings_options($input));
        $sanitized_input = array_merge($sanitized_input, FQI3_Emails_Settings::sanitize_emails_settings_options($input));
        $sanitized_input = array_merge($sanitized_input, FQI3_Content_Settings::sanitize_content_options($input));
        $sanitized_input = array_merge($sanitized_input, FQI3_Api_Settings::sanitize_api_options($input));

        return $sanitized_input;
    }

    /**
     * Adds menu pages and submenus to the WordPress admin menu.
     */
    public function add_menu_pages(): void {
        $pages = $this->plugin_page_slugs;
        $allowed_roles = $this->get_allowed_roles();

        foreach ($pages as $key => $page) {
            $property = isset($page['property']) ? $page['property'] : null;
            $callback = isset($page['callback']) ? $page['callback'] : null;
            $thisCallback = !empty($property) && property_exists($this, $property) ? [$this->$property, $callback] : [$this, $page['callback']];
            if ($page['restricted'] && !$this->user_has_any_role($allowed_roles)) {
                continue;
            }

            // Add main menu page
            if ($key === 'main') {
                add_menu_page(
                    __($page['title'], 'form-quizz-fqi3'),
                    __($page['title'], 'form-quizz-fqi3'),
                    $page['capability'],
                    $page['slug'],
                    '__return_false',
                    $page['icon'],
                    $page['position']
                );
            } else {
                // Add submenus
                add_submenu_page(
                    $pages['main']['slug'],
                    __($page['title'], 'form-quizz-fqi3'),
                    __($page['title'], 'form-quizz-fqi3'),
                    $page['capability'],
                    $page['slug'],
                    $thisCallback
                );
            }
        }
        // Remove the main menu entry from submenus
        remove_submenu_page($pages['main']['slug'], $pages['main']['slug']);
    }

     /**
     * Display custom admin header on plugin pages.
     *
     * This method outputs a custom header on admin pages specific to the plugin.
     * It is hooked to the 'admin_notices' action, which displays the content 
     * in the WordPress admin area.
     *
     * @return void
     * 
     * @since 1.3.2
     */
    public function display_admin_header(): void
    {
        $screen = get_current_screen();
        if (is_plugin_admin_page($screen->id)) {
            include(FQI3_PATH . '/templates/header.php');
        }
    }

    /**
    * Display custom admin footer on plugin pages.
    *
    * This method outputs a custom footer on admin pages specific to the plugin.
    * It is hooked to the 'in_admin_footer' action, which displays the content 
    * in the WordPress admin area.
    *
    * @return void
    * 
    * @since 1.3.2
    */
    public function display_admin_footer(): void {
        $screen = get_current_screen();
        if (is_plugin_admin_page($screen->id)) {
            include(FQI3_PATH . '/templates/footer.php');
        }
    }

    /**
     * Retrieves the slug for the 'view_questions' page from the plugin_page_slugs property.
     *
     * @return string|null The slug of the 'view_questions' page, or null if not found.
     * 
     * @since 1.4.0
     */
    public function get_view_questions_slug(): ?string {
        return isset($this->plugin_page_slugs['view_questions']['slug']) ? $this->plugin_page_slugs['view_questions']['slug'] : null;
    }

    /**
     * Retrieves the table name for storing quiz questions.
     *
     * @return string The table name with prefix.
     */
    private function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . FQI3_TABLE_QUIZZES;
    }

    /**
     * Public method to get the quiz table name.
     *
     * @return string The quiz table name.
     * 
     * @since 1.4.0
     */
    public function get_quiz_table_name(): string {
        return $this->get_table_name();
    }


    /**
     * Renders a form for adding or editing a quiz question.
     *
     * @param string $action The action to perform, either 'add_question' or 'update_question'.
     * @param object|null $question The question object to edit (null if adding a new question).
     * @param string $nonce_action The nonce action name for security.
     * @param string $nonce_name The nonce field name for security.
     *
     * @return void
     */
    public function render_question_form($action, $question = null, $nonce_action = '', $nonce_name = '') {
        $is_edit = $action === 'update_question';
        $options = $question ? json_decode($question->options, true) : [];
        include(FQI3_PATH . '/templates/question-form.php');
    }

     /**
     * Saves the number of items per page option when changed.
     * 
     * This method updates the option value in the database when the number of items per page setting is changed.
     * 
     * @param mixed $status Current status.
     * @param string $option Option name.
     * @param mixed $value New value for the option.
     * @return mixed Updated value if the option is 'fqi3_items_per_page', otherwise the original status.
     */
    public function save_screen_options($status, $option, $value)
    {
        if ($option === 'fqi3_items_per_page') {
            update_option($option, $value, false);
            return $value;
        }
        return $status;
    }

    /**
     * Adds screen options for the plugin's admin page.
     * 
     * This method adds a screen option to allow users to set the number of items per page on the plugin's admin page.
     * It is only applied to the specific admin screen identified by 'form-quizz-fqi3_page_' . $this->get_view_questions_slug() .
     */
    public function screen_options(): void
    {
        $screen = get_current_screen();
        if ($screen->id !== 'form-quizz-fqi3_page_' . $this->get_view_questions_slug()) {
            return;
        }

        $args = array(
            'label'   => __('Questions per page', 'form-quizz-fqi3'),
            'default' => 10,
            'option'  => 'fqi3_items_per_page'
        );

        add_screen_option('per_page', $args);
    }
    
    /**
     * Retrieves the allowed roles from the options.
     * 
     * Returns the roles that are allowed to manage questions. If no roles are selected, it defaults to allowing only administrators.
     * 
     * @return array An array of allowed roles.
     * @since 1.1.0
     */
    private function get_allowed_roles(): array {
        $roles = get_option('fqi3_access_roles', []);
        return $roles ?: ['administrator'];
    }

    /**
     * Checks if the current user has any of the specified roles.
     * 
     * Allows access to administrators and checks if the user has any of the specified roles.
     * 
     * @param array $roles The roles to check for.
     * @return bool True if the user has any of the specified roles, false otherwise.
     * @since 1.1.0
     */
    private function user_has_any_role(array $roles): bool {
        if (current_user_can('administrator')) {
                return true;
            }

        foreach ($roles as $role) {
            if (current_user_can($role)) {
                return true;
            }
        }
        return false;
    }

     /**
     * Retrieve levels for quizzes.
     *
     * @return array The levels for quizzes.
     * 
     * @since 1.4.0
     */
    public function get_levels_quiz(): array {
        return $this->levelsQuiz;
    }
}

endif; // Class exists check.

new FQI3_Backend();