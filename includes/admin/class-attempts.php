<?php
namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/*
 * Class FQI3_Attempts_Page
 *
 * @since 3.0.0
 */
class FQI3_Attempts_Page {
	private FQI3_Backend $backend;

	public function __construct( FQI3_Backend $backend ) {
		$this->backend = $backend;
		// Handle CSV export via admin-post to avoid sending headers after admin output
		add_action( 'admin_post_fqi3_attempts_export', array( $this, 'handle_export' ) );
		// Handle Manual Purge
		add_action( 'admin_post_fqi3_attempts_purge_now', array( $this, 'handle_purge_now' ) );
	}

	public function render_attempts_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'form-quizz-fqi3' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fqi3_quiz_attempts';
		$users = $wpdb->users;

		$per_page = (int) get_option( 'fqi3_attempts_per_page', 20 );
		if ( $per_page <= 0 ) {
			$per_page = 20; }
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		$level   = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		$from    = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to      = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$export  = isset( $_GET['export'] ) ? sanitize_text_field( wp_unslash( $_GET['export'] ) ) : '';

		$wheres = array();
		$params = array();
		if ( $level !== '' ) {
			$wheres[] = 'a.level = %s';
			$params[] = $level; }
		if ( $user_id > 0 ) {
			$wheres[] = 'a.user_id = %d';
			$params[] = $user_id; }
		if ( $from !== '' ) {
			$wheres[] = 'a.started_at >= %s';
			$params[] = $from . ' 00:00:00'; }
		if ( $to !== '' ) {
			$wheres[] = 'a.started_at <= %s';
			$params[] = $to . ' 23:59:59'; }
		$where_sql = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';

		// CSV export moved to admin-post handler (handle_export)

