<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3_Import_Export_Page Class
 *
 * Manages import and export functionality for the FQI3 plugin with enhanced security and performance.
 *
 * @package    Form Quizz FQI3
 * @subpackage Admin Pages
 * @since      1.5.0
 * @version    2.0.0
*/

 if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Import_Export_Page' ) ) :
class FQI3_Import_Export_Page {
    private const MAX_UPLOAD_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(private FQI3_Backend $backend) {
        $this->backend = $backend;
        add_action('admin_notices', [$this, 'display_import_success_notice']);
        add_action('admin_post_fqi3_export_data', [$this, 'handle_export_data']);
        add_action('admin_post_fqi3_import_data', [$this, 'handle_import_data']);
    }

    public static function or_get_allowed_export_types(): array {
        return [
            'questions' => __('Questions', 'form-quizz-fqi3'),
            'levels' => __('Levels', 'form-quizz-fqi3'),
            'performance' => __('Performance', 'form-quizz-fqi3'),
            'advanced_stats' => __('Advanced Stats', 'form-quizz-fqi3'),
            'badges' => __('Badges', 'form-quizz-fqi3'),
            'options' => __('Options', 'form-quizz-fqi3')
        ];
    }
    public static function get_allowed_export_types(): array {
        return [
            'questions' => [
                'label' => __('Questions', 'form-quizz-fqi3'),
                'method' => 'export_questions',
            ],
            'levels' => [
                'label' => __('Levels', 'form-quizz-fqi3'),
                'method' => 'export_levels',
            ],
            'badges' => [
                'label' => __('Badges', 'form-quizz-fqi3'),
                'method' => 'export_badges',
            ],
            'performance' => [
                'label' => __('Performance', 'form-quizz-fqi3'),
                'method' => 'export_performance',
            ],
            'advanced_stats' => [
                'label' => __('Advanced Stats', 'form-quizz-fqi3'),
                'method' => 'export_advanced_stats',
            ],
            'options' => [
                'label' => __('Settings', 'form-quizz-fqi3'),
                'method' => 'export_options',
            ],
        ];
    }
    

    public function display_import_success_notice(): void {
        $screen = get_current_screen();
        $admin_pages = fqi3_get_admin_pages();

        if ($screen->id !== 'form-quizz-fqi3_page_' . $admin_pages['import_export']['slug']) {
            return;
        }
    
        if (get_transient('fqi3_import_success')) {
            ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <p><?php _e('Data successfully imported.', 'form-quizz-fqi3'); ?></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php
            
            // Delete the transient to prevent repeated notices
            delete_transient('fqi3_import_success');
        }
    }

