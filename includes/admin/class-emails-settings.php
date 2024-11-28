<?php
/**
 * FQI3_Emails_Settings Class
 *
 * This class handles the email settings of the FQI3 plugin.
 * It allows customization of email content and settings related to email delivery.
 *
 * @package    Form Quizz FQI3
 * @subpackage Settings
 * @since      1.4.0
 * @version    1.4.0
 */

namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'FQI3_Emails_Settings' ) ) :

class FQI3_Emails_Settings {
    public function __construct() {
        add_action('wp_ajax_fqi3_send_test_email', [$this, 'handle_test_email_submission']);
        add_action('admin_init', [$this, 'set_default_options_emails']); 
    }


    public function register_settings() {
        $this->add_settings_section();
    }

    /*public function register_fields_settings () {
        register_setting(
            'fqi3_options_group',
            'fqi3_options',
            [ $this, 'sanitize_emails_settings_options' ]
        );
    }*/

    public function add_settings_section() {
        $settings = fqi3_options_settings_sections();
        $sections = $settings['sections']['emails'];
        add_settings_section(
            'fqi3_emails_section',
            $sections['title'],
            null,
            fqi3_get_options_page_slug(),
            array(
                'before_section' => '<div id="' . esc_attr(array_search($sections, $settings['sections'])) .'" class="fqi3-section-options-page">',
                'after_section' => '</div>'
            )
        );

        $this->add_emails_settings_fields();
    }

    public function add_emails_settings_fields() {
        add_settings_field(
            'fqi3_disable_emails',
            __('Disable email notification', 'form-quizz-fqi3'),
            [$this, 'render_disable_emails'],
            fqi3_get_options_page_slug(),
            'fqi3_emails_section'
        );
        add_settings_field(
            'fqi3_email_logo',
            __('Upload Email Image', 'form-quizz-fqi3'),
            [$this, 'render_email_logo_field'],
            fqi3_get_options_page_slug(),
            'fqi3_emails_section'
        );
        add_settings_field(
            'fqi3_email_hour',
            __('Email Send Hour', 'form-quizz-fqi3'),
            [$this, 'render_email_hour_field'],
            fqi3_get_options_page_slug(),
            'fqi3_emails_section'
        );
        add_settings_field(
            'fqi3_email_link_cta',
            __('Email CTA settings', 'form-quizz-fqi3'),
            [$this, 'render_email_cta'],
            fqi3_get_options_page_slug(),
            'fqi3_emails_section'
        );
        add_settings_field(
            'fqi3_test_email',
            __('Send Test Email to WP admin', 'form-quizz-fqi3'),
            [$this, 'render_test_email_button'],
            fqi3_get_options_page_slug(),
            'fqi3_emails_section'
        );
    }

    /**
     * Renders the checkbox option to disable email notifications.
     * 
     * This method displays a checkbox input field on the plugin settings page
     * that allows the administrator to enable or disable email notifications.
     * When checked, email notifications will be disabled for users.
     * 
     * @since 1.4.0
     */
    public function render_disable_emails() {
        $options = fqi3_get_options();
        $checked = isset($options['fqi3_disable_emails']) && $options['fqi3_disable_emails'] ? 'checked' : '';

        
        echo '<div class="form-check form-switch mb-3">';
        echo '<input class="form-check-input" type="checkbox" name="fqi3_options[fqi3_disable_emails]" value="1" ' . esc_attr($checked) . ' id="fqi3_disable_emails">';
        echo '<label class="form-check-label" for="fqi3_disable_emails">' . esc_html__('Disable email notifications', 'form-quizz-fqi3') . '</label>';
        echo '</div>';
        
        echo '<p class="text-muted">' . esc_html__('Check this to disable the email notifications.', 'form-quizz-fqi3') . '</p>';        
    }