		$sql_count = "SELECT COUNT(*) FROM {$table} a {$where_sql}";
		$total     = ! empty( $params ) ? (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) ) : (int) $wpdb->get_var( $sql_count );

		$sql_rows      = "SELECT a.id, a.user_id, a.level, a.score, a.started_at, a.completed_at, u.display_name, u.user_email
                     FROM {$table} a LEFT JOIN {$users} u ON u.ID = a.user_id
                     {$where_sql}
                     ORDER BY a.started_at DESC LIMIT %d OFFSET %d";
		$params_rows   = array_merge( $params, array( $per_page, $offset ) );
		$prepared_rows = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql_rows ), $params_rows ) );
		$rows          = $wpdb->get_results( $prepared_rows );

		$total_pages  = max( 1, (int) ceil( $total / $per_page ) );
		$pages        = fqi3_get_admin_pages();
		$parent_slug  = $pages['main']['slug'];
		$current_slug = $pages['attempts']['slug'];

		echo '<div class="wrap container-fluid">';
			echo '<div class="row mb-4">';
				echo '<div class="col">';
					echo '<h2 class="wp-heading-inline page-title">' . esc_html__( 'Attempts (Audit)', 'form-quizz-fqi3' ) . '</h2><hr>';
					echo '<p>' .
						sprintf(
							/* translators: %1$d is the number of days after which attempts are automatically purged, %2$s is a link to the settings page */
							__(
								'This page displays all the attempts made by users on the quizzes. You can filter the attempts by level, user ID, from date, and to date. You can also export the attempts to a CSV file. You can also purge the attempts. The purge is automatic after <strong>%1$d</strong> days (<a href="%2$s">View settings</a>).',
								'form-quizz-fqi3'
							),
							(int) get_option( 'fqi3_attempts_retention_days', 30 ),
							esc_url( admin_url( 'admin.php?page=fqi3-options-quiz-questions' ) )
						)
					. '</p>';
				echo '</div>'; // .col
			echo '</div>'; // .row.mb-4

			// Filters + Export + Purge
			echo '<div class="container-fluid mt-4">';
				echo '<div class="bg-light p-4 rounded shadow-sm">';
					echo '<div class="row g-3 align-items-center">';

						// Filter form
						echo '<div class="col-12 col-md-9">';
							echo '<form method="get" class="tablenav top row row-cols-lg-auto g-3 align-items-end flex-nowrap mb-2">';
								echo '<input type="hidden" name="page" value="' . esc_attr( $current_slug ) . '" />';

								// Levels dropdown
								$levels_options = get_option( 'fqi3_quiz_levels', array() );
								$level_names    = $levels_options['fqi3_quiz_levels_name'] ?? array();
								$level_labels   = $levels_options['fqi3_quiz_levels_label'] ?? array();

								// Level (label above)
								echo '<div class="col-auto">';
									echo '<label class="form-label mb-1">' . esc_html__( 'Level', 'form-quizz-fqi3' ) . '</label>';
									echo '<select name="level" class="form-select form-select-sm">';
										echo '<option value="">' . esc_html__( 'All levels', 'form-quizz-fqi3' ) . '</option>';
		foreach ( $level_names as $idx => $name ) {
			$name  = (string) $name;
			$label = isset( $level_labels[ $idx ] ) && $level_labels[ $idx ] !== '' ? $level_labels[ $idx ] : $name;
			echo '<option value="' . esc_attr( $name ) . '" ' . selected( $level, $name, false ) . '>' . esc_html( $label ) . '</option>';
		}
									echo '</select>';
								echo '</div>';

								// User ID
								echo '<div class="col-auto">';
									echo '<label class="form-label mb-1">' . esc_html__( 'User ID', 'form-quizz-fqi3' ) . '</label>';
									echo '<input type="number" class="form-control form-control-sm" name="user_id" value="' . esc_attr( $user_id ) . '" />';
								echo '</div>';

								// From date
								echo '<div class="col-auto">';
									echo '<label class="form-label mb-1">' . esc_html__( 'From', 'form-quizz-fqi3' ) . '</label>';
									echo '<input type="date" class="form-control form-control-sm" name="from" value="' . esc_attr( $from ) . '" />';
								echo '</div>';

								// To date
								echo '<div class="col-auto">';
									echo '<label class="form-label mb-1">' . esc_html__( 'To', 'form-quizz-fqi3' ) . '</label>';
									echo '<input type="date" class="form-control form-control-sm" name="to" value="' . esc_attr( $to ) . '" />';
								echo '</div>';

								// Use a custom button to avoid WP's enforced 'button' class and allow an icon
								echo '<div class="col-auto">';
									echo '<button type="submit" class="btn btn-dark btn-sm">'
										. '<i class="bi bi-filter"></i> '
										. esc_html__( 'Filter', 'form-quizz-fqi3' )
										. '</button>';
								echo '</div>';

							echo '</form>';
						echo '</div>'; // col-md-10

						// Export & Purge buttons (outside form)
						echo '<div class="col-12 col-md-3 d-flex justify-content-end gap-2">';
							// Build admin-post export URL with filters and nonce
							$export_url = add_query_arg(
								array(
									'action'  => 'fqi3_attempts_export',
									'level'   => $level,
									'user_id' => $user_id,
									'from'    => $from,
									'to'      => $to,
								),
								admin_url( 'admin-post.php' )
							);
							$export_url = wp_nonce_url( $export_url, 'fqi3_attempts_export' );

							echo '<a class="btn btn-success btn-sm" href="' . esc_url( $export_url ) . '"><i class="bi bi-download"></i>'
								. esc_html__( 'Export CSV', 'form-quizz-fqi3' )
								. '</a>';

							// Manual purge button (manage_options only)
		if ( current_user_can( 'manage_options' ) ) {
			$purge_url = add_query_arg(
				array(
					'action' => 'fqi3_attempts_purge_now',
				),
				admin_url( 'admin-post.php' )
			);
			$purge_url = wp_nonce_url( $purge_url, 'fqi3_attempts_purge_now' );
			echo '<a class="btn btn-danger btn-sm" href="' . esc_url( $purge_url ) . '"><i class="bi bi-trash"></i>'
				. esc_html__( 'Purge now', 'form-quizz-fqi3' )
				. '</a>';
		}
						echo '</div>';

			// end export url build

					echo '</div>'; // row g-3
				echo '</div>'; // bg-light
			echo '</div>'; // row mb-4

		// End filter

		// Show purge success notice
		if ( isset( $_GET['fqi3_purged'] ) && (int) $_GET['fqi3_purged'] >= 0 ) {
			$deleted_count = isset( $_GET['fqi3_purged'] ) ? (int) $_GET['fqi3_purged'] : 0;
			echo '<div class="alert alert-success mt-3" role="alert">'
				. sprintf(
					/* translators: %d is the number of attempts that were successfully purged */
					esc_html__( '%d attempts purged successfully.', 'form-quizz-fqi3' ),
					$deleted_count
				)
				. '</div>';
		}

		// Table
		echo '<div class="table-responsive">';
			echo '<table class="table table-bordered table-striped table-sm">';
				echo '<thead><tr>';
				echo '<th>' . esc_html__( 'ID', 'form-quizz-fqi3' ) . '</th>';
				echo '<th>' . esc_html__( 'User', 'form-quizz-fqi3' ) . '</th>';
				echo '<th>' . esc_html__( 'Level', 'form-quizz-fqi3' ) . '</th>';
				echo '<th>' . esc_html__( 'Score', 'form-quizz-fqi3' ) . '</th>';
				echo '<th>' . esc_html__( 'Started', 'form-quizz-fqi3' ) . '</th>';
				echo '<th>' . esc_html__( 'Completed', 'form-quizz-fqi3' ) . '</th>';
				echo '</tr></thead><tbody>';

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				$user_label = $r->display_name ? $r->display_name . ' (' . $r->user_email . ')' : ( '#' . (int) $r->user_id );
				$user_link  = $r->user_id ? '<a href="' . esc_url( get_edit_user_link( $r->user_id ) ) . '">' . esc_html( $user_label ) . '</a>' : '-';
				echo '<tr>';
				echo '<td>' . (int) $r->id . '</td>';
				echo '<td>' . $user_link . '</td>';
				echo '<td>' . esc_html( $r->level ) . '</td>';
				echo '<td>' . ( isset( $r->score ) ? esc_html( $r->score ) : '-' ) . '</td>';
				echo '<td>' . esc_html( $r->started_at ) . '</td>';
				echo '<td>' . ( isset( $r->completed_at ) ? esc_html( $r->completed_at ) : '-' ) . '</td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="6">' . esc_html__( 'No attempts found.', 'form-quizz-fqi3' ) . '</td></tr>';
		}

				echo '</tbody>';
			echo '</table>';
		echo '</div>';

		// Pagination (Bootstrap 5 styled)
		if ( $total_pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => add_query_arg( array( 'paged' => '%#%' ) ),
					'format'    => '',
					'prev_text' => __( '«' ),
					'next_text' => __( '»' ),
					'total'     => $total_pages,
					'current'   => $paged,
					'type'      => 'array',
				)
			);

			if ( ! empty( $links ) ) {
				echo '<nav class="mt-3" aria-label="' . esc_attr__( 'Attempts pagination', 'form-quizz-fqi3' ) . '">';
				echo '<ul class="pagination pagination-sm justify-content-end mb-0">';
				foreach ( $links as $link_html ) {
					$original = $link_html; // Keep for checks

					// Determine state
					$is_current = ( strpos( $original, 'current' ) !== false );
					$is_dots    = ( strpos( $original, 'dots' ) !== false );

					$li_class = 'page-item';
					if ( $is_current ) {
						$li_class .= ' active'; }
					if ( $is_dots ) {
						$li_class .= ' disabled'; }

					// Normalize classes to Bootstrap page-link
					$item = preg_replace( '/\sclass="[^"]*"/', '', $original );
					if ( strpos( $item, '<a' ) !== false ) {
						$item = str_replace( '<a ', '<a class="page-link" ', $item );
					} elseif ( strpos( $item, '<span' ) !== false ) {
						$item = str_replace( '<span ', '<span class="page-link" ', $item );
					}

					// Accessibility for current page
					if ( $is_current ) {
						$item = preg_replace( '/<(a|span)\s/', '<$1 aria-current="page" ', $item, 1 );
					}

					echo '<li class="' . esc_attr( $li_class ) . '">' . $item . '</li>';
				}
				echo '</ul>';
				echo '</nav>';
			}
		}
		echo '</div>'; // wrap
	}

	/**
	 * Handle CSV export via admin-post
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have sufficient permissions to export.', 'form-quizz-fqi3' ) );
		}

		// Verify nonce
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'fqi3_attempts_export' ) ) {
			wp_die( __( 'Invalid export request (nonce).', 'form-quizz-fqi3' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fqi3_quiz_attempts';
		$users = $wpdb->users;

		// Filters
		$level   = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		$from    = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to      = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

		$wheres = array();
		$params = array();
		if ( $level !== '' ) {
			$wheres[] = 'a.level = %s';
			$params[] = $level; }
		if ( $user_id > 0 ) {
			$wheres[] = 'a.user_id = %d';
			$params[] = $user_id; }
		if ( $from !== '' ) {
			$wheres[] = 'a.started_at >= %s';
			$params[] = $from . ' 00:00:00'; }
		if ( $to !== '' ) {
			$wheres[] = 'a.started_at <= %s';
			$params[] = $to . ' 23:59:59'; }
		$where_sql = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';

		$sql_all  = "SELECT a.id, a.user_id, a.level, a.score, a.started_at, a.completed_at, u.display_name, u.user_email
                     FROM {$table} a LEFT JOIN {$users} u ON u.ID = a.user_id
                     {$where_sql}
                     ORDER BY a.started_at DESC";
		$all_rows = ! empty( $params )
			? $wpdb->get_results( $wpdb->prepare( $sql_all, $params ) )
			: $wpdb->get_results( $sql_all );

		// Stream CSV
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="attempts-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv(
			$out,
			array(
				__( 'ID', 'form-quizz-fqi3' ),
				__( 'User ID', 'form-quizz-fqi3' ),
				__( 'Display Name', 'form-quizz-fqi3' ),
				__( 'Email', 'form-quizz-fqi3' ),
				__( 'Level', 'form-quizz-fqi3' ),
				__( 'Score', 'form-quizz-fqi3' ),
				__( 'Started', 'form-quizz-fqi3' ),
				__( 'Completed', 'form-quizz-fqi3' ),
			),
			',',
			'"',
			'\\'
		);

		if ( ! empty( $all_rows ) ) {
			foreach ( $all_rows as $r ) {
				fputcsv(
					$out,
					array_map(
						'fqi3_csv_safe',
						array(
							(int) $r->id,
							(int) $r->user_id,
							isset( $r->display_name ) ? $r->display_name : '',
							isset( $r->user_email ) ? $r->user_email : '',
							isset( $r->level ) ? $r->level : '',
							isset( $r->score ) ? $r->score : '',
							isset( $r->started_at ) ? $r->started_at : '',
							isset( $r->completed_at ) ? $r->completed_at : '',
						)
					),
					',',
					'"',
					'\\'
				);
			}
		}

		fclose( $out );
		exit;
	}

	/**
	 * Handle manual purge via admin-post
	 */
	public function handle_purge_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to purge.', 'form-quizz-fqi3' ) );
		}

		// Verify nonce
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'fqi3_attempts_purge_now' ) ) {
			wp_die( __( 'Invalid purge request (nonce).', 'form-quizz-fqi3' ) );
		}

		global $wpdb;
		//$options = fqi3_get_options();
		//$days = isset($options['fqi3_attempts_retention_days']) ? absint($options['fqi3_attempts_retention_days']) : 30;
		//if ($days <= 0) { $days = 30; }

		$table = $wpdb->prefix . 'fqi3_quiz_attempts';
		// Count to be deleted
		//$to_delete = (int) $wpdb->get_var($wpdb->prepare(
		//    "SELECT COUNT(*) FROM {$table} WHERE started_at < (NOW() - INTERVAL %d DAY)",
		//    $days
		//));
		$to_delete = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// Delete in batches
		$batch = 1000;
		do {
			//$deleted = $wpdb->query(
			//    $wpdb->prepare(
			//        "DELETE FROM {$table} WHERE started_at < (NOW() - INTERVAL %d DAY) LIMIT {$batch}",
			//        $days
			//    )
			//);
			$deleted = $wpdb->query( "DELETE FROM {$table} LIMIT {$batch}" );
		} while ( $deleted === $batch );

		// Redirect back with notice
		$redirect = add_query_arg(
			array(
				'page'        => fqi3_get_admin_pages()['attempts']['slug'],
				'fqi3_purged' => $to_delete,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}
}