    /**
     * Render the Import/Export Data admin page
     *
     * @return void
     * 
     * @since 1.5.0 Initial version
     * @since 2.0.0 Add User choice field
     */
    public function render_import_export_page(): void {
        $users = get_users();
        ?>
        <div class="wrap">
        <h2 class="page-title">
            <?php _e('Import/Export Data', 'form-quizz-fqi3'); ?>
        </h2>
        <div class="fqi3-section-options">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <h3>
                    <?php _e('Export Data', 'form-quizz-fqi3'); ?>
                </h3>
                <hr>
                <p><?php _e('Select the data you want to export:', 'form-quizz-fqi3'); ?></p>
                <?php if (isset($_GET['message']) && $_GET['message'] === 'no_export_type_selected'): ?>
                    <div class="alert alert-danger">
                        <p><?php _e('Please select at least one export option.', 'form-quizz-fqi3'); ?></p>
                    </div>
                <?php endif; ?>
                <div class="form-check form-switch">
                    <input type="checkbox" id="selectAll" class="form-check-input">
                    <label for="selectAll" class="form-check-label"><?php _e('Select All', 'form-quizz-fqi3'); ?></label>
                </div>
                <hr>
                <div class="export-options">
                    <?php foreach (self::get_allowed_export_types() as $type => $details): ?>
                        <div class="form-check form-switch">
                            <input type="checkbox" 
                                    id="export<?php echo ucfirst($type); ?>" 
                                    name="export_types[]" 
                                    value="<?php echo esc_attr($type); ?>" 
                                    class="form-check-input"
                                    data-dependency="<?php echo in_array($type, ['performance', 'advanced_stats']) ? 'choice-user-container' : ''; ?>">
                            <label for="export<?php echo ucfirst($type); ?>" class="form-check-label">
                                <?php echo esc_html($details['label']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <div id="choice-user-container" class="form-group mt-3" style="display: none;">
                        <label for="export_choice_user">
                            <?php _e('Export for specific user (optional):', 'form-quizz-fqi3'); ?>
                        </label>
                        <select name="export_choice_user_id" id="export_choice_user" class="form-control">
                            <option value=""><?php _e('All Users', 'form-quizz-fqi3'); ?></option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>">
                                    <?php echo esc_html($user->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" name="action" value="fqi3_export_data">
                <?php wp_nonce_field('fqi3_export_nonce'); ?>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <?php _e('Export', 'form-quizz-fqi3'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="fqi3-section-options">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <h3>
                    <?php _e('Import Data', 'form-quizz-fqi3'); ?>
                </h3>
                <hr>
                <p><?php _e('Select the JSON file to import:', 'form-quizz-fqi3'); ?></p>
                <div class="import-file-container">
                    <input type="file" 
                            name="import_file" 
                            accept=".json" 
                            required 
                            class="form-control">
                    <small><?php _e('Maximum file size: 10MB. JSON format only.', 'form-quizz-fqi3'); ?></small>
                </div>

                <input type="hidden" name="action" value="fqi3_import_data">
                <?php wp_nonce_field('fqi3_import_nonce'); ?>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <?php _e('Import', 'form-quizz-fqi3'); ?>
                    </button>
                </div>
            </form>
        </div>
        </div>
        <?php
    }

    /**
     * Handle data export with improved security and error handling
     *
     * @return void
     * 
     * @since 1.5.0 Initial version
     * @since 2.0.0 Add User arg
     */
    public function handle_export_data(): void {
        // Permission and nonce check
        $this->check_export_permissions();

        // Validate export types
        $selected_export_types = $this->validate_export_types();

        // Types to check
        $user_specific_types = ['performance', 'advanced_stats'];

        if (array_intersect($user_specific_types, $selected_export_types)) {
            $user_id = !empty($_POST['export_choice_user_id']) ? intval($_POST['export_choice_user_id']) : null;
        }

        // Prepare export data
        $export_data = $this->collect_export_data($selected_export_types, $user_id);

        // Generate and send export file
        $this->send_export_file($export_data, $selected_export_types);
    }

    /**
     * Handle data import with enhanced validation and error handling
     *
     * @return void
     */
    public function handle_import_data(): void {
        // Permission check
        $this->check_import_permissions();

        // Validate uploaded file
        $import_file = $this->validate_import_file();

        // Parse JSON data
        $import_data = $this->parse_import_data($import_file);

        // Import data with database transaction
        $this->import_data_with_transaction($import_data);
    }

    /**
     * Validate and collect export data
     *
     * @param array $selected_types
     * @return array
     */
    private function or_collect_export_data(array $selected_types, $user_id = null): array {
        global $wpdb;
        $export_data = [];

        foreach ($selected_types as $type) {
            switch ($type) {
                case 'questions':
                    $table_name = $this->backend->get_quiz_table_name();
                    $questions = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
                    $export_data['questions'] = array_map(function($q) {
                        unset($q['id']);
                        return $q;
                    }, $questions);
                    break;

                case 'levels':
                    $export_data['levels'] = get_option('fqi3_quiz_levels', []);
                    break;

                case 'badges':
                    $export_data['badges'] = get_option('fqi3_badges', []);
                    break;

                case 'performance':
                    $table_name = $wpdb->prefix . FQI3_TABLE_PERFORMANCE;
    
                    $query = "SELECT * FROM $table_name";
                    if (!is_null($user_id)) {
                        $query .= $wpdb->prepare(" WHERE user_id = %d", $user_id);
                    }
    
                    // Récupérer les données
                    $performance_data = $wpdb->get_results($query, ARRAY_A);

                    $export_data['performance'] = $performance_data;
                    break;

                case 'advanced_stats':
                    $table_name = $wpdb->prefix . FQI3_TABLE_PERIODIC_STATISTICS;
                    
                    $query = "SELECT * FROM $table_name";
                    
                    $where_conditions = [];
                    
                    if (!is_null($user_id)) {
                        $where_conditions[] = $wpdb->prepare("user_id = %d", $user_id);
                    }
                    
                    // Other conditions if becessary
                    // Example : filter by period
                    // $where_conditions[] = $wpdb->prepare("period_type = %s", 'weekly');
                    
                    if (!empty($where_conditions)) {
                        $query .= " WHERE " . implode(" AND ", $where_conditions);
                    }
                    
                    $query .= " ORDER BY created_at DESC"; // Sort by date DESC
                    // $query .= " LIMIT 10"; // Limit to 10 results
                    
                    // Récupérer les données
                    $performance_data = $wpdb->get_results($query, ARRAY_A);
                    
                    $export_data['advanced_stats'] = $performance_data;
                    break;

                case 'options':
                    $export_data['options'] = get_option('fqi3_options', []);
                    break;
            }
        }

        return $export_data;
    }
    private function collect_export_data(array $selected_types, $user_id = null): array {
        global $wpdb;
    
        $export_data = [];
        $allowed_types = self::get_allowed_export_types();
    
        foreach ($selected_types as $type) {
            if (isset($allowed_types[$type])) {
                $method = $allowed_types[$type]['method'];
                if (method_exists($this, $method)) {
                    $export_data[$type] = $this->$method($user_id);
                }
            }
        }
    
        return $export_data;
    }

    private function export_questions(): array {
        global $wpdb;
        $table_name = $this->backend->get_quiz_table_name();
        $questions = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        return array_map(function($q) {
            unset($q['id']);
            return $q;
        }, $questions);
    }
    
    private function export_levels(): array {
        return get_option('fqi3_quiz_levels', []);
    }
    
    private function export_badges(): array {
        return get_option('fqi3_badges', []);
    }
    
    private function export_performance($user_id = null): array {
        global $wpdb;
        $table_name = $wpdb->prefix . FQI3_TABLE_PERFORMANCE;
        $query = "SELECT * FROM $table_name";
        
        if (!is_null($user_id)) {
            $query .= $wpdb->prepare(" WHERE user_id = %d", $user_id);
        }
    
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    private function export_advanced_stats($user_id = null): array {
        global $wpdb;
        $table_name = $wpdb->prefix . FQI3_TABLE_PERIODIC_STATISTICS;
        $query = "SELECT * FROM $table_name";
        
        $where_conditions = [];
        if (!is_null($user_id)) {
            $where_conditions[] = $wpdb->prepare("user_id = %d", $user_id);
        }
    
        if (!empty($where_conditions)) {
            $query .= " WHERE " . implode(" AND ", $where_conditions);
        }
    
        $query .= " ORDER BY created_at DESC";
    
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    private function export_options(): array {
        return get_option('fqi3_options', []);
    }

    /**
     * Send export file with proper headers
     *
     * @param array $export_data
     * @param array $selected_types
     */
    private function send_export_file(array $export_data, array $selected_types): void {
        $filename = 'fqi3_' . implode('_', $selected_types) . '_' . date('Y-m-d_H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Check export permissions and nonce
     *
     * @throws \Exception If permissions are insufficient
     */
    private function check_export_permissions(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions for data export.', 'form-quizz-fqi3'));
        }

        if (!check_admin_referer('fqi3_export_nonce')) {
            wp_die(__('Security verification failed.', 'form-quizz-fqi3'));
        }
    }

    /**
     * Validate selected export types
     *
     * @return array
     */
    private function validate_export_types(): array {
        $allowed_types = array_keys(self::get_allowed_export_types());

        $selected_types = isset($_POST['export_types']) ? 
            array_intersect($_POST['export_types'], $allowed_types) : 
            [];

        if (empty($selected_types)) {
            wp_redirect(add_query_arg('message', 'no_export_type_selected', wp_get_referer()));
            exit;
        }

        return $selected_types;
    }

    /**
     * Check import permissions
     *
     * @throws \Exception If permissions are insufficient
     */
    private function check_import_permissions(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions for data import.', 'form-quizz-fqi3'));
        }

        if (!check_admin_referer('fqi3_import_nonce')) {
            wp_die(__('Security verification failed.', 'form-quizz-fqi3'));
        }
    }

    /**
     * Validate uploaded import file
     *
     * @return string Temporary file path
     * @throws \Exception For file upload errors
     */
    private function validate_import_file(): string {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed.', 'form-quizz-fqi3'));
        }

        $file_size = $_FILES['import_file']['size'];
        $file_type = wp_check_filetype($_FILES['import_file']['name'], ['json' => 'application/json']);

        if ($file_size > self::MAX_UPLOAD_SIZE) {
            // Translators: %d indicates the maximum upload limit.
            wp_die(sprintf(__('File size exceeds maximum limit of %d MB.', 'form-quizz-fqi3'), self::MAX_UPLOAD_SIZE / (1024 * 1024)));
        }

        if (!$file_type['ext']) {
            wp_die(__('Invalid file type. JSON files only.', 'form-quizz-fqi3'));
        }

        return $_FILES['import_file']['tmp_name'];
    }

    /**
     * Parse and validate import JSON data
     *
     * @param string $file_path Temporary file path
     * @return array Validated import data
     */
    private function parse_import_data(string $file_path): array {
        $json_data = file_get_contents($file_path);
        $import_data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(__('Invalid JSON file.', 'form-quizz-fqi3'));
        }

        // Validate import data structure
        $this->validate_import_data_structure($import_data);

        return $import_data;
    }

    /**
     * Validate import data structure
     *
     * @param array $import_data
     * @throws \Exception If data structure is invalid
    */
    private function validate_import_data_structure(array $import_data): void {
        $allowed_keys = array_keys(self::get_allowed_export_types());
        
        // Check if any of the allowed keys are present
        $valid_import = false;
        foreach ($allowed_keys as $key) {
            if (isset($import_data[$key]) && is_array($import_data[$key])) {
                $valid_import = true;
                break;
            }
        }

        if (!$valid_import) {
            wp_die(__('Invalid import data: No valid data sections found.', 'form-quizz-fqi3'));
        }
    }

    /**
     * Import data with database transaction for data integrity
     *
     * @param array $import_data
    */
    private function import_data_with_transaction(array $import_data): void {
        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            // Sanitize import data
            $sanitized_data = $this->sanitize_import_data($import_data);

            // Create backup before import
            $backup_path = $this->create_import_backup($sanitized_data);

            // Import only the sections that are present
            if (isset($sanitized_data['questions'])) {
                // Clear existing questions before importing
                $this->truncate_existing_data();
                $this->import_questions($sanitized_data['questions']);
            }

            // Update WordPress options for present sections
            if (isset($sanitized_data['levels'])) {
                update_option('fqi3_quiz_levels', $sanitized_data['levels']);
            }

            if (isset($sanitized_data['badges'])) {
                update_option('fqi3_badges', $sanitized_data['badges']);
            }

            if (isset($sanitized_data['options'])) {
                update_option('fqi3_options', $sanitized_data['options']);
            }

            // Generate operation report
            $report_filename = $this->generate_operation_report('import', $sanitized_data);

            // Log successful operation
            $this->log_operation('import', [
                'backup_file' => $backup_path,
                'report_file' => $report_filename
            ]);

            $wpdb->query('COMMIT');

            // Set transient for admin notice
            set_transient('fqi3_import_success', true, 60);

            // Redirect with success message
            $admin_pages = fqi3_get_admin_pages();
            wp_redirect(admin_url('admin.php?page=' . $admin_pages['import_export']['slug'] . '&imported=success'));
            exit;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');

            // Log failed operation
            $this->log_operation('import', [
                'error_message' => $e->getMessage()
            ], false);
            // Translators: %s is the error message.
            wp_die(sprintf(__('Import failed: %s', 'form-quizz-fqi3'), $e->getMessage()));
        }
    }

    /**
     * Truncate existing data before import
     */
    private function truncate_existing_data(): void {
        global $wpdb;
        $table_name = $this->backend->get_quiz_table_name();
        $wpdb->query("TRUNCATE TABLE $table_name");
    }

    /**
     * Import questions in batches
     *
     * @param array $questions
     */
    private function import_questions(array $questions): void {
        global $wpdb;
        $table_name = $this->backend->get_quiz_table_name();
        $batch_size = 100;

        foreach (array_chunk($questions, $batch_size) as $batch) {
            foreach ($batch as $question) {
                $wpdb->insert(
                    $table_name,
                    [
                        'niveau' => sanitize_text_field($question['niveau']),
                        'q' => sanitize_textarea_field($question['q']),
                        'q2' => sanitize_textarea_field($question['q2'] ?? ''),
                        'options' => maybe_serialize($question['options'] ?? []),
                        'answer' => sanitize_text_field($question['answer'])
                    ]
                );
            }
        }
    }

    /**
     * Logging mechanism for import/export operations
     *
     * @param string $type Log type (export/import)
     * @param array $data Operation details
     * @param bool $success Operation status
     */
    private function log_operation(string $type, array $data, bool $success = true): void {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'details' => $data,
            'status' => $success ? 'success' : 'failure'
        ];

        $existing_logs = get_option('fqi3_import_export_logs', []);
        $existing_logs[] = $log_entry;

        // Limit log entries to prevent database bloat
        $existing_logs = array_slice($existing_logs, -20);
        update_option('fqi3_import_export_logs', $existing_logs);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CLIENT_IP', 
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED', 
            'HTTP_X_CLUSTER_CLIENT_IP', 
            'HTTP_FORWARDED_FOR', 
            'HTTP_FORWARDED', 
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return 'unknown';
    }

    /**
     * Create backup before import
     *
     * @param array $import_data
     * @return string Backup file path
     */
    private function create_import_backup(array $import_data): string {
        $backup_dir = wp_upload_dir()['basedir'] . '/fqi3_backups/';
        
        // Ensure backup directory exists
        wp_mkdir_p($backup_dir);

        $backup_filename = 'backup_' . date('Y-m-d_H-i-s') . '.json';
        $backup_path = $backup_dir . $backup_filename;

        // Write backup file
        file_put_contents($backup_path, wp_json_encode($import_data, JSON_PRETTY_PRINT));

        // Set file permissions
        chmod($backup_path, 0600);

        return $backup_path;
    }

    /**
     * Validate and sanitize import data
     *
     * @param array $data Raw import data
     * @return array Sanitized import data
     */
    private function sanitize_import_data(array $data): array {
        $sanitized_data = [];

        // Sanitize questions
        if (isset($data['questions'])) {
            $sanitized_data['questions'] = array_map(function($question) {
                return [
                    'niveau' => sanitize_text_field($question['niveau'] ?? ''),
                    'q' => sanitize_textarea_field($question['q'] ?? ''),
                    'q2' => sanitize_textarea_field($question['q2'] ?? ''),
                    'options' => is_array($question['options']) ? 
                        array_map('sanitize_text_field', $question['options']) : 
                        [],
                    'answer' => sanitize_text_field($question['answer'] ?? '')
                ];
            }, $data['questions']);
        }

        // Sanitize levels, badges, and options similarly
        $sanitizable_keys = ['levels', 'badges', 'options'];
        foreach ($sanitizable_keys as $key) {
            if (isset($data[$key])) {
                $sanitized_data[$key] = array_map(function($value) {
                    return is_string($value) ? sanitize_text_field($value) : $value;
                }, $data[$key]);
            }
        }

        return $sanitized_data;
    }

    /**
     * Generate import/export report
     *
     * @param string $type Report type
     * @param array $data Operation data
     * @return string Report filename
     */
    private function generate_operation_report(string $type, array $data): string {
        $report = [
            'type' => $type,
            'timestamp' => current_time('mysql'),
            'user' => wp_get_current_user()->display_name,
            'details' => [
                'total_questions' => isset($data['questions']) ? count($data['questions']) : 0,
                'levels_imported' => isset($data['levels']) ? count($data['levels']) : 0,
                'badges_imported' => isset($data['badges']) ? count($data['badges']) : 0
            ]
        ];

        $reports_dir = wp_upload_dir()['basedir'] . '/fqi3_reports/';
        wp_mkdir_p($reports_dir);

        $filename = $type . '_report_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = $reports_dir . $filename;

        file_put_contents($filepath, wp_json_encode($report, JSON_PRETTY_PRINT));
        chmod($filepath, 0600);

        return $filename;
    }
}

endif;