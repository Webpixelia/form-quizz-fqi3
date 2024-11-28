<?php
namespace Form_Quizz_FQI3;
/**
 * Class FQI3_Edit_Questions_Page
 *
 * Handles the display and processing of the "Edit Question" page in the WordPress admin for the Form Quizz FQI3 plugin.
 * 
 * This class provides the interface and logic for editing existing quiz questions, including rendering the edit form 
 * and handling updates to the database. The page validates the question ID, displays error messages if the question 
 * is not found, and ensures security by verifying nonces during form submission. It also processes the form submission 
 * to update the question data, including options and correct answers, in the database.
 *
 * @package Form Quizz FQI3
 * @since      1.0.0
 * @version    2.0.0
 */

use \WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Edit_Questions_Page' ) ) :
class FQI3_Edit_Questions_Page {
    /**
     * @var string The nonce action name
     */
    private const NONCE_ACTION = 'update_question_action';
    
    /**
     * @var string The nonce field name
     */
    private const NONCE_FIELD = 'update_question_nonce';

    /**
     * Validation rules for each field
     */
    private const VALIDATION_RULES = [
        'question_id' => ['required' => true, 'type' => 'int'],
        'niveau' => ['required' => true, 'max_length' => 50],
        'questionA' => ['required' => true, 'max_length' => 500],
        'questionB' => ['required' => true, 'max_length' => 500],
        'reponse1' => ['required' => true, 'max_length' => 255],
        'reponse2' => ['required' => true, 'max_length' => 255],
        'reponse3' => ['required' => true, 'max_length' => 255],
        'reponse4' => ['required' => true, 'max_length' => 255],
        'reponseCorrecte' => ['required' => true, 'type' => 'int', 'min' => 0, 'max' => 3]
    ];

    public function __construct(private FQI3_Backend $backend) {}

     /**
     * Renders the page to edit a question.
     * 
     * Displays a form to edit an existing question. If no question ID is provided or if the question is not found,
     * appropriate error messages are shown. If the question is found, the form is populated with existing data.
     */
    public function render_edit_question_page(): void {
        $question_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
        ?>
        <div class="wrap">
            <h2 class="page-title">
                <?php _e('Edit question', 'form-quizz-fqi3'); ?>
            </h2>
        <?php 
        if (!$question_id) {
            $this->render_invalid_id_message();
            return;
        }

        $question = $this->get_question($question_id);
        if (is_wp_error($question)) {
            echo '<div class="alert alert-danger" role="alert"><p>', esc_html($question->get_error_message()), '</p></div>';
            return;
        }

        $options = $this->parse_question_options($question);
        if (is_wp_error($options)) {
            echo '<div class="alert alert-danger" role="alert"><p>', esc_html($options->get_error_message()), '</p></div>';
            return;
        }

        $this->backend->render_question_form('update_question', $question, self::NONCE_ACTION, self::NONCE_FIELD);
        ?>
        </div>
        <?php
    }

    /**
     * Handles the update of a question.
     * 
     * Verifies the nonce for security, retrieves the submitted data, and updates the question in the database. 
     * Redirects to the question view page with a success or failure message.
     */
    public function handle_update_question(): void {
        if (!$this->verify_update_nonce()) {
            wp_die(__('Security check failed.', 'form-quizz-fqi3'), '', ['response' => 403]);
        }

        $question_data = $this->validate_question_data($_POST);
        if (is_wp_error($question_data)) {
            $this->redirect_with_error($question_data->get_error_message());
            return;
        }

        $result = $this->update_question($question_data);
        if (is_wp_error($result)) {
            $this->redirect_with_error($result->get_error_message());
            return;
        }

        $this->redirect_with_success();
    }

