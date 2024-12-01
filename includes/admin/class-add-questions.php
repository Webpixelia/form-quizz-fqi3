<?php
/**
 * Class FQI3_Add_Questions_Page
 *
 * Manages the page for adding new questions to the quiz in the WordPress admin for the Form Quizz FQI3 plugin.
 * 
 * This class is responsible for rendering the form to add a new quiz question and processing the form submission.
 * It ensures the security of the form by verifying nonces and sanitizing the input before inserting the new question 
 * into the database. Once the question is successfully added, the user is redirected to the page displaying all quiz 
 * questions. Error handling is implemented to ensure smooth operation in case of failure during the insertion.
 *
 * @package Form Quizz FQI3
 * @since      1.0.0
 * @version    2.0.0
 */

namespace Form_Quizz_FQI3;

use \WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'FQI3_Add_Questions_Page' ) ) :

class FQI3_Add_Questions_Page {
    private const NONCE_ACTION = 'add_question_action';
    private const NONCE_FIELD = 'add_question_nonce';
    private const VALIDATION_RULES = [
        'niveau' => ['required' => true, 'max_length' => 50],
        'questionA' => ['required' => true, 'max_length' => 500],
        'questionB' => ['required' => true, 'max_length' => 500],
        'reponseCorrecte' => ['required' => true, 'min' => 1, 'max' => 4],
    ];

    public function __construct(private FQI3_Backend $backend) {}

    /**
     * Renders the page for adding a new question to the quiz.
     */
    public function render_add_question_page(): void {
        $error_messages = $this->get_error_messages();
        ?>
        <div class="wrap">
            <h2 class="wp-heading-inline page-title">
                <?php esc_html_e('Add question', 'form-quizz-fqi3'); ?>
            </h2>

            <?php if ($error_messages): ?>
                <div class="alert alert-danger is-dismissible" role="alert">
                    <?php foreach ($error_messages as $error): ?>
                        <p><?php echo esc_html($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php 
                $this->backend->render_question_form(
                    'add_question', 
                    null, 
                    self::NONCE_ACTION,
                    self::NONCE_FIELD
                ); 
            ?>
        </div>
        <?php
    }

    /**
     * Handles the submission of the form for adding a new question.
     * Verifies nonce for security, sanitizes input data, and inserts the new question into the database.
     * Redirects to the page displaying all questions upon successful insertion.
     */
    public function handle_add_question(): void {
        try {
            if (!isset($_POST[self::NONCE_FIELD]) || 
                !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
                throw new \Exception(__('Security check failed', 'form-quizz-fqi3'));
            }

            $data = $this->validate_and_sanitize_input();
            if ($data instanceof \WP_Error) {
                $this->handle_error($data);
                return;
            }

            $result = $this->insert_question($data);
            if ($result instanceof \WP_Error) {
                $this->handle_error($result);
                return;
            }

            // Redirect on success
            wp_safe_redirect(
                add_query_arg(
                    'page',
                    $this->backend->get_view_questions_slug(),
                    admin_url('admin.php')
                )
            );
            exit;

        } catch (\Exception $e) {
            $this->handle_error(
                new \WP_Error('insertion_failed', $e->getMessage())
            );
        }
    }

    /**
     * Validates and sanitizes form input
     * 
     * @return array|\WP_Error Sanitized data or WP_Error on failure
     */
    private function or_validate_and_sanitize_input(): array|\WP_Error {
        $data = [];
        
        foreach (self::VALIDATION_RULES as $field => $rules) {
            if (!isset($_POST[$field]) && $rules['required']) {
                return new \WP_Error(
                    'missing_field',
                    // Translators: %s indicates the name of the required field missing.
                    sprintf(__('Field %s is required', 'form-quizz-fqi3'), $field)
                );
            }
        }

        $data['niveau'] = $this->sanitize_unicode_text('niveau', 50);
        $data['questionA'] = $this->sanitize_unicode_text('questionA', 500);
        $data['questionB'] = $this->sanitize_unicode_text('questionB', 500);

        $data['reponses'] = [];
        for ($i = 1; $i <= 4; $i++) {
            $answer = $this->sanitize_unicode_text("reponse{$i}", 255);
            if (empty($answer)) {
                return new \WP_Error(
                    'invalid_answer',
                    // Translators: %d indicates the number of the field answer.
                    sprintf(__('Answer %d cannot be empty', 'form-quizz-fqi3'), $i)
                );
            }
            $data['reponses'][] = $answer;
        }

        $data['reponseCorrecte'] = filter_input(
            INPUT_POST,
            'reponseCorrecte',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 3]]
        );

        if ($data['reponseCorrecte'] === false) {
            return new \WP_Error(
                'invalid_correct_answer',
                __('Invalid correct answer selection', 'form-quizz-fqi3')
            );
        }

