<?php
/**
 * Custom admin header for the plugin.
 *
 * @package Form Quizz FQI3
 * 
 * @since 1.3.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$admin_pages = fqi3_get_admin_pages();
$slugViewInfos = $admin_pages['infos']['slug'];
$documentation_url = esc_url(admin_url('admin.php?page=' . $slugViewInfos));
$support_url = 'https://webpixelia.com/contact';
?>
<div class="fqi3_banner">
    <div class="fqi3_banner_wrapper">
        <div class="fqi3_logo">
            <h1><?php echo esc_html(FQI3_NAME); ?></h1>
            <span class="fqi3_version">v<?php echo esc_html(FQI3_VERSION); ?></span>
        </div>
        <div class="fqi3_meta">
            <a rel="noopener noreferrer" href="<?php echo esc_url($documentation_url); ?>"><?php esc_html_e('Documentation', 'form-quizz-fqi3'); ?></a>
            <a target="_blank" href="<?php echo esc_url($support_url); ?>"><?php esc_html_e('Support', 'form-quizz-fqi3'); ?></a>
		</div> <!-- end fqi3_meta -->
    </div> <!-- end fqi3_banner_wrapper -->		
</div>