<?php
/**
 * FQI3_Badges_Settings Class
 *
 * This class handles the badges settings of the FQI3 plugin.
 * It allows customization of badges content and settings.
 *
 * @package    Form Quizz FQI3
 * @subpackage Settings
 * @since      1.5.0
 * @version    1.5.0
 */

namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'FQI3_Badges_Settings' ) ) :

class FQI3_Badges_Settings {

    public function register_settings() {
        $this->register_fields_settings();
        $this->add_settings_section();
    }

    public function register_fields_settings () {
        register_setting(
            'fqi3_options_group',
            'fqi3_badges',
            [ $this, 'sanitize_badges_options' ]
        );
    }

    public function add_settings_section() {
        $settings = fqi3_options_settings_sections();
        $sections = $settings['sections']['badges'];
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

        $this->add_badges_settings_fields();
    }

    public function add_badges_settings_fields() {
        add_settings_field(
            'fqi3_disable_badges',
            __('Disable badges', 'form-quizz-fqi3'),
            [$this, 'render_disable_badges'],
            fqi3_get_options_page_slug(),
            'fqi3_badges_section'
        );
        add_settings_field(
            'fqi3_disable_badges_legend',
            __('Disable badges legend', 'form-quizz-fqi3'),
            [$this, 'render_disable_badges_legend'],
            fqi3_get_options_page_slug(),
            'fqi3_badges_section'
        );
        add_settings_field(
            'fqi3_completion_badges',
            __('Quiz Completion Badge Level', 'form-quizz-fqi3'),
            [$this, 'render_quiz_completion_badge_level'],
            fqi3_get_options_page_slug(),
            'fqi3_badges_section'
        );
        add_settings_field(
            'fqi3_success_rate_badges',
            __('Success Rate Badge Level', 'form-quizz-fqi3'),
            [$this, 'render_success_rate_badge_level'],
            fqi3_get_options_page_slug(),
            'fqi3_badges_section'
        );
    }

    /**
     * Renders the checkbox option to disable badges system.
     * 
     * This method displays a checkbox input field on the plugin settings page
     * that allows the administrator to enable or disable badges system.
     * When checked, badges system will be disabled.
     * 
     * @since 1.5.0
     */
    public function render_disable_badges() {
        $badges_options = get_option('fqi3_badges', array());
        $checked = isset($badges_options['fqi3_disable_badges']) && $badges_options['fqi3_disable_badges'] ? 'checked' : '';

        
        echo '<div class="form-check form-switch">';
        echo '<input class="form-check-input" type="checkbox" name="fqi3_badges[fqi3_disable_badges]" value="1" ' . esc_attr($checked) . '>';
        echo '<label class="form-check-label">' . esc_html__('Disable badges', 'form-quizz-fqi3') . '</label>';
        echo '</div>';
        echo '<p>' . esc_html__('Check this to disable the badges system.', 'form-quizz-fqi3') . '</p>';
        echo '<p style="color:red;">' . esc_html__('Please note that this option only works if statistics are not disabled in general settings.', 'form-quizz-fqi3') . '</p>';
    }

    /**
     * Renders the checkbox option to disable the display of badges legend.
     * 
     * This method displays a checkbox input field on the plugin settings page
     * that allows the administrator to enable or disable the display of the badges legend.
     * When this option is checked, the badge legend is not displayed to users.
     * 
     * @since 1.5.1
     */
    public function render_disable_badges_legend() {
        $badges_options = get_option('fqi3_badges', array());
        $checked = isset($badges_options['fqi3_disable_badges_legend']) && $badges_options['fqi3_disable_badges_legend'] ? 'checked' : '';

        
        echo '<div class="form-check form-switch">';
        echo '<input class="form-check-input" type="checkbox" name="fqi3_badges[fqi3_disable_badges_legend]" value="1" ' . esc_attr($checked) . '>';
        echo '<label class="form-check-label">' . esc_html__('Disable badges legend', 'form-quizz-fqi3') . '</label>';
        echo '</div>';
        echo '<p>' . esc_html__('Check this to disable the display of the badges legend below the user earned badges display section.', 'form-quizz-fqi3') . '</p>';
    }

