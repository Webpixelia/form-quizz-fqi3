<?php
namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FQI3_Tools_Settings' ) ) :

	class FQI3_Tools_Settings {
		public function register_settings() {
			$this->add_settings_section();
		}

		private function add_settings_section() {
			$settings = fqi3_options_settings_sections();
			$sections = $settings['sections']['tools'];

			add_settings_section(
				$sections['id'],
				$sections['title'],
				null,
				fqi3_get_options_page_slug(),
				array(
					'before_section' => '<div id="' . esc_attr( array_search( $sections, $settings['sections'] ) ) . '" class="fqi3-section-options-page">',
					'after_section'  => '</div>',
				)
			);

			$this->add_tools_settings_fields();
		}

		private function add_tools_settings_fields() {
			// Purge settings
			add_settings_field(
				'fqi3_attempts_purge_enabled',
				__( 'Enable automatic purge of attempts', 'form-quizz-fqi3' ),
				array( $this, 'render_purge_enabled' ),
				fqi3_get_options_page_slug(),
				'fqi3_tools_section'
			);
			add_settings_field(
				'fqi3_attempts_retention_days',
				__( 'Retention period (days)', 'form-quizz-fqi3' ),
				array( $this, 'render_retention_days' ),
				fqi3_get_options_page_slug(),
				'fqi3_tools_section'
			);
			add_settings_field(
				'fqi3_disable_sharing',
				__( 'Disable social sharing of results', 'form-quizz-fqi3' ),
				array( $this, 'render_disable_sharing_field' ),
				fqi3_get_options_page_slug(),
				'fqi3_tools_section'
			);
			add_settings_field(
				'fqi3_disable_statistics',
				__( 'Disable statistics', 'form-quizz-fqi3' ),
				array( $this, 'render_disable_statistics' ),
				fqi3_get_options_page_slug(),
				'fqi3_tools_section'
			);
			add_settings_field(
				'fqi3_delete_data',
				__( 'Delete data on uninstall', 'form-quizz-fqi3' ),
				array( $this, 'render_delete_data_field' ),
				fqi3_get_options_page_slug(),
				'fqi3_tools_section'
			);
		}

		public function render_purge_enabled() {
			$options = fqi3_get_options();
			// Enabled by default when not set
			$checked = ( ! isset( $options['fqi3_attempts_purge_enabled'] ) || ! empty( $options['fqi3_attempts_purge_enabled'] ) ) ? 'checked' : '';
			echo '<div class="form-check form-switch">';
			echo '<input class="form-check-input" type="checkbox" id="fqi3_attempts_purge_enabled" name="fqi3_options[fqi3_attempts_purge_enabled]" value="1" ' . esc_attr( $checked ) . ' />';
			echo '<label class="form-check-label" for="fqi3_attempts_purge_enabled">' . esc_html__( 'Purge attempts automatically via daily cron', 'form-quizz-fqi3' ) . '</label>';
			echo '</div>';
		}

		public function render_retention_days() {
			$options = fqi3_get_options();
			$value   = isset( $options['fqi3_attempts_retention_days'] ) ? (int) $options['fqi3_attempts_retention_days'] : 30;
			echo '<div class="input-group input-group-sm" style="max-width:220px">';
			echo '<input type="number" min="1" step="1" class="form-control" id="fqi3_attempts_retention_days" name="fqi3_options[fqi3_attempts_retention_days]" value="' . esc_attr( $value ) . '" />';
			echo '<span class="input-group-text">' . esc_html__( 'days', 'form-quizz-fqi3' ) . '</span>';
			echo '</div>';
		}

		// Reuse renderers similar to General
		public function render_delete_data_field() {
			$options = fqi3_get_options();
			$checked = isset( $options['fqi3_delete_data'] ) && $options['fqi3_delete_data'] ? 'checked' : '';
			echo '<div class="form-check form-switch">';
			echo '<input class="form-check-input" type="checkbox" id="fqi3_delete_data" name="fqi3_options[fqi3_delete_data]" value="1" ' . esc_attr( $checked ) . ' />';
			echo '<label class="form-check-label" for="fqi3_delete_data">' . esc_html__( 'Check this to delete data on uninstall', 'form-quizz-fqi3' ) . '</label>';
			echo '</div>';
			echo '<div class="alert alert-warning mt-2" role="alert">'
			. '<i class="bi bi-exclamation-triangle-fill me-1"></i>'
			. esc_html__( 'Warning: This option will delete all plugin data when uninstalling it. This action cannot be undone.', 'form-quizz-fqi3' )
			. '</div>';
		}

		public function render_disable_sharing_field() {
			$options = fqi3_get_options();
			$checked = isset( $options['fqi3_disable_sharing'] ) && $options['fqi3_disable_sharing'] ? 'checked' : '';
			echo '<div class="form-check form-switch">';
			echo '<input class="form-check-input" type="checkbox" id="fqi3_disable_sharing" name="fqi3_options[fqi3_disable_sharing]" value="1" ' . esc_attr( $checked ) . ' />';
			echo '<label class="form-check-label" for="fqi3_disable_sharing">' . esc_html__( 'Disable social sharing of results', 'form-quizz-fqi3' ) . '</label>';
			echo '</div>';
			echo '<p>' . esc_html__( 'Note: Only applicable for logged-in users.', 'form-quizz-fqi3' ) . '</p>';
		}

		public function render_disable_statistics() {
			$options = fqi3_get_options();
			$checked = isset( $options['fqi3_disable_statistics'] ) && $options['fqi3_disable_statistics'] ? 'checked' : '';
			echo '<div class="form-check form-switch">';
			echo '<input class="form-check-input" type="checkbox" id="fqi3_disable_statistics" name="fqi3_options[fqi3_disable_statistics]" value="1" ' . esc_attr( $checked ) . ' />';
			echo '<label class="form-check-label" for="fqi3_disable_statistics">' . esc_html__( 'Disable results statistics', 'form-quizz-fqi3' ) . '</label>';
			echo '</div>';
		}
	}

endif;
