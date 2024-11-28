<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3_Statistics Class
 *
 * Handles quiz statistics management including user performance tracking,
 * badge management, and visualization of statistics.
 *
 * Introduced in version 1.3.0, it includes methods for managing quiz data, generating performance tables,
 * and displaying statistics using a pie chart via the Chart.js library.
 *
 * @package    Form Quizz FQI3
 * @subpackage Statistics
 * @since      1.3.0
 * @version    2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

 if (!class_exists('FQI3_Statistics')):
    class FQI3_Statistics {
        /**
         * Maximum possible score for any quiz
         * @var int
         */
        private const MAX_SCORE_VALUE = 10;

        /**
         * Duration to cache statistics in seconds
         * @var int
         */
        private const CACHE_DURATION = DAY_IN_SECONDS;

        /**
         * URL for Chart.js CDN
         * @var string
         */
        private const CHART_CDN = 'https://cdn.jsdelivr.net/npm/chart.js';
        
        /**
         * Instance of the awards manager
         * @var ?FQI3_Awards
         */
        private ?FQI3_Awards $awards = null;

        /**
         * Available quiz levels
         * @var array
         */
        private array $levels;

        /**
         * Initialize the statistics manager
        */
        public function __construct() {
            $this->levels = fqi3_get_free_quiz_levels();
            $this->init_hooks();
        }

        private function init_hooks(): void {
            add_action('wp_ajax_update_quiz_statistics', [$this, 'handle_statistics_update']);
            add_action('wp_ajax_nopriv_update_quiz_statistics', [$this, 'handle_statistics_update']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
            
            // Register shortcodes for displaying statistics
            $this->register_shortcodes();
        }

        /*
        * Initialize FQI3_Awards only when needed
        *
        * @since 1.5.1
        */
        public function get_awards_instance() {
            if (is_null($this->awards)) {
                $this->awards = new FQI3_Awards();
            }
            return $this->awards;
        }

        /**
         * Creates the performance table upon plugin activation.
         * 
         * @since 1.3.0
         */
        public static function init_performance_database() {
            global $wpdb;
            $table_name = $wpdb->prefix . FQI3_TABLE_PERFORMANCE;
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                entry_id BIGINT(20) NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) NOT NULL,
                level VARCHAR(255) NOT NULL,
                total_quizzes INT NOT NULL DEFAULT 0,
                total_questions_answered INT NOT NULL DEFAULT 0,
                total_good_answers INT NOT NULL DEFAULT 0,
                success_rate FLOAT NOT NULL DEFAULT 0,
                best_score FLOAT DEFAULT NULL,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (entry_id),
                UNIQUE KEY user_level (user_id, level)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }

        /**
         * statistics-related shortcodes
         * 
         * @since 2.0.0
        */
        private function register_shortcodes(): void {
            $shortcodes = [
                'fqi3_user_statistics' => 'render_statistics_table',
                'fqi3_comparative_statistics' => 'display_comparative_stats',
                'fqi3_current_user_badges' => 'render_user_badge_cards'
            ];

            array_walk($shortcodes, function($callback, $tag) {
                add_shortcode($tag, [$this, $callback]);
            });
        }

        /**
         * Enqueues JavaScript resources required for charts.
         * 
         * - Includes Chart.js library from CDN
         * - Registers and loads pie chart initialization script
         * - Localizes chart data for JavaScript
         * 
         * @return void
         */
        public function enqueue_assets(): void {
            wp_enqueue_script('chartjs', self::CHART_CDN, [], null, true);
            wp_register_script('fqi3-pie-chart-init', FQI3_ASSETS . '/js/pie-chart.js', ['chartjs'], null, true);
            wp_enqueue_script('fqi3-pie-chart-init');

            $this->localize_chart_data();
        }

        /**
         * Prepares and localizes chart data for the current user.
         * 
         * Retrieves user statistics, prepares them for the chart, 
         * and makes them available client-side via wp_localize_script().
         * 
         * @return void
         */
        private function localize_chart_data(): void {
            $user_id = get_current_user_id();
            $stats = $this->get_user_statistics($user_id);
            
            $chart_data = $this->prepare_chart_data($stats);
            
            wp_localize_script('fqi3-pie-chart-init', 'fqi3PieChartData', $chart_data);
        }

        /**
         * Transforms raw statistics into chart-ready data.
         * 
         * @param array $stats Array of statistics by level
         * @return array Formatted chart data
         */
        private function prepare_chart_data(array $stats): array {
            $data = [];
            $levels = [];
            
            foreach ($stats as $level_stats) {
                $level = esc_html($level_stats['level']);
                $levels[] = $level;
                $data[$level] = [
                    'successRate' => $level_stats['success_rate']
                ];
            }
    
            return [
                'data' => $data,
                'levels' => $levels,
                'labels' => [
                    __('Success Rate', 'form-quizz-fqi3'),
                    __('Failure Rate', 'form-quizz-fqi3')
                ],
                'lineChartLabels' => [
                    'successRateLabel' => __('Success Rate', 'form-quizz-fqi3')
                ]
            ];
        } 

        /**
         * Handles statistics update via AJAX request.
         * 
         * Process:
         * 1. Validates the update request
         * 2. Processes statistics update
         * 3. Returns a JSON success or error response
         * 
         * @return void
         */
        public function handle_statistics_update(): void {
            try {
                $this->validate_update_request();
                
                $stats = $this->process_statistics_update();
                
                wp_send_json_success(__('Statistics updated successfully', 'form-quizz-fqi3'));
            } catch (\Exception $e) {
                wp_send_json_error($e->getMessage());
            }
        }
    
        /**
         * Validates the statistics update request.
         * 
         * Checks:
         * - Security nonce validity
         * - User permissions
         * 
         * @throws \Exception If validation fails
         * @return void
         */
        private function validate_update_request(): void {
            check_ajax_referer('fqi3_cookies_nonce', 'security');
    
            if (!fqi3_userHasAnyRole(get_current_user_id(), fqi3_getUserPremiumRoles())) {
                throw new \Exception(__('User not logged in or does not have the required role', 'form-quizz-fqi3'));
            }
        }
    
        /**
         * Processes statistics update for the current user.
         * 
         * Handles:
         * - Data retrieval and sanitization
         * - Statistics update or insertion
         * - Transient cache deletion
         * 
         * @return array Updated statistics data
         */
        private function process_statistics_update(): array {
            $user_id = get_current_user_id();
            $data = $this->get_sanitized_post_data();
            
            global $wpdb;
            $table_name = $wpdb->prefix . FQI3_TABLE_PERFORMANCE;
            
            $existing_stats = $this->get_existing_stats($wpdb, $table_name, $user_id, $data['level']);
            
            if ($existing_stats) {
                $this->update_existing_stats($wpdb, $table_name, $existing_stats, $data);
            } else {
                $this->insert_new_stats($wpdb, $table_name, $user_id, $data);
            }
            
            delete_transient('fqi3_stats_' . $user_id);
            
            return $data;
        }
    

        /**
         * Retrieves and sanitizes data from the POST request.
         *
         * @return array Sanitized POST data including level, correct answers, total questions, and calculated score.
         *
         * @since 2.0.0
         */
        private function get_sanitized_post_data(): array {
            return [
                'level' => sanitize_text_field($_POST['level'] ?? ''),
                'correct_answers' => intval($_POST['correct_answers'] ?? 0),
                'total_questions' => intval($_POST['total_questions'] ?? 0),
                'score' => $this->calculate_score(
                    intval($_POST['correct_answers'] ?? 0),
                    intval($_POST['total_questions'] ?? 0)
                )
            ];
        }
    
        /**
         * Calculates the score percentage based on correct answers and total questions.
         *
         * @param int $correct_answers Number of correct answers.
         * @param int $total_questions Total number of questions.
         * @return float The calculated score as a percentage.
         *
         * @since 2.0.0
         */
        private function calculate_score(int $correct_answers, int $total_questions): float {
            return ($total_questions > 0) ? ($correct_answers / $total_questions) * 100 : 0;
        }
    
        /**
         * Retrieves existing statistics for a user and level from the database.
         *
         * @param wpdb $wpdb The WordPress database object.
         * @param string $table_name The name of the database table.
         * @param int $user_id The ID of the user.
         * @param string $level The level of the quiz.
         * @return object|null The existing statistics row, or null if not found.
         *
         * @since 2.0.0
         */
        private function get_existing_stats($wpdb, string $table_name, int $user_id, string $level) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d AND level = %s",
                $user_id, $level
            ));
        }
    
        /**
         * Updates the existing statistics for a user and level in the database.
         *
         * @param wpdb $wpdb The WordPress database object.
         * @param string $table_name The name of the database table.
         * @param object $existing_stats The existing statistics row.
         * @param array $data New data to update the statistics with.
         * @return void
         *
         * @since 2.0.0
         */
        private function update_existing_stats($wpdb, string $table_name, object $existing_stats, array $data): void {
            $updated_stats = $this->calculate_updated_stats($existing_stats, $data);
            
            $wpdb->update(
                $table_name,
                $updated_stats,
                ['user_id' => get_current_user_id(), 'level' => $data['level']],
                ['%d', '%d', '%d', '%f', '%f'],
                ['%d', '%s']
            );
        }
    
        /**
         * Calculates updated statistics based on existing statistics and new data.
         *
         * @param object $existing_stats The existing statistics row.
         * @param array $data New data to update the statistics with.
         * @return array The updated statistics data.
         *
         * @since 2.0.0
         */
        private function calculate_updated_stats(object $existing_stats, array $data): array {
            $total_quizzes = $existing_stats->total_quizzes + 1;
            $total_good_answers = $existing_stats->total_good_answers + $data['correct_answers'];
            $total_questions = $existing_stats->total_questions_answered + $data['total_questions'];
            
            return [
                'total_quizzes' => $total_quizzes,
                'total_questions_answered' => $total_questions,
                'total_good_answers' => $total_good_answers,
                'success_rate' => ($total_good_answers / $total_questions) * 100,
                'best_score' => max($existing_stats->best_score, $data['score'])
            ];
        }
    
        /**
         * Inserts new statistics for a user and level into the database.
         *
         * @param wpdb $wpdb The WordPress database object.
         * @param string $table_name The name of the database table.
         * @param int $user_id The ID of the user.
         * @param array $data The statistics data to insert.
         * @return void
         *
         * @since 2.0.0
         */
        private function insert_new_stats($wpdb, string $table_name, int $user_id, array $data): void {
            $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'level' => $data['level'],
                    'total_quizzes' => 1,
                    'total_questions_answered' => $data['total_questions'],
                    'total_good_answers' => $data['correct_answers'],
                    'success_rate' => $data['score'],
                    'best_score' => $data['score']
                ],
                ['%d', '%s', '%d', '%d', '%d', '%f', '%f']
            );
        }
    
         /**
         * Retrieves the statistics of the current user.
         *
         * @param int $user_id ID of the user.
         * @return array User's statistics.
         * 
         * @since 1.3.0 Initial
         * @since 2.0.0 Refactored
         */
        public function get_user_statistics(int $user_id): array {
            $cache_key = 'fqi3_stats_' . $user_id;
            $stats = get_transient($cache_key);
    
            if ($stats === false) {
                $stats = $this->fetch_user_statistics($user_id);
                if ($stats) {
                    set_transient($cache_key, $stats, self::CACHE_DURATION);
                }
            }
    
            return $stats ?: [];
        }
    
        /**
         * Fetches the user's performance statistics from the database.
         *
         * @param int $user_id The ID of the user whose statistics are being fetched.
         * @return array An array of statistics, or an empty array if no data is found.
         *
         * @since 1.3.0 Initial
         * @since 2.0.0 Refactored
         */
        private function fetch_user_statistics(int $user_id): array {
            global $wpdb;
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}" . FQI3_TABLE_PERFORMANCE . " WHERE user_id = %d",
                    $user_id
                ),
                ARRAY_A
            ) ?: [];
        }
    
        /**
        * Generates the user's statistics table.
        * 
        * This method checks if the user is logged in, if they have the required permissions, 
        * and if the statistics feature is enabled. If everything is valid, it retrieves the 
        * user's statistics and generates an HTML table for display.
        * 
        * @since 1.3.0
        * 
        * @return string The HTML table or an error message if the user is not authorized or no statistics are found.
        */
        public function render_statistics_table(): string {
            $validation_result = $this->validate_user_permissions('your statistics');
            if ($validation_result !== null) {
                return $this->render_error_message($validation_result);
            }
    
            $stats = $this->get_user_statistics(get_current_user_id());
            if (empty($stats)) {
                return $this->render_error_message(
                    __('No statistics found for your account.', 'form-quizz-fqi3')
                );
            }
    
            return $this->generate_statistics_html($stats);
        }
    
        /**
         * Validates the user's permissions to view statistics.
         * 
         * This method checks whether the user is logged in, if they have the required
         * permissions (premium member), and whether the statistics feature is disabled
         * via plugin options.
         * 
         * @since 1.3.0
         * 
         * @param array $options The plugin options array.
         * @return string|null An error message if validation fails, or null if all checks pass.
         */
        public function validate_user_permissions(string $subject = null): ?string {
            if ($subject === null) {
                $subject = __('this section', 'form-quizz-fqi3');
            }
            $user_id = get_current_user_id();
            $options = fqi3_get_options();
    
            if (isset($options['fqi3_disable_statistics']) && $options['fqi3_disable_statistics'] === 1) {
                return __('Statistics option disabled. For more details, please inquire with the administrator.', 'form-quizz-fqi3');
            }
    
            if (!$user_id) {
                /* translators: %s is the subject section in a shortcode */
                return sprintf(__('If you want to access %s, you must be logged in.', 'form-quizz-fqi3'), $subject);
            }
    
            if (!fqi3_userHasAnyRole($user_id, fqi3_getUserPremiumRoles())) {
                /* translators: %s is the subject section in a shortcode */
                return sprintf(__('You must be a premium member to view %s.', 'form-quizz-fqi3'), $subject);
            }
    
            return null;
        }
    
        /**
         * Generates the HTML table to display the user's statistics.
         * 
         * This method receives the user's statistics data and constructs an HTML table
         * to present the statistics in a readable format.
         * 
         * @since 1.3.0 Initial
         * @since 2.0.0 Refactored
         * 
         * @param array $stats The statistics data for the user.
         * @return string The generated HTML table.
         */
        private function generate_statistics_html(array $stats): string {
            $output = $this->initialize_stats_container();
            $output .= $this->generate_stats_table($stats);
            $output .= $this->generate_pie_charts($stats);
            $output .= '</div>';
            
            return $output;
        }
    
        /**
         * Initializes the HTML container for displaying user statistics.
         *
         * @return string The opening HTML structure for the user statistics container.
         *
         * @since 2.0.0
         */
        private function initialize_stats_container(): string {
            return '<div class="fqi3-user-stats"><h2>' . 
                   __('Your statistics', 'form-quizz-fqi3') . '</h2>' .
                   '<div class="x-scroll">';
        }
    
        /**
         * Generates the HTML table containing user statistics.
         *
         * @param array $stats The user statistics data.
         * @return string The constructed HTML table for displaying user statistics.
         *
         * @since 2.0.0
         */
        private function generate_stats_table(array $stats): string {
            $table = new StatsTableBuilder();
            $totals = [
                'quizzes' => 0, 
                'good_answers' => 0, 
                'questions' => 0
            ];
            
            $table->addHeader([
                __('Level', 'form-quizz-fqi3'),
                __('Total Quizzes', 'form-quizz-fqi3'),
                __('Total Questions Answered', 'form-quizz-fqi3'),
                __('Correct Answers', 'form-quizz-fqi3'),
                __('Overall average', 'form-quizz-fqi3'),
                __('Success Rate', 'form-quizz-fqi3'),
                __('Best Score', 'form-quizz-fqi3')
            ]);
    
            foreach ($stats as $level_stats) {
                $table->addRow($this->prepare_stats_row($level_stats));
                $this->update_totals($totals, $level_stats);
            }
    
            $table->addFooter($this->calculate_average_score($totals));
            
            return $table->build();
        }
    
        /**
         * Prepares a single row of data for the statistics table.
         *
         * @param array $level_stats The statistics data for a specific level.
         * @return array An array of formatted values for a table row.
         *
         * @since 2.0.0
         */
        private function prepare_stats_row(array $level_stats): array {
            $labelLevel = $this->levels[$level_stats['level']]['label'] ?? $level_stats['level'];
            $average_correct_answers = $level_stats['success_rate'] / self::MAX_SCORE_VALUE;
            $best_score_display = $this->format_best_score($level_stats['best_score']);
    
            return [
                esc_html($labelLevel),
                esc_html($level_stats['total_quizzes']),
                esc_html($level_stats['total_questions_answered']),
                esc_html($level_stats['total_good_answers']),
                esc_html(number_format($average_correct_answers, 1)) . ' / ' . self::MAX_SCORE_VALUE,
                esc_html(number_format($level_stats['success_rate'], 2)) . '%',
                $best_score_display
            ];
        }
    
        /**
         * Formats the best score for display.
         *
         * @param float|null $score The best score value.
         * @return string A formatted string representing the best score or 'N/A' if unavailable.
         *
         * @since 2.0.0
         */
        private function format_best_score(?float $score): string {
            return isset($score) && $score !== null ? esc_html($score) . '%' : __('N/A', 'form-quizz-fqi3');
        }
    
        /**
         * Updates the totals for quizzes, correct answers, and questions.
         *
         * @param array $totals A reference to the totals array.
         * @param array $level_stats The statistics data for a specific level.
         * @return void
         *
         * @since 2.0.0
         */
        private function update_totals(array &$totals, array $level_stats): void {
            $totals['quizzes'] += $level_stats['total_quizzes'];
            $totals['good_answers'] += $level_stats['total_good_answers'];
            $totals['questions'] += $level_stats['total_questions_answered'];
        }
    
        /**
         * Calculates the average score from the provided totals.
         *
         * @param array $totals The totals array containing quizzes, good answers, and questions.
         * @return array An array containing the average score label and value.
         *
         * @since 2.0.0
         */
        private function calculate_average_score(array $totals): array {
            $average_success_rate = $totals['questions'] > 0 
                ? ($totals['good_answers'] / $totals['questions']) * 100 
                : 0;
    
            return [
                'label' => __('Average Score', 'form-quizz-fqi3'),
                'value' => sprintf(
                    '%s / %d (%s%%)',
                    number_format($average_success_rate / self::MAX_SCORE_VALUE, 1),
                    self::MAX_SCORE_VALUE,
                    number_format($average_success_rate, 2)
                )
            ];
        }
    
         /**
         * Generates an HTML snippet for a pie chart showing success rate for a given level.
         *
         * @param array $level_stats The statistics for the level.
         * @return string HTML output for the pie chart.
         * 
         * @since 1.3.0 Initial
         * @since 2.0.0 Refactored
         */
        private function generate_pie_charts(array $stats): string {
            $output = '<div class="fqi3-pie-charts-container mt-20">';
            foreach ($stats as $level_stats) {
                $output .= $this->generate_single_pie_chart($level_stats);
            }
            return $output . '</div>';
        }
    
        /**
         * Generates the HTML for a single pie chart based on level statistics.
         *
         * Validates the input data and prepares the necessary information before
         * rendering the HTML for the pie chart. If the data is invalid, returns an empty string.
         *
         * @param array $level_stats An associative array
         * @return string The HTML output for the pie chart, or an empty string if validation fails.
         *
         * @since 2.0.0
         */
        private function generate_single_pie_chart(array $level_stats): string {
            if (!$this->validate_pie_chart_data($level_stats)) {
                return '';
            }
    
            $chart_data = $this->prepare_pie_chart_data($level_stats);
            return $this->render_pie_chart_html($chart_data);
        }
    
        /**
         * Validates the input data for generating a pie chart.
         *
         * Checks if the required keys ('success_rate' and 'total_quizzes') are present
         * in the provided data array.
         *
         * @param array $level_stats An associative array of level statistics.
         * @return bool True if the data is valid, false otherwise.
         *
         * @since 2.0.0
         */
        private function validate_pie_chart_data(array $level_stats): bool {
            return isset($level_stats['success_rate'], $level_stats['total_quizzes']);
        }
    
        /**
         * Prepares the data needed for rendering a pie chart.
         *
         * Extracts and sanitizes relevant data from the provided level statistics array,
         * ensuring the data is safe and formatted correctly for use in the pie chart.
         *
         * @param array $level_stats An associative array 
         * @return array An associative array with sanitized and prepared data:
         *
         * @since 2.0.0
         */
        private function prepare_pie_chart_data(array $level_stats): array {
            return [
                'success_rate' => $level_stats['success_rate'],
                'total_quizzes' => (int)$level_stats['total_quizzes'],
                'level' => esc_html($level_stats['level']),
                'label_level' => $this->levels[$level_stats['level']]['label'] ?? ''
            ];
        }
    
        /**
         * Renders the HTML for a single pie chart with relevant data.
         *
         * Generates the HTML structure for a pie chart representing the success rate
         * and additional completion details for a given quiz level.
         *
         * @param array $chart_data An associative array containing the chart data
         * @return string The HTML output for the pie chart.
         *
         * @since 1.3.0 Initial realease
         * @since 2.0.0 Refactored version
         */
        private function render_pie_chart_html(array $chart_data): string {
            $canvas_id = 'pieChart-' . esc_attr($chart_data['level']);
            
            return sprintf(
                '<div class="fqi3-single-pie-chart">
                    <div class="fqi3-pie-chart">
                        <canvas id="%s"></canvas>
                        <div class="fqi3-pie-chart-text">%s%%</div>
                    </div>
                    <div class="fqi3-pie-chart-infos">%s</div>
                </div>',
                $canvas_id,
                esc_html($chart_data['success_rate']),
                $this->get_quiz_completion_text($chart_data)
            );
        }
    
        /**
         * Retrieves the text describing quiz completion statistics for a level.
         *
         * Generates a localized string indicating the number of quizzes completed
         * and the associated level for display purposes.
         *
         * @param array $chart_data An associative array
         * @return string The localized quiz completion text.
         *
         * @since 1.3.0 Initial realease
         * @since 2.0.0 Refactored version
         */
        private function get_quiz_completion_text(array $chart_data): string {
            return sprintf(
                _n(
                    // translators: %s is the number of quizzes completed.
                    '%s quiz completed', 
                    '%s quizzes completed', 
                    $chart_data['total_quizzes'], 
                    'form-quizz-fqi3'
                ),
                $chart_data['total_quizzes']
            ) . ' ' . sprintf(
                // translators: %s is the level (e.g., 'beginner', 'intermediate', 'advanced') where the action or achievement took place.
                esc_html__('in %s level', 'form-quizz-fqi3'),
                $chart_data['label_level']
            );
        }

        /**
         * Displays the comparative statistics for the current user.
         *
         * Validates the user's permissions to view comparative statistics, prepares
         * the necessary data, and generates the corresponding HTML output.
         * If the user lacks permissions or no data is available, an error message is rendered.
         *
         * @return string The HTML output for the comparative statistics or an error message.
         *
         * @since 1.3.0 Initial realease
         * @since 2.0.0 Refactored version
         */
        public function display_comparative_stats(): string {
            $validation_result = $this->validate_user_permissions('your comparative statistics');
            if ($validation_result !== null) {
                return $this->render_error_message($validation_result);
            }
    
            $comparative_data = $this->prepare_comparative_data();
            if (empty($comparative_data)) {
                return $this->render_error_message(
                    __('No statistics', 'form-quizz-fqi3')
                );
            }
    
            return $this->generate_comparative_stats_html($comparative_data);
        }
    
        /**
         * Prepares comparative data for user and global statistics.
         *
         * Fetches the user's statistics and global statistics, processes them,
         * and organizes them for display in comparative tables.
         *
         * @return array An array containing 'user_scores' and 'global_scores'.
         *
         * @since 2.0.0
         */
        private function prepare_comparative_data(): array {
            $user_stats = $this->get_user_statistics(get_current_user_id());
            $comparative_stats = $this->get_comparative_stats();
    
            if (empty($comparative_stats)) {
                return [];
            }
    
            return [
                'user_scores' => $this->extract_user_scores($user_stats),
                'global_scores' => $this->extract_global_scores($comparative_stats)
            ];
        }
    
        /**
         * Extracts the user's best scores by level from the statistics data.
         *
         * @param array $stats The user's statistics data.
         * @return array An associative array mapping levels to the user's best scores.
         *
         * @since 2.0.0
         */
        private function extract_user_scores(array $stats): array {
            $scores = [];
            foreach ($stats as $stat) {
                $scores[$stat['level']] = $stat['best_score'];
            }
            return $scores;
        }
    
        /**
         * Extracts the global best scores by level from the statistics data.
         *
         * Processes the global statistics data to determine the highest scores for each level.
         *
         * @param array $stats The global statistics data.
         * @return array An associative array mapping levels to the global best scores.
         *
         * @since 2.0.0
         */
        private function extract_global_scores(array $stats): array {
            $scores = [];
            foreach ($stats as $stat) {
                $level = $stat['level'];
                if (!isset($scores[$level]) || $stat['best_score'] > $scores[$level]) {
                    $scores[$level] = $stat['best_score'];
                }
            }
            return $scores;
        }
    
        /**
         * Generates the HTML output for comparative statistics.
         *
         * Builds a comparative statistics table showing the user's best scores
         * alongside the global best scores for each level.
         *
         * @param array $comparative_data The data containing 'user_scores' and 'global_scores'.
         * @return string The HTML output for the comparative statistics section.
         *
         * @since 1.3.0 Initial release
         * @since 2.0.0 Refactored version
         */
        private function generate_comparative_stats_html(array $comparative_data): string {
            $levels = $this->levels;
            $user_scores = $comparative_data['user_scores'];
            $global_scores = $comparative_data['global_scores'];
            
            $output = '<div class="fqi3-comparative-stats">';
            $output .= '<h2>' . __('Comparative Statistics', 'form-quizz-fqi3') . '</h2>';
            $output .= '<div class="x-scroll">';
            $output .= '<table class="fqi3-table">';
            $output .= '<thead><tr>';
            $output .= '<th>' . __('Level', 'form-quizz-fqi3') . '</th>';
            $output .= '<th>' . __('Your Best Score', 'form-quizz-fqi3') . '</th>';
            $output .= '<th>' . __('The Best Overall Score', 'form-quizz-fqi3') . '</th>';
            $output .= '</tr></thead>';
            $output .= '<tbody>';
        
            $level_ids = array_unique(array_merge(array_keys($user_scores), array_keys($global_scores)));
            
            foreach ($level_ids as $level_id) {
                $no_data_message = (!fqi3_userHasAnyRole(get_current_user_id(), fqi3_getUserPremiumRoles())) 
                    ? __('You do not have access to this feature', 'form-quizz-fqi3') 
                    : __('No data yet', 'form-quizz-fqi3');
        
                $user_best_score = isset($user_scores[$level_id]) 
                    ? $user_scores[$level_id] . '%' 
                    : $no_data_message;
                    
                $global_best_score = isset($global_scores[$level_id]) 
                    ? $global_scores[$level_id] . '%' 
                    : __('No data yet', 'form-quizz-fqi3');
        
                $user_score_class = ($user_best_score === $global_best_score) ? 'best-score' : 'not-best-score';
        
                $output .= '<tr>';
                $output .= '<td>' . esc_html($levels[$level_id]["label"]) . '</td>';
                $output .= '<td class="' . esc_attr($user_score_class) . '">' . esc_html($user_best_score) . '</td>';
                $output .= '<td>' . esc_html($global_best_score) . '</td>';
                $output .= '</tr>';
            }
        
            $output .= '</tbody></table></div></div>';
            
            return $output;
        }
    
        /**
         * Retrieves comparative statistics for all levels.
         *
         * This function retrieves the highest scores achieved across all users for each level.
         *
         * @return array Comparative statistics data, with each entry containing a level and the highest score for that level.
         * 
         * @since 1.3.0
         */
        private function get_comparative_stats(): array {
            global $wpdb;
            $table_name = $wpdb->prefix . FQI3_TABLE_PERFORMANCE;
            
            $results = $wpdb->get_results(
                "SELECT level, MAX(best_score) AS best_score 
                FROM $table_name 
                WHERE best_score IS NOT NULL 
                GROUP BY level 
                ORDER BY level",
                ARRAY_A
            );
    
            // Optionally sort results if you want to have them in a specific order
            usort($results, function($a, $b) {
                return strcmp($a['level'], $b['level']);
            });
    
            return $results ?: [];
        }
    
        /**
         * Handles the shortcode for displaying user badge cards.
         *
         * @return string HTML output of badge cards or error message
        */
        public function render_user_badge_cards(): string {
            $validation_result = $this->validate_badge_access();
            if ($validation_result !== null) {
                return $this->render_error_message($validation_result);
            }
        
            $user_stats = $this->get_user_statistics(get_current_user_id());
            if (empty($user_stats)) {
                return $this->render_error_message(
                    __('No badges found for your account.', 'form-quizz-fqi3')
                );
            }
        
            return $this->generate_badge_cards_html();
        }
    
        /**
         * Validates user access to badge features.
         *
         * @return string|null Error message if validation fails, null if successful
        */
        private function validate_badge_access(): ?string {
            return $this->validate_user_permissions(
                get_current_user_id(),
                fqi3_get_options(),
                __('your badges', 'form-quizz-fqi3')
            );
        }
    
        /**
         * Renders an error message with consistent styling.
         *
         * @param string $message The error message to display
         * @return string HTML formatted error message
        */
        private function render_error_message(string $message): string {
            return sprintf('<p class="fqi3-error-message">%s</p>', esc_html($message));
        }
    
        /**
         * Generates the HTML for displaying badge cards.
         *
         * @return string HTML output of badge cards
        */
        private function generate_badge_cards_html(): string {
            $badges = $this->get_awards_instance()->display_user_badges();
            
            return $this->render_awards_container([
                $this->render_badge_section_title($badges),
                $this->render_badge_cards($badges),
                $this->render_badges_legend()
            ]);
        }
    
        /**
         * Renders the containing element for all badge-related content.
         *
         * @param array $sections Array of HTML sections to include
         * @return string Complete HTML container
        */
        private function render_awards_container(array $sections): string {
            return sprintf(
                '<div class="fqi3-awards-container">%s</div>',
                implode('', array_filter($sections))
            );
        }
    
        /**
         * Renders the section title with proper pluralization.
         *
         * @param array $badges Array of user badges
         * @return string HTML title section
        */
        private function render_badge_section_title(array $badges): string {
            $badge_count = count($badges);
            return sprintf(
                '<h2>%s</h2>',
                sprintf(
                    _n('Awarded badge', 'Awarded badges', $badge_count, 'form-quizz-fqi3'),
                    $badge_count
                )
            );
        }
    
        /**
         * Renders the badge cards section.
         *
         * @param array $badges Array of user badges
         * @return string HTML of badge cards
        */
        private function render_badge_cards(array $badges): string {
            if (empty($badges)) {
                return sprintf(
                    '<p>%s</p>',
                    __('No badges awarded.', 'form-quizz-fqi3')
                );
            }
    
            $cards = array_map([$this, 'render_single_badge_card'], $badges);
            
            return sprintf(
                '<div class="user-badges">%s</div>',
                implode('', $cards)
            );
        }
    
        /**
         * Renders a single badge card.
         *
         * @param array $badge Badge data
         * @return string HTML for single badge card
        */
        private function render_single_badge_card(array $badge): string {
            $badge_image_url = wp_get_attachment_url($badge['badge_id']);
            
            $image_html = sprintf(
                '<div class="badge-image"><img src="%s" alt="%s" /></div>',
                esc_url($badge_image_url),
                esc_attr($badge['name'])
            );
            
            $info_html = sprintf(
                '<div class="badge-info"><h3>%s</h3><p>%s %s</p></div>',
                esc_html($badge['name']),
                __('Awarded on', 'form-quizz-fqi3'),
                esc_html($badge['awarded_at'])
            );
            
            return sprintf(
                '<div class="badge-card">%s%s</div>',
                $image_html,
                $info_html
            );
        }
    
        /**
         * Renders the badges legend section if enabled.
         *
         * @return string|null HTML of badges legend or null if disabled
         */
        private function render_badges_legend(): ?string {
            $badges_options = get_option('fqi3_badges', []);
            
            if (isset($badges_options['fqi3_disable_badges_legend']) 
                && $badges_options['fqi3_disable_badges_legend']) {
                return null;
            }
            
            return $this->get_awards_instance()->display_all_badges();
        }
    }
    
    /**
     * Helper class for building HTML tables
     * 
     * @since 2.0.0
     */
    class StatsTableBuilder {
        private array $headers = [];
        private array $rows = [];
        private array $footer = [];
    
        public function addHeader(array $headers): void {
            $this->headers = $headers;
        }
    
        public function addRow(array $row): void {
            $this->rows[] = $row;
        }
    
        public function addFooter(array $footer): void {
            $this->footer = $footer;
        }
    
        public function build(): string {
            $html = '<table class="fqi3-table">';
            $html .= $this->buildHeader();
            $html .= $this->buildBody();
            $html .= $this->buildFooter();
            $html .= '</table>';
            return $html;
        }
    
        private function buildHeader(): string {
            $html = '<thead><tr>';
            foreach ($this->headers as $header) {
                $html .= '<th>' . esc_html($header) . '</th>';
            }
            $html .= '</tr></thead>';
            return $html;
        }
    
        private function buildBody(): string {
            $html = '<tbody>';
            foreach ($this->rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . $cell . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
            return $html;
        }
    
        private function buildFooter(): string {
            if (empty($this->footer)) {
                return '';
            }
    
            return sprintf(
                '<tfoot><tr>
                    <td colspan="5" style="text-align:right; font-weight:bold;">%s</td>
                    <td colspan="2" style="text-align:center; font-weight:bold;">%s</td>
                </tr></tfoot>',
                esc_html($this->footer['label']),
                esc_html($this->footer['value'])
            );
        }
    }
    
endif;

new FQI3_Statistics();