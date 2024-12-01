<?php
/**
 * FQI3_Content_Settings Class
 *
 * This class manages content settings for the FQI3 plugin.
 * It provides options for customizing the content displayed by the plugin.
 *
 * @package    Form Quizz FQI3
 * @subpackage Settings
 * @since      1.4.0
 * @version    1.4.0
 */

namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'FQI3_Content_Settings' ) ) :

class FQI3_Content_Settings {
    public function register_settings() {
        $this->add_settings_section();
    }

    public function add_settings_section() {
        $settings = fqi3_options_settings_sections();
        $sections = $settings['sections']['content'];
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

        $this->add_content_all_fields();
    }
    
    public function add_content_all_fields() {
        add_settings_field(
            'fqi3_rtl_mode',
            __('RTL mode for table', 'form-quizz-fqi3'),
            [$this, 'render_rtl_mode'],
            fqi3_get_options_page_slug(),
            'fqi3_content_section'
        );
        $this->add_content_fields();
    }

    /**
     * Renders the checkbox field for enabling RTL mode.
     * 
     * Displays a checkbox that allows the user to enable RTL (right-to-left) mode.
     * The checkbox value is stored in the 'fqi3_options' option.
     * 
     * @since 1.2.0
     */
    public function render_rtl_mode() {
        $options = fqi3_get_options();
        $checked = isset($options['fqi3_rtl_mode']) && $options['fqi3_rtl_mode'] ? 'checked' : '';
 
        echo '<div class="form-check form-switch mb-3">';
        echo '<input class="form-check-input" type="checkbox" id="fqi3_rtl_mode" name="fqi3_options[fqi3_rtl_mode]" value="1" ' . esc_attr($checked) . '>';
        echo '<label class="form-check-label" for="fqi3_rtl_mode">' . esc_html__('Enable RTL (Right-to-Left) mode', 'form-quizz-fqi3') . '</label>';
        echo '</div>';
        echo '<p class="text-muted">' . esc_html__('Check this to activate RTL layout for languages like Arabic or Hebrew for the question overview table.', 'form-quizz-fqi3') . '</p>';
    } 

    /**
     * Adds fields to the "Content Options" section of the options page.
     * 
     * This method defines various settings fields for the content options section, including text areas and color pickers.
     * The fields are populated with default values and are rendered using the `render_content_fields` method.
     */
    public function add_content_fields() {
        $default_options = fqi3_default_options();
    
        $fields = [
            'fqi3_text_pre_form' => [
            'label' => __('Title/Description Pre Form Text', 'form-quizz-fqi3'),
            'type' => 'textarea',
            'default' => $default_options['fqi3_text_pre_form']
            ],
            'fqi3_color_text_pre_form' => [
                'label' => __('Title/Description Pre Form Color', 'form-quizz-fqi3'),
                'type' => 'color',
                'default' => $default_options['fqi3_color_text_pre_form']
            ],
            'fqi3_color_text_top_question' => [
                'label' => __('Top Question Text Color', 'form-quizz-fqi3'),
                'type' => 'color',
                'default' => $default_options['fqi3_color_text_top_question']
            ],
            'fqi3_color_bg_top_question' => [
                'label' => __('Top Question Background Color', 'form-quizz-fqi3'),
                'type' => 'color',
                'default' => $default_options['fqi3_color_bg_top_question']
            ],
            'fqi3_color_text_btn' => [
                'label' => __('Button Text Color', 'form-quizz-fqi3'),
                'type' => 'color',
                'default' => $default_options['fqi3_color_text_btn']
            ],
            'fqi3_color_bg_btn' => [
                'label' => __('Button Background Color', 'form-quizz-fqi3'),
                'type' => 'color',
                'default' => $default_options['fqi3_color_bg_btn']
            ],
        ];
    
        foreach ($fields as $id => $field) {
            add_settings_field(
                $id,
                $field['label'],
                [$this, 'render_content_fields'],
                'fqi3-options-quiz-questions',
                'fqi3_content_section',
                ['id' => $id, 'label' => $field['label'], 'type' => $field['type'], 'default' => $field['default']]
            );
        }
    }

