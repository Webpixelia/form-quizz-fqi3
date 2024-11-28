<?php
// templates/quiz-form.php
?>
<div id="quiz-container" class="content-quiz">
    <!-- Start Container -->
    <div id="start-container" class="home-box custom-box"> 
        <div class="content-pre-form"><?php echo $text_pre_form; ?></div>
        <button type="button" class="btn" onclick="showLevelSelection()">
            <?php echo esc_html($translations['start']); ?>
        </button>
    </div>

    <!-- Level Selection Container -->
    <div id="level-container" class="level-box custom-box" style="display:none;">
        <?php if ($is_user_logged_in && $current_user) : ?>
            <p class="large-p">
                <?php 
                /* translators: %s represents the username of the current user */
                printf(
                    esc_html($translations['welcome']),
                    esc_html($current_user->user_login)
                ); ?>
            </p>
        <?php endif; ?>

        <h2><?php echo esc_html($title_form); ?></h2>
        <div id="level-choice-message"></div>
        
        <div class="level-options">
            <?php foreach ($levels as $valueLevel => $levelData) : ?>
                <?php if ((!$is_user_logged_in && $levelData['free']) || 
                         ($is_user_logged_in && !$levelData['free'])) : ?>
                    <label class="btn" onclick="startQuizWithLevel('<?php echo esc_attr($valueLevel); ?>')">
                        <input type="radio" name="niveau" value="<?php echo esc_attr($valueLevel); ?>" required>
                        <?php echo esc_html($levelData['label']); ?>
                        <span class="question-count" id="question-count-<?php echo esc_attr($valueLevel); ?>"></span>
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($is_user_logged_in) : ?>
            <?php include 'partials/timer-settings.php'; ?>
        <?php endif; ?>

        <input type="hidden" id="user-level" name="user_level" value="">
    </div>

    <!-- Loading Indicator -->
    <div id="loading-indicator" style="display:none;">
        <p><?php echo esc_html($translations['loading']); ?></p>
    </div>

    <!-- Quiz Questions Container -->
    <?php include 'partials/quiz-questions.php'; ?>

    <!-- Results Container -->
    <?php include 'partials/quiz-results.php'; ?>

    <!-- Error Message -->
    <?php include 'partials/error-message.php'; ?>
</div>