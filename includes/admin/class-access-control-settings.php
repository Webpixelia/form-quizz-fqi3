<?php
/**
 * FQI3_Access_Control_Settings Class
 *
 * This class manages access control settings for the FQI3 plugin.
 * It allows configuration of user permissions and access levels.
 *
 * @package    Form Quizz FQI3
 * @subpackage Settings
 * @since      1.4.0
 * @version    1.4.0
 */

namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'FQI3_Access_Control_Settings' ) ) :

class FQI3_Access_Control_Settings {
    public function register_settings() {
        //$this->register_fields_settings();
        $this->add_settings_section();
    }

    public function add_settings_section() {
        $settings = fqi3_options_settings_sections();
        $sections = $settings['sections']['access_control'];
        add_settings_section(
            $sections['id'],
            $sections['title'],
            array($this, 'access_section_callback'),
            fqi3_get_options_page_slug(),
            array(
                'before_section' => '<div id="' . esc_attr(array_search($sections, $settings['sections'])) .'" class="fqi3-section-options-page">',
                'after_section' => '</div>'
            )
        );

        $this->add_access_control_settings_fields();
    }

    /**
     * Outputs a description for the Access Control section.
     *
     * Explains that roles with the capability to publish posts
     * can manage questions, while only admins can access settings.
     *
     * @since 1.4.0
     */
    public function access_section_callback() {
        echo '<p>' . esc_html__(
            'This access control system allows to define granular roles and permissions for different functionalities of the platform',
            'form-quizz-fqi3'
        ) . '</p>';
    }

    public function add_access_control_settings_fields() {
        add_settings_field(
            'fqi3_access_roles_admin',
            __('Who can manage questions?', 'form-quizz-fqi3'),
            [ $this, 'render_access_roles_field' ],
            fqi3_get_options_page_slug(),
            'fqi3_access_section'
        );
        add_settings_field(
            'fqi3_admin_premium_features',
            __('Grant premium features to administrators?', 'form-quizz-fqi3'),
            [ $this, 'render_premium_features_admin_field' ],
            fqi3_get_options_page_slug(),
            'fqi3_access_section'
        );
    }

    /**
     * Renders the access roles field for the options page.
     * 
     * Displays checkboxes for selecting which user roles can manage questions. The value is stored as a subkey within the 'fqi3_access_roles' option in the database.
     * 
     * @since 1.1.0
    */
    public function render_access_roles_field() {
        $roles = wp_roles()->roles;
        $roles_with_publish_posts = [];
    
        foreach ($roles as $role => $details) {
            // Exclude administrator
            if ($role === 'administrator') {
                continue;
            }
    
            // Add roles that have the 'publish_posts' capability
            if (isset($details['capabilities']['publish_posts']) && $details['capabilities']['publish_posts']) {
                $roles_with_publish_posts[$role] = translate_user_role($details['name']);
            }
        }
    
        // Retrieves the roles selected in the options
        $options = get_option('fqi3_access_roles', []);
        $selected_roles = isset($options['fqi3_access_roles_admin']) && is_array($options['fqi3_access_roles_admin']) ? $options['fqi3_access_roles_admin'] : [];
        if (!is_array($selected_roles)) {
            $selected_roles = [];
        }

        echo '<p>' . esc_html__('This feature allows specific roles with at least the capability to publish posts to access the question management (viewing, editing, and deleting questions). However, access to plugin settings is restricted to administrators only.', 'form-quizz-fqi3') . '</p>';
    
        // Generating the field with the roles that have the 'publish_posts' capability except the administrator
        echo '<div class="mb-3 mt-3">';
        foreach ($roles_with_publish_posts as $role => $label) {
            $checked = in_array($role, $selected_roles) ? 'checked' : '';
            $disabled = ($role === 'administrator') ? 'disabled' : '';

            echo '<div class="form-check form-switch">';
            echo '<input class="form-check-input" type="checkbox" name="fqi3_access_roles[fqi3_access_roles_admin][]" value="' . esc_attr($role) . '" ' . esc_attr($checked) . ' id="role_' . esc_attr($role) . '">';
            echo '<label class="form-check-label" for="role_' . esc_attr($role) . '">';
            echo esc_html($label);
            echo '</label>';
            echo '</div>';          
        }
        echo '</div>';

        echo '<p class="text-muted">' . esc_html__('Please note: The Administrator role cannot be disabled and always has access to plugin settings.', 'form-quizz-fqi3') . '</p>';
    }
    