        return $data;
    }
    private function validate_and_sanitize_input(): array|\WP_Error {
        $data = [];
        
        foreach (self::VALIDATION_RULES as $field => $rules) {
            if (!isset($_POST[$field]) && $rules['required']) {
                return new \WP_Error(
                    'missing_field',
                    // Translators: %s indicates the name of the required field missing.
                    sprintf(__('Field %s is required', 'form-quizz-fqi3'), $field)
                );
            }
        }
    
        $data['niveau'] = $this->sanitize_unicode_text('niveau', 50);
        $data['questionA'] = $this->sanitize_unicode_text('questionA', 500);
        $data['questionB'] = $this->sanitize_unicode_text('questionB', 500);
    
        $data['reponses'] = [];
        $max_answers = 10; // Maximum in JavaScript
        $min_answers = 4;  // Minimum of answers
    
        $submitted_answers = $_POST['reponse'] ?? [];
    
        if (count($submitted_answers) < $min_answers) {
            return new \WP_Error(
                'insufficient_answers',
                // Translators: %d indicates the minimum answers required.
                sprintf(__('At least %d answers are required', 'form-quizz-fqi3'), $min_answers)
            );
        }
    
        if (count($submitted_answers) > $max_answers) {
            return new \WP_Error(
                'too_many_answers',
                // Translators: %d indicates the maximum answers authorized.
                sprintf(__('Maximum %d answers are allowed', 'form-quizz-fqi3'), $max_answers)
            );
        }
    
        foreach ($submitted_answers as $index => $answer) {
            // Créer un faux champ pour utiliser la méthode sanitize_unicode_text existante
            $_POST['reponse'] = $answer;
            $sanitized_answer = $this->sanitize_unicode_text('reponse', 255);
            
            if (empty($sanitized_answer)) {
                return new \WP_Error(
                    'invalid_answer',
                    // Translators: %d indicates the number of the field answer.
                    sprintf(__('Answer %d cannot be empty', 'form-quizz-fqi3'), $index + 1)
                );
            }
            
            $data['reponses'][] = $sanitized_answer;
        }
    
        $correct_answer = filter_input(
            INPUT_POST,
            'reponseCorrecte',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 0, 
                    'max_range' => count($data['reponses']) - 1
                ]
            ]
        );
    
        if ($correct_answer === false || $correct_answer === null) {
            return new \WP_Error(
                'invalid_correct_answer',
                __('Invalid correct answer selection', 'form-quizz-fqi3')
            );
        }
    
        $data['reponseCorrecte'] = $correct_answer;
    
        return $data;
    }

    /**
     * Inserts a new question into the database
     * 
     * @param array $data Validated and sanitized data
     * @return true|\WP_Error True on success, WP_Error on failure
     */
    private function insert_question(array $data): true|\WP_Error {
        global $wpdb;

        // Ensure proper UTF-8 encoding for the JSON
        $options_json = wp_json_encode($data['reponses'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($options_json === false) {
            return new \WP_Error(
                'json_error',
                __('Error encoding answers', 'form-quizz-fqi3')
            );
        }
        
        $result = $wpdb->insert(
            $this->backend->get_quiz_table_name(),
            [
                'niveau' => $data['niveau'],
                'q' => $data['questionA'],
                'q2' => $data['questionB'],
                'options' => $options_json,
                'answer' => $data['reponseCorrecte'],
            ],
            ['%s', '%s', '%s', '%s', '%d']
        );

        if ($result === false) {
            return new \WP_Error(
                'db_error',
                __('Database error occurred while inserting the question', 'form-quizz-fqi3')
            );
        }

        return true;
    }

    /**
     * Sanitizes text while preserving all Unicode characters including Arabic
     */
    private function sanitize_unicode_text(string $field, int $max_length): string {
        if (!isset($_POST[$field])) {
            return '';
        }

        $value = wp_unslash($_POST[$field]);
        
        // Remove HTML and PHP tags while preserving Unicode characters
        $value = strip_tags($value);
        
        // Remove control characters but preserve Unicode
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        
        // Normalize whitespace
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);
        
        // Truncate by UTF-8 character length, not byte length
        if (mb_strlen($value, 'UTF-8') > $max_length) {
            $value = mb_substr($value, 0, $max_length, 'UTF-8');
        }
        
        return $value;
    }

    private function handle_error(\WP_Error $error): void {
        set_transient('fqi3_add_question_errors', $error->get_error_messages(), 45);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    private function get_error_messages(): array {
        $messages = get_transient('fqi3_add_question_errors');
        delete_transient('fqi3_add_question_errors');
        return is_array($messages) ? $messages : [];
    }
}

endif;