<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Returns the array of quiz levels with translated labels and free status.
 * 
 * @return array
 * 
 * @since 1.2.2
 * @since 1.3.1 Modified to include 'free' status for each level
 * @since 1.5.0 Updated to retrieve levels from the database
*/
function fqi3_get_free_quiz_levels() {
    $stored_levels = get_option('fqi3_quiz_levels', false);

    if ($stored_levels) {
        $levels = [];
        foreach ($stored_levels['fqi3_quiz_levels_name'] as $index => $name) {
            $levels[$name] = [
                'label' => $stored_levels['fqi3_quiz_levels_label'][$index],
                'free'  => (bool) $stored_levels['fqi3_quiz_levels_is_free'][$index],
            ];
        }
        return $levels;
    }
    
    return [];
}

/**
 * Get the plugin page slugs.
 * 
 * @return array
 * 
 * @since 1.3.1
 */
function fqi3_get_admin_pages() {
    return [
        'main' => [
            'slug' => 'fqi3-main',
            'title' => __('Form Quizz FQI3', 'form-quizz-fqi3'),
            'capability' => 'edit_posts',
            'callback' => 'fqi3_render_main_page',
            'icon' => 'dashicons-welcome-learn-more',
            'position' => 6,
            'restricted' => true
        ],
        'view_questions' => [
            'slug' => 'fqi3-view-quiz-questions',
            'title' => __('View Questions', 'form-quizz-fqi3'),
            'capability' => 'edit_posts',
            'property' => 'view_questions_page',
            'callback' => 'render_consultation_page',
            'restricted' => true
        ],
        'add_questions' => [
            'slug' => 'fqi3-add-quiz-questions',
            'title' => __('Add Question', 'form-quizz-fqi3'),
            'capability' => 'edit_posts',
            'property' => 'add_questions_page',
            'callback' => 'render_add_question_page',
            'restricted' => true
        ],
        'edit_questions' => [
            'slug' => 'fqi3-update-quiz-questions',
            'title' => __('Edit Question', 'form-quizz-fqi3'),
            'capability' => 'edit_posts',
            'property' => 'edit_questions_page',
            'callback' => 'render_edit_question_page',
            'restricted' => true
        ],
        'statistics' => [
            'slug' => 'fqi3-statistics-quiz-questions',
            'title' => __('Statistics', 'form-quizz-fqi3'),
            'capability' => 'edit_posts',
            'property' => 'statistics_page',
            'callback' => 'render_statistics_page',
            'restricted' => true
        ],
        'options' => [
            'slug' => 'fqi3-options-quiz-questions',
            'title' => __('Settings', 'form-quizz-fqi3'),
            'capability' => 'manage_options',
            'property' => 'options_page',
            'callback' => 'render_options_page',
            'restricted' => false
        ],
        'import_export' => [
            'slug' => 'fqi3-import-export',
            'title' => __('Import/Export', 'form-quizz-fqi3'),
            'capability' => 'manage_options',
            'property' => 'import_export_page',
            'callback' => 'render_import_export_page',
            'restricted' => false
        ],
        'infos' => [
            'slug' => 'fqi3-changelog-user-guide',
            'title' => __('Changelog and User Guide', 'form-quizz-fqi3'),
            'capability' => 'manage_options',
            'property' => 'infos_page',
            'callback' => 'render_infos_page',
            'restricted' => false
        ]
    ];
}

/**
 * Get the slug for the options page.
 *
 * @return string The slug of the options page.
 * 
 * @since 1.4.0
 */
function fqi3_get_options_page_slug() {
    $plugin_page_slugs = fqi3_get_admin_pages();
    return isset($plugin_page_slugs['options']['slug']) ? $plugin_page_slugs['options']['slug'] : '';
}

/**
 * Retrieves the settings sections configuration for the plugin.
 *
 * @since 1.4.0
 * 
 * @return array An array of settings classes and their associated sections, 
 *               including IDs, titles, and options details.
 */
function fqi3_options_settings_sections() {
    return [
        'settings_classes' => [
            'FQI3_General_Settings',
            'FQI3_Access_Control_Settings',
            'FQI3_Emails_Settings',
            'FQI3_Content_Settings',
            'FQI3_Badges_Settings',
            'FQI3_Levels_Settings',
            'FQI3_Api_Settings'
        ],
        'sections' => [
            'general_settings' => [
                'id' => 'fqi3_general_section',
                'title' => __('General Settings', 'form-quizz-fqi3'),
                'icon' => 'bi-gear'
            ],
            'levels_settings' => [
                'id' => 'fqi3_levels_section',
                'title' => __('Levels Settings', 'form-quizz-fqi3'),
                'icon' => 'bi-bar-chart-steps'
            ],
            'access_control' => [
                'id' => 'fqi3_access_section',
                'title' => __('Access Control', 'form-quizz-fqi3'),
                'icon' => 'bi-universal-access-circle'
            ],
            'emails' => [
                'id' => 'fqi3_emails_section',
                'title' => __('Emails Options', 'form-quizz-fqi3'),
                'icon' => 'bi-envelope-at'
            ],
            'content' => [
                'id' => 'fqi3_content_section',
                'title' => __('Content Options', 'form-quizz-fqi3'),
                'icon' => 'bi-body-text'
            ],
            'badges' => [
                'id' => 'fqi3_badges_section',
                'title' => __('Badges Options', 'form-quizz-fqi3'),
                'icon' => 'bi-patch-check'
            ],
            'api' => [
                'id' => 'fqi3_api_section',
                'title' => __('API Options', 'form-quizz-fqi3'),
                'icon' => 'bi-braces'
            ]
        ]
    ];
}

