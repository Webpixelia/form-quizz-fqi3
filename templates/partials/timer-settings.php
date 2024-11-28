<?php
// templates/partials/timer-settings.php
?>
<div class="timer-options">
    <label>
        <input type="checkbox" id="enable-timer" <?php echo $timer_settings['enabled'] ? 'checked' : ''; ?>>
        <?php echo esc_html($translations['timer_label']); ?>
    </label>
    <div id="timer-settings" style="<?php echo $timer_settings['enabled'] ? 'display:block;' : 'display:none;'; ?>">
        <label>
            <?php echo esc_html($translations['timer_duration']); ?>
            <input type="number" 
                   id="timer-duration" 
                   class="timer-duration-input" 
                   min="1" 
                   value="<?php echo esc_attr($timer_settings['duration']); ?>"
                   <?php echo !$timer_settings['enabled'] ? 'disabled' : ''; ?>>
        </label>
    </div>
</div>