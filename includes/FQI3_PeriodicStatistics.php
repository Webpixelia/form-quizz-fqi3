<?php
namespace Form_Quizz_FQI3;
/**
* FQI3_PeriodicStatistics Class
*
* This class is responsible for managing the periodic statistics for the Form Quizz FQI3 plugin.
* It handles the creation of the periodic statistics table, scheduling of cron jobs, and displaying
* of the periodic statistics charts using a shortcode.
*
* @package    Form Quizz FQI3
* @subpackage Statistics
* @since      2.0.0
* @version    2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_PeriodicStatistics' ) ) :
    class FQI3_PeriodicStatistics extends FQI3_Statistics {
        /** @var FQI3_Template_Manager */
        private FQI3_Template_Manager $template_manager;
    
        private $tableName;
    
        public function __construct() {
            global $wpdb;
            $this->tableName = $wpdb->prefix . FQI3_TABLE_PERIODIC_STATISTICS;
            $this->template_manager = new FQI3_Template_Manager();
            $this->createPeriodicStatsTable();
            
            // Schedule weekly and monthly cron tasks
            add_action('init', [$this, 'schedulePeriodicStatisticsCronJobs']);
            add_action('fqi3_weekly_statistics_event', [$this, 'recordWeeklyStatistics']);
            add_action('fqi3_monthly_statistics_event', [$this, 'recordMonthlyStatistics']);
            
            // Register shortcode for displaying charts
            add_shortcode('fqi3_periodic_stats', [$this, 'displayPeriodicStatisticsShortcode']);

            // Hook
            add_action('fqi3_after_statistics_page', [$this, 'render_manual_test_advanced_stats']);
        }
    
        /**
        * Create the periodic performance table if it doesn't exist
        */
        private function createPeriodicStatsTable(): void {
            global $wpdb;

            if ($this->doesPeriodicStatsTableExist()) {
                return;
            }        
            
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
                entry_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT(20) NOT NULL,
                level VARCHAR(255) NOT NULL,
                period_type ENUM('weekly', 'monthly') NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                total_quizzes INT(11) DEFAULT 0,
                total_questions_answered INT(11) DEFAULT 0,
                total_good_answers INT(11) DEFAULT 0,
                success_rate FLOAT NOT NULL,
                best_score FLOAT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
        * Check if the periodic statistics table exists
        * 
        * @return bool
        */
        private function doesPeriodicStatsTableExist(): bool {
            global $wpdb;
            $query = $wpdb->prepare("SHOW TABLES LIKE %s", $this->tableName);
            return $wpdb->get_var($query) === $this->tableName;
        }
    
        /**
        * Schedule cron jobs for weekly and monthly statistics recording
        */
        public function schedulePeriodicStatisticsCronJobs(): void {
            if (!wp_next_scheduled('fqi3_weekly_statistics_event')) {
                wp_schedule_event(strtotime('next Sunday 23:59'), 'weekly', 'fqi3_weekly_statistics_event');
            }
            if (!wp_next_scheduled('fqi3_monthly_statistics_event')) {
                wp_schedule_event(strtotime('first day of next month midnight'), 'monthly', 'fqi3_monthly_statistics_event');
            }
        }
    
        /**
         * Record weekly statistics
         */
        public function recordWeeklyStatistics(): void {
            $startDate = date('Y-m-d', strtotime('last Sunday -7 days'));
            $endDate = date('Y-m-d', strtotime('last Sunday'));
            $this->recordPeriodicStatistics('weekly', $startDate, $endDate);
        }
    
        /**
         * Record monthly statistics
         */
        public function recordMonthlyStatistics(): void {
            $startDate = date('Y-m-01', strtotime('first day of last month'));
            $endDate = date('Y-m-t', strtotime('last day of last month'));
            $this->recordPeriodicStatistics('monthly', $startDate, $endDate);
        }
    
        /**
         * Core function to record statistics based on period
         * 
         * @param string $period_type 'weekly' or 'monthly'
         * @param string $start_date Start date of the period
         * @param string $end_date End date of the period
         * @param int $userId The ID of a specific user for whom statistics should be recorded (optional).
         */
        private function recordPeriodicStatistics(string $periodType, string $startDate, string $endDate, int $userId = null): void {
            global $wpdb;
        
            $userCondition = $userId ? [$startDate, $endDate, $userId] : [$startDate, $endDate];
                
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT user_id, level,
                    total_quizzes,
                    total_questions_answered,
                    total_good_answers,
                    success_rate,
                    best_score
                FROM {$wpdb->prefix}" . FQI3_TABLE_PERFORMANCE . "
                WHERE DATE(last_updated) BETWEEN %s AND %s
                GROUP BY user_id, level
            ", ...$userCondition));
        
            if (empty($results)) {
                throw new \Exception(__('No data found for the specified period.', 'form-quizz-fqi3'));
            }
        
            foreach ($results as $row) {
                $wpdb->insert($this->tableName, [
                    'user_id' => $row->user_id,
                    'level' => $row->level,
                    'period_type' => $periodType,
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                    'total_quizzes' => $row->total_quizzes,
                    'total_questions_answered' => $row->total_questions_answered,
                    'total_good_answers' => $row->total_good_answers,
                    'success_rate' => $row->success_rate,
                    'best_score' => $row->best_score
                ]);
            }
        }
             
    
        /**
         * Display periodic statistics shortcode
         * 
         * @param array $atts Shortcode attributes
         * @return string HTML output
         */
        public function displayPeriodicStatisticsShortcode($atts): string {
            $userId = get_current_user_id();
            $atts = shortcode_atts([
                'user_id' => $userId
            ], $atts);
        
            $levels = fqi3_get_free_quiz_levels();
            $options = fqi3_get_options();
            $subject = __('your advanced statistics', 'form-quizz-fqi3');
            $errorMessage = parent::validate_user_permissions($userId, $options, $subject);

            if ($errorMessage) {
                $output = '<p>' . esc_html($errorMessage) . '</p>';
                return $output; 
            }

            $output = '<div class="fqi3-periodic-stats fqi3-shortcode">';
            $output .= '<h2>' . __('Your advanced statistics', 'form-quizz-fqi3') . '</h2>';
        
            $hasStats = false;
            foreach ($levels as $level => $levelData) {
                $monthlyData = $this->getPeriodicStatistics($atts['user_id'], $level, 'monthly');
                $weeklyData = $this->getPeriodicStatistics($atts['user_id'], $level, 'weekly');
        
                if (empty($monthlyData['periods']) && empty($weeklyData['periods'])) {
                    continue;
                }

                $hasStats = true;
                $output .= $this->generatePieChartsHtml($levelData, $monthlyData, $weeklyData);
            }

            if (!$hasStats) {
                $output .= '<p class="mt-20">' . __('No advanced statistics yet but that shouldn\'t last long if you keep up your efforts by regularly completing quizzes.', 'form-quizz-fqi3') . '</p>';
            }
        
            $output .= '</div>';
            return $output;
        }
        
        
        /**
         * Get the periodic statistics for a user and a specific level
         * 
         * @param int $user_id User ID
         * @param string $level Quiz level
         * @param string $period_type 'weekly' or 'monthly'
         * @return array
         */
        private function getPeriodicStatistics(int $userId, string $level, string $periodType): array {
            global $wpdb;
        
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT period_start, period_end, AVG(success_rate) AS avg_success_rate
                FROM {$this->tableName}
                WHERE user_id = %d AND level = %s AND period_type = %s
                GROUP BY period_start
                ORDER BY period_start
            ", $userId, $level, $periodType));
        
            $chartData = [
                'periods' => [],
                'success_rates' => []
            ];
        
            foreach ($results as $row) {
                $chartData['periods'][] = $row->period_start;
                $chartData['success_rates'][] = $row->avg_success_rate;
            }
        
            return $chartData;
        }

        /**
         * Generate the HTML for the pie charts
         * 
         * @param array $levelData Level data
         * @param array $monthlyData Monthly statistics data
         * @param array $weeklyData Weekly statistics data
         * @return string
         */
        private function generatePieChartsHtml(array $levelData, array $monthlyData, array $weeklyData): string {
            $html = '<div class="mt-20">';
            $html .= '<h3>' . esc_html($levelData['label']) . '</h3>';
            $html .= '<div class="fqi3-pie-charts-container mt-20">';
            $html .= '<div class="fqi3-single-pie-chart" style="max-width:unset;">';
            $html .= '<div class="fqi3-pie-chart-infos">' . esc_html($levelData['label']) . ' - ' . __('Monthly Statistics', 'form-quizz-fqi3') . '</div>';
            $html .= '<canvas id="monthly-' . esc_attr(sanitize_title($levelData['label'])) . '-chart" data-stats=\'' . json_encode($monthlyData) . '\'></canvas>';
            $html .= '</div>';
            $html .= '<div class="fqi3-single-pie-chart" style="max-width:unset;">';
            $html .= '<div class="fqi3-pie-chart-infos">' . esc_html($levelData['label']) . ' - ' . __('Weekly Statistics', 'form-quizz-fqi3') . '</div>';
            $html .= '<canvas id="weekly-' . esc_attr(sanitize_title($levelData['label'])) . '-chart" data-stats=\'' . json_encode($weeklyData) . '\'></canvas>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            return $html;
        }

        /**
         * Renders the manual test advanced statistics form and handles form submission.
         * 
         * @since 2.0.0
         * @return void
         */
        public function render_manual_test_advanced_stats(): void {
            try {
                // Verify nonce first if this is a form submission
                if (isset($_POST['record_weekly_stats'])) {
                    if (!isset($_POST['stats_nonce']) || !wp_verify_nonce($_POST['stats_nonce'], 'record_weekly_stats_nonce')) {
                        throw new \Exception(__('Security verification failed.', 'form-quizz-fqi3'));
                    }
                }

                // Include the form template
                echo $this->template_manager->render('form-manually-save-stats');

                // Process form submission
                if (isset($_POST['record_weekly_stats'])) {
                    // Validate required fields
                    $required_fields = ['user_id', 'start_date', 'end_date'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new \Exception(sprintf(
                                /* translators: %s: field name */
                                __('Field %s is required.', 'form-quizz-fqi3'),
                                $field
                            ));
                        }
                    }

                    // Sanitize and validate input data
                    $user_id = (int) sanitize_text_field($_POST['user_id']);
                    $start_date = sanitize_text_field($_POST['start_date']);
                    $end_date = sanitize_text_field($_POST['end_date']);

                    // Validate user exists
                    if (!get_user_by('ID', $user_id)) {
                        throw new \Exception(__('Invalid user ID.', 'form-quizz-fqi3'));
                    }

                    // Validate dates
                    $start_timestamp = strtotime($start_date);
                    $end_timestamp = strtotime($end_date);
                    
                    if (!$start_timestamp || !$end_timestamp) {
                        throw new \Exception(__('Invalid date format.', 'form-quizz-fqi3'));
                    }

                    // Convert timestamps to DateTime objects for more accurate comparison
                    $start_datetime = new \DateTime($start_date);
                    $end_datetime = new \DateTime($end_date);
                    
                    // Ensure end date is after start date
                    if ($end_datetime <= $start_datetime) {
                        throw new \Exception(__('End date must be after start date.', 'form-quizz-fqi3'));
                    }

                    // Calculate date difference
                    $date_diff = $start_datetime->diff($end_datetime);
                    
                    // Add maximum range validation (e.g., max 1 year)
                    $max_days = 365; // Configurable maximum days
                    if ($date_diff->days > $max_days) {
                        throw new \Exception(sprintf(
                            /* translators: %d: maximum number of days */
                            __('Date range cannot exceed %d days.', 'form-quizz-fqi3'),
                            $max_days
                        ));
                    }

                    // Validate against future dates
                    $current_date = new \DateTime();
                    if ($end_datetime > $current_date) {
                        throw new \Exception(__('End date cannot be in the future.', 'form-quizz-fqi3'));
                    }

                    // Record statistics
                    $response = $this->manuallyRecordWeeklyStatistics($user_id, $start_date, $end_date);
                    
                    // Display success message
                    if ($response === true) {
                        $this->render_notice(
                            'success',
                            __('Statistics recorded successfully for user.', 'form-quizz-fqi3')
                        );
                    } else {
                        throw new \Exception($response);
                    }
                }
            } catch (\Exception $e) {
                $this->render_notice('error', $e->getMessage());
            }
        }

        /**
         * Renders a notice message with the specified type and message.
         *
         * @param string $type    Notice type ('success' or 'error')
         * @param string $message Notice message
         * @return void
         */
        private function render_notice(string $type, string $message): void {
            $class = ($type === 'success') ? 'notice-success' : 'notice-error';
            printf(
                '<div class="notice %s is-dismissible"><p>%s</p></div>',
                esc_attr($class),
                esc_html($message)
            );
        }

        /**
        * Manually record weekly statistics for a given period
        */
        public function manuallyRecordWeeklyStatistics(int $userId, string $startDate, string $endDate): bool|string {
            if (strtotime($startDate) === false || strtotime($endDate) === false) {
                return __('Invalid date format provided.', 'form-quizz-fqi3');
            }

            if (strtotime($endDate) < strtotime($startDate)) {
                return __('End date cannot be before start date.', 'form-quizz-fqi3');
            }

            try {
                $this->recordPeriodicStatistics('weekly', $startDate, $endDate, $userId);
                return true; 
            } catch (\Exception $e) {
                return $e->getMessage(); 
            }
        }
    }
endif;

new FQI3_PeriodicStatistics();