    /**
     * Renders the field for uploading an email image.
     *
     * This method generates the HTML for the email image upload field in the admin settings.
     * It includes an upload button, a remove button (if an image is already set), and a hidden 
     * input field that stores the image URL. The method also displays a preview of the image 
     * if one is set.
     *
     * @since 1.4.0
     */
    public function render_email_logo_field() {
        $options = fqi3_get_options();
        $image_id = isset($options['fqi3_email_logo']) ? $options['fqi3_email_logo'] : '';

        echo '<div class="bts-img mb-3 d-flex flex-row align-items-end">';
        if ($image = wp_get_attachment_image_url($image_id, 'medium')) :
            echo '<a href="#" class="btn-success btn-sm rudr-upload">
                    <img src="' . esc_url($image) . '" style="max-width: 200px; width: 10em;" />
                </a>';
            echo '<a href="#" class="btn btn-danger btn-sm rudr-remove">' . esc_html__('Remove Image', 'form-quizz-fqi3') . '</a>';
            echo '<input type="hidden" id="fqi3_email_logo" name="fqi3_options[fqi3_email_logo]" value="' . absint($image_id) . '">';
        else :
            echo '<a href="#" class="btn btn-success btn-sm rudr-upload">' . esc_html__('Upload Image', 'form-quizz-fqi3') . '</a>';
            echo '<a href="#" class="btn btn-danger btn-sm rudr-remove" style="display:none;">' . esc_html__('Remove Image', 'form-quizz-fqi3') . '</a>';
            echo '<input type="hidden" name="fqi3_options[fqi3_email_logo]" value="">';
        endif;

        echo '</div>';
    }

    /**
     * Renders the time field for email sending.
     *
     * Displays an input field for selecting the time (HH:MM) of email sending.
     *
     * @since 1.4.0
     */
    public function render_email_hour_field() {
        $options = fqi3_get_options();
        $email_time = isset($options['fqi3_email_hour']) ? $options['fqi3_email_hour'] : '08:00'; // Default to 08:00 if not set
        echo '<div class="mb-3">';
        echo '<label for="fqi3_email_hour" class="form-label">' . esc_html__('Select the time (HH:MM) for sending reminder emails.', 'form-quizz-fqi3') . '</label>';
        echo '<input type="time" id="fqi3_email_hour" name="fqi3_options[fqi3_email_hour]" class="form-control w-auto" value="' . esc_attr($email_time) . '" />';
        echo '<small class="form-text text-muted">' . esc_html__('Select the time (HH:MM) for sending reminder emails.', 'form-quizz-fqi3') . '</small>';
        echo '</div>';

        echo '<div class="mb-3">';
        echo '<a href="' . esc_url(admin_url('admin-post.php?action=fqi3_test_cron')) . '" class="btn btn-info btn-sm">';
        echo esc_html__('Test Cron Execution', 'form-quizz-fqi3');
        echo '</a>';
        echo '</div>';
    }