    /**
     * Renders badge level fields for quiz completion or success rate badges dynamically.
     *
     * This function generates the HTML form fields for defining thresholds, badge names,
     * and badge images associated with different levels of quiz completion or success rate badges.
     * It dynamically handles any number of badge levels based on the provided count and allows
     * for new badges to be added via JavaScript using a predefined hidden template.
     *
     * @param string $section_title   The title of the badge section (e.g., "Quiz Completion Badge Level").
     * @param string $threshold_name  The key name used for badge thresholds in the options array.
     * @param string $badge_name      The key name used for badge names in the options array.
     * @param string $badge_image     The key name used for badge images in the options array.
     * @param int    $badge_count     The number of initial badge levels to render.
     *
     * @since 1.5.0
     *
     * The hidden badge template is used by JavaScript to dynamically add new badge levels when
     * the "Add Badge" button is clicked, enabling the creation of new badges on the fly.
     */
    public function render_badge_levels($section_title, $threshold_name, $badge_name, $badge_image, $badge_count) {
        $badges_options = get_option('fqi3_badges', array());

        echo '<div class="badges-container">';
        for ($i = 0; $i < $badge_count; $i++) {
            echo '<div class="badge-group">';
            echo '<p class="sub-section mb-3">' . esc_html__($section_title . ' ' . ($i + 1), 'form-quizz-fqi3') . '</p>';

            echo '<div class="group-sub-section mb-3">';
            echo '<div class="d-flex flex-row align-items-center">';
            echo '<label for="threshold_' . esc_attr($threshold_name) . '" class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Threshold:', 'form-quizz-fqi3') . '</label>';
            echo '<input type="number" id="threshold_' . esc_attr($threshold_name) . '_' . $i . '" name="fqi3_badges[' . esc_attr($threshold_name) . '][]" value="' . esc_attr($badges_options[$threshold_name][$i] ?? '') . '" class="form-control-sm">';
            echo '</div></div>';

            echo '<div class="group-sub-section mb-3">';
            echo '<div class="d-flex flex-row align-items-center">';
            echo '<label for="badge_name_' . esc_attr($badge_name) . '" class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Badge Name:', 'form-quizz-fqi3') . '</label>';
            echo '<input type="text" id="badge_name_' . esc_attr($badge_name) . '_' . $i . '" name="fqi3_badges[' . esc_attr($badge_name) . '][]" value="' . esc_attr($badges_options[$badge_name][$i] ?? '') . '" class="form-control-sm">';
            echo '</div></div>';

