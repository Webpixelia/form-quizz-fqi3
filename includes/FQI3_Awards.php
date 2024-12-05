<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3_Awards Class
 *
 * This class manages the badge awarding system for the FQI3 plugin.
 * It assigns badges to users based on their performance in quizzes.
 *
 * @package    Form Quizz FQI3
 * @subpackage Awards System
 * @since      1.5.0
 * @version    2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Awards' ) ) :
    class FQI3_Awards {
         /** @var FQI3_Template_Manager */
        private FQI3_Template_Manager $template_manager;

        private const CACHE_GROUP = 'fqi3_awards';
        private const CACHE_EXPIRATION = 3600; // 1 hour
        private const CACHE_VERSION = '1.0';

        private ?FQI3_Statistics $statistics = null;
        private $table_name;
        private array $badges_cache = [];

        /**
         * Constructor to initialize the class and create the badges table if it doesn't exist.
         */
        public function __construct() {
            global $wpdb;
            $this->table_name = $wpdb->prefix . FQI3_TABLE_AWARDS;
            
            $this->template_manager = new FQI3_Template_Manager();

            // Initialize hooks
            add_action('init', [$this, 'initialize']);
            add_action('wp_ajax_award_badges', [$this, 'handle_award_badges']);
            add_action('wp_ajax_nopriv_award_badges', [$this, 'handle_award_badges']);
            
            // Set up activation hook for table creation
            register_activation_hook(FQI3_FILE, [$this, 'create_badges_table']);
    
            // Add hook for clearing cache when badges are updated
            add_action('update_option_fqi3_badges', [$this, 'clear_badges_cache']);
        }

        /**
         * Initialize the class.
         */
        public function initialize(): void {
            $this->maybe_load_badges_cache();
        }

         /**
         * Load badges into cache if not already loaded.
         * This method ensures we only load the badges from the database when necessary.
         */
        private function maybe_load_badges_cache(): void {
            if (empty($this->badges_cache)) {
                $cache_key = 'fqi3_badges_' . self::CACHE_VERSION;
                $cached_badges = wp_cache_get($cache_key, self::CACHE_GROUP);

                if ($cached_badges === false) {
                    $badges_options = get_option('fqi3_badges', []);

                    if (empty($badges_options)) {
                        $this->badges_cache = [];
                        wp_cache_set(
                            $cache_key,
                            $this->badges_cache,
                            self::CACHE_GROUP,
                            self::CACHE_EXPIRATION
                        );
                        return;
                    }
                    
                    // Ensure the badges are unserialized if needed
                    $this->badges_cache = is_serialized($badges_options) ? 
                        unserialize($badges_options) : $badges_options;
                    
                    // Cache the badges
                    wp_cache_set(
                        $cache_key,
                        $this->badges_cache,
                        self::CACHE_GROUP,
                        self::CACHE_EXPIRATION
                    );
                } else {
                    $this->badges_cache = $cached_badges;
                }
            }
        }

        /**
         * Clear the badges cache when the options are updated.
         */
        public function clear_badges_cache(): void {
            $cache_key = 'fqi3_badges_' . self::CACHE_VERSION;
            wp_cache_delete($cache_key, self::CACHE_GROUP);
            $this->badges_cache = [];
        }

        /**
         * Get cached badges options.
         * Now uses the cached version loaded by maybe_load_badges_cache.
         * 
         * @return array An associative array containing the badges information.
         */
        private function get_cached_badges_options(): array {
            if (empty($this->badges_cache)) {
                $this->maybe_load_badges_cache();
            }
            return $this->badges_cache;
        }

        /**
         * Get or create statistics instance using lazy loading.
         */
        public function get_statistics_instance(): FQI3_Statistics {
            return $this->statistics ??= new FQI3_Statistics();
        }

         /**
         * Create the badges table with improved schema and error handling.
         */
        public function create_badges_table() {
            global $wpdb;

            $charset_collate = $wpdb->get_charset_collate();

             if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) !== $this->table_name) {
                $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    badge_name VARCHAR(255) NOT NULL,
                    badge_id BIGINT UNSIGNED NOT NULL,
                    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_badges (user_id, badge_name),
                    UNIQUE KEY unique_user_badge (user_id, badge_name)
                ) $charset_collate;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);

                if (!empty($wpdb->last_error)) {
                    error_log("FQI3 Awards: Error creating table - {$wpdb->last_error}");
                }
            }
        }

        /**
         * Handles the awarding of badges based on user performance in quizzes.
         *
         * This function is triggered via AJAX requests when a user completes a quiz. 
         * It verifies the nonce for security, retrieves the user ID and the quiz level 
         * from the POST request, and then calls the method to award badges based on 
         * the specified criteria.
         *
         * @throws WP_Error If the nonce verification fails or if the user ID is invalid.
         * 
         * @return void Returns a JSON response indicating the success or failure of the operation.
         */
        public function handle_award_badges(): void {
            try {
                if (!check_ajax_referer('fqi3_cookies_nonce', 'security', false)) {
                    throw new \Exception('Invalid security token.');
                }
    
                $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                //$level = filter_input(INPUT_POST, 'level', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '';
    
                if (!$user_id || !$level) {
                    throw new \Exception('Invalid user ID or level.');
                }
    
                $this->award_badges_based_on_criteria($user_id, $level);
                wp_send_json_success('Badges awarded successfully.');
    
            } catch (\Exception $e) {
                wp_send_json_error([
                    'message' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
            }
        }  

        /**
         * Award badges based on user performance and criteria.
         *
         * @param int $user_id The ID of the user to award the badge to.
         * @param string $level The level (beginner, intermediate, advanced) for which badges are being awarded.
         */
        public function award_badges_based_on_criteria(int $user_id, string $level): void {
            $badges_options = $this->get_cached_badges_options();
    
            if (!empty($badges_options['fqi3_disable_badges']) && $badges_options['fqi3_disable_badges']) {
                return;
            }
    
            $quizzes_completed = $this->get_completed_quiz_count($user_id, $level);
            $success_rate = $this->get_user_success_rate($user_id, $level);
            $min_quizzes = (int) ($badges_options['fqi3_min_quizzes_for_success_rate'] ?? 20);
    
            // Process completion badges
            $this->process_completion_badges($user_id, $level, $quizzes_completed, $badges_options);
    
            // Process success rate badges if minimum quiz requirement met
            if ($quizzes_completed >= $min_quizzes) {
                $this->process_success_rate_badges($user_id, $level, $success_rate, $badges_options);
            }
        }

         /**
         * Process completion-based badges.
         */
        private function process_completion_badges(int $user_id, string $level, int $completed, array $badges_options): void {
            if (empty($badges_options['fqi3_quizzes_completed_thresholds'])) {
                return;
            }

            foreach ($badges_options['fqi3_quizzes_completed_thresholds'] as $index => $threshold) {
                if ($completed >= (int)$threshold) {
                    $badge_name = $badges_options['fqi3_quizzes_completed_badge_names'][$index] ?? '';
                    if ($badge_name) {
                        $badge_id = $this->get_badge_id_by_name($badge_name);
                        $this->award_badge($user_id, "{$badge_name} " . ucfirst($level), $badge_id);
                    }
                }
            }
        }

        /**
         * Process success rate-based badges.
         */
        private function process_success_rate_badges(int $user_id, string $level, float $success_rate, array $badges_options): void {
            if (empty($badges_options['fqi3_success_rate_thresholds'])) {
                return;
            }

            foreach ($badges_options['fqi3_success_rate_thresholds'] as $index => $threshold) {
                if ($success_rate >= (float)$threshold) {
                    $badge_name = $badges_options['fqi3_success_rate_badge_names'][$index] ?? '';
                    if ($badge_name) {
                        $badge_id = $this->get_badge_id_by_name($badge_name);
                        $this->award_badge($user_id, "{$badge_name} " . ucfirst($level), $badge_id);
                    }
                }
            }
        }

        /**
         * Get the total number of quizzes completed by the user for a specific level.
         *
         * @param int $user_id The ID of the user.
         * @param string $level The level for which the quiz count is retrieved.
         * @return int The total number of quizzes completed.
         */
        private function get_completed_quiz_count(int $user_id, string $level): int {
            $cache_key = "quiz_count_{$user_id}_{$level}";
            $count = wp_cache_get($cache_key, self::CACHE_GROUP);
    
            if ($count === false) {
                $stats = $this->get_statistics_instance()->get_user_statistics($user_id);
                $count = 0;
    
                foreach ($stats as $stat) {
                    if ($stat['level'] === $level) {
                        $count = (int)$stat['total_quizzes'];
                        break;
                    }
                }
    
                wp_cache_set($cache_key, $count, self::CACHE_GROUP, self::CACHE_EXPIRATION);
            }
    
            return $count;
        }

        /**
         * Get the user's success rate for a specific level.
         *
         * @param int $user_id The ID of the user.
         * @param string $level The level for which the success rate is retrieved.
         * @return float The user's success rate.
         */
        private function get_user_success_rate(int $user_id, string $level): float {
            global $wpdb;
            
            $cache_key = "success_rate_{$user_id}_{$level}";
            $rate = wp_cache_get($cache_key, self::CACHE_GROUP);
    
            if ($rate === false) {
                $table_name = $wpdb->prefix . FQI3_TABLE_PERFORMANCE;
                $rate = (float)$wpdb->get_var($wpdb->prepare(
                    "SELECT success_rate FROM {$table_name} WHERE user_id = %d AND level = %s",
                    $user_id,
                    $level
                ));
    
                wp_cache_set($cache_key, $rate, self::CACHE_GROUP, self::CACHE_EXPIRATION);
            }
    
            return $rate !== null ? (float) $rate : 0.0;
        }

        /**
        * Award a badge to a user with improved error handling.
        *
        * @param int $user_id The ID of the user receiving the badge.
        * @param string $badge_name The name of the badge being awarded.
        * @param int|null $badge_id The ID of the badge being awarded (optional).
        */
        private function award_badge(int $user_id, string $badge_name, ?int $badge_id): void {
            global $wpdb;

            if ($badge_id === null) {
                return;
            }

            $result = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_name} (user_id, badge_name, badge_id, awarded_at)
                VALUES (%d, %s, %d, NOW())",
                $user_id,
                $badge_name,
                $badge_id
            ));

            if ($result === false) {
                error_log("FQI3 Awards: Error awarding badge - {$wpdb->last_error}");
            }
        }

        /**
        * Display user badges with improved security and caching.
        * 
        * @param int|null $user_id Optional. User ID for which to retrieve badges. Defaults to the current logged-in user if null.
        * @return array Array of badges, where each badge contains 'name' and 'awarded_at' (formatted date).
        */
        public function display_user_badges(?int $user_id = null): array {
            global $wpdb;

            $user_id = $user_id ?? get_current_user_id();
            $cache_key = "user_badges_{$user_id}";
            $badges = wp_cache_get($cache_key, self::CACHE_GROUP);

            if ($badges === false) {
                $badges = $wpdb->get_results($wpdb->prepare(
                    "SELECT badge_name, badge_id, awarded_at FROM {$this->table_name} WHERE user_id = %d",
                    $user_id
                ));

                wp_cache_set($cache_key, $badges, self::CACHE_GROUP, self::CACHE_EXPIRATION);
            }

            if (empty($badges)) {
                return [];
            }

            return array_map(function($badge) {
                return [
                    'name' => esc_html($badge->badge_name),
                    'badge_id' => (int)$badge->badge_id,
                    'awarded_at' => esc_html(date_i18n(get_option('date_format'), strtotime($badge->awarded_at)))
                ];
            }, $badges);
        }

        /**
         * Get badge ID by name with caching.
         */
        private function get_badge_id_by_name(string $badge_name): ?int {
            $badges = $this->get_cached_badges_options();
            $cache_key = "badge_id_{$badge_name}";
            $badge_id = wp_cache_get($cache_key, self::CACHE_GROUP);

            if ($badge_id === false) {
                $badge_id = $this->find_badge_id_in_options($badges, $badge_name);
                wp_cache_set($cache_key, $badge_id, self::CACHE_GROUP, self::CACHE_EXPIRATION);
            }

            return $badge_id;
        }
        
        /**
         * Helper method to find badge ID in options.
         */
        private function find_badge_id_in_options(array $badges, string $badge_name): ?int {
            $badge_types = [
                ['names' => 'fqi3_quizzes_completed_badge_names', 'images' => 'fqi3_quizzes_completed_badge_images'],
                ['names' => 'fqi3_success_rate_badge_names', 'images' => 'fqi3_success_rate_badge_images']
            ];

            foreach ($badge_types as $type) {
                $names = $badges[$type['names']] ?? [];
                $images = $badges[$type['images']] ?? [];

                foreach ($names as $index => $name) {
                    if ($name === $badge_name) {
                        return $images[$index] ?? null;
                    }
                }
            }

            return null;
        }

         /**
         * Display all available badges with their requirements.
         * 
         * @return string HTML output of the badges table
         */
        public function display_all_badges(): string {
            // Try to get cached version first
            $cache_key = 'fqi3_all_badges_display_' . self::CACHE_VERSION;
            $cached_output = wp_cache_get($cache_key, self::CACHE_GROUP);
            
            if ($cached_output !== false) {
                return $cached_output;
            }

            // Prepare data structure
            $badges_data = $this->prepare_badges_data();
            
            // Generate HTML using the template
            $output = $this->render_badges_template($badges_data);
            
            // Cache the output
            wp_cache_set($cache_key, $output, self::CACHE_GROUP, self::CACHE_EXPIRATION);
            
            return $output;
        }
        
         /**
         * Prepare badges data structure.
         * 
         * @return array Structured badges data
         */
        public function prepare_badges_data(): array {
            $badges_options = $this->get_cached_badges_options();
            
            return [
                [
                    'type' => __('Completed Quizzes', 'form-quizz-fqi3'),
                    'names' => $badges_options['fqi3_quizzes_completed_badge_names'] ?? [],
                    'images' => $badges_options['fqi3_quizzes_completed_badge_images'] ?? [],
                    'thresholds' => $badges_options['fqi3_quizzes_completed_thresholds'] ?? [],
                    'howget' => __('achieve a specific number of quizzes', 'form-quizz-fqi3'),
                    'unity' => __('Completed Quizzes', 'form-quizz-fqi3')
                ],
                [
                    'type' => __('Success Rate', 'form-quizz-fqi3'),
                    'names' => $badges_options['fqi3_success_rate_badge_names'] ?? [],
                    'images' => $badges_options['fqi3_success_rate_badge_images'] ?? [],
                    'thresholds' => $badges_options['fqi3_success_rate_thresholds'] ?? [],
                    'howget' => sprintf(
                        // translators: %s is the number of quizzes that the user has completed to achieve a specific success rate.
                        __('have a specific success rate since a specific number of quizzes (%s)', 'form-quizz-fqi3'),
                        esc_html($badges_options['fqi3_min_quizzes_for_success_rate'] ?? 0)
                    ),
                    'unity' => '%'
                ]
            ];
        }
        /**
         * Render badges template with provided data.
         * 
         * @param array $badges_data Structured badges data
         * @return string Rendered HTML
         */
        private function render_badges_template(array $badges_data): string {
            // Pre-escape static strings
            $static_strings = [
                'title' => esc_html__('List of existing badges', 'form-quizz-fqi3'),
                'badge_type' => esc_html__('Badge Type', 'form-quizz-fqi3'),
                'how_to_get' => esc_html__('How to get it', 'form-quizz-fqi3'),
                'badges_versions' => esc_html__('Badges versions', 'form-quizz-fqi3'),
                'threshold' => esc_html__('Threshold:', 'form-quizz-fqi3'),
                'na' => esc_html__('N/A', 'form-quizz-fqi3'),
                // translators: %s represents the action or condition that needs to be fulfilled, e.g., 'complete a quiz' or 'reach a score.'
                'how_to_get_prefix' => esc_html__('You need to %s', 'form-quizz-fqi3')
            ];

            // Combine badges_data with static strings into one array
            $data = array_merge($static_strings, ['badges_data' => $badges_data]);

            return $this->template_manager->render('badge-template', $data);
        }
    }

endif;

$options = fqi3_get_options();
if ( empty( $options['fqi3_disable_statistics'] ) || $options['fqi3_disable_statistics'] !== '1' ) {
    new FQI3_Awards();
}