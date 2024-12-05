<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3_Option_Page Class
 *
 * This class manages settings page for the FQI3 plugin.
 * It generates the options table of the Settings page.
 *
 * @package    Form Quizz FQI3
 * @subpackage Admin Pages
 * @since      1.4.0
 * @version    1.4.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Option_Page' ) ) :

    class FQI3_Option_Page {

        public function __construct(private FQI3_Backend $backend) {}

        /**
         * Renders the options page for the plugin.
         * 
         * Displays a form for configuring plugin options. Uses WordPress settings API to handle form submission and validation.
         */
        public function render_options_page() {
            $settings = fqi3_options_settings_sections();
            $sections = $settings['sections'];
            $plugin_page_slugs = fqi3_get_admin_pages();
            ?>
            <div class="wrap container-fluid">
                <h2 class="wp-heading-inline page-title"><?php echo esc_html__('Form Quizz FQI3 Settings', 'form-quizz-fqi3'); ?></h2>

                <?php settings_errors(); ?>

                <div id="options_container" class="fqi3-options-container">
                    <div id="options_tab_link">
                        <?php $this->render_options_tabs($sections); ?>
                    </div>
                    <div id="options_tab_content">
                        <?php $this->render_options_form($plugin_page_slugs); ?>
                    </div>
                </div>
            <?php
        }

        /**
         * Renders the options tabs navigation.
         *
         * @param array $sections Sections to render as tabs
         * @return void
         */
        private function render_options_tabs(array $sections): void 
        {
            if (empty($sections)) {
                return;
            }
            
            ?>
            <ul class="fqi3-tab-links">
                <?php foreach ($sections as $key => $section) : ?>
                    <li class="fqi3-tab-link <?php echo $key === array_key_first($sections) ? 'active' : ''; ?>" 
                        data-tab="<?php echo esc_attr($key); ?>">
                        <a href="#<?php echo esc_attr($key); ?>">
                            <i class="bi <?php echo esc_attr($section['icon']); ?>"></i>
                            <?php echo esc_html($section['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php
        }

        /**
         * Renders the options form.
         *
         * @param array $plugin_page_slugs Plugin page slugs configuration
         * @return void
         */
        private function render_options_form(array $plugin_page_slugs): void 
        {
            ?>
            <form 
                id="fqi3-form-options" 
                method="post" 
                action="options.php"
            >
                <?php
                //wp_nonce_field('fqi3_nonce_action', 'fqi3_nonce_field');
                settings_fields('fqi3_options_group');
                do_settings_sections($plugin_page_slugs['options']['slug']);
                ?>
                <div class="mt-3 sticky-bottom">
                    <input id="submit" type="submit" name="submit" value="<?php _e('Save settings', 'form-quizz-fqi3'); ?>" class="btn btn-primary btn-sm">
                </div>
            </form>
            <?php
        }
    }

endif;