<?php
// templates/partials/quiz-results.php
?>
<div id="result-container" class="result-box custom-box" style="display:none;">
    <h1><?php echo esc_html($translations['results']); ?></h1>
    <p id="result-text"></p>
    <div class="end-bt">
        <?php if (!$is_user_logged_in) : ?>
            <button class="btn btn-secondary" onclick="restartQuiz()">
                <?php echo esc_html($translations['restart_quiz']); ?>
            </button>
            <a class="btn" href="<?php echo esc_url(wp_login_url()); ?>">
                <?php echo esc_html($translations['sign_up']); ?>
            </a>
        <?php else : ?>
            <a class="btn" href="javascript:void(0);" onclick="handleGoBackToOrigin()">
                <?php echo esc_html($translations['go_back']); ?>
            </a>
        <?php endif; ?>
    </div>

    <div id="incorrect-answers-container" class="x-scroll mt-20 mb-20">
        <h2><?php _e('Incorrect answers','form-quizz-fqi3'); ?></h2>
        <p><?php _e('Here is the list of questions that you did not answer correctly and that you could rework','form-quizz-fqi3'); ?></h2></p>
        <table id="incorrect-answers-table" class="fqi3-table">
            <thead>
                <tr>
                    <th><?php _e('Question','form-quizz-fqi3'); ?></th>
                    <th><?php _e('Your answer','form-quizz-fqi3'); ?></th>
                    <th><?php _e('Good answer','form-quizz-fqi3'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- The lines will be injected here by JavaScript -->
            </tbody>
        </table>
    </div>

    <?php if ($is_user_logged_in && $sharing_enabled) : ?>
        <div class="social-share">
            <p><?php echo esc_html($translations['share_results']); ?></p>
            <button class="btn facebook" onclick="shareOnFacebook()">
                <?php echo esc_html($translations['share_facebook']); ?>
            </button>
            <button class="btn x" onclick="shareOnX()">
                <?php echo esc_html($translations['share_x']); ?>
            </button>
            <button class="btn linkedin" onclick="shareOnLinkedIn()">
                <?php echo esc_html($translations['share_linkedin']); ?>
            </button>
        </div>
    <?php endif; ?>
</div>