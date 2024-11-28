<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3_Statistics_Page Class
 *
 * This class manages content settings for the FQI3 plugin.
 * It provides options for customizing the content displayed by the plugin.
 *
 * @package    Form Quizz FQI3
 * @subpackage Admin Pages
 * @since      1.4.0
 * @version    2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FQI3_Statistics_Page')):

class FQI3_Statistics_Page {
    private ?FQI3_Awards $awards = null;
    
    // Made constants private since they're only used internally
    private const PERFORMANCE_LEVELS = [
        'very_good' => ['min' => 90, 'class' => 'bg-success text-white'],
        'good' => ['min' => 70, 'class' => 'bg-info text-white'],
        'average' => ['min' => 40, 'class' => 'bg-warning'],
        'poor' => ['min' => 0, 'class' => 'bg-danger text-white']
    ];

    // Cache for user statistics
    private array $userStatsCache = [];

    public function __construct() {
        // Add any initialization if needed
        add_action('wp_ajax_refresh_statistics', [$this, 'ajax_refresh_statistics']);
    }

    public function get_awards_instance(): FQI3_Awards {
        return $this->awards ??= new FQI3_Awards();
    }

    public function render_statistics_page(): void {     
        ?>
        <div class="wrap container-fluid mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="page-title"><?php _e('Statistics', 'form-quizz-fqi3'); ?></h2>
                <button type="button" class="btn btn-success refresh-stats">
                    <i class="bi bi-arrow-clockwise text-light me-3"></i>
                    <?php _e('Refresh Statistics', 'form-quizz-fqi3'); ?>
                </button>
            </div>
            
            <?php $this->render_performance_legend(); ?>
            
            <div id="statistics-container">
                <?php echo $this->display_all_user_statistics(); ?>
            </div>
            
            <?php do_action('fqi3_after_statistics_page'); ?>
        </div>
        <?php
    }

