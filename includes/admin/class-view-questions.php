<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3_View_Questions_Page Class
 *
 * This class handles the display and management of quiz questions for the FQI3 plugin.
 * It allows administrators to view, filter, edit, and delete questions. 
 * Additionally, it provides features for exporting questions and paginating through large sets of questions.
 *
 * @package    Form Quizz FQI3
 * @subpackage Admin Pages
 * @since      1.2.0
 * @version    2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_View_Questions_Page' ) ) :

class FQI3_View_Questions_Page {

    public function __construct(
        private FQI3_Backend $backend,  
        private array $levelsQuiz
    ) {
        $this->levelsQuiz = $backend->get_levels_quiz();
        add_action('wp_ajax_delete_question', [ $this, 'handle_delete_question' ]);
        add_action('admin_post_export_questions', [$this, 'export_questions']);
    }

    /**
     * Render the page for viewing questions.
     *
     * Generates the HTML for the page where admin users can view, filter, and manage quiz questions.
     * Displays pagination, filtering options by level, and a table of questions with edit/delete actions.
     * 
     * @global wpdb $wpdb The WordPress database abstraction object.
     * 
     * @since 1.6.0
     */
    public function render_consultation_page() {
        global $wpdb;

        // Get the table name and filter values
        $table_name = $this->backend->get_quiz_table_name();
        $filter_level = $this->get_filter_level();
        $search_term = $this->get_search_term();
        $rtlClass = $this->get_rtl_class();
        
        // Pagination setup
        list($items_per_page, $current_page, $offset) = $this->get_pagination();

        // Build query to retrieve questions with filters
        $questions = $this->get_questions($table_name, $filter_level, $search_term, $items_per_page, $offset);
        $total_items = $this->get_total_items($table_name, $filter_level, $search_term);
        $total_pages = ceil($total_items / $items_per_page);
        $total_pages = ceil($total_items / $items_per_page);

        // Fetching levels and questions count
        $levels = array_keys($this->levelsQuiz);
        $questions_count = $this->get_questions_count_by_level($table_name, $levels);

        // Prepare page URL for filter links
        $base_url = esc_url(remove_query_arg('filter_level'));
        $plugin_pages = fqi3_get_admin_pages();
        $add_page = $plugin_pages['add_questions']['slug'];
        $edit_page = $plugin_pages['edit_questions']['slug'];

        ?>
        <div class="wrap container-fluid">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col">
                    <h1 class="h2 d-inline-block"><?php _e('Viewing questions', 'form-quizz-fqi3'); ?></h1>
                    <a href="<?php echo esc_url(admin_url("admin.php?page=$add_page")); ?>" 
                       class="btn btn-primary ms-2">
                        <i class="bi bi-plus-circle"></i> <?php _e('Add a question', 'form-quizz-fqi3'); ?>
                    </a>
                </div>
            </div>
            <hr class="wp-header-end">
            <div class="container-fluid mt-4">
                <div class="bg-light p-4 rounded shadow-sm">
                    <!-- Filters Section -->
                    <?php $this->render_filter_links($levels, $questions_count, $filter_level, $base_url, $total_items); ?>
                    
                    <!-- Pagination Form -->
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-10 d-flex gap-2">
                            <?php $this->render_filter_form($levels, $filter_level, $search_term); ?>
                            <?php $this->render_export_form(); ?>
                        </div>

                        <div class="col-12 col-md-2 d-flex justify-content-end">
                            <?php $this->render_pagination($current_page, $total_pages); ?>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Questions Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm <?php echo esc_attr($rtlClass); ?>">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'form-quizz-fqi3'); ?></th>
                            <th><?php _e('Access', 'form-quizz-fqi3'); ?></th>
                            <th><?php _e('Level', 'form-quizz-fqi3'); ?></th>
                            <th><?php _e('Question', 'form-quizz-fqi3'); ?></th>
                            <th><?php _e('Instructions for the question', 'form-quizz-fqi3'); ?></th>
                            <th><?php _e('Options', 'form-quizz-fqi3'); ?></th>
                            <th><?php _e('Answer', 'form-quizz-fqi3'); ?></th>
                            <th><?php _e('Action', 'form-quizz-fqi3'); ?></th>
                            <th><?php _e('Edit', 'form-quizz-fqi3'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $this->render_question_rows($questions, $edit_page); ?>
                    </tbody>
                </table>
            </div>

            <!-- Add pagination links -->
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php $this->render_pagination_links($total_pages, $current_page); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get the level filter from the URL.
     * 
     * @since 1.6.0
     */
    private function get_filter_level() {
        return isset($_GET['filter_level']) ? sanitize_text_field($_GET['filter_level']) : '';
    }

    /**
     * Get the search term from the URL.
     * 
     * @since 1.6.0
     */
    private function get_search_term() {
        return isset($_GET['search_term']) ? sanitize_text_field($_GET['search_term']) : '';
    }

    /**
     * Get the RTL class for the page.
     * 
     * @since 1.6.0
     */
    private function get_rtl_class() {
        $options = fqi3_get_options();
        return isset($options['fqi3_rtl_mode']) && $options['fqi3_rtl_mode'] == 1 ? "rtl-mode" : '';
    }

    /**
     * Get pagination details.
     * 
     * @since 1.6.0
     */
    private function get_pagination() {
        $items_per_page = get_option('fqi3_items_per_page', 10);
        $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $offset = ($current_page - 1) * $items_per_page;
        return [$items_per_page, $current_page, $offset];
    }

    /**
     * Get questions from the database with filters applied.
     * 
     * @since 1.6.0
     */
    private function get_questions($table_name, $filter_level, $search_term, $items_per_page, $offset) {
        global $wpdb;
        $where_clause = [];
        if (!empty($filter_level)) {
            $where_clause[] = $wpdb->prepare('niveau = %s', $filter_level);
        }
        if (!empty($search_term)) {
            $where_clause[] = $wpdb->prepare('q LIKE %s', '%' . $wpdb->esc_like($search_term) . '%');
        }
        $where_clause_str = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

        $sql = $wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS * FROM $table_name $where_clause_str LIMIT %d OFFSET %d",
            $items_per_page,
            $offset
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Get the total number of items with filters.
     * 
     * @since 1.6.0
     */
    private function get_total_items($table_name, $filter_level, $search_term) {
        global $wpdb;
        $where_clause = [];
        if (!empty($filter_level)) {
            $where_clause[] = $wpdb->prepare('niveau = %s', $filter_level);
        }
        if (!empty($search_term)) {
            $where_clause[] = $wpdb->prepare('q LIKE %s', '%' . $wpdb->esc_like($search_term) . '%');
        }
        $where_clause_str = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause_str");
    }

    /**
     * Get the number of questions per level for filtering.
     * 
     * @since 1.6.0
     */
    private function get_questions_count_by_level($table_name, $levels) {
        global $wpdb;
        $questions_count = [];
        foreach ($levels as $level) {
            $questions_count[$level] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE niveau = %s", $level));
        }
        return $questions_count;
    }

    /**
     * Render the filter links at the top of the page.
     * 
     * @since 1.6.0
     */
    private function render_filter_links($levels, $questions_count, $filter_level, $base_url, $total_items) {
        ?>
        <nav class="nav nav-pills mb-3 small">
            <a class="nav-link <?php echo empty($filter_level) ? 'active' : ''; ?>" 
                href="<?php echo $base_url; ?>">
                <?php _e('All', 'form-quizz-fqi3'); ?>
                <span class="badge bg-dark ms-1">
                    <?php echo esc_html($total_items); ?>
                </span>
            </a>
            <?php foreach ($levels as $level): ?>
                <a class="nav-link <?php echo $filter_level === $level ? 'active' : ''; ?>"
                    href="<?php echo esc_url(add_query_arg('filter_level', $level, $base_url)); ?>">
                    <?php echo esc_html($this->levelsQuiz[$level]["label"]); ?>
                    <span class="badge bg-dark ms-1">
                        <?php echo esc_html($questions_count[$level]); ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * Render the filter form for selecting level and searching.
     * 
     * @since 1.6.0
     */
    private function render_filter_form($levels, $filter_level, $search_term) {
        ?>
        <div class="col-md-9">
            <form method="get" action="" class="d-flex gap-2">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                <select name="filter_level" class="form-select-sm">
                    <option value=""><?php _e('All Levels', 'form-quizz-fqi3'); ?></option>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?php echo esc_attr($level); ?>" 
                                <?php selected($filter_level, $level); ?>>
                            <?php echo esc_html($this->levelsQuiz[$level]['label'] . 
                                ($this->levelsQuiz[$level]['free'] ? ' (' . __('Free', 'form-quizz-fqi3') . ')' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="search" 
                        name="search_term" 
                        class="form-control-sm"
                        value="<?php echo esc_attr($search_term); ?>" 
                        placeholder="<?php _e('Search questions...', 'form-quizz-fqi3'); ?>">
                <button type="submit" class="btn btn-dark btn-sm">
                    <i class="bi bi-filter"></i> <?php _e('Filter', 'form-quizz-fqi3'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Render the export form.
     * 
     * @since 1.6.0
     */
    private function render_export_form() {
        ?>
        <div class="col-md-3">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline">
                <input type="hidden" name="action" value="export_questions">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-download"></i> <?php _e('Export CSV', 'form-quizz-fqi3'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Render the pagination.
     * 
     * @since 1.6.0
     */
    private function render_pagination($current_page, $total_pages) {
        echo sprintf(
            '%s %d %s %d',
            esc_html(__('Page', 'form-quizz-fqi3')),
            intval($current_page),
            esc_html(__('of', 'form-quizz-fqi3')),
            intval($total_pages)
        );
    }

    /**
     * Render the pagination links.
     * 
     * @since 1.6.0
     */
    private function render_pagination_links($total_pages, $current_page) {
        $pagination = paginate_links(array(
            'base'    => add_query_arg('paged', '%#%'),
            'format'  => '',
            'prev_text' => __('Previous', 'form-quizz-fqi3'),
            'next_text' => __('Next', 'form-quizz-fqi3'),
            'total'   => $total_pages,
            'current' => $current_page,
            'type'    => 'array',
        ));
    
        if ($pagination) {
            // Démarrer la structure ul de Bootstrap
            echo '<ul class="pagination justify-content-center">';
    
            // Parcourir les liens de pagination et ajouter des classes Bootstrap
            foreach ($pagination as $page) {
                if (strpos($page, 'current') !== false) {
                    // Lien actif
                    echo '<li class="page-item active">';
                    echo str_replace('<span', '<span class="page-link"', $page);
                    echo '</li>';
                } elseif (strpos($page, 'prev') !== false) {
                    echo '<li class="page-item ' . (strpos($page, 'disabled') !== false ? 'disabled' : '') . '">';
                    echo str_replace('<a', '<a class="page-link"', $page);
                    echo '</li>';
                } elseif (strpos($page, 'next') !== false) {
                    echo '<li class="page-item ' . (strpos($page, 'disabled') !== false ? 'disabled' : '') . '">';
                    echo str_replace('<a', '<a class="page-link"', $page);
                    echo '</li>';
                } else {
                    // Liens de page numérotée
                    echo '<li class="page-item ' . (strpos($page, 'current') !== false ? 'active' : '') . '">';
                    echo str_replace('<a', '<a class="page-link"', $page);
                    echo '</li>';
                }
            }
    
            // Fermer la structure ul
            echo '</ul>';
        }
    }

    /**
     * Render the question rows.
     * 
     * @since 1.6.0 Initial release
     */
    private function render_question_rows(array $questions, string $edit_page): void {
        foreach ($questions as $question):
            $labelLevel = $this->levelsQuiz[$question->niveau]['label'] ?? $question->niveau;
            $freeLevel = $this->levelsQuiz[$question->niveau]['free'] ?? $question->niveau;
        ?>
            <tr>
                <td class="id_column"><?php echo esc_html($question->id); ?></td>
                <td class="access_column"><?php echo esc_html($freeLevel === true ? __('Free', 'form-quizz-fqi3') : __('Premium', 'form-quizz-fqi3')); ?></td>
                <td class="level_column"><?php echo esc_html($labelLevel); ?></td>
                <td class="question_column"><?php echo esc_html($question->q); ?></td>
                <td class="question_2_column"><?php echo esc_html($question->q2); ?></td>
                <td class="options_column">
                    <?php $this->render_options($question->options, $question->id); ?>
                </td>
                <td class="answer_column"><?php echo esc_html($this->get_correct_answer($question)); ?></td>
                <td class="action_column">
                    <a href="#" class="btn btn-sm btn-danger delete-question" data-id="<?php echo esc_attr($question->id); ?>">
                        <?php _e('Delete', 'form-quizz-fqi3'); ?>
                    </a>
                </td>
                <td class="edit_column">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $edit_page . '&id=' . $question->id)); ?>" class="btn btn-sm btn-success">
                        <?php _e('Edit', 'form-quizz-fqi3'); ?>
                    </a>
                </td>
            </tr>
        <?php endforeach;
    }
    

    /**
     * Renders question options in an accordion format
     * 
     * @param string $options_json JSON string containing options
     * @param int $question_id Question identifier
     * @return void
     * 
     * @since 1.6.0 Initial release
     */
    private function render_options(string $options_json, int $question_id): void {
        $options = json_decode($options_json, true);
        if (empty($options)) {
            return;
        }
    
        $question_id = esc_attr($question_id);
        $this->render_accordion_wrapper($question_id, function() use ($options) {
            $this->render_options_list($options);
        });
    }

    /**
     * Renders the accordion wrapper structure
     * 
     * @param string $question_id Escaped question ID
     * @param callable $content_callback Callback to render accordion content
     * @return void
    */
    private function render_accordion_wrapper(string $question_id, callable $content_callback): void {
        $accordion_id = "accordionOptions{$question_id}";
        $collapse_id = "collapseOptions{$question_id}";
        $heading_id = "headingOptions{$question_id}";
        
        ?>
        <div class="accordion small" id="<?php echo $accordion_id; ?>">
            <div class="accordion-item">
                <h2 class="accordion-header" id="<?php echo $heading_id; ?>">
                    <button class="accordion-button collapsed p-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?php echo $collapse_id; ?>"
                            aria-expanded="false"
                            aria-controls="<?php echo $collapse_id; ?>">
                        <?php _e('Show Options', 'form-quizz-fqi3'); ?>
                    </button>
                </h2>
                <div id="<?php echo $collapse_id; ?>"
                    class="accordion-collapse collapse"
                    aria-labelledby="<?php echo $heading_id; ?>"
                    data-bs-parent="#<?php echo $accordion_id; ?>">
                    <div class="accordion-body">
                        <?php $content_callback(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renders the list of options
     * 
     * @param array $options Array of option strings
     * @return void
    */
    private function render_options_list(array $options): void {
        ?>
        <ul class="options-list">
            <?php foreach ($options as $option): ?>
                <li><?php echo esc_html($option); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
    
    /**
     * Get the correct answer.
     * 
     * @since 1.6.0 Initial release
     */
    private function get_correct_answer($question) {
        $options = json_decode($question->options, true);
        return isset($options[(int)$question->answer]) ? $options[(int)$question->answer] : __('Unknown', 'form-quizz-fqi3');
    }


    /**
     * Handles the AJAX request to delete a question from the quiz.
     * Verifies user permissions, checks for valid question ID, and deletes the question from the database.
     * Responds with a JSON success or error message.
     */
    public function handle_delete_question() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'You do not have the necessary permissions to perform this action.' );
            wp_die();
        }

        global $wpdb;
        $table_name = $this->backend->get_quiz_table_name();

        if ( isset( $_POST['id'] ) && is_numeric( $_POST['id'] ) ) {
            error_log(print_r($_POST['id'], true));
            $question_id = intval( $_POST['id'] );

            $deleted = $wpdb->delete( $table_name, array( 'id' => $question_id ) );

            if ( $deleted ) {
                wp_send_json_success( 'Question deleted.' );
            } else {
                wp_send_json_error( 'Error deleting question.' );
            }
        } else {
            wp_send_json_error( 'Invalid question ID.' );
        }
        wp_die();
    }

    /**
     * Exports all quiz questions to a CSV file for download.
     *
     * This method retrieves all quiz questions from the database and generates a CSV file
     * containing the question details such as ID, level, question text, options, and the correct answer.
     * It then streams the CSV file for download by setting the appropriate headers.
     * 
     * @return void
     * @throws WP_Error If database query fails or no questions found
     *
     * @since 1.5.0 Initial release
     * @since 2.0.0 Optimized performance and error handling
     */
    public function export_questions() {
        global $wpdb;
        
        // Use prepared statement to prevent SQL injection
        $table_name = $this->backend->get_quiz_table_name();
        
        // Implement batch processing for large datasets
        $batch_size = 1000;
        $offset = 0;
        
        try {
            // Buffer the output to prevent memory issues
            ob_start();
            
            // Set headers with proper encoding and cache control
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="quiz_questions_' . date('Y-m-d') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Create CSV writer with memory efficiency
            $output = fopen('php://temp', 'r+');
            
            // Define CSV headers
            $headers = [
                'ID',
                'Access',
                'Level',
                'Question',
                'Instructions',
                'Options',
                'Correct Answer'
            ];
            fputcsv($output, $headers);
            
            do {
                // Fetch questions in batches
                $questions = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `{$table_name}` LIMIT %d OFFSET %d",
                        $batch_size,
                        $offset
                    )
                );
                
                if ($wpdb->last_error) {
                    throw new \Exception($wpdb->last_error);
                }
                
                // Process each question in the batch
                foreach ($questions as $question) {
                    $options = json_decode($question->options, true) ?: [];
                    $correct_answer_index = (int) $question->answer;
                    
                    // Get level information with null coalescing
                    $level_info = $this->levelsQuiz[$question->niveau] ?? ['free' => false, 'label' => $question->niveau];
                    
                    // Prepare row data with proper escaping
                    $row = [
                        $question->id,
                        $level_info['free'] ? __('Free', 'form-quizz-fqi3') : __('Premium', 'form-quizz-fqi3'),
                        $level_info['label'],
                        wp_kses_post($question->q),
                        wp_kses_post($question->q2),
                        implode('; ', array_map('esc_html', $options)),
                        esc_html($options[$correct_answer_index] ?? __('Unknown', 'form-quizz-fqi3'))
                    ];
                    
                    fputcsv($output, $row);
                }
                
                $offset += $batch_size;
                
            } while (!empty($questions));
            
            // Check if any questions were processed
            if ($offset === 0) {
                throw new \Exception(__('No questions found for export', 'form-quizz-fqi3'));
            }
            
            // Output the CSV content
            rewind($output);
            fpassthru($output);
            fclose($output);
            ob_end_flush();
            
        } catch (\Exception $e) {
            ob_end_clean();
            wp_die(
                esc_html($e->getMessage()),
                __('Export Error', 'form-quizz-fqi3'),
                ['response' => 500]
            );
        }
        
        exit;
    }
}
endif;