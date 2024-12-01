<?php
/**
 * FQI3_General_Settings Class
 *
 * This class handles the general settings of the FQI3 plugin.
 * It provides functionality for managing various plugin configurations.
 *
 * @package    Form Quizz FQI3
 * @subpackage Settings
 * @since      1.4.0
 * @version    1.4.0
 */

namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'FQI3_General_Settings' ) ) :

class FQI3_General_Settings {
    public function register_settings() {
        $this->add_settings_section();
    }

    public function add_settings_section() {
        $settings = fqi3_options_settings_sections();
        $sections = $settings['sections']['general_settings'];
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

        $this->add_general_settings_fields();
    }

    public function add_general_settings_fields() {
        add_settings_field(
            'fqi3_delete_data',
            __('Delete data on uninstall', 'form-quizz-fqi3'),
            [ $this, 'render_delete_data_field' ],
            fqi3_get_options_page_slug(),
            'fqi3_general_section'
        );
        add_settings_field(
            'fqi3_disable_sharing',
            __('Social sharing of results', 'form-quizz-fqi3'),
            [ $this, 'render_disable_sharing_field' ],
            fqi3_get_options_page_slug(),
            'fqi3_general_section'
        );
        add_settings_field(
            'fqi3_disable_statistics',
            __('Disable statistics', 'form-quizz-fqi3'),
            [ $this, 'render_disable_statistics' ],
            fqi3_get_options_page_slug(),
            'fqi3_general_section'
        );
        add_settings_field(
            'fqi3_sales_page',
            __('Link to the sales page', 'form-quizz-fqi3'),
            [ $this, 'render_link_sales_page_field' ],
            fqi3_get_options_page_slug(),
            'fqi3_general_section'
        );
        add_settings_field(
            'fqi3_free_trials_per_day',
            __('Number of free trials per day', 'form-quizz-fqi3'),
            [ $this, 'render_free_trials_per_day' ],
            fqi3_get_options_page_slug(),
            'fqi3_general_section'
        );
        add_settings_field(
            'fqi3_number_questions_per_quiz',
            __('Number of questions per quiz', 'form-quizz-fqi3'),
            [ $this, 'render_number_questions_per_quiz' ],
            fqi3_get_options_page_slug(),
            'fqi3_general_section'
        );
    }

    /**
     * Renders the checkbox field for deleting data on plugin uninstall.
     * 
     * Displays a checkbox that allows the user to select whether to delete plugin data upon uninstallation.
     * The checkbox value is stored in the 'fqi3_options' option.
     */
    public function render_delete_data_field() {
        $options = fqi3_get_options();
        $checked = isset($options['fqi3_delete_data']) && $options['fqi3_delete_data'] ? 'checked' : '';
        
        echo '<div class="form-check form-switch">';
        echo '<input class="form-check-input" type="checkbox" id="fqi3_delete_data" name="fqi3_options[fqi3_delete_data]" value="1" ' . esc_attr($checked) . '>';
        echo '<label class="form-check-label" for="fqi3_delete_data">' . esc_html__('Check this to delete data on uninstall', 'form-quizz-fqi3') . '</label>';
        echo '</div>';
    }

    /**
     * Renders the checkbox field to disable social sharing for logged-in users.
     * 
     * Displays a checkbox that allows the user to disable the social sharing feature for logged-in users.
     * The checkbox value is stored in the 'fqi3_options' option.
     * 
     * @since 1.2.0
     */
    public function render_disable_sharing_field() {
        $options = fqi3_get_options();
        $checked = isset($options['fqi3_disable_sharing']) && $options['fqi3_disable_sharing'] ? 'checked' : '';
        
        echo '<div class="form-check form-switch">';
        echo '<input class="form-check-input" type="checkbox" id="fqi3_disable_sharing" name="fqi3_options[fqi3_disable_sharing]" value="1" ' . esc_attr($checked) . '>';
        echo '<label class="form-check-label" for="fqi3_disable_sharing">' . esc_html__('Disable social sharing of results', 'form-quizz-fqi3') . '</label>';
        echo '</div>';
        echo '<p>' . esc_html__('Note: It is an option only available for logged in users. Check this to disable the social sharing feature for users.', 'form-quizz-fqi3') . '</p>';
    }

    /**
     * Renders the checkbox option to disable the results statistics feature.
     * 
     * This method displays a checkbox input field in the plugin settings page
     * which allows the administrator to enable or disable the statistics feature
     * for members. When checked, the statistics feature will be disabled for users
     * who have access to the quiz.
     * 
     * @since 1.3.0
     */
    public function render_disable_statistics() {
        $options = fqi3_get_options();
        $checked = isset($options['fqi3_disable_statistics']) && $options['fqi3_disable_statistics'] ? 'checked' : '';
        
        echo '<div class="form-check form-switch">';
        echo '<input class="form-check-input" type="checkbox" id="fqi3_disable_statistics" name="fqi3_options[fqi3_disable_statistics]" value="1" ' . esc_attr($checked) . '>';
        echo '<label class="form-check-label" for="fqi3_disable_statistics">' . esc_html__('Disable results statistics', 'form-quizz-fqi3') . '</label>';
        echo '</div>';
        echo '<p>' . wp_kses(__('Check this option to disable saving statistics to the database and displaying them to the user.<br>Note: Saving statistics to the database and displaying frontend statistics are options valid only for member users.', 'form-quizz-fqi3'), ['br' => []]) . '</p>';
    }