    private function render_performance_legend(): void {
        ?>
        <div class="mb-4">
            <h5><strong><?php _e('Color codes:', 'form-quizz-fqi3'); ?></strong></h5>
            <ul class="list-group list-group-horizontal-sm">
                <?php foreach (self::PERFORMANCE_LEVELS as $level => $data): ?>
                    <li class="list-group-item d-flex align-items-center">
                        <span class="badge <?php echo esc_attr($data['class']); ?> p-1">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $level))); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    private function display_all_user_statistics(): string {
        $user_stats = $this->get_user_statistics();
        $arrayLevels = fqi3_get_free_quiz_levels();

        ob_start();
        ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm">
                <?php 
                $this->render_table_header();
                $this->render_table_body($user_stats, $arrayLevels);
                ?>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_table_header(): void {
        ?>
        <thead class="thead-dark">
            <tr>
                <th><?php _e('User ID', 'form-quizz-fqi3'); ?></th>
                <th><?php _e('Username', 'form-quizz-fqi3'); ?></th>
                <th><?php _e('Levels', 'form-quizz-fqi3'); ?></th>
                <th><?php _e('Awards / Badges', 'form-quizz-fqi3'); ?></th>
            </tr>
        </thead>
        <?php
    }

    private function render_table_body(array $user_stats, array $arrayLevels): void {
        ?>
        <tbody>
            <?php if (empty($user_stats)): ?>
                <tr>
                    <td colspan="4" class="text-center">
                        <?php _e('No user statistics available yet.', 'form-quizz-fqi3'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($user_stats as $user_id => $user): ?>
                    <tr>
                        <td><?php echo esc_html($user_id); ?></td>
                        <td><?php echo esc_html($user['username']); ?></td>
                        <td><?php $this->render_user_levels($user, $arrayLevels); ?></td>
                        <td><?php $this->render_user_badges($user_id); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php
    }

    private function render_user_levels(array $user, array $arrayLevels): void {
        ?>
        <div class="list-group">
            <?php foreach ($arrayLevels as $level_key => $level_data):
                if (isset($user['levels'][$level_key])):
                    $stats = $user['levels'][$level_key];
                    $this->render_level_item($level_data, $stats);
                endif;
            endforeach; ?>
        </div>
        <?php
    }

    private function render_level_item(array $level_data, array $stats): void {
        $success_rate = $stats['success_rate'] ?? __('N/A', 'form-quizz-fqi3');
        $total_quizzes = $stats['total_quizzes'] ?? 0;
        $performance_class = $this->get_performance_class($success_rate);
        ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <span><?php echo esc_html($level_data['label']); ?></span>
                <span class="badge <?php echo esc_attr($performance_class); ?> rounded-pill">
                    <?php
                     // Translators: 1: Success rate percentage, 2: Number of quizzes
                    echo esc_html(sprintf(
                        '%s%% (%s)',
                        $success_rate,
                        sprintf(
                            /* translators: %s is the number of quizzes */
                            _n('%s quiz', '%s quizzes', $total_quizzes, 'form-quizz-fqi3'),
                            $total_quizzes
                        )
                    )); 
                    ?>
                </span>
            </div>
        </div>
        <?php
    }

    private function render_user_badges(int $user_id): void {
        $badges = $this->get_awards_instance()->display_user_badges($user_id);
        if (empty($badges)) {
            $this->render_no_badges_message();
            return;
        }
        
        $this->render_badges_list($badges);
    }

    private function render_no_badges_message(): void {
        ?>
        <div class="alert alert-info d-flex align-items-center" role="alert">
            <i class="bi bi-trophy-fill me-2"></i>
            <div>
                <strong><?php _e('No badges awarded yet!', 'form-quizz-fqi3'); ?></strong>
                <p class="mb-0"><?php _e('This user has not yet earned a badge.', 'form-quizz-fqi3'); ?></p>
            </div>
        </div>
        <?php
    }

    private function render_badges_list(array $badges): void {
        ?>
        <ul class="list-group">
            <?php foreach ($badges as $badge): ?>
                <li class="list-group-item d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-trophy-fill text-warning me-3"></i>
                        <strong class="me-2"><?php echo esc_html($badge['name']); ?></strong>
                    </div>
                    <small class="text-muted">
                        <?php   
                        printf(
                             /* translators: %s is the date the badge was awarded */
                            __('earned at %s', 'form-quizz-fqi3'),
                            esc_html(date_i18n(get_option('date_format'), strtotime($badge['awarded_at'])))
                        ); 
                        ?>
                    </small>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    public function ajax_refresh_statistics(): void {
        check_ajax_referer('fqi3_refresh_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Clear the cache to force fresh data
        $this->userStatsCache = [];
        
        wp_send_json_success([
            'html' => $this->display_all_user_statistics()
        ]);
    }

    private function get_user_statistics(): array {
        if (!empty($this->userStatsCache)) {
            return $this->userStatsCache;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . FQI3_TABLE_PERFORMANCE;
        
        // Optimized query with JOIN and single query execution
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.ID AS user_id,
                u.display_name,
                p.level,
                p.success_rate,
                p.total_quizzes
            FROM {$wpdb->users} u
            LEFT JOIN {$table_name} p ON u.ID = p.user_id
            ORDER BY u.display_name ASC
        "), ARRAY_A);

        $this->userStatsCache = $this->process_user_statistics($results);
        return $this->userStatsCache;
    }

    private function process_user_statistics(array $results): array {
        $user_stats = [];
        foreach ($results as $row) {
            $user_id = $row['user_id'];
            $user_stats[$user_id]['username'] = $row['display_name'] ?: __('Deleted User', 'form-quizz-fqi3');
            if ($row['level']) {
                $user_stats[$user_id]['levels'][$row['level']] = [
                    'success_rate' => $row['success_rate'],
                    'total_quizzes' => $row['total_quizzes'],
                ];
            }
        }
        return $user_stats;
    }

    private function get_performance_class(float|string $success_rate): string {
        if (!is_numeric($success_rate)) {
            return 'bg-secondary';
        }
        
        foreach (self::PERFORMANCE_LEVELS as $level) {
            if ($success_rate >= $level['min']) {
                return $level['class'];
            }
        }
        
        return 'bg-secondary';
    }
}

endif;