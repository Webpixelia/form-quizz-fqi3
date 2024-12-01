<?php
/**
 * Form to add or edit a quiz question.
 * 
 * This file handles the creation and editing of quiz questions. It provides
 * a form that includes fields for selecting the question's level, entering
 * the question text, providing instructions, setting answer choices, and 
 * selecting the correct answer.
 * 
 * It supports both adding new questions and editing existing ones. If the form 
 * is for editing, it pre-fills the fields with the current question data. 
 * The form is processed via `admin-post.php` and includes proper security 
 * with a nonce field to prevent CSRF attacks.
 * 
 * @package Form Quiz FQI3
 * @since 2.0.0 Initial release
 * @since 2.1.0 Add option to set the number of answer choices for questions
 */

// Initial setup and data preparation
$allOptions = fqi3_get_options();
$rtlClass = isset($allOptions['fqi3_rtl_mode']) && $allOptions['fqi3_rtl_mode'] == 1 ? "rtl-mode" : '';

// Dynamic calculation of the number of responses
$num_answers = $is_edit && !empty($options) 
    ? min(count($options), MAX_ANSWERS_COUNT) 
    : DEFAULT_ANSWERS_COUNT;

// Resetting the flag for required inputs
$first = true;
?>
<form id="<?php echo $is_edit ? 'editQuestionForm' : 'ajouterQuestionForm'; ?>" 
      class="<?php echo $is_edit ? 'editQuestionForm' : 'ajouterQuestionForm'; ?> <?php echo esc_attr($rtlClass); ?>" 
      action="<?php echo esc_url(admin_url('admin-post.php')); ?>" 
      method="post" 
      accept-charset="UTF-8">
    
    <?php if ($is_edit) : ?>
        <input type="hidden" name="question_id" value="<?php echo esc_attr($question->id ?? ''); ?>">
    <?php endif; ?>
    
    <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
    <?php wp_nonce_field($nonce_action, $nonce_name); ?>
    
    <!-- Level selection -->
    <div class="field-question">
        <label class="field-question-label" for="niveau"><?php _e('What level?', 'form-quizz-fqi3'); ?></label>
        <div class="field-question-answers">
            <?php 
            $first = true;
            foreach ($this->levelsQuiz as $valueLevel => $levelData) : ?>
                <label for="<?php echo esc_attr($valueLevel); ?>">
                    <input type="radio" 
                           id="<?php echo esc_attr($valueLevel); ?>" 
                           name="niveau" 
                           value="<?php echo esc_attr($valueLevel); ?>" 
                           <?php if ($first) { echo 'required'; $first = false; } ?> 
                           <?php if ($is_edit) checked($question->niveau, $valueLevel); ?>>
                    <?php echo esc_html($levelData['label']); ?>
                    <?php if ($levelData['free']) : ?>
                        <span style="font-size: xx-small; vertical-align: super;">(<?php _e('Free', 'form-quizz-fqi3'); ?>)</span>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Question text -->
    <div class="field-question">
        <label class="field-question-label" for="questionA"><?php _e('Question:', 'form-quizz-fqi3'); ?></label>
        <input type="text" 
               id="questionA" 
               name="questionA" 
               value="<?php echo esc_attr($question->q ?? ''); ?>" 
               required>
    </div>

    <!-- Instructions text -->
    <div class="field-question">
        <label class="field-question-label" for="questionB"><?php _e('Instructions for the question:', 'form-quizz-fqi3'); ?></label>
        <input type="text" 
               id="questionB" 
               name="questionB" 
               value="<?php echo esc_attr($question->q2 ?? ''); ?>" 
               required>
    </div>

    <!-- Answer options -->
    <div class="field-question">
        <label class="mb-3"><?php _e('Possible answers:', 'form-quizz-fqi3'); ?></label>
        <ul id="answer-options-container">
            <?php for ($i = 1; $i <= $num_answers; $i++) : ?>
                <li class="answer-option">
                    <label for="reponse<?php echo $i; ?>">
                        <?php 
                        // translators: %d is the number of the answer choice (e.g., 0, 1, 2, 3).
                        echo sprintf(__('Answer choice %d:', 'form-quizz-fqi3'), $i); ?>
                    </label>
                    <input type="text"
                        id="reponse<?php echo $i; ?>"
                        name="reponse[]"
                        value="<?php echo esc_attr(isset($options[$i - 1]) ? $options[$i - 1] : ''); ?>"
                        required>
                    <?php if ($i > DEFAULT_ANSWERS_COUNT) : ?>
                        <button type="button" class="remove-answer-option btn btn-danger"><?php esc_html_e('Remove Answer Option', 'form-quizz-fqi3'); ?></button>
                    <?php endif; ?>
                </li>
            <?php endfor; ?>
        </ul>
        
        <?php if ($num_answers < MAX_ANSWERS_COUNT) : ?>
            <button type="button" id="add-answer-option" 
                    class="btn btn-success" 
                    data-max-answers="<?php echo MAX_ANSWERS_COUNT; ?>">
                <?php esc_html_e('Add Answer Option', 'form-quizz-fqi3'); ?>
            </button>
        <?php endif; ?>
    </div>


    <!-- Correct answer selection -->
    <div class="field-question">
        <label class="field-question-label" for="reponseCorrecte">
            <?php _e('Select the correct answer:', 'form-quizz-fqi3'); ?>
        </label>
        <div class="field-question-answers" id="correct-answer-container">
            <?php for ($i = 0; $i < $num_answers; $i++) { 
                $id = "reponseCorrecte" . ($i + 1);
                $value = $i;
                ?>
                <label for="<?php echo esc_attr($id); ?>" class="correct-answer-option">
                    <input type="radio"
                            id="<?php echo esc_attr($id); ?>"
                            name="reponseCorrecte"
                            value="<?php echo esc_attr($value); ?>"
                            <?php if ($first) { echo 'required'; $first = false; } ?>
                            <?php if ($is_edit) checked($question->answer, $value); ?>>
                    <?php 
                    // translators: %d is the number of the answer choice (e.g., 0, 1, 2, 3).
                    echo esc_html(sprintf(__('Answer choice %d', 'form-quizz-fqi3'), $i + 1)); 
                    ?>
                </label>
            <?php } ?>
        </div>
    </div>

    <!-- Submit button -->
    <div class="mb-3">
        <button type="submit" class="btn btn-primary">
            <?php echo $is_edit ? __('Edit question', 'form-quizz-fqi3') : __('Add question', 'form-quizz-fqi3'); ?>
        </button>
    </div>
</form>