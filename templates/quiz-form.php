<?php // templates/quiz-form.php ?>
<div id="quiz-container" class="content-quiz" <?php echo $data_attrs ?? ''; ?>>
	<!-- Start Container -->
	<div id="start-container" class="home-box custom-box <?php echo esc_attr( $custom_box_classes ?? '' ); ?>" style="<?php echo esc_attr( $custom_box_styles ?? '' ); ?>">
		<div class="content-pre-form"><?php echo $text_pre_form; ?></div>
		<button type="button" class="btn" onclick="showLevelSelection()">
			<?php echo esc_html( $translations['start'] ); ?>
		</button>
	</div>

	<!-- Level Selection Container -->
	<div id="level-container" class="level-box custom-box <?php echo esc_attr( $custom_box_classes ?? '' ); ?>" style="display:none;<?php echo esc_attr( $custom_box_styles ?? '' ); ?>">
		<?php if ( $is_user_logged_in && $current_user ) : ?>
			<p class="large-p">
				<?php
				/* translators: %s represents the username of the current user */
				printf(
					esc_html( $translations['welcome'] ),
					esc_html( $current_user->user_login )
				);
				?>
			</p>
		<?php endif; ?>

		<h2><?php echo esc_html( $title_form ); ?></h2>
		<div id="level-choice-message"></div>
		
		<div class="level-options">
			<?php foreach ( $levels as $valueLevel => $levelData ) : ?>
				<?php
				if ( ( ! $is_user_logged_in && $levelData['free'] ) ||
							( $is_user_logged_in && ! $levelData['free'] ) ) :
					?>
					<?php $lockState = fqi3_get_level_lock_state( (string) $valueLevel, get_current_user_id() ); ?>
					<?php if ( $lockState['locked'] ) : ?>
						<div class="btn level-locked" aria-disabled="true">
							<span class="level-lock-icon" aria-hidden="true">&#128274;</span>
							<?php echo esc_html( $levelData['label'] ); ?>
							<?php if ( '' !== $lockState['message'] ) : ?>
								<span class="level-lock-message"><?php echo esc_html( $lockState['message'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $lockState['cta_url'] && '' !== $lockState['cta_label'] ) : ?>
								<a class="level-lock-cta" href="<?php echo esc_url( $lockState['cta_url'] ); ?>"><?php echo esc_html( $lockState['cta_label'] ); ?></a>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<label class="btn" onclick="startQuizWithLevel('<?php echo esc_attr( $valueLevel ); ?>')">
							<input type="radio" name="niveau" value="<?php echo esc_attr( $valueLevel ); ?>" required>
							<?php echo esc_html( $levelData['label'] ); ?>
							<span class="question-count" id="question-count-<?php echo esc_attr( $valueLevel ); ?>"></span>
						</label>
					<?php endif; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

		<?php
		if ( ! isset( $showTimer ) ) {
			$showTimer = true;
		}
		if ( $is_user_logged_in && $showTimer ) :
			include 'partials/timer-settings.php';
		endif;
		?>

		<input type="hidden" id="user-level" name="user_level" value="">
	</div>

	<!-- Loading Indicator -->
	<div id="loading-indicator" class="custom-box <?php echo esc_attr( $custom_box_classes ?? '' ); ?>" style="display:none;<?php echo esc_attr( $custom_box_styles ?? '' ); ?>">
		<p><?php echo esc_html( $translations['loading'] ); ?></p>
	</div>

	<!-- Quiz Questions Container -->
	<?php require 'partials/quiz-questions.php'; ?>

	<!-- Results Container -->
	<?php require 'partials/quiz-results.php'; ?>

	<!-- Error Message -->
	<?php require 'partials/error-message.php'; ?>
</div>