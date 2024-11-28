<?php
// templates/partials/error-message.php
?>
<div id="error-message">
    <p id="error-text"></p>
    <p><?php echo esc_html($translations['upgrade_message']); ?></p>
    <a href="<?php echo esc_url($selected_page_url); ?>" class="btn">
        <?php echo esc_html($translations['view_details']); ?>
    </a>
</div>