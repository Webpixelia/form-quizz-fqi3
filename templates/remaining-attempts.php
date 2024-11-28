<?php
// templates/remaining-attempts.php
?>
<div class="attempts-container">
    <div class="attempts-col1">
        <p class="infos-attempts">
            <?php if (!$is_premium_user) : ?>
                <span id="attempts-made"><?php echo esc_html($infos_attempts['attempts_made']); ?></span>
                /
                <span id="total-attempts"><?php echo esc_html($infos_attempts['total_attempts']); ?></span>
                <?php esc_html_e('MCQs / Day', 'form-quizz-fqi3'); ?>
            <?php else : ?>
                <?php esc_html_e('Unlimited MCQs', 'form-quizz-fqi3'); ?>
            <?php endif; ?>
        </p>
        
        <div id="progress-bar-attempts" class="bar-attempts">
            <div id="attempts-progress-bar" style="width: <?php 
                echo esc_html(
                    !$is_premium_user && $infos_attempts['total_attempts'] > 0 
                    ? ($infos_attempts['attempts_made'] / $infos_attempts['total_attempts']) * 100 
                    : 0
                );
            ?>%;"></div>
        </div>
    </div>

    <?php if (!$is_premium_user) : ?>
        <div class="attempts-col2">
            <a id="start-quiz-btn" class="cta-premium" href="<?php echo esc_url($sales_page_url); ?>">
                <?php esc_html_e('Upgrade to unlimited', 'form-quizz-fqi3'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>