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
 * @since 2.0.0
 */
?>
<form id="<?php echo $is_edit ? 'editQuestionForm' : 'ajouterQuestionForm'; ?>" 
      class="<?php echo $is_edit ? 'editQuestionForm' : 'ajouterQuestionForm'; ?>" 
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
        <ul>
        <?php for ($i = 1; $i <= 4; $i++) : ?>
            <li>
                <label for="reponse<?php echo $i; ?>">
                    <?php 
                    // translators: %d is the number of the answer choice (e.g., 0, 1, 2, 3.
                    echo sprintf(__('Answer choice %d:', 'form-quizz-fqi3'), $i); ?>
                </label>
                <input type="text" 
                    id="reponse<?php echo $i; ?>" 
                    name="reponse<?php echo $i; ?>" 
                    value="<?php echo esc_attr(isset($options[$i - 1]) ? $options[$i - 1] : ''); ?>" 
                    required>
            </li>
        <?php endfor; ?>
        </ul>
    </div>

    <!-- Correct answer selection -->
    <div class="field-question">
        <label class="field-question-label" for="reponseCorrecte"><?php _e('Select the correct answer:', 'form-quizz-fqi3'); ?></label>
        <div class="field-question-answers">
            <?php
            $first = true;
            $num_answers = 4; 
            for ($i = 0; $i < $num_answers; $i++) {
                $id = "reponseCorrecte" . ($i + 1);
                $value = $i;
                ?>
                <label for="<?php echo esc_attr($id); ?>">
                    <input type="radio" 
                           id="<?php echo esc_attr($id); ?>" 
                           name="reponseCorrecte" 
                           value="<?php echo esc_attr($value); ?>" 
                           <?php if ($first) { echo 'required'; $first = false; } ?> 
                           <?php if ($is_edit) checked($question->answer, $value); ?>>
                    <?php 
                    // translators: %d is the number of the answer choice (e.g., 0, 1, 2, 3).
                    echo esc_html(sprintf(__('Answer choice %d', 'form-quizz-fqi3'), $i + 1)); ?>
                </label>
                <?php
            }
            ?>
        </div>
    </div>

    <!-- Submit button -->
    <div class="mb-3">
        <button type="submit" class="btn btn-primary">
            <?php echo $is_edit ? __('Edit question', 'form-quizz-fqi3') : __('Add question', 'form-quizz-fqi3'); ?>
        </button>
    </div>
</form>