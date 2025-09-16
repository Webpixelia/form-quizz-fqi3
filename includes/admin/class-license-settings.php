<?php
namespace Form_Quizz_FQI3;

if (!defined('ABSPATH')) {
    exit;
}

class FQI3_License_Settings {
    public function __construct() {
        add_action('wp_ajax_fqi3_save_github_token', [ $this, 'ajax_save_github_token' ]);
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
     */
    public function github_token_callback() {
        $token = self::get_current_github_token();
        $is_defined = self::has_github_token();
        ?>
        <div class="github-token-wrapper">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="form-group flex-grow-1">
                    <input type="password" 
                        id="fqi3_github_token" 
                        name="fqi3_github_token" 
                        value="" 
                        class="form-control"
                        placeholder="<?php echo $is_defined ? 
                            esc_attr__('Enter new token to update', 'form-quizz-fqi3') : 
                            esc_attr__('Enter your GitHub personal token', 'form-quizz-fqi3'); ?>"
                        autocomplete="off" />
                </div>
                <button type="button" id="fqi3-save-github-token" class="btn btn-success btn-sm me-2">
                    <i class="bi bi-key"></i>
                    <?php esc_html_e('Save Token', 'form-quizz-fqi3'); ?>
                </button>
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
            </div>
            
            <div id="fqi3-github-token-message" class="alert alert-dismissible fade show" role="alert" style="display: none;">
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                <div class="message-content"></div>
            </div>
            
            <p class="text-muted mb-2">
                <?php esc_html_e('Enter your GitHub personal token for API access.', 'form-quizz-fqi3'); ?>
            </p>
            
            <p class="text-muted mb-2">
                <a href="https://github.com/settings/tokens" target="_blank" class="text-decoration-none">
                    <?php esc_html_e('Create a new token on GitHub', 'form-quizz-fqi3'); ?>
                </a>
            </p>
            
            <?php if ($is_defined) : ?>
                <p class="text-muted mb-0">
                    <strong><?php esc_html_e('Current token:', 'form-quizz-fqi3'); ?></strong> 
                    <code class="bg-light p-1 rounded"><?php echo '••••••••••' . substr($token, -4); ?></code>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get the current GitHub token from wp-config.php
     * 
     * @return string
    */
    private static function get_current_github_token() {
        // First, try the constant if it is defined
        if (defined('GITHUB_PERSONAL_TOKEN')) {
            return GITHUB_PERSONAL_TOKEN;
        }
        
        // Otherwise, read directly from wp-config.php
        $config_file = ABSPATH . 'wp-config.php';
        
        if (!file_exists($config_file)) {
            return '';
        }
        
        $config_content = file_get_contents($config_file);
        
        if ($config_content === false) {
            return '';
        }
        
        // Search for the token definition
        $pattern = "/define\s*\(\s*['\"]GITHUB_PERSONAL_TOKEN['\"]\s*,\s*['\"]([^'\"]*?)['\"]\s*\)\s*;/s";
        
        if (preg_match($pattern, $config_content, $matches)) {
            return $matches[1];
        }
        
        return '';
    }

    /**
     * AJAX handler to save the GitHub token
     */
    public function ajax_save_github_token() {
        // Verify the nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fqi3_save_github_token_nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'form-quizz-fqi3')
            ]);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'form-quizz-fqi3')
            ]);
        }

        $token = sanitize_text_field($_POST['token']);
        
        if (empty($token)) {
            wp_send_json_error([
                'message' => __('Token cannot be empty.', 'form-quizz-fqi3')
            ]);
        }

        // Validate the GitHub token format (generally 40 characters alphanumeric)
        if (!preg_match('/^[a-zA-Z0-9_]{20,}$/', $token)) {
            wp_send_json_error([
                'message' => __('Invalid token format. GitHub tokens should contain only letters, numbers, and underscores.', 'form-quizz-fqi3')
            ]);
        }

        $result = self::save_github_token($token);
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }

        wp_send_json_success([
            'message' => __('GitHub token saved successfully!', 'form-quizz-fqi3'),
            'masked_token' => '••••••••••' . substr($token, -4)
        ]);
    }

    /**
     * Save the GitHub token to wp-config.php
     * 
     * @param string $token The GitHub personal token to save
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function save_github_token($token) {
        if (empty($token)) {
            return new \WP_Error('empty_token', __('Token cannot be empty', 'form-quizz-fqi3'));
        }

        $config_file = ABSPATH . 'wp-config.php';
        
        if (!file_exists($config_file)) {
            return new \WP_Error('config_not_found', __('wp-config.php not found.', 'form-quizz-fqi3'));
        }
        
        if (!is_writable($config_file)) {
            return new \WP_Error('not_writable', __('wp-config.php is not writable. Please check file permissions.', 'form-quizz-fqi3'));
        }

        // Créer une sauvegarde
        $backup_file = $config_file . '.backup.' . time();
        if (!copy($config_file, $backup_file)) {
            return new \WP_Error('backup_failed', __('Failed to create backup of wp-config.php', 'form-quizz-fqi3'));
        }

        $config_content = file_get_contents($config_file);
        
        if ($config_content === false) {
            return new \WP_Error('read_failed', __('Failed to read wp-config.php', 'form-quizz-fqi3'));
        }

        // Pattern pour trouver la définition existante
        $pattern = "/define\s*\(\s*['\"]GITHUB_PERSONAL_TOKEN['\"]\s*,\s*['\"].*?['\"]\s*\)\s*;/s";
        
        $new_definition = "define('GITHUB_PERSONAL_TOKEN', '" . addcslashes($token, "'\"\\") . "');";
        
        if (preg_match($pattern, $config_content)) {
            // Remplacer la définition existante
            $new_config_content = preg_replace($pattern, $new_definition, $config_content, 1);
        } else {
            // Ajouter avant le commentaire de fin
            $end_markers = [
                '/* That\'s all, stop editing! Happy publishing. */',
                '/* C\'est tout, ne modifiez plus rien ! Bonne publication. */',
                '/* That\'s all, stop editing! Happy blogging. */'
            ];
            
            $found = false;
            foreach ($end_markers as $marker) {
                if (strpos($config_content, $marker) !== false) {
                    $new_config_content = str_replace(
                        $marker,
                        "\n" . $new_definition . "\n\n" . $marker,
                        $config_content
                    );
                    $found = true;
                    break;
                }
            }
            
            // Si aucun marqueur trouvé, chercher avant la fermeture PHP
            if (!$found) {
                if (strrpos($config_content, '?>') !== false) {
                    $pos = strrpos($config_content, '?>');
                    $new_config_content = substr($config_content, 0, $pos) . 
                                        "\n" . $new_definition . "\n" . 
                                        substr($config_content, $pos);
                } else {
                    // Ajouter à la fin du fichier
                    $new_config_content = rtrim($config_content) . "\n\n" . $new_definition . "\n";
                }
            }
        }

        // Écrire le nouveau contenu
        if (file_put_contents($config_file, $new_config_content, LOCK_EX) === false) {
            // Restaurer la sauvegarde en cas d'échec
            copy($backup_file, $config_file);
            unlink($backup_file);
            return new \WP_Error('write_failed', __('Failed to write to wp-config.php', 'form-quizz-fqi3'));
        }

        // Supprimer la sauvegarde si tout s'est bien passé
        unlink($backup_file);

        // Invalider le cache opcache si disponible
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($config_file);
        }

        return true;
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