    /**
     * Renders the dropdown field for selecting a sales page.
     * 
     * Displays a dropdown menu populated with all published pages. The user can select a page which will be used as the link to the CTA email.
     * The selected page's ID is stored in the 'fqi3_options' option.
     * 
     * @since 1.4.0
     */
    public function render_email_cta() {
        $pages = get_pages();
        $default_options = $this->get_default_options_cta();

        $options = fqi3_get_options();
              
        $selected_page = isset($options['fqi3_email_link_cta']) && !empty($options['fqi3_email_link_cta']) 
            ? $options['fqi3_email_link_cta'] 
            : '';
        $labelCta = isset($options['fqi3_email_cta_label']) && !empty($options['fqi3_email_cta_label']) ? $options['fqi3_email_cta_label'] : __('Go to the site', 'form-quizz-fqi3');
        $colorText = isset($options['fqi3_email_cta_color_text']) ? $options['fqi3_email_cta_color_text'] : '';
        $colorBg = isset($options['fqi3_email_cta_color_bg']) ? $options['fqi3_email_cta_color_bg'] : '';
    
        echo '<div class="option-container">';

        echo '<div class="mb-3">';
        echo '<label for="fqi3_email_link_cta" class="form-label">' . esc_html__('Email CTA link', 'form-quizz-fqi3') . '</label>';
        echo '<select class="form-select form-select-sm" name="fqi3_options[fqi3_email_link_cta]" id="fqi3_email_link_cta">';
        echo '<option value="" disabled selected>' . esc_html('Select a page', 'form-quizz-fqi3') . '</option>';
        foreach ($pages as $page) {
            $selected = $page->ID == $selected_page ? 'selected' : '';
            echo '<option value="' . esc_attr($page->ID) . '" ' . esc_attr($selected) . '>' . esc_html($page->post_title) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<div class="mb-3">';
        echo '<label for="fqi3_email_cta_label" class="form-label">' . esc_html__('Email CTA Label', 'form-quizz-fqi3') . '</label>';
        echo '<input type="text" id="fqi3_email_cta_label" name="fqi3_options[fqi3_email_cta_label]" class="form-control form-control-sm w-auto" value="' . esc_attr($labelCta) . '" />';
        echo '<div class="form-text text-muted">' . esc_html__('Default:', 'form-quizz-fqi3') . ' ' . esc_html($default_options["fqi3_email_cta_label"]) . '</div>';
        echo '<button type="button" class="btn btn-secondary btn-sm mt-2 reset-button" data-id="fqi3_email_cta_label" data-default="' . esc_attr($default_options["fqi3_email_cta_label"]) . '">' . esc_html__('Reset', 'form-quizz-fqi3') . '</button>';
        echo '</div>';

        echo '<div class="mb-3">';
        echo '<label for="fqi3_email_cta_color_text" class="form-label">' . esc_html__('Link Text Color', 'form-quizz-fqi3') . '</label>';
        echo '<input type="color" id="fqi3_email_cta_color_text" name="fqi3_options[fqi3_email_cta_color_text]" class="form-control form-control-color" value="' . esc_attr($colorText) . '" />';
        echo '<div class="form-text text-muted">' . esc_html__('Default:', 'form-quizz-fqi3') . ' ' . esc_html($default_options["fqi3_email_cta_color_text"]) . '</div>';
        echo '<button type="button" class="btn btn-secondary btn-sm mt-2 reset-button" data-id="fqi3_email_cta_color_text" data-default="' . esc_attr($default_options["fqi3_email_cta_color_text"]) . '">' . esc_html__('Reset', 'form-quizz-fqi3') . '</button>';
        echo '</div>';

        echo '<div class="mb-3">';
        echo '<label for="fqi3_email_cta_color_bg" class="form-label">' . esc_html__('Link Background Color', 'form-quizz-fqi3') . '</label>';
        echo '<input type="color" id="fqi3_email_cta_color_bg" name="fqi3_options[fqi3_email_cta_color_bg]" class="form-control form-control-color" value="' . esc_attr($colorBg) . '" />';
        echo '<div class="form-text text-muted">' . esc_html__('Default:', 'form-quizz-fqi3') . ' ' . esc_html($default_options["fqi3_email_cta_color_bg"]) . '</div>';
        echo '<button type="button" class="btn btn-secondary btn-sm mt-2 reset-button" data-id="fqi3_email_cta_color_bg" data-default="' . esc_attr($default_options["fqi3_email_cta_color_bg"]) . '">' . esc_html__('Reset', 'form-quizz-fqi3') . '</button>';
        echo '</div>';
    } 

    /**
     * Renders a button for sending a test email.
     *
     * This method generates a submit button that allows users to send a test email. 
     * The button is styled with the class 'btn btn-info' and is labeled 
     * "Send Test Email" in the admin interface.
     *
     * @since 1.4.0 Initial
     */
    public function render_test_email_button() {
        echo '<div class="group-test-email">';
        echo '<input type="submit" name="fqi3_test_email" class="btn btn-info btn-sm fqi3-test-email-button" value="' . esc_attr__('Send Test Email', 'form-quizz-fqi3') . '" />';
        echo '<div class="loader" style="display:none;">
                <div></div>
                <div></div>
                <div></div>
            </div>';
        echo '</div>';
        echo '<div class="fqi3-notices"></div>';
    }

    /**
     * Handles the test email form submission.
     *
     * Checks if the 'fqi3_test_email' button was pressed. If so, retrieves plugin options
     * and verifies if email sending is disabled. If not disabled, sends a test email
     * and displays a success message. If disabled, shows an error message instead.
     *
     * @since 1.4.0 Initial
     * @since 1.6.0 Added nonce to secure AJAX request and protect against CSRF attacks.
     */
    public function handle_test_email_submission() {
        if (isset($_POST['security']) && wp_verify_nonce($_POST['security'], 'fqi3_test_email_nonce')) {
            $options = fqi3_get_options();

            if ($options['fqi3_disable_emails'] == '1') {
                wp_send_json_error([
                    'message' => __('Email sending is disabled.', 'form-quizz-fqi3')
                ]);
            } else {
                $email_instance = new \Form_Quizz_FQI3\FQI3_Emails();
                $email_instance->send_test_email();
            
                wp_send_json_success([
                    'message' => __('Test email has been sent.', 'form-quizz-fqi3')
                ]);
            }       
        } else {
            wp_send_json_error(array('message' => __('Security check failed.', 'form-quizz-fqi3')));
        }   
    }

    /**
     * Sets default options for the plugin if not already set.
     * 
     * This method retrieves default options and merges them with the current options. 
     * It updates the options only if there are changes.
     * 
     * @since 1.4.0 Initial
     */
    public function set_default_options_emails() {
        $default_options = $this->get_default_options_cta();

        // Use the generic function to set default options
        fqi3_set_default_options($default_options, 'fqi3_options');
    }

    /**
     * Retrieves the default options for the email CTA.
     * 
     * This method returns an associative array of default options that will be used if no custom options are set.
     * 
     * @return array Default options for the email CTA.
     * 
     * @since 1.4.0 Initial
     */
    public function get_default_options_cta() {
        $home_page_id = absint(get_option('page_on_front'));
        return [
            'fqi3_email_hour' => '08:00',
            'fqi3_email_link_cta' => $home_page_id,
            'fqi3_email_cta_label' => __('Go to the site', 'form-quizz-fqi3'),
            'fqi3_email_cta_color_text' => '#ffffff',
            'fqi3_email_cta_color_bg' => '#0D0D0D',
        ];
    }

    /**
     * Sanitizes email settings input to ensure only valid values are saved.
     * 
     * This method processes the options for disabling email notifications, 
     * the email image, the email sending time, and the email CTA link. 
     * It ensures that the values are appropriately sanitized and validated 
     * before being saved in the database.
     * 
     * @param array $input The input values from the settings form.
     * @return array The sanitized values to be saved.
     * @since 1.4.0 Initial
     */
    public static function sanitize_emails_settings_options($input) {
        $sanitized_input = [];
        // Sanitize the option to disable email notifications
        $sanitized_input['fqi3_disable_emails'] = !empty($input['fqi3_disable_emails']) ? 1 : 0;
        $sanitized_input['fqi3_email_logo'] = !empty($input['fqi3_email_logo']) ? absint($input['fqi3_email_logo']) : '';

        if (isset($input['fqi3_email_hour'])) {
            if (preg_match('/^\d{2}:\d{2}$/', $input['fqi3_email_hour'])) {
                $sanitized_input['fqi3_email_hour'] = sanitize_text_field($input['fqi3_email_hour']);
            } else {
                $sanitized_input['fqi3_email_hour'] = '08:00'; // Default to '08:00'
            }
        } else {
            $sanitized_input['fqi3_email_hour'] = '08:00'; // Default to '08:00'
        }
        $sanitized_input['fqi3_email_link_cta'] = !empty($input['fqi3_email_link_cta']) ? absint($input['fqi3_email_link_cta']) : '';
        $sanitized_input['fqi3_email_cta_label'] = !empty($input['fqi3_email_cta_label']) ? sanitize_text_field($input['fqi3_email_cta_label']) : '';
        $sanitized_input['fqi3_email_cta_color_text'] = !empty($input['fqi3_email_cta_color_text']) ? sanitize_hex_color($input['fqi3_email_cta_color_text']) : '';
        $sanitized_input['fqi3_email_cta_color_bg'] = !empty($input['fqi3_email_cta_color_bg']) ? sanitize_hex_color($input['fqi3_email_cta_color_bg']) : '';
    
        return $sanitized_input;
    }
}

endif;