    /**
     * Renders the dropdown for selecting a sales page.
     * 
     * Displays a dropdown that allows the user to select a page for the sales link.
     * If no page is selected, the default value will be the homepage.
     */
    public function render_link_sales_page_field() {
        $pages = get_pages();
        $options = fqi3_get_options();
        $selected_page = isset($options['fqi3_sales_page']) ? $options['fqi3_sales_page'] : get_option('page_on_front');

        echo '<select class="form-select form-select-sm" name="fqi3_options[fqi3_sales_page]" id="fqi3_sales_page">';
        echo '<option value="" disabled selected>' . esc_html('Select a page', 'form-quizz-fqi3') . '</option>';

        foreach ($pages as $page) {
            $selected = $page->ID == $selected_page ? 'selected' : '';
            echo '<option value="' . esc_attr($page->ID) . '" ' . esc_attr($selected) . '>' . esc_html($page->post_title) . '</option>';
        }

        echo '</select>';
        echo '<p>' . esc_html__('Choose the page to link to for sales.', 'form-quizz-fqi3') . '</p>';
    }

    /**
     * Renders the number input field for free trials per day.
     * 
     * Displays a number input field that allows the user to specify how many free trials per day are allowed.
     * Default value is 3.
     */
    public function render_free_trials_per_day() {
        $options = fqi3_get_options();
        $value = isset($options['fqi3_free_trials_per_day']) && $options['fqi3_free_trials_per_day'] !== '' ? $options['fqi3_free_trials_per_day'] : '3'; // Valeur par défaut: 3

        echo '<div class="form-group mb-3 d-flex flex-column">';
        echo '<label for="fqi3_free_trials_per_day" class="form-label">' . __('Number of free trials per day:', 'form-quizz-fqi3') . '</label>';
        echo '<div class="input-group input-group-sm">';
        echo '<input type="number" id="fqi3_free_trials_per_day" name="fqi3_options[fqi3_free_trials_per_day]" value="' . esc_attr($value) . '" min="1" step="1" class="form-control-sm w-auto" />';
        echo '<span class="input-group-text pb-0 pt-0 text-secondary" id="basic-addon2">' . __('times a day', 'form-quizz-fqi3') . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<p>' . esc_html__('Specify how many free trials are allowed per day (default is 3).', 'form-quizz-fqi3') . '</p>';
    }
    

    /**
     * Renders the number input field for questions per quiz.
     * 
     * Displays a number input field that allows the user to specify the number of questions per quiz.
     * Default value is 10, and the minimum is 4.
     */
    public function render_number_questions_per_quiz() {
        $options = fqi3_get_options();
        $value = isset($options['fqi3_number_questions_per_quiz']) && $options['fqi3_number_questions_per_quiz'] !== '' ? $options['fqi3_number_questions_per_quiz'] : '10'; // Valeur par défaut: 10

        echo '<div class="form-group mb-3 d-flex flex-column">';
        echo '<label for="fqi3_number_questions_per_quiz" class="form-label">' . __('Number of questions per quiz:', 'form-quizz-fqi3') . '</label>';
        echo '<div class="input-group input-group-sm">';
        echo '<input type="number" id="fqi3_number_questions_per_quiz" name="fqi3_options[fqi3_number_questions_per_quiz]" value="' . esc_attr($value) . '" min="4" step="1" class="form-control-sm w-auto" />';
        echo '<span class="input-group-text pb-0 pt-0 text-secondary" id="basic-addon2">' . __('questions per quiz', 'form-quizz-fqi3') . '</span>';
        echo '</div>'; // End input-group
        echo '</div>';
        echo '<p>' . esc_html__('Minimum 4 questions', 'form-quizz-fqi3') . '</p>';
    }

    /**
     * Sanitize the plugin options before saving them to the database.
     * 
     * @param array $input The input values to sanitize.
     * @return array The sanitized values.
     * 
     * @since 1.4.0
     */
    public static function sanitize_general_settings_options($input) {
        $sanitized_input = [];

        $sanitized_input['fqi3_delete_data'] = !empty($input['fqi3_delete_data']) ? 1 : 0;
        $sanitized_input['fqi3_disable_sharing'] = !empty($input['fqi3_disable_sharing']) ? 1 : 0;
        $sanitized_input['fqi3_disable_statistics'] = !empty($input['fqi3_disable_statistics']) ? 1 : 0;
        
        $sanitized_input['fqi3_sales_page'] = isset($input['fqi3_sales_page']) ? absint($input['fqi3_sales_page']) : '';
        $sanitized_input['fqi3_free_trials_per_day'] = isset($input['fqi3_free_trials_per_day']) ? absint($input['fqi3_free_trials_per_day']) : 3;
        $sanitized_input['fqi3_number_questions_per_quiz'] = isset($input['fqi3_number_questions_per_quiz']) ? absint($input['fqi3_number_questions_per_quiz']) : 10;

        return $sanitized_input;
    }
}

endif;