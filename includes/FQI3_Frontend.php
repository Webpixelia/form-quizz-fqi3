<?php
namespace Form_Quizz_FQI3;

/**
 * FQI3 Frontend Main Class
 *
 * Manages the quiz system frontend for the i3raab Free Quiz plugin.
 * This class initializes and manages the quiz display, user attempts,
 * quiz questions and AJAX interactions for the frontend.
 *
 * @package Form Quizz FQI3
 * @since 1.0.0
 * @version 2.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FQI3_Frontend' ) ) :

	class FQI3_Frontend {
		private static $instance = null;

		/** @var FQI3_Template_Manager */
		private FQI3_Template_Manager $template_manager;

		/** @var array */
		private array $levelsQuiz = array();

		/** @var array */
		private array $options = array();

		/** @var string */
		private const COOKIE_QUIZ_COUNT = 'fqi3_quiz_count';
		private const META_DAILY_COUNT  = 'fqi3_daily_quiz_count';

		/** Option remembering that the attempts table exists (avoids a DDL query on every request). */
		private const ATTEMPTS_TABLE_OPTION  = 'fqi3_attempts_table_version';
		private const ATTEMPTS_TABLE_VERSION = '1';

		/** @var string */
		private const CACHE_GROUP = 'free_quiz';
		/** @var string */
		private const ATTEMPT_SESSION_KEY = 'fqi3_attempt_id';

		/**
		 * User meta: the attempt in progress (attempt id, level, questions served for it, timestamps).
		 * One open attempt per user. It is what the completion request is checked against.
		 */
		private const META_OPEN_ATTEMPT = 'fqi3_open_attempt';

		/** User meta: result of the last completed attempt, consumed once by `fqi3_process_quiz_hooks`. */
		private const META_LAST_RESULT = 'fqi3_last_result';

		/** An attempt that was started but never served any question is reused for this long (seconds). */
		private const OPEN_ATTEMPT_REUSE_TTL = 21600;

		public static function getInstance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Initialize the frontend functionality
		 */
		public function __construct() {
			$this->template_manager = new FQI3_Template_Manager();
			$this->options          = fqi3_get_options();

			// Initialize hooks
			$this->initHooks();
		}

		private function start_session_if_needed(): void {
			if ( session_status() === PHP_SESSION_NONE ) {
				session_start();
			}
		}

		/**
		 * Initialize WordPress hooks
		 */
		private function initHooks(): void {
			// Shortcodes @since 1.3.0
			add_shortcode( 'free_quiz_form', array( $this, 'free_quiz_form_shortcode' ) );
			add_shortcode( 'fqi3_quizz_form', array( $this, 'renderForm' ) );
			add_shortcode( 'fqi3_remaining_attempts', array( $this, 'renderRemainingAttempts' ) );

			// AJAX handlers
			add_action( 'wp_ajax_get_questions', array( $this, 'handleGetQuestions' ) );
			add_action( 'wp_ajax_nopriv_get_questions', array( $this, 'handleGetQuestions' ) );
			add_action( 'wp_ajax_increment_quiz_count', array( $this, 'handleIncrementQuizCount' ) );
			add_action( 'wp_ajax_nopriv_increment_quiz_count', array( $this, 'handleIncrementQuizCount' ) );
			add_action( 'wp_ajax_save_timer_settings', array( $this, 'handleSaveTimerSettings' ) );
			add_action( 'wp_ajax_nopriv_save_timer_settings', array( $this, 'handleSaveTimerSettings' ) );

			// Start & Finish quizz
			add_action( 'wp_ajax_fqi3_register_quiz_start', array( $this, 'fqi3_register_quiz_start' ) );
			add_action( 'wp_ajax_nopriv_fqi3_register_quiz_start', array( $this, 'fqi3_register_quiz_start' ) );
			add_action( 'wp_ajax_fqi3_register_quiz_complete', array( $this, 'fqi3_register_quiz_complete_handler' ) );
			add_action( 'wp_ajax_nopriv_fqi3_register_quiz_complete', array( $this, 'fqi3_register_quiz_complete_handler' ) );
			add_action( 'wp_ajax_fqi3_process_quiz_hooks', array( $this, 'process_quiz_hooks_handler' ) );
			add_action( 'wp_ajax_nopriv_fqi3_process_quiz_hooks', array( $this, 'process_quiz_hooks_handler' ) );

			// Other hooks
			add_action( 'wp_head', array( $this, 'outputDynamicCSS' ) );
			add_action( 'wp_loaded', array( $this, 'initializeLevels' ) );
			// Ensure attempts table exists (lightweight safety)
			add_action( 'init', array( $this, 'ensure_attempts_table' ) );
		}

		/**
		 * Render the quiz form
		 */
		public function renderForm(): string {
			\Form_Quizz_FQI3\FormQuizzFQI3::getInstance()->enqueueFrontendAssets();
			$this->start_session_if_needed();

			$data = $this->prepareQuizFormData();

			return $this->template_manager->render( 'quiz-form', $data );
		}

		/**
		 * Redirects the old [free_quiz_form] shortcode to the new [fqi3_quizz_form] shortcode.
		 *
		 * This method ensures backward compatibility by processing the old shortcode and rendering
		 * the quiz form using the same logic as the new [fqi3_quizz_form] shortcode. It acts as an alias
		 * for the new shortcode to avoid breaking sites that still use the old one.
		 *
		 * @param array $atts Shortcode attributes passed by the user (optional).
		 *
		 * @return string The rendered HTML of the quiz form.
		 * @since 3.0.0
		 */
		public function free_quiz_form_shortcode( $atts ) {
			return $this->renderForm( $atts );
		}

		/**
		 * Prepare data for quiz form template
		 */
		private function prepareQuizFormData(): array {
			$is_user_logged_in = is_user_logged_in();

			return array(
				'text_pre_form'     => wp_kses_post(
					$this->options['fqi3_text_pre_form'] ?? sprintf(
						'<h1>%s</h1>',
						__( 'Default Title', 'form-quizz-fqi3' )
					)
				),
				'selected_page_url' => $this->getSalesPageUrl(),
				'is_user_logged_in' => $is_user_logged_in,
				'current_user'      => $is_user_logged_in ? wp_get_current_user() : null,
				'title_form'        => $is_user_logged_in
					? __( 'Choose your level:', 'form-quizz-fqi3' )
					: __( 'As no logged user, you are only acces to:', 'form-quizz-fqi3' ),
				'levels'            => $this->levelsQuiz,
				'timer_settings'    => $this->getTimerSettings(),
				'sharing_enabled'   => empty( $this->options['fqi3_disable_sharing'] ),
				'translations'      => $this->getTranslations(),
			);
		}

		/**
		 * Render remaining attempts
		 *
		 * @return string The rendered HTML of the remaining attempts template.
		*/
		public function renderRemainingAttempts(): string {
			\Form_Quizz_FQI3\FormQuizzFQI3::getInstance()->enqueueFrontendAssets();
			$data = $this->prepareAttemptsData();

			return $this->template_manager->render( 'remaining-attempts', $data );
		}

		/**
		 * Prepare data for attempts template
		 *
		 * @return array The data for the attempts template.
		*/
		private function prepareAttemptsData(): array {
			$user_id         = get_current_user_id();
			$is_premium_user = fqi3_userHasAnyRole( $user_id, fqi3_getUserPremiumRoles() );

			return array(
				'infos_attempts'  => $this->getAttemptsInfo(),
				'is_premium_user' => $is_premium_user,
				'sales_page_url'  => $this->getSalesPageUrl(),
			);
		}

		/**
		 * Get attempts data
		*/
		public function getAttemptsData(): array {
			return $this->prepareAttemptsData();
		}

		/**
		 * Get timer settings
		*/
		private function getTimerSettings(): array {
			return array(
				'enabled'  => isset( $_SESSION['fqi3_enable_timer'] )
					&& $_SESSION['fqi3_enable_timer'] === 'yes',
				'duration' => isset( $_SESSION['fqi3_timer_duration'] )
					? intval( $_SESSION['fqi3_timer_duration'] )
					: 5,
			);
		}

		/**
		 * Get sales page URL
		*/
		private function getSalesPageUrl(): string {
			$page_id = $this->options['fqi3_sales_page'] ?? '';
			return $page_id ? get_permalink( $page_id ) : '#';
		}

		/**
		 * Get translations for templates
		*/
		public function getTranslations(): array {
			$num_questions = $this->options['fqi3_number_questions_per_quiz'] ?? 10;
			return array(
				'start'           => __( 'Start', 'form-quizz-fqi3' ),
				// translators: %s is the name or username of the user.
				'welcome'         => __( 'Welcome, %s. Happy to see you again for some fun learning!', 'form-quizz-fqi3' ),
				'loading'         => __( 'Loading...', 'form-quizz-fqi3' ),
				'next'            => __( 'Next', 'form-quizz-fqi3' ),
				'results'         => __( 'Results', 'form-quizz-fqi3' ),
				'restart_quiz'    => __( 'Restart the quizz', 'form-quizz-fqi3' ),
				'sign_up'         => __( 'Sign Up to Access More Levels', 'form-quizz-fqi3' ),
				'go_back'         => __( 'Go back to the start', 'form-quizz-fqi3' ),
				'share_results'   => __( 'Share your results:', 'form-quizz-fqi3' ),
				'share_facebook'  => __( 'Share on Facebook', 'form-quizz-fqi3' ),
				'share_x'         => __( 'Share on X', 'form-quizz-fqi3' ),
				'share_linkedin'  => __( 'Share on LinkedIn', 'form-quizz-fqi3' ),
				'upgrade_message' => __( 'Upgrade to our premium version to get unlimited access!', 'form-quizz-fqi3' ),
				'view_details'    => __( 'View Details and register', 'form-quizz-fqi3' ),
				'timer_label'     => sprintf(
					/* translators: %d is the number of questions in the quiz. */
					__( 'Do you want to activate a timer to complete the %d questions?', 'form-quizz-fqi3' ),
					intval( $num_questions )
				),
				'timer_duration'  => __( 'How many minutes?', 'form-quizz-fqi3' ),
			);
		}

		/**
		 * Handle AJAX request for getting questions based on the user's level.
		 *
		 * This method checks if the user is logged in and if they have reached their daily quiz limit.
		 * If the user is allowed to take a quiz, it retrieves questions from the database,
		 * caches the results, and returns them as JSON. If no questions are found, it returns an error message.
		 *
		 * @return void
		*/
		public function handleGetQuestions(): void {
			if ( $this->shouldBlockQuizAccess() ) {
				wp_send_json_error(
					array(
						'message' => __( 'You have reached your quiz limit for today.', 'form-quizz-fqi3' ),
					)
				);
			}

			$level = $this->sanitizeLevel( $_GET['niveau'] ?? 'free' );

			// The level picker only offers visitors the levels flagged "free": enforce it here too,
			// otherwise changing the `niveau` parameter exposes the levels reserved to members.
			if ( ! $this->canAccessLevel( $level ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Please log in to access this level.', 'form-quizz-fqi3' ),
					)
				);
			}

			$this->start_session_if_needed();
			// Logged-in users get a server-side attempt (the one opened by "quiz start", or a new one).
			$user_id    = get_current_user_id();
			$attempt_id = 0;
			if ( $user_id > 0 ) {
				$attempt_id = $this->acquireAttempt( $user_id, $level )['attempt_id'];
			}
			$questions = $this->getQuestions( $level );

			if ( empty( $questions ) ) {
				wp_send_json(
					array(
						'error' => sprintf(
						// translators: %s represents the level (e.g., 'beginner', 'intermediate', 'advanced') where no questions are found.
							__( 'No questions found in %s level.', 'form-quizz-fqi3' ),
							'<span id="levelChoose"></span>'
						),
					)
				);
			}

			// Bind the served questions to the attempt: the completion request is validated against them.
			$question_ids = array_map( 'intval', wp_list_pluck( $questions, 'id' ) );
			$this->rememberServedQuestions( $attempt_id, $user_id, $level, $question_ids );

			// Notify listeners that the final question set has been fixed for this request
			// Allows addons (e.g., Question Bank Pro) to persist a snapshot or log
			do_action(
				'fqi3_quiz_questions_drawn',
				get_current_user_id() ?: 0,
				$level,
				array_map( 'intval', wp_list_pluck( $questions, 'id' ) ),
				array(
					'source'     => 'core',
					'attempt_id' => $attempt_id,
				)
			);

			$this->incrementQuizCount();

			/**
			 * Filters the questions right before they are sent to the browser.
			 *
			 * Runs after the questions cache, so a filter can localize the content without polluting the cache.
			 * Callbacks must not change the question ids, their order, the number of options or the `answer` index:
			 * scoring is done server-side from the ids and the option indexes.
			 *
			 * @since 3.6.0
			 *
			 * @param array  $questions Question rows (id, niveau, q, q2, options, answer).
			 * @param string $level     Quiz level.
			 * @param array  $context   Request context (source, attempt_id).
			 */
			$questions = apply_filters(
				'fqi3_quiz_questions_response',
				$questions,
				$level,
				array(
					'source'     => 'core',
					'attempt_id' => $attempt_id,
				)
			);
			wp_send_json( is_array( $questions ) ? array_values( $questions ) : array() );
		}

		/**
		 * Whether the current visitor may request the questions of a level.
		 *
		 * Visitors who are not logged in can only access levels flagged as free. Logged-in users
		 * are not restricted here (the daily limit is enforced separately). A level that does not
		 * exist is let through so that the regular "no questions found" response is returned.
		 *
		 * @param string $level Level slug.
		 * @return bool
		 * @since 3.4.0
		 */
		private function canAccessLevel( string $level ): bool {
			if ( is_user_logged_in() ) {
				return true;
			}

			$levels = fqi3_get_free_quiz_levels();
			if ( ! isset( $levels[ $level ] ) ) {
				return true;
			}

			return ! empty( $levels[ $level ]['free'] );
		}

		/**
		 * Get questions for a specific level
		 *
		 * @param string $level The level for which to retrieve questions
		 * @return array The questions for the specified level
		*/
		private function getQuestions( string $level ): array {
			// Number of questions comes from the settings (it was hard-coded to 10 before).
			$limit = max( 1, min( 100, (int) ( $this->options['fqi3_number_questions_per_quiz'] ?? 10 ) ) );

			// The draw can be user-specific (see the `fqi3_quiz_questions_draw` filter), so the cache
			// key includes the user: otherwise a persistent object cache would hand one user's set to another.
			$cacheKey  = sprintf( 'questions_%s_u%d_n%d', $level, get_current_user_id(), $limit );
			$questions = wp_cache_get( $cacheKey, self::CACHE_GROUP );

			if ( false === $questions ) {
				// Allow addons to provide a deterministic question set for this level
				$user_id = get_current_user_id() ?: 0;
				$context = array(
					'source' => 'core',
					'level'  => $level,
				);
				$draw    = apply_filters(
					'fqi3_quiz_questions_draw',
					array(
						'question_ids' => array(),
						'meta'         => array(),
					),
					$level,
					$user_id,
					$context
				);

				global $wpdb;
				if ( is_array( $draw ) && ! empty( $draw['question_ids'] ) && is_array( $draw['question_ids'] ) ) {
					// Sanitize and ensure unique IDs
					$ids = array_values( array_unique( array_map( 'intval', $draw['question_ids'] ) ) );
					$ids = array_filter( $ids, static fn( $v ) => $v > 0 );

					if ( ! empty( $ids ) ) {
						// Fetch all requested questions (preserve order in PHP)
						$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
						$sql          = "SELECT * FROM {$wpdb->prefix}" . FQI3_TABLE_QUIZZES . " WHERE id IN ($placeholders)";
						$prepared     = $wpdb->prepare( $sql, $ids );
						$questions    = $wpdb->get_results( $prepared, ARRAY_A );

						// Preserve the order provided by addon
						$orderMap = array_flip( $ids );
						usort(
							$questions,
							static function ( $a, $b ) use ( $orderMap ) {
								$ia = $orderMap[ (int) ( $a['id'] ?? 0 ) ] ?? PHP_INT_MAX;
								$ib = $orderMap[ (int) ( $b['id'] ?? 0 ) ] ?? PHP_INT_MAX;
								return $ia <=> $ib;
							}
						);
					} else {
						$questions = array();
					}
				} else {
					// Fallback to core random selection by level
					$questions = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}" . FQI3_TABLE_QUIZZES . ' WHERE niveau = %s ORDER BY RAND() LIMIT %d',
							$level,
							$limit
						),
						ARRAY_A
					);
				}

				if ( $questions ) {
					array_walk(
						$questions,
						function ( &$question ) {
							$question['options'] = json_decode( $question['options'], true );
						}
					);

					wp_cache_set( $cacheKey, $questions, self::CACHE_GROUP, 300 );
				}
			}

			return $questions ?: array();
		}

		/**
		 * Handle cookie operations
		 *
		 * @param string $action The action to perform ('get' or 'set')
		 * @param string|null $value The value to set (for 'set' action)
		 * @return int|null The value of the cookie (for 'get' action)
		*/
		private function handleCookie( string $action, ?string $value = null ): ?int {
			switch ( $action ) {
				case 'get':
					return isset( $_COOKIE[ self::COOKIE_QUIZ_COUNT ] )
					? (int) $_COOKIE[ self::COOKIE_QUIZ_COUNT ]
					: 0;

				case 'set':
					$expire = strtotime( 'tomorrow' );
					setcookie(
						self::COOKIE_QUIZ_COUNT,
						$value,
						$expire,
						COOKIEPATH,
						COOKIE_DOMAIN,
						is_ssl(),
						true
					);
					$_COOKIE[ self::COOKIE_QUIZ_COUNT ] = $value;
					return null;

				case 'reset':
					return $this->handleCookie( 'set', '0' );

				default:
					return null;
			}
		}

		/**
		 * Check if quiz access should be blocked
		*/
		private function shouldBlockQuizAccess(): bool {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			$user = wp_get_current_user();
			if ( ! in_array( 'subscriber', $user->roles ) ) {
				return false;
			}

			return ! $this->canTakeQuiz();
		}

		/**
		 * Check if user can take another quiz based on the daily limit.
		 *
		 * This method retrieves the current quiz count and compares it to a configurable daily limit stored in the database.
		 * The daily limit is obtained from the 'free_trials_per_day' option, with a default value of 3 if not set.
		 * It returns true if the quiz count is below the daily limit, otherwise false.
		 *
		 * @return bool True if the user can take another quiz, otherwise false.
		 * @since 1.2.0 Added configurable daily limit option.
		 * @since 1.1.0 Initial release.
		*/
		public function canTakeQuiz(): bool {
			$dailyLimit = (int) ( $this->options['fqi3_free_trials_per_day'] ?? 3 );

			return $this->getDailyQuizCount() < $dailyLimit;
		}

		/**
		 * Get attempts information
		 *
		 * This method calculates the remaining quiz attempts by subtracting
		 * the number of attempts made (stored in a cookie) from the total number
		 * of free trials allowed per day (retrieved from plugin options).
		 *
		 * @return int The number of remaining attempts. Returns 0 if there are no remaining attempts.
		 *
		 * @since 1.3.0
		*/
		public function getAttemptsInfo(): array {
			$totalAttempts     = (int) ( $this->options['fqi3_free_trials_per_day'] ?? 0 );
			$attemptsMade      = $this->getDailyQuizCount();
			$remainingAttempts = max( 0, $totalAttempts - $attemptsMade );

			return array(
				'total_attempts'     => $totalAttempts,
				'attempts_made'      => $attemptsMade,
				'remaining_attempts' => $remainingAttempts,
			);
		}

		/**
		 * Output dynamic CSS
		*/
		public function outputDynamicCSS(): void {
			$cssVars = array(
				'color-text-pre-form'     => $this->options['fqi3_color_text_pre_form'] ?? '',
				'color-text-top-question' => $this->options['fqi3_color_text_top_question'] ?? '',
				'color-bg-top-question'   => $this->options['fqi3_color_bg_top_question'] ?? '',
				'color-text-btn'          => $this->options['fqi3_color_text_btn'] ?? '',
				'color-bg-btn'            => $this->options['fqi3_color_bg_btn'] ?? '',
			);

			$css = ':root {' . PHP_EOL;
			foreach ( $cssVars as $key => $value ) {
				$css .= sprintf( '    --%s: %s;%s', $key, esc_attr( $value ), PHP_EOL );
			}
			$css .= '}';

			printf( '<style>%s</style>', $css );
		}

		/**
		 * Handle timer settings
		 *
		 * This method is triggered via AJAX and handles saving the timer settings (whether the timer is enabled and its duration)
		 * into the user's session. If the timer is enabled, it stores the 'enable_timer' status and the 'timer_duration' (default 5 minutes).
		 * If the timer is disabled, it clears the session values for the timer.
		 *
		 * It also validates the AJAX request by checking the session and the nonce for security.
		 *
		 * @since 1.2.0
		*/
		public function handleSaveTimerSettings(): void {
			$this->start_session_if_needed();

			check_ajax_referer( 'fqi3_session_nonce', 'nonce' );

			$enableTimer                   = $_POST['enable_timer'] ?? 'no';
			$_SESSION['fqi3_enable_timer'] = $enableTimer;

			if ( $enableTimer === 'yes' ) {
				$_SESSION['fqi3_timer_duration'] = (int) ( $_POST['timer_duration'] ?? 5 );
			} else {
				unset( $_SESSION['fqi3_timer_duration'] );
			}

			wp_send_json_success();
		}

		/**
		 * Register quiz start
		 *
		 * This method is triggered via AJAX and opens (or reuses) the server-side attempt of the current user.
		 * Only logged-in users, subject to the daily limit, can start a quiz on a known level.
		 *
		 * `fqi3_quiz_started` and `fqi3_quiz_attempt_started` fire once per attempt: a repeated request
		 * (double click, retry) reuses the attempt that has not served any question yet and fires nothing.
		 *
		 * @since 3.0.0
		 * @since 3.5.0 Validates the level, applies the daily limit and reuses the open attempt.
		*/
		public function fqi3_register_quiz_start(): void {
			check_ajax_referer( 'fqi3_session_nonce', 'nonce' );

			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => 'Utilisateur non connecté.' ) );
				return;
			}

			$user_id = get_current_user_id();

			if ( ! $user_id || ! is_numeric( $user_id ) ) {
				wp_send_json_error( array( 'message' => 'ID utilisateur invalide.' ) );
				return;
			}

			$quiz_level = sanitize_text_field( wp_unslash( $_POST['quiz_level'] ?? '' ) );

			if ( empty( $quiz_level ) ) {
				wp_send_json_error( array( 'message' => 'Niveau du quiz manquant.' ) );
				return;
			}

			if ( ! $this->isKnownLevel( $quiz_level ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid quiz level.', 'form-quizz-fqi3' ) ) );
				return;
			}

			if ( $this->shouldBlockQuizAccess() ) {
				wp_send_json_error( array( 'message' => __( 'You have reached your quiz limit for today.', 'form-quizz-fqi3' ) ) );
				return;
			}

			$this->start_session_if_needed();
			$attempt = $this->acquireAttempt( (int) $user_id, $quiz_level );

			if ( $attempt['is_new'] ) {
				// Back-compat action (the attempt id is an extra argument: callbacks declared with 2 are unaffected).
				do_action( 'fqi3_quiz_started', $user_id, $quiz_level, $attempt['attempt_id'] );
				// Detailed action with attempt id
				do_action( 'fqi3_quiz_attempt_started', $attempt['attempt_id'], (int) $user_id, $quiz_level );
			}

			wp_send_json_success(
				array(
					'message'    => 'Quiz démarré avec succès.',
					'attempt_id' => $attempt['attempt_id'],
				)
			);
		}

		/**
		 * Register quiz completion
		 *
		 * This method is triggered via AJAX and closes the attempt in progress of the current user.
		 *
		 * The request is only accepted when the questions of the attempt were served by the server, on the same
		 * level, at least `fqi3_min_seconds_per_question` seconds per question ago, and when the attempt is not
		 * already closed. The score is computed here from the submitted answers (`answers`, a JSON object
		 * {question id: chosen option index}); the `quiz_score` sent by the browser is ignored.
		 *
		 * `fqi3_quiz_completed` and `fqi3_quiz_attempt_completed` fire exactly once per attempt.
		 *
		 * @since 3.0.0
		 * @since 3.5.0 Validated against the served questions; server-side score; hooks fire once.
		*/
		public function fqi3_register_quiz_complete_handler(): void {
			check_ajax_referer( 'fqi3_session_nonce', 'nonce' );

			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => 'Utilisateur non connecté.' ) );
				return;
			}

			$user_id = get_current_user_id();

			if ( ! $user_id || ! is_numeric( $user_id ) ) {
				wp_send_json_error( array( 'message' => 'ID utilisateur invalide.' ) );
				return;
			}

			$quiz_level = sanitize_text_field( wp_unslash( $_POST['quiz_level'] ?? '' ) );

			if ( empty( $quiz_level ) ) {
				wp_send_json_error( array( 'message' => 'Niveau du quiz manquant.' ) );
				return;
			}

			if ( ! $this->isKnownLevel( $quiz_level ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid quiz level.', 'form-quizz-fqi3' ) ) );
				return;
			}

			// The attempt must exist and its questions must have been served by this server.
			$open       = $this->getOpenAttempt( (int) $user_id );
			$attempt_id = (int) ( $open['attempt_id'] ?? 0 );
			$served_ids = array_values( array_filter( array_map( 'intval', (array) ( $open['question_ids'] ?? array() ) ) ) );

			if ( $attempt_id <= 0 || empty( $served_ids ) ) {
				wp_send_json_error( array( 'message' => __( 'No quiz in progress.', 'form-quizz-fqi3' ) ) );
				return;
			}

			if ( ( $open['level'] ?? '' ) !== $quiz_level ) {
				wp_send_json_error( array( 'message' => __( 'Invalid quiz level.', 'form-quizz-fqi3' ) ) );
				return;
			}

			if ( time() - (int) ( $open['served_at'] ?? 0 ) < $this->minimumQuizSeconds( $quiz_level, count( $served_ids ) ) ) {
				wp_send_json_error( array( 'message' => __( 'The quiz was completed too quickly.', 'form-quizz-fqi3' ) ) );
				return;
			}

			// The score is recomputed from the answers: what the browser claims is not used.
			$answers = $this->parseSubmittedAnswers( wp_unslash( $_POST['answers'] ?? '' ), $served_ids );
			$result  = null === $answers ? null : $this->computeServerResult( $served_ids, $answers );

			if ( null === $result ) {
				wp_send_json_error( array( 'message' => __( 'The quiz answers could not be verified.', 'form-quizz-fqi3' ) ) );
				return;
			}

			$score = $result['score'];

			// Atomic gate: only one request closes the attempt, a replay or a parallel request gets false.
			if ( ! $this->completeAttempt( $attempt_id, (int) $user_id, $score ) ) {
				$this->forgetOpenAttempt( (int) $user_id );
				wp_send_json_error( array( 'message' => __( 'This quiz is already completed.', 'form-quizz-fqi3' ) ) );
				return;
			}

			$this->forgetOpenAttempt( (int) $user_id );
			update_user_meta(
				(int) $user_id,
				self::META_LAST_RESULT,
				array(
					'attempt_id' => $attempt_id,
					'level'      => $quiz_level,
					'score'      => $score,
					'at'         => time(),
				)
			);

			// Statistics and badges are fed from the verified result, never from values sent by the browser.
			$this->recordVerifiedStatistics( (int) $user_id, $quiz_level, $result['correct'], $result['total'] );

			// Back-compat action (the attempt id is an extra argument: callbacks declared with 2 are unaffected).
			do_action( 'fqi3_quiz_completed', $user_id, $quiz_level, $attempt_id );
			// Detailed action
			do_action( 'fqi3_quiz_attempt_completed', $attempt_id, (int) $user_id, $quiz_level, $score );

			wp_send_json_success(
				array(
					'message'    => 'Quiz terminé avec succès.',
					'level'      => $quiz_level,
					'score'      => $score,
					'attempt_id' => $attempt_id,
				)
			);
		}

		/**
		 * Process quiz hooks
		 *
		 * This method is triggered via AJAX and fires the `fqi3_before_results_quiz_form` action.
		 *
		 * For a logged-in user the level and the score are the ones recorded by the server when the attempt was
		 * completed (the browser values are ignored) and the result can be consumed only once; `$verified` is true.
		 * A visitor has no server-side attempt: the values come from the browser, are NOT trustworthy and
		 * `$verified` is false. Never grant points, badges or data on an unverified call.
		 *
		 * @since 3.0.0
		 * @since 3.5.0 Verified result for logged-in users, `$verified` argument.
		*/
		public function process_quiz_hooks_handler(): void {
			check_ajax_referer( 'fqi3_session_nonce', 'nonce' );

			$user_id = get_current_user_id();

			if ( $user_id > 0 ) {
				$result = get_user_meta( $user_id, self::META_LAST_RESULT, true );
				if ( ! is_array( $result ) || empty( $result['attempt_id'] ) ) {
					wp_send_json_error( array( 'message' => __( 'No completed quiz to process.', 'form-quizz-fqi3' ) ) );
					return;
				}
				delete_user_meta( $user_id, self::META_LAST_RESULT );

				$quiz_level = (string) ( $result['level'] ?? '' );
				$quiz_score = (float) ( $result['score'] ?? 0 );
				$verified   = true;
			} else {
				$quiz_level = sanitize_text_field( wp_unslash( $_POST['quiz_level'] ?? '' ) );

				if ( ! $this->isKnownLevel( $quiz_level ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid quiz level.', 'form-quizz-fqi3' ) ) );
					return;
				}

				$quiz_score = $this->sanitizeQuizScore( $_POST['quiz_score'] ?? 0 );
				$verified   = false;
			}

			do_action( 'fqi3_before_results_quiz_form', $user_id, $quiz_level, $quiz_score, $verified );

			wp_send_json_success(
				array(
					'message' => 'Hooks processed successfully',
				)
			);
		}

		/**
		 * Initialize quiz levels
		 *
		 * @since 1.2.2
		*/
		public function initializeLevels(): void {
			$this->levelsQuiz = fqi3_get_free_quiz_levels();
		}

		/**
		 * Whether the level exists in the configured quiz levels.
		 *
		 * @since 3.4.0
		 */
		private function isKnownLevel( string $level ): bool {
			return $level !== '' && isset( fqi3_get_free_quiz_levels()[ $level ] );
		}

		/**
		 * Quiz scores are computed in the browser: only accept a percentage between 0 and 100.
		 *
		 * @param mixed $raw Raw POST value.
		 * @since 3.4.0
		 */
		private function sanitizeQuizScore( $raw ): float {
			$score = is_numeric( $raw ) ? (float) $raw : 0.0;

			return max( 0.0, min( 100.0, round( $score, 2 ) ) );
		}

		/**
		 * Sanitize level input
		*/
		private function sanitizeLevel( string $level ): string {
			return sanitize_text_field( $level );
		}

		/**
		 * Increment quiz count stored in the cookie.
		*/
		private function incrementQuizCount(): void {
			if ( is_user_logged_in() ) {
				// Logged-in users: counted server-side, a cookie can be deleted or forged.
				update_user_meta(
					get_current_user_id(),
					self::META_DAILY_COUNT,
					array(
						'date'  => current_time( 'Y-m-d' ),
						'count' => $this->getDailyQuizCount() + 1,
					)
				);
				return;
			}

			$count = $this->handleCookie( 'get' );
			$this->handleCookie( 'set', (string) ( $count + 1 ) );
		}

		/**
		 * Number of quizzes started today (site timezone).
		 *
		 * Logged-in users: stored in user meta, reset when the date changes.
		 * Visitors: cookie (informational only, the daily limit applies to logged-in subscribers).
		 *
		 * @return int
		 * @since 3.4.0
		 */
		private function getDailyQuizCount(): int {
			if ( ! is_user_logged_in() ) {
				return (int) $this->handleCookie( 'get' );
			}

			$data = get_user_meta( get_current_user_id(), self::META_DAILY_COUNT, true );
			if ( is_array( $data ) && ( $data['date'] ?? '' ) === current_time( 'Y-m-d' ) ) {
				return max( 0, (int) ( $data['count'] ?? 0 ) );
			}

			return 0;
		}

		/**
		 * Ensure attempts table exists
		 */
		public function ensure_attempts_table(): void {
			// Autoloaded option: no database query once the table has been created.
			if ( get_option( self::ATTEMPTS_TABLE_OPTION ) === self::ATTEMPTS_TABLE_VERSION ) {
				return;
			}

			global $wpdb;
			$table           = $wpdb->prefix . 'fqi3_quiz_attempts';
			$charset_collate = $wpdb->get_charset_collate();
			$sql             = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `level` VARCHAR(50) NOT NULL,
            `score` FLOAT NULL,
            `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `completed_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id_idx` (`user_id`),
            KEY `level_idx` (`level`),
            KEY `started_at_idx` (`started_at`)
        ) $charset_collate;";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $sql );

			update_option( self::ATTEMPTS_TABLE_OPTION, self::ATTEMPTS_TABLE_VERSION );
		}

		/**
		 * Create an attempt and store id in session
		 */
		private function createAttempt( int $user_id, string $level ): int {
			global $wpdb;
			$this->ensure_attempts_table();
			$table  = $wpdb->prefix . 'fqi3_quiz_attempts';
			$insert = static function () use ( $wpdb, $table, $user_id, $level ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $wpdb->insert(
					$table,
					array(
						'user_id'    => $user_id,
						'level'      => sanitize_text_field( $level ),
						'started_at' => current_time( 'mysql', 1 ),
					),
					array(
						'%d',
						'%s',
						'%s',
					)
				);
			};
			$ins    = $insert();
			if ( ! $ins ) {
				// The table may have been dropped while the "already created" flag remains: recreate it once and retry.
				delete_option( self::ATTEMPTS_TABLE_OPTION );
				$this->ensure_attempts_table();
				$ins = $insert();
			}
			if ( $ins ) {
				$attempt_id                            = (int) $wpdb->insert_id;
				$_SESSION[ self::ATTEMPT_SESSION_KEY ] = $attempt_id;
				return $attempt_id;
			}
			return 0;
		}

		/**
		 * The attempt in progress of a user (empty array when there is none).
		 *
		 * @return array{attempt_id?:int,level?:string,question_ids?:int[],started_at?:int,served_at?:int}
		 * @since 3.5.0
		 */
		private function getOpenAttempt( int $user_id ): array {
			if ( $user_id <= 0 ) {
				return array();
			}

			$data = get_user_meta( $user_id, self::META_OPEN_ATTEMPT, true );

			return is_array( $data ) && ! empty( $data['attempt_id'] ) ? $data : array();
		}

		/**
		 * Forget the attempt in progress of a user (server state and session).
		 *
		 * @since 3.5.0
		 */
		private function forgetOpenAttempt( int $user_id ): void {
			delete_user_meta( $user_id, self::META_OPEN_ATTEMPT );
			unset( $_SESSION[ self::ATTEMPT_SESSION_KEY ] );
		}

		/**
		 * Return the attempt a user is about to play: the recent one that has not served any question yet
		 * (its level is aligned on the requested one), otherwise a new attempt.
		 *
		 * @return array{attempt_id:int,is_new:bool} `is_new` is true only when a row has just been created.
		 * @since 3.5.0
		 */
		private function acquireAttempt( int $user_id, string $level ): array {
			$open = $this->getOpenAttempt( $user_id );

			if (
				! empty( $open )
				&& empty( $open['question_ids'] )
				&& ( time() - (int) ( $open['started_at'] ?? 0 ) ) < self::OPEN_ATTEMPT_REUSE_TTL
			) {
				$attempt_id = (int) $open['attempt_id'];

				if ( ( $open['level'] ?? '' ) !== $level ) {
					if ( ! $this->updateAttemptLevel( $attempt_id, $user_id, $level ) ) {
						// The row is gone or closed: do not keep a ghost attempt.
						$this->forgetOpenAttempt( $user_id );
						return $this->acquireAttempt( $user_id, $level );
					}
					$open['level'] = $level;
					update_user_meta( $user_id, self::META_OPEN_ATTEMPT, $open );
				}

				$_SESSION[ self::ATTEMPT_SESSION_KEY ] = $attempt_id;

				return array(
					'attempt_id' => $attempt_id,
					'is_new'     => false,
				);
			}

			$attempt_id = $this->createAttempt( $user_id, $level );
			if ( $attempt_id > 0 ) {
				update_user_meta(
					$user_id,
					self::META_OPEN_ATTEMPT,
					array(
						'attempt_id'   => $attempt_id,
						'level'        => $level,
						'question_ids' => array(),
						'started_at'   => time(),
						'served_at'    => 0,
					)
				);
			}

			return array(
				'attempt_id' => $attempt_id,
				'is_new'     => $attempt_id > 0,
			);
		}

		/**
		 * Record the questions served for an attempt. The completion request is validated against them.
		 *
		 * @param int[] $question_ids Ids of the questions sent to the browser.
		 * @since 3.5.0
		 */
		private function rememberServedQuestions( int $attempt_id, int $user_id, string $level, array $question_ids ): void {
			if ( $attempt_id <= 0 || $user_id <= 0 ) {
				return;
			}

			$open = $this->getOpenAttempt( $user_id );
			if ( (int) ( $open['attempt_id'] ?? 0 ) !== $attempt_id ) {
				return;
			}

			$open['level']        = $level;
			$open['question_ids'] = array_values( array_unique( array_filter( array_map( 'intval', $question_ids ) ) ) );
			$open['served_at']    = time();
			update_user_meta( $user_id, self::META_OPEN_ATTEMPT, $open );
		}

		/**
		 * Minimum time between the questions being served and the completion request.
		 *
		 * @param string $level      Level slug.
		 * @param int    $nb_questions Number of questions served.
		 * @since 3.5.0
		 */
		private function minimumQuizSeconds( string $level, int $nb_questions ): int {
			/**
			 * Filters the minimum number of seconds per question before a quiz can be completed
			 * (a speed bump against scripted completions). Return 0 to disable the check.
			 *
			 * @param int    $seconds Seconds per question (default 1).
			 * @param string $level   Level slug.
			 */
			$per_question = (int) apply_filters( 'fqi3_min_seconds_per_question', 1, $level );

			return max( 0, $per_question ) * max( 0, $nb_questions );
		}

		/**
		 * Normalise the answers sent by the browser: {question id: chosen option index}, restricted to the served questions.
		 *
		 * @param mixed $raw        JSON string (or already decoded array).
		 * @param int[] $served_ids Ids of the questions served for the attempt.
		 * @return array<int,int>|null Null when the payload is missing or not a JSON object.
		 * @since 3.5.0
		 */
		private function parseSubmittedAnswers( $raw, array $served_ids ): ?array {
			$decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			if ( ! is_array( $decoded ) ) {
				return null;
			}

			$allowed = array_flip( $served_ids );
			$answers = array();
			foreach ( $decoded as $question_id => $choice ) {
				$question_id = (int) $question_id;
				if ( isset( $allowed[ $question_id ] ) && is_numeric( $choice ) ) {
					$answers[ $question_id ] = (int) $choice;
				}
			}

			return $answers;
		}

		/**
		 * Verified result of an attempt, from the stored correct answers of the served questions.
		 * Questions deleted since they were served no longer count. Null if no served question can be found.
		 *
		 * @param int[]          $served_ids Ids of the questions served for the attempt.
		 * @param array<int,int> $answers    Chosen option index by question id.
		 * @return array{score:float,correct:int,total:int}|null Score is 0-100 with 2 decimals.
		 * @since 3.5.0
		 */
		private function computeServerResult( array $served_ids, array $answers ): ?array {
			global $wpdb;

			if ( empty( $served_ids ) ) {
				return null;
			}

			$placeholders = implode( ',', array_fill( 0, count( $served_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, answer FROM {$wpdb->prefix}" . FQI3_TABLE_QUIZZES . " WHERE id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$served_ids
				),
				ARRAY_A
			);

			if ( empty( $rows ) || ! is_array( $rows ) ) {
				return null;
			}

			$correct = 0;
			foreach ( $rows as $row ) {
				$id = (int) ( $row['id'] ?? 0 );
				if ( isset( $answers[ $id ] ) && $answers[ $id ] === (int) ( $row['answer'] ?? -1 ) ) {
					++$correct;
				}
			}

			return array(
				'score'   => $this->sanitizeQuizScore( ( $correct / count( $rows ) ) * 100 ),
				'correct' => $correct,
				'total'   => count( $rows ),
			);
		}

		/**
		 * The statistics manager (a seam for the unit tests).
		 *
		 * @since 3.5.0
		 */
		protected function statisticsService(): ?object {
			return class_exists( __NAMESPACE__ . '\FQI3_Statistics' ) ? FQI3_Statistics::getInstance() : null;
		}

		/**
		 * Record the verified result in the user statistics and award the badges.
		 *
		 * Same conditions as the former browser-driven calls: statistics not disabled and a premium user.
		 * A failure here never prevents the quiz from being completed.
		 *
		 * @since 3.5.0
		 */
		private function recordVerifiedStatistics( int $user_id, string $level, int $correct, int $total ): void {
			if ( ! empty( $this->options['fqi3_disable_statistics'] ) ) {
				return;
			}

			if ( ! fqi3_userHasAnyRole( $user_id, fqi3_getUserPremiumRoles() ) ) {
				return;
			}

			try {
				$statistics = $this->statisticsService();
				if ( $statistics && $statistics->save_user_performance( $user_id, $level, $correct, $total ) ) {
					$statistics->get_awards_instance()->award_badges_based_on_criteria( $user_id, $level );
				}
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[FQI3] Could not record the quiz statistics: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}

		/**
		 * Align the level of an attempt that has not served any question yet.
		 * Returns false when no open attempt of that user was changed (missing, closed or already on that level).
		 *
		 * @since 3.5.0
		 */
		private function updateAttemptLevel( int $attempt_id, int $user_id, string $level ): bool {
			global $wpdb;
			$table = $wpdb->prefix . 'fqi3_quiz_attempts';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE `$table` SET level = %s WHERE id = %d AND user_id = %d AND completed_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$level,
					$attempt_id,
					$user_id
				)
			);

			return 1 === (int) $updated;
		}

		/**
		 * Close an attempt of a user. Returns true only if THIS call closed it: false when the attempt does not
		 * exist, belongs to someone else or is already closed (single conditional UPDATE, safe against replays
		 * and parallel requests).
		 *
		 * @since 3.5.0 Returns a bool and only closes an open attempt of the given user.
		 */
		private function completeAttempt( int $attempt_id, int $user_id, float $score ): bool {
			if ( $attempt_id <= 0 || $user_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fqi3_quiz_attempts';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$closed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE `$table` SET score = %f, completed_at = %s WHERE id = %d AND user_id = %d AND completed_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$score,
					current_time( 'mysql', 1 ),
					$attempt_id,
					$user_id
				)
			);

			return 1 === (int) $closed;
		}

		/**
		 * Handle increment quiz count AJAX request
		 */
		public function handleIncrementQuizCount(): void {
			check_ajax_referer( 'fqi3_cookies_nonce', 'nonce' );

			// Logged-in users are already counted server-side when the questions are served
			// (handleGetQuestions). Counting again here would consume two trials per quiz.
			if ( ! is_user_logged_in() ) {
				$this->incrementQuizCount();
			}
			wp_send_json_success();
		}

		/**
		 * Get quiz levels
		 *
		 * @return array
		 */
		public function getLevelsQuiz(): array {
			return $this->levelsQuiz;
		}
	}

	FQI3_Frontend::getInstance();

endif;