/**
 * Returns the default options for the plugin.
 * 
 * This function defines and returns an array of default options for the plugin, including
 * various settings such as the number of trials per day, the color scheme for buttons and text, 
 * the email settings, and more. These default options are used when initializing or updating 
 * the plugin's options in the database.
 * 
 * @return array An associative array containing the default options for the plugin.
 * 
 * @since 2.1.0 Introduced default options for the plugin setup.
*/
function fqi3_default_options() {
    return [
        'fqi3_render_link_sales_page_field' => absint(get_option('page_on_front')),
        'fqi3_free_trials_per_day' => '3',
        'fqi3_number_questions_per_quiz' => '10',
        'fqi3_text_pre_form' => '<h1>Bienvenue au quiz de <span style="color:#D9D0C6; font-weight: bold;">' . get_bloginfo('name') . '</span></h1><p>QCMs aléatoires pour t’entrainer gratuitement !</p>',
        'fqi3_color_text_pre_form' => '#393A3A',
        'fqi3_color_text_top_question' => '#ffffff',
        'fqi3_color_bg_top_question' => '#393A3A',
        'fqi3_color_text_btn' => '#ffffff',
        'fqi3_color_bg_btn' => '#0D0D0D',
        'fqi3_email_hour' => '08:00',
        'fqi3_email_link_cta' => absint(get_option('page_on_front')),
        'fqi3_email_cta_label' => __('Go to the site', 'form-quizz-fqi3'),
        'fqi3_email_cta_color_text' => '#ffffff',
        'fqi3_email_cta_color_bg' => '#0D0D0D',
    ];
}

/**
 * Retrieves the list of premium user roles.
 *
 * This function returns an array of user roles that are considered premium.
 * It uses the constant ROLE_PREMIUM to ensure consistency in referencing the premium member role.
 * It can be used to check whether a user has the necessary role to access certain premium features.
 *
 * @return array List of premium user roles.
 * 
 * @since 1.3.0
 */
function fqi3_getUserPremiumRoles() {
    return [ROLE_PREMIUM, 'administrator'];
}

/**
 * Checks if a user has any of the specified roles.
 *
 * @param int $user_id The user ID to check.
 * @param array $roles An array of roles to check against.
 * 
 * @return bool True if the user has at least one of the specified roles, false otherwise.
 * 
 * @since 1.3.0
 */
function fqi3_userHasAnyRole($user_id, $roles) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }

    foreach ($roles as $role) {
        if (user_can($user, $role)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if the current admin page is one of the plugin's admin pages.
 *
 * @param string $screen_id The current admin page hook.
 * @return bool True if the current page is a plugin page, false otherwise.
 * 
 * @since 1.3.2
 */
function is_plugin_admin_page($screen_id) {
    // Get the slugs from the backend class
    $plugin_page_slugs = fqi3_get_admin_pages();
    $plugin_pages = array_map(function($page) {
        return 'form-quizz-fqi3_page_' . $page['slug'];
    }, $plugin_page_slugs);

    return in_array($screen_id, $plugin_pages, true);
}

/**
 * Get FQI3 plugin options.
 *
 * This function retrieves the FQI3 plugin options from the database for the specified option name. 
 * If no option name is provided, it defaults to retrieving 'fqi3_options'.
 *
 * @param string $option_name The name of the option to retrieve. Default is 'fqi3_options'.
 * @return array The array of plugin options, or an empty array if the option does not exist.
 * 
 * @since 1.4.0 .
 * @since 1.5.0 Added the ability to specify an option name, defaulting to 'fqi3_options'.
 */
function fqi3_get_options($option_name = 'fqi3_options') {
    return get_option($option_name, []);
}

/**
 * Set default options for the FQI3 plugin.
 *
 * This function sets default options for the specified option name in the database. 
 * It merges the provided default options with the current options and updates the database 
 * only if there are differences.
 *
 * @param array $default_options The default options to set.
 * @param string $option_name The name of the option to update in the database.
 * 
 * @since 1.4.0 .
 * @since 1.5.0 Added the ability to specify an option name for updating.
 * @since 2.1.0 Optimized options sanitization and merged default options with current options.
 */
function fqi3_set_default_options($default_options, $option_name) {
    // Retrieve the current options or initialize an empty array if none exist
    $current_options = fqi3_get_options($option_name);

    // If the current options are not an array, initialize them as an empty array
    if (!is_array($current_options)) {
        $current_options = [];
    }
    $default_options = array_map(function($value) {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return esc_url_raw($value);
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return sanitize_email($value);
        }
        return sanitize_text_field($value);
    }, $default_options);
    $current_options = array_map(function($value) {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return esc_url_raw($value);  // Pour les URL
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return sanitize_email($value);  // Pour les emails
        }
        return sanitize_text_field($value);  // Pour les autres types (texte)
    }, $current_options);
    // Merge the default options with the current options
    $new_options = array_merge($default_options, $current_options);

    // If the new options differ from the current options, update the database
    if ($new_options !== $current_options) {
        update_option($option_name, $new_options, false);
    }
}