<?php
namespace Form_Quizz_FQI3;

if (!defined('ABSPATH')) {
    exit;
}

class FQI3_License_Settings {
    public function __construct() {
        // The token is read from the GITHUB_PERSONAL_TOKEN constant defined by the site owner in wp-config.php.
        // The plugin no longer edits wp-config.php itself.
    }
    
    /**
     * Check if GitHub token is defined and not empty
     * 
     * @return bool True if token is defined and not empty
     */
    public static function has_github_token() {
        $token = self::get_current_github_token();
        return !empty($token);
    }

    public function register_settings() {
        $this->add_settings_section();
    }

    private function add_settings_section() {
        $settings = fqi3_options_settings_sections();
        $sections = $settings['sections']['license_settings'];

        add_settings_section(
            $sections['id'],
            $sections['title'],
            [ $this, 'license_section_callback' ],
            fqi3_get_options_page_slug(),
            [
                'before_section' => '<div id="' . esc_attr(array_search($sections, $settings['sections'])) . '" class="fqi3-section-options-page">',
                'after_section'  => '</div>'
            ]
        );

        $this->add_license_settings_fields();
    }

    private function add_license_settings_fields() {
        add_settings_field(
            'fqi3_github_token',
            __('GitHub Personal Token', 'form-quizz-fqi3'),
            [ $this, 'github_token_callback' ],
            fqi3_get_options_page_slug(),
            'fqi3_license_section'
        );
    }

    /**
     * License section callback function.
     */
    public function license_section_callback() {
        echo '<p>' . esc_html__('Configure your GitHub personal token here to enable access to add-ons and receive updates for the core plugin and installed add-ons.', 'form-quizz-fqi3') . '</p>';
    }    

    /**
     * GitHub token field callback function.
     *
     * Read-only: the token is defined as a constant in wp-config.php by the site owner.
     */
    public function github_token_callback() {
        $token = self::get_current_github_token();
        $is_defined = self::has_github_token();
        ?>
        <div class="github-token-wrapper">
            <?php if ($is_defined) : ?>
                <p class="mb-2">
                    <strong><?php esc_html_e('Current token:', 'form-quizz-fqi3'); ?></strong>
                    <code class="bg-light p-1 rounded"><?php echo esc_html('••••••••••' . substr($token, -4)); ?></code>
                </p>
            <?php else : ?>
                <p class="text-danger mb-2">
                    <?php esc_html_e('No GitHub token is defined.', 'form-quizz-fqi3'); ?>
                </p>
            <?php endif; ?>

            <p class="text-muted mb-2">
                <?php esc_html_e('To set or change the token, add this line to your wp-config.php file (above the line "That\'s all, stop editing!"), then reload this page:', 'form-quizz-fqi3'); ?>
            </p>
            <pre class="bg-light p-2 rounded"><code>define( 'GITHUB_PERSONAL_TOKEN', 'your_token_here' );</code></pre>

            <p class="text-muted mb-0">
                <a href="https://github.com/settings/tokens" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                    <?php esc_html_e('Create a new token on GitHub', 'form-quizz-fqi3'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Get the GitHub token defined by the GITHUB_PERSONAL_TOKEN constant.
     *
     * @return string Empty string when the constant is not defined.
     */
    private static function get_current_github_token() {
        return defined('GITHUB_PERSONAL_TOKEN') ? (string) GITHUB_PERSONAL_TOKEN : '';
    }

    /**
     * Validate a GitHub token
     * 
     * @param string $token
     * @return bool|WP_Error
     */
    public static function validate_github_token($token) {
        if (empty($token)) {
            return new \WP_Error('empty_token', __('Token cannot be empty', 'form-quizz-fqi3'));
        }

        // Test API GitHub to validate the token
        $response = wp_remote_get('https://api.github.com/user', [
            'headers' => [
                'Authorization' => 'token ' . $token,
                'User-Agent' => 'WordPress-Plugin'
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', __('Failed to validate token with GitHub API.', 'form-quizz-fqi3'));
        }

        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            return new \WP_Error('invalid_token', __('Invalid GitHub token.', 'form-quizz-fqi3'));
        }

        return true;
    }
}