            echo '<div class="group-sub-section mb-3">';
            echo '<div class="d-flex flex-row align-items-center">';
            echo '<label for="badge_image_' . esc_attr($badge_image) . '" class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Badge Image:', 'form-quizz-fqi3') . '</label>';

            $image_id = $badges_options[$badge_image][$i] ?? '';
            if ($image = wp_get_attachment_image_url($image_id, 'medium')) :
                echo '<a href="#" class="button rudr-upload"><img src="' . esc_url($image) . '" style="max-width: 200px;" /></a>';
                echo '<a href="#" class="btn btn-warning btn-sm p-1 rudr-remove">' . esc_html__('Remove Image', 'form-quizz-fqi3') . '</a>';
                echo '<input type="hidden" name="fqi3_badges[' . esc_attr($badge_image) . '][]" value="' . absint($image_id) . '">';
            else :
                echo '<a href="#" class="button rudr-upload">' . esc_html__('Upload Image', 'form-quizz-fqi3') . '</a>';
                echo '<a href="#" class="btn btn-warning btn-sm p-1 rudr-remove" style="display:none">' . esc_html__('Remove Image', 'form-quizz-fqi3') . '</a>';
                echo '<input type="hidden" name="fqi3_badges[' . esc_attr($badge_image) . '][]" value="">';
            endif;

            echo '</div></div>';

            echo '<div class="d-flex justify-content-end">';
            echo '<button type="button" class="remove-badge btn btn-danger btn-sm p-1" data-index="' . esc_attr($i) . '">' . esc_html__('Remove this badge', 'form-quizz-fqi3') . '</button>';
            echo '</div>';

            echo '</div>';
        }
        echo '</div>';
    
        echo '<button type="button" class="add-badge btn btn-success btn-sm p-1" class="button">' . esc_html__('Add a badge', 'form-quizz-fqi3') . '</button>';

        echo '<div class="badge-template" style="display:none">';
        echo '<div class="badge-group">';
        echo '<p class="sub-section">' . esc_html__($section_title . ' ', 'form-quizz-fqi3') . '<span class="badge-level"></span></p>';
    
        echo '<div class="group-sub-section mb-3">';
        echo '<div class="d-flex flex-row align-items-center">';
        echo '<label class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Threshold:', 'form-quizz-fqi3') . '</label>';
        echo '<input type="number" name="fqi3_badges[' . esc_attr($threshold_name) . '][]" value="" class="form-control-sm">';
        echo '</div></div>';
    
        echo '<div class="group-sub-section mb-3">';
        echo '<div class="d-flex flex-row align-items-center">';
        echo '<label class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Badge Name:', 'form-quizz-fqi3') . '</label>';
        echo '<input type="text" name="fqi3_badges[' . esc_attr($badge_name) . '][]" value="" class="form-control-sm">';
        echo '</div></div>';
    
        echo '<div class="group-sub-section mb-3">';
        echo '<div class="d-flex flex-row align-items-center">';
        echo '<label class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Badge Image:', 'form-quizz-fqi3') . '</label>';
        echo '<a href="#" class="btn btn-success btn-sm p-1 rudr-upload">' . esc_html__('Upload Image', 'form-quizz-fqi3') . '</a>';
        echo '<a href="#" class="btn btn-warning btn-sm p-1 rudr-remove" style="display:none">' . esc_html__('Remove Image', 'form-quizz-fqi3') . '</a>';
        echo '<input type="hidden" name="fqi3_badges[' . esc_attr($badge_image) . '][]" value="">';
        echo '</div></div>';
    
        echo '<div class="d-flex justify-content-end">';
        echo '<button type="button" class="remove-badge btn btn-danger btn-sm p-1">' . esc_html__('Remove this badge', 'form-quizz-fqi3') . '</button>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }    

    /**
     * Renders the fields for quiz completion badge levels dynamically.
     *
     * This function generates the form fields needed to define thresholds, names, and images 
     * for different quiz completion badge levels. It dynamically handles the number of badge levels based 
     * on user input and provides an option to specify the minimum number of quizzes completed 
     * required to start earning these badges.
     *
     * @uses fqi3_render_badge_levels() to render the actual badge fields.
     * 
     * @since 1.5.0
     *
     * The number of badge levels is flexible and will dynamically reflect the number of badge thresholds defined in the options.
     */
    public function render_quiz_completion_badge_level() {
        $this->render_badge_levels(
            __('Quiz Completion Badge Level', 'form-quizz-fqi3'), 
            'fqi3_quizzes_completed_thresholds', 
            'fqi3_quizzes_completed_badge_names', 
            'fqi3_quizzes_completed_badge_images', 
            count(get_option('fqi3_badges')['fqi3_quizzes_completed_thresholds'] ?? [0])
        );
    }
    

    /**
    * Renders the fields for success rate badge levels dynamically.
    *
    * This function generates the form fields needed to define thresholds, names, and images 
    * for different success rate badge levels. It allows for a dynamic number of badge levels 
    * based on the current configuration and includes an input field to specify the minimum 
    * number of quizzes completed required to start earning these badges.
    *
    * @uses fqi3_render_badge_levels() to render the actual badge fields.
    *
    * @since 1.5.0
    *
    * The number of badge levels is flexible and will dynamically reflect the number of badge 
    * thresholds defined in the options.
    */
    public function render_success_rate_badge_level() {
        $badges_options = get_option('fqi3_badges', array());
        $min_quizzes_completed = $badges_options['fqi3_min_quizzes_for_success_rate'] ?? 20;

        echo '<div class="form-group mb-3 d-flex flex-column">';
        echo '<label for="min_quizzes_for_success_rate" class="form-label mb-0">' . esc_html__('Minimum quizzes completed to start assigning success rate badges:', 'form-quizz-fqi3') . '</label>';
        echo '<input type="number" id="min_quizzes_for_success_rate" name="fqi3_badges[fqi3_min_quizzes_for_success_rate]" value="' . esc_attr($min_quizzes_completed) . '" class="form-control-sm-sm w-auto" min="2">';
        echo '</div>';
        $this->render_badge_levels(
            __('Success Rate Badge Level', 'form-quizz-fqi3'),
            'fqi3_success_rate_thresholds', 
            'fqi3_success_rate_badge_names', 
            'fqi3_success_rate_badge_images', 
            count(get_option('fqi3_badges')['fqi3_success_rate_thresholds'] ?? [0])
        );
    }
    
    

    /**
     * Sanitize the plugin options before saving them to the database.
     * 
     * @param array $input The input values to sanitize.
     * @return array The sanitized values.
     * 
     * @since 1.5.0
     */
    public static function sanitize_badges_options($input) { 
        $sanitized_input = [];
    
        $sanitized_input['fqi3_disable_badges'] = isset($input['fqi3_disable_badges']) ? 1 : 0;
        $sanitized_input['fqi3_disable_badges_legend'] = isset($input['fqi3_disable_badges_legend']) ? 1 : 0;
    
        $sanitized_input['fqi3_quizzes_completed_thresholds'] = array_filter(array_map('absint', $input['fqi3_quizzes_completed_thresholds'] ?? []));
        $sanitized_input['fqi3_quizzes_completed_badge_names'] = array_filter(array_map('sanitize_text_field', $input['fqi3_quizzes_completed_badge_names'] ?? []));
        $sanitized_input['fqi3_quizzes_completed_badge_images'] = array_filter(array_map('absint', $input['fqi3_quizzes_completed_badge_images'] ?? []));

        $sanitized_input['fqi3_success_rate_thresholds'] = array_filter(array_map('absint', $input['fqi3_success_rate_thresholds'] ?? []));
        $sanitized_input['fqi3_success_rate_badge_names'] = array_filter(array_map('sanitize_text_field', $input['fqi3_success_rate_badge_names'] ?? []));
        $sanitized_input['fqi3_success_rate_badge_images'] = array_filter(array_map('absint', $input['fqi3_success_rate_badge_images'] ?? []));

        $sanitized_input['fqi3_min_quizzes_for_success_rate'] = absint($input['fqi3_min_quizzes_for_success_rate'] ?? 20);

        if (empty($sanitized_input['fqi3_quizzes_completed_thresholds']) ||
            empty($sanitized_input['fqi3_quizzes_completed_badge_names']) ||
            empty($sanitized_input['fqi3_quizzes_completed_badge_images']) ||
            empty($sanitized_input['fqi3_success_rate_thresholds']) ||
            empty($sanitized_input['fqi3_success_rate_badge_names']) ||
            empty($sanitized_input['fqi3_success_rate_badge_images'])) {
            return [];
        }
    
        return $sanitized_input;
    }    
}

endif;