     /**
     * Renders an input field for plugin options based on the provided arguments.
     * 
     * This method dynamically generates form fields based on the specified type. It supports text inputs, color pickers, and textareas.
     * It also provides a reset button to revert to default values if specified.
     * 
     * @param array $args Arguments for rendering the field. Expected keys are 'id' (field ID), 'type' (field type), and 'default' (default value).
     */
    public function render_content_fields($args) { 
        $options = fqi3_get_options();
        $value = isset($options[$args['id']]) ? esc_attr($options[$args['id']]) : '';
        $default_value = isset($args['default']) ? esc_attr($args['default']) : '';
    
        $type = isset($args['type']) ? $args['type'] : 'text';
        switch ($type) {
            case 'color':
                echo '<div class="mb-3">';
                echo '<label for="' . esc_attr($args['id']) . '" class="form-label visually-hidden">' . esc_html($args['label']) . '</label>';
                echo '<input type="color" name="fqi3_options[' . esc_attr($args['id']) . ']" class="form-control form-control-color" value="' . esc_attr($value) . '" id="' . esc_attr($args['id']) . '" />';
                if ($default_value) {
                    echo ' <div class="form-text text-muted">' . esc_html__('Default:', 'form-quizz-fqi3') . ' ' . esc_html($default_value) . '</div>';
                    echo ' <button type="button" class="btn btn-secondary btn-sm mt-2 reset-button" data-id="' . esc_attr($args['id']) . '" data-default="' . esc_attr($default_value) . '">' . esc_html__('Reset', 'form-quizz-fqi3') . '</button>';
                }
                echo '</div>';
                break;
            case 'textarea':
                echo '<div class="mb-3">';
                echo '<label for="' . esc_attr($args['id']) . '" class="form-label visually-hidden">' . esc_html($args['label']) . '</label>';
                echo '<textarea name="fqi3_options[' . esc_attr($args['id']) . ']" id="' . esc_attr($args['id']) . '" class="form-control" rows="5" cols="50">' . esc_html($value) . '</textarea>';
                
                if ($default_value) {
                    echo ' <div class="form-text text-muted mt-2">' . esc_html__('Default:', 'form-quizz-fqi3') . ' ' . esc_html($default_value) . '</div>';
                    echo ' <button type="button" class="btn btn-secondary btn-sm mt-2 reset-button" data-id="' . esc_attr($args['id']) . '" data-default="' . esc_attr($default_value) . '">' . esc_html__('Reset', 'form-quizz-fqi3') . '</button>';
                }
                echo '</div>';                
                break;
            default:
                echo '<div class="mb-3">';
                echo '<label for="' . esc_attr($args['id']) . '" class="form-label visually-hidden">' . esc_html($args['label']) . '</label>';
                echo '<input type="text" name="fqi3_options[' . esc_attr($args['id']) . ']" id="' . esc_attr($args['id']) . '" class="form-control" value="' . esc_attr($value) . '" />';
                
                if ($default_value) {
                    echo ' <div class="form-text text-muted mt-2">' . esc_html__('Default:', 'form-quizz-fqi3') . ' ' . esc_html($default_value) . '</div>';
                    echo ' <button type="button" class="btn btn-secondary btn-sm mt-2 reset-button" data-id="' . esc_attr($args['id']) . '" data-default="' . esc_attr($default_value) . '">' . esc_html__('Reset', 'form-quizz-fqi3') . '</button>';
                }
                echo '</div>';            
                break;
        }
    }

     /**
     * Sanitize the plugin options before saving them to the database.
     * 
     * @param array $input The input values to sanitize.
     * @return array The sanitized values.
     * 
     * @since 1.4.0
     */
    public static function sanitize_content_options($input) { 
        $sanitized_input = [];
    
        // Textarea
        $sanitized_input['fqi3_text_pre_form'] = isset($input['fqi3_text_pre_form']) ? wp_kses_post($input['fqi3_text_pre_form']) : '<h1>Bienvenue au quiz de <span style="color:#D9D0C6; font-weight: bold;">' . get_bloginfo('name') . '</span></h1>';
    
        // Input color)
        $sanitized_input['fqi3_color_text_pre_form'] = isset($input['fqi3_color_text_pre_form']) ? sanitize_hex_color($input['fqi3_color_text_pre_form']) : '#393A3A';
        $sanitized_input['fqi3_color_text_top_question'] = isset($input['fqi3_color_text_top_question']) ? sanitize_hex_color($input['fqi3_color_text_top_question']) : '#ffffff';
        $sanitized_input['fqi3_color_bg_top_question'] = isset($input['fqi3_color_bg_top_question']) ? sanitize_hex_color($input['fqi3_color_bg_top_question']) : '#393A3A';
        $sanitized_input['fqi3_color_text_btn'] = isset($input['fqi3_color_text_btn']) ? sanitize_hex_color($input['fqi3_color_text_btn']) : '#ffffff';
        $sanitized_input['fqi3_color_bg_btn'] = isset($input['fqi3_color_bg_btn']) ? sanitize_hex_color($input['fqi3_color_bg_btn']) : '#0D0D0D';

         // RTL Mode
        $sanitized_input['fqi3_rtl_mode'] = isset($input['fqi3_rtl_mode']) ? 1 : 0;
    
        return $sanitized_input;
    } 
}
endif;