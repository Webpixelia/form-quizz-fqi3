<?php
/**
 * Template for rendering generate sats manually
 *
 */
?>
<div class="wrap container mt-5">
    <h2 class="page-title mb-4"><?php _e('Manually Record Weekly Statistics', 'form-quizz-fqi3'); ?> <span class="badge text-bg-info"><?php _e('New', 'form-quizz-fqi3'); ?></span></h2>

    <form method="POST" action="" class="border p-4 rounded shadow-sm bg-light">
        <?php wp_nonce_field('record_weekly_stats_nonce', 'stats_nonce'); ?>
        <div class="mb-3 row">
            <div class="col">
                <label for="user_id" class="form-label"><?php _e('User:', 'form-quizz-fqi3'); ?></label>
                <select name="user_id" id="user_id" class="form-select" required>
                    <?php
                    $users = get_users(); 
                    foreach ($users as $user) {
                        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col">
                <label for="start_date" class="form-label"><?php _e('Start Date:', 'form-quizz-fqi3'); ?></label>
                <input type="date" name="start_date" id="start_date" class="form-control" required>
            </div>
            <div class="col">
                <label for="end_date" class="form-label"><?php _e('End Date:', 'form-quizz-fqi3'); ?></label>
                <input type="date" name="end_date" id="end_date" class="form-control" required>
            </div>
        </div>
        <div class="mb-3 text-center">
            <input type="submit" name="record_weekly_stats" value="<?php _e('Record Statistics', 'form-quizz-fqi3'); ?>" class="btn btn-primary">
        </div>
    </form>
</div>