    /**
     * Renders the grant premium features to administrators field for the options page.
     * 
     * The value is stored as a subkey within the 'fqi3_access_roles' option in the database.
     * 
     * @since 2.2.0
    */
    public function render_premium_features_admin_field() {
        $options = get_option('fqi3_access_roles', []);
        $checked = isset($options['fqi3_admin_premium_features']) && $options['fqi3_admin_premium_features'] === '1' ? 'checked' : '';
        
        echo '<div class="form-check form-switch">';
        echo '<input class="form-check-input" type="checkbox" id="fqi3_admin_premium_features" name="fqi3_access_roles[fqi3_admin_premium_features]" value="1" ' . esc_attr($checked) . '>';
        echo '<label class="form-check-label" for="fqi3_admin_premium_features">' . esc_html__('Check to grant premium features to administrators', 'form-quizz-fqi3') . '</label>';
        echo '</div>';
    }

    /**
     * Sanitizes the roles input to ensure only allowed roles are saved.
     * 
     * This method ensures that the input for roles is an array, sanitizes each role, 
     * and filters the roles to include only those that have the 'publish_posts' capability.
     * 
     * @param array $roles The input roles to be sanitized.
     * @return array The sanitized and filtered roles, including only those allowed to publish posts.
     * 
     * @since 1.4.0 Updated to dynamically filter allowed roles based on 'publish_posts' capability.
     * @since 1.1.0 
     */
    public static function or_sanitize_roles($roles) {
        if (!is_array($roles)) {
            $roles = array();
        }
        $roles = array_map('sanitize_text_field', $roles);
    
        // Get all roles with the 'publish_posts' capability
        $all_roles = wp_roles()->roles;
        $allowed_roles = [];
        
        foreach ($all_roles as $role => $details) {
            if (isset($details['capabilities']['publish_posts']) && $details['capabilities']['publish_posts']) {
                $allowed_roles[] = $role;
            }
        }
    
        // Filter input roles to include only those that are allowed
        $roles = array_intersect($roles, $allowed_roles);
    
        return $roles;
    }
    public static function sanitize_roles($input) {
        // Initialise la structure de données attendue
        $sanitized = [
            'fqi3_access_roles_admin' => [],
            'fqi3_admin_premium_features' => '0',
        ];
    
        // Vérifier si `fqi3_access_roles_admin` est présent et est un tableau
        if (isset($input['fqi3_access_roles_admin']) && is_array($input['fqi3_access_roles_admin'])) {
            $roles = array_map('sanitize_text_field', $input['fqi3_access_roles_admin']);
            
            // Récupérer tous les rôles avec la capacité 'publish_posts'
            $all_roles = wp_roles()->roles;
            $allowed_roles = [];
            
            foreach ($all_roles as $role => $details) {
                if (isset($details['capabilities']['publish_posts']) && $details['capabilities']['publish_posts']) {
                    $allowed_roles[] = $role;
                }
            }
    
            // Filtrer les rôles pour inclure uniquement ceux qui sont autorisés
            $sanitized['fqi3_access_roles_admin'] = array_intersect($roles, $allowed_roles);
        }
    
        // Vérifier si `fqi3_admin_premium_features` est défini et le sanitiser
        if (isset($input['fqi3_admin_premium_features']) && $input['fqi3_admin_premium_features'] === '1') {
            $sanitized['fqi3_admin_premium_features'] = '1';
        }
    
        return $sanitized;
    }
    
}

endif;