<?php
/**
 * Custom admin footer for the plugin.
 *
 * @package Form Quizz FQI3
 * 
 * @since 1.3.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
?>

<div id="fqi3_footer" role="contentinfo">
		<p id="footer-left" class="alignleft">
		    <?php 
			$url = 'https://webpixelia.com/';
			$developer_name = 'Jonathan Webpixelia';
			$link = sprintf(
				wp_kses(
					// Translators: %1$s is the plugin name, %2$s is the URL of the developer, and %3$s is the developer's name.
					__( '%1$s is developed and maintained by <a href="%2$s" target="_blank">%3$s</a>.', 'form-quizz-fqi3' ),
					array(
						'a' => array( 'href' => array(), 'target' => array() )
					)
				),
				FQI3_NAME,
				esc_url( $url ),
				$developer_name
			);
			
			echo $link;
			?>
        </p>
	    <p id="footer-upgrade" class="alignright">
		</p>
	<div class="clear"></div>
</div>