    /**
     * Retrieves a question from the database
     *
     * @param int $question_id Question ID
     * @return object|WP_Error Question object or error
     */
    private function get_question(int $question_id) {
        global $wpdb;
        
        $table_name = $this->backend->get_quiz_table_name();
        $question = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $question_id)
        );

        if (!$question) {
            return new WP_Error(
                'question_not_found',
                __('Question not found.', 'form-quizz-fqi3')
            );
        }

        return $question;
    }

    /**
     * Parses and validates question options
     *
     * @param object $question Question object
     * @return array|WP_Error Parsed options or error
     */
    private function parse_question_options(object $question) {
        $options = json_decode($question->options, true);
        
        if (!$options) {
            return new WP_Error(
                'invalid_options',
                __('Error: Question options not found or invalid.', 'form-quizz-fqi3')
            );
        }

        return $options;
    }

    /**
     * Validates and sanitizes question data with proper Unicode support
     *
     * @param array $post_data POST data
     * @return array|WP_Error Sanitized data or error
     */
    private function validate_question_data(array $post_data): array|WP_Error {
        // Check for required fields
        foreach (self::VALIDATION_RULES as $field => $rules) {
            if (!isset($post_data[$field]) && $rules['required']) {
                return new WP_Error(
                    'missing_field',
                    sprintf(__('Field %s is required', 'form-quizz-fqi3'), $field)
                );
            }
        }

        // Validate question ID
        $question_id = filter_var($post_data['question_id'], FILTER_VALIDATE_INT);
        if (!$question_id) {
            return new WP_Error(
                'invalid_question_id',
                __('Invalid question ID', 'form-quizz-fqi3')
            );
        }

        // Validate correct answer
        $reponseCorrecte = filter_var(
            $post_data['reponseCorrecte'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 3]]
        );
        if ($reponseCorrecte === false) {
            return new WP_Error(
                'invalid_correct_answer',
                __('Invalid correct answer selection', 'form-quizz-fqi3')
            );
        }

        try {
            // Sanitize text fields with Unicode support
            $sanitized_data = [
                'id' => $question_id,
                'niveau' => $this->sanitize_unicode_text($post_data['niveau'], self::VALIDATION_RULES['niveau']['max_length']),
                'q' => $this->sanitize_unicode_text($post_data['questionA'], self::VALIDATION_RULES['questionA']['max_length']),
                'q2' => $this->sanitize_unicode_text($post_data['questionB'], self::VALIDATION_RULES['questionB']['max_length']),
                'answer' => $reponseCorrecte
            ];

            // Sanitize and validate answers
            $answers = [];
            for ($i = 1; $i <= 4; $i++) {
                $answer = $this->sanitize_unicode_text(
                    $post_data["reponse{$i}"], 
                    self::VALIDATION_RULES["reponse{$i}"]['max_length']
                );
                
                if (empty($answer)) {
                    return new WP_Error(
                        'invalid_answer',
                        sprintf(__('Answer %d cannot be empty', 'form-quizz-fqi3'), $i)
                    );
                }
                $answers[strval($i - 1)] = $answer;
            }

            $sanitized_data['options'] = json_encode($answers, JSON_UNESCAPED_UNICODE);
            if ($sanitized_data['options'] === false) {
                throw new \Exception(__('Failed to encode answers', 'form-quizz-fqi3'));
            }

            return $sanitized_data;

        } catch (\Exception $e) {
            return new WP_Error(
                'validation_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Sanitizes Unicode text while preserving special characters
     *
     * @param string $value The text to sanitize
     * @param int $max_length Maximum allowed length
     * @return string Sanitized text
     */
    private function sanitize_unicode_text(string $value, int $max_length): string {
        // Remove WordPress slashes if present
        $value = wp_unslash($value);
        
        // Remove HTML and PHP tags while preserving Unicode characters
        $value = strip_tags($value);
        
        // Remove control characters but preserve Unicode
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        
        // Normalize whitespace while respecting Unicode
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);
        
        // Truncate by UTF-8 character length, not byte length
        if (mb_strlen($value, 'UTF-8') > $max_length) {
            $value = mb_substr($value, 0, $max_length, 'UTF-8');
        }
        
        return $value;
    }

    /**
     * Updates the question in the database
     *
     * @param array $data Question data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function update_question(array $data): bool|WP_Error {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->backend->get_quiz_table_name(),
            [
                'niveau' => $data['niveau'],
                'q' => $data['q'],
                'q2' => $data['q2'],
                'options' => $data['options'],
                'answer' => $data['answer']
            ],
            ['id' => $data['id']],
            ['%s', '%s', '%s', '%s', '%d'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error(
                'update_failed',
                __('Failed to update question.', 'form-quizz-fqi3')
            );
        }

        return true;
    }

    /**
     * Verifies the update nonce
     *
     * @return bool
     */
    private function verify_update_nonce(): bool {
        return (
            isset($_POST[self::NONCE_FIELD]) &&
            wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)
        );
    }

    /**
     * Renders the invalid ID message and back button
     *
     * @return void
     */
    private function render_invalid_id_message(): void {
        ?>
        <div class="alert alert-danger" role="alert">
            <p><?php esc_html_e('Invalid question ID. Please choose the question to modify in the question consultation page.', 'form-quizz-fqi3'); ?></p>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->backend->get_view_questions_slug())); ?>" 
           class="btn btn-warning">
            <?php esc_html_e('View questions', 'form-quizz-fqi3'); ?>
        </a>
        <?php
    }

    /**
     * Redirects with an error message
     *
     * @param string $message Error message
     * @return void
     */
    private function redirect_with_error(string $message): void {
        $redirect_url = add_query_arg([
            'page' => $this->backend->get_view_questions_slug(),
            'update' => 'failed',
            'message' => urlencode($message)
        ], admin_url('admin.php'));
        
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Redirects with a success message
     *
     * @return void
     */
    private function redirect_with_success(): void {
        wp_safe_redirect(add_query_arg([
            'page' => $this->backend->get_view_questions_slug(),
            'update' => 'success'
        ], admin_url('admin.php')));
        exit;
    }
}

endif;