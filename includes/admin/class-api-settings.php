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
 * @version    1.6.0
 */

namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'FQI3_Api_Settings' ) ) :
class FQI3_Api_Settings {
    public function register_settings() {
        $this->add_settings_section();
    }

    /*public function register_fields_settings() {
        register_setting(
            'fqi3_options_group',
            'fqi3_options',
            [ $this, 'sanitize_api_options' ]
        );
    }*/

    public function add_settings_section() {
        $settings = fqi3_options_settings_sections();
        $sections = $settings['sections']['api'];
        add_settings_section(
            $sections['id'],
            $sections['title'],
            null,
            fqi3_get_options_page_slug(),
            array(
                'before_section' => '<div id="' . esc_attr(array_search($sections, $settings['sections'])) .'" class="fqi3-section-options-page">',
                'after_section' => '</div>'
            )
        );

        $this->add_api_fields();
    }
    
    public function add_api_fields() {
        add_settings_field(
            'fqi3_quiz_api',
            __('Enable API Rest', 'form-quizz-fqi3'),
            [ $this, 'render_enable_api' ],
            fqi3_get_options_page_slug(),
            'fqi3_api_section'
        );
        add_settings_field(
            'fqi3_quiz_api_token',
            __('API Token', 'form-quizz-fqi3'),
            [$this, 'render_generate_token_button'],
            fqi3_get_options_page_slug(),
            'fqi3_api_section'
        );
    }

    public function render_enable_api() {
        $options = fqi3_get_options();
        $checked = isset($options['fqi3_quiz_api']) && $options['fqi3_quiz_api'] ? 'checked' : '';

        echo '<div class="form-check form-switch mb-3">';
        echo '<input class="form-check-input" type="checkbox" name="fqi3_options[fqi3_quiz_api]" role="switch" id="fqi3_quiz_api" value="1" ' . esc_attr($checked) . '>';
        echo '<label class="form-check-label" for="fqi3_quiz_api">' . esc_html__('Allow access to plugin data via the REST API', 'form-quizz-fqi3') . '</label>';
        echo '</div>';        
    }

    public function render_generate_token_button() {
        // Get existing token if any
        $options = fqi3_get_options();
        $current_token = isset($options['fqi3_quiz_api_token']) ? $options['fqi3_quiz_api_token'] : '';
        $api_token = isset($options['fqi3_quiz_api_token']) ? $options['fqi3_quiz_api_token'] : '';

        $has_token = !empty($current_token);

        $admin_pages = fqi3_get_admin_pages();
        $slugViewInfos = $admin_pages['infos']['slug'];
        $documentation_url = esc_url(admin_url('admin.php?page=' . $slugViewInfos));

        echo '<div class="token-wrapper">';
            // Generate Token Button
            echo '<div class="button-group mb-3">';
            echo '<button type="button" class="btn btn-warning btn-sm mt-2" id="generate-api-token">' . __('Generate Token', 'form-quizz-fqi3') . '</button>';
            echo '<button type="button" class="btn btn-danger btn-sm mt-2' . ($has_token ? '' : ' d-none') . '" id="revoke-api-token">' . 
                __('Revoke Token', 'form-quizz-fqi3') . '</button>';
            echo '</div>';

            echo '<p class="form-text text-muted">' . __('Click the button to generate a new API token.', 'form-quizz-fqi3') . '</p>';

            // Token Input Group
            echo '<div class="token-input-group d-flex align-items-center gap-3 mb-3">';
            echo '<input type="password" name="fqi3_options[fqi3_quiz_api_token]" id="fqi3_quiz_api_token" value="' . esc_attr($current_token) . '" readonly class="form-control form-control-sm wide-token-input" />';
            echo '<span class="toggle-password" style="cursor: pointer;" title="' . esc_attr__('Show/Hide Token', 'form-quizz-fqi3') . '"><i class="bi bi-eye"></i></span>';
            echo '<span class="copy-icon" data-shortcode="' . esc_attr($api_token) . '" style="cursor: pointer;" title="' . esc_attr__('Copy token', 'form-quizz-fqi3') . '"><i class="bi bi-copy"></i></span>';
            echo '</div>';
            
            echo '<p class="text-muted mb-2">' . __('The generated token will appear above. Click Save Settings to store it.', 'form-quizz-fqi3') . '</p>';
            echo '<p class="text-danger"><strong>' . __('Note:', 'form-quizz-fqi3') . '</strong> ' . __('Do not share your API token and ensure it is stored securely.', 'form-quizz-fqi3') . '</p>';            
            $link = sprintf(
                wp_kses(
                     /* translators: Guide page URL */
                    __('The API documentation can be found <a href="%s#api_doc" target="_blank" rel="noopener noreferrer">here</a>.', 'form-quizz-fqi3'),
                    array('a' => array('href' => array()))
                ),
                $documentation_url
            );
            echo '<p class="lead"><strong>' . $link . '</strong></p>';       
        echo '</div>';
    }
         

    public static function sanitize_api_options($input) { 
        $sanitized_input = [];
    
        $sanitized_input['fqi3_quiz_api'] = !empty($input['fqi3_quiz_api']) ? 1 : 0;

        if (isset($input['fqi3_quiz_api_token'])) {
            $sanitized_input['fqi3_quiz_api_token'] = sanitize_text_field($input['fqi3_quiz_api_token']);
        }
    
        return $sanitized_input;
    }
}
endif;