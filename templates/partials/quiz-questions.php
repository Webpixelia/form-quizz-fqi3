<?php
// templates/partials/quiz-questions.php
?>
<div id="quiz-questions-container" class="quiz-box custom-box" style="display:none;">
    <div id="timer-display"></div>
    <div id="question-title" class="question-number"></div>
    <div id="reappuyer"></div>
    <div id="options-container"></div>
    <div id="answers-indicator-container" class="answers-indicator-container"></div>
    <div class="next-question-btn">
        <button id="next-button" class="btn" onclick="nextQuestion()">
            <?php echo esc_html($translations['next']); ?>
        </button>
    </div>
</div>