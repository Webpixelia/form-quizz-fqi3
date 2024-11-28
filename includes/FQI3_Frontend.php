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

 if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FQI3_Frontend')) :

class FQI3_Frontend {
    /** @var FQI3_Template_Manager */
    private FQI3_Template_Manager $template_manager;

    /** @var array */
    private array $levelsQuiz = [];
    
    /** @var array */
    private array $options = [];
    
    /** @var string */
    private const COOKIE_QUIZ_COUNT = 'fqi3_quiz_count';
    
    /** @var string */
    private const CACHE_GROUP = 'free_quiz';

    /**
     * Initialize the frontend functionality
     */
    public function __construct() {
        $this->template_manager = new FQI3_Template_Manager();
        $this->options = fqi3_get_options();
        
        // Initialize hooks
        $this->initHooks();
    }

    private function start_session_if_needed(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
   
    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void {
        // Shortcodes @since 1.3.0
        add_shortcode('free_quiz_form', [$this, 'renderForm']);
        add_shortcode('fqi3_remaining_attempts', [$this, 'renderRemainingAttempts']);

        // AJAX handlers
        add_action('wp_ajax_get_questions', [$this, 'handleGetQuestions']);
        add_action('wp_ajax_nopriv_get_questions', [$this, 'handleGetQuestions']);
        add_action('wp_ajax_increment_quiz_count', [$this, 'handleIncrementQuizCount']);
        add_action('wp_ajax_nopriv_increment_quiz_count', [$this, 'handleIncrementQuizCount']);
        add_action('wp_ajax_save_timer_settings', [$this, 'handleSaveTimerSettings']);
        add_action('wp_ajax_nopriv_save_timer_settings', [$this, 'handleSaveTimerSettings']);

        // Other hooks
        add_action('wp_head', [$this, 'outputDynamicCSS']);
        add_action('wp_loaded', [$this, 'initializeLevels']);
    }

    /**
     * Render the quiz form
     */
    public function renderForm(): string {
        $this->start_session_if_needed();

        $data = $this->prepareQuizFormData();
        
        return $this->template_manager->render('quiz-form', $data);
    }

    /**
     * Prepare data for quiz form template
     */
    private function prepareQuizFormData(): array {
        $is_user_logged_in = is_user_logged_in();
        
        return [
            'text_pre_form' => wp_kses_post($this->options['fqi3_text_pre_form'] ?? sprintf(
                '<h1>%s</h1>',
                __('Default Title', 'form-quizz-fqi3')
            )),
            'selected_page_url' => $this->getSalesPageUrl(),
            'is_user_logged_in' => $is_user_logged_in,
            'current_user' => $is_user_logged_in ? wp_get_current_user() : null,
            'title_form' => $is_user_logged_in 
                ? __('Choose your level:', 'form-quizz-fqi3')
                : __('As no logged user, you are only acces to:', 'form-quizz-fqi3'),
            'levels' => $this->levelsQuiz,
            'timer_settings' => $this->getTimerSettings(),
            'sharing_enabled' => empty($this->options['fqi3_disable_sharing']),
            'translations' => $this->getTranslations()
        ];
    }

    /**
     * Render remaining attempts
    */
    public function renderRemainingAttempts(): string {
        $data = $this->prepareAttemptsData();
        
        return $this->template_manager->render('remaining-attempts', $data);
    }

    /**
     * Prepare data for attempts template
    */
    private function prepareAttemptsData(): array {
        $user_id = get_current_user_id();
        $is_premium_user = fqi3_userHasAnyRole($user_id, fqi3_getUserPremiumRoles());
        
        return [
            'infos_attempts' => $this->getAttemptsInfo(),
            'is_premium_user' => $is_premium_user,
            'sales_page_url' => $this->getSalesPageUrl(),
        ];
    }

    /**
     * Get timer settings
    */
    private function getTimerSettings(): array {
        return [
            'enabled' => isset($_SESSION['fqi3_enable_timer']) 
                && $_SESSION['fqi3_enable_timer'] === 'yes',
            'duration' => isset($_SESSION['fqi3_timer_duration']) 
                ? intval($_SESSION['fqi3_timer_duration']) 
                : 5
        ];
    }

    /**
     * Get sales page URL
    */
    private function getSalesPageUrl(): string {
        $page_id = $this->options['fqi3_sales_page'] ?? '';
        return $page_id ? get_permalink($page_id) : '#';
    }

    /**
     * Get translations for templates
    */
    private function getTranslations(): array {
        return [
            'start' => __('Start', 'form-quizz-fqi3'),
            // translators: %s is the name or username of the user.
            'welcome' => __('Welcome, %s. Happy to see you again for some fun learning!', 'form-quizz-fqi3'),
            'loading' => __('Loading...', 'form-quizz-fqi3'),
            'next' => __('Next', 'form-quizz-fqi3'),
            'results' => __('Results', 'form-quizz-fqi3'),
            'restart_quiz' => __('Restart the quizz', 'form-quizz-fqi3'),
            'sign_up' => __('Sign Up to Access More Levels', 'form-quizz-fqi3'),
            'go_back' => __('Go back to the start', 'form-quizz-fqi3'),
            'share_results' => __('Share your results:', 'form-quizz-fqi3'),
            'share_facebook' => __('Share on Facebook', 'form-quizz-fqi3'),
            'share_x' => __('Share on X', 'form-quizz-fqi3'),
            'share_linkedin' => __('Share on LinkedIn', 'form-quizz-fqi3'),
            'upgrade_message' => __('Upgrade to our premium version to get unlimited access!', 'form-quizz-fqi3'),
            'view_details' => __('View Details and register', 'form-quizz-fqi3'),
            'timer_label' => __('Do you want to activate a timer to complete the 10 questions?', 'form-quizz-fqi3'),
            'timer_duration' => __('How many minutes?', 'form-quizz-fqi3')
        ];
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
        if ($this->shouldBlockQuizAccess()) {
            wp_send_json_error([
                'message' => __('You have reached your quiz limit for today.', 'form-quizz-fqi3')
            ]);
        }

        $level = $this->sanitizeLevel($_GET['niveau'] ?? 'free');
        $questions = $this->getQuestions($level);

        if (empty($questions)) {
            wp_send_json([
                'error' => sprintf(
                    // translators: %s represents the level (e.g., 'beginner', 'intermediate', 'advanced') where no questions are found.
                    __('No questions found in %s level.', 'form-quizz-fqi3'),
                    '<span id="levelChoose"></span>'
                )
            ]);
        }

        $this->incrementQuizCount();
        wp_send_json($questions);
    }

    /**
     * Get questions for a specific level
    */
    private function getQuestions(string $level): array {
        $cacheKey = "questions_{$level}";
        $questions = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if (false === $questions) {
            global $wpdb;
            $questions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}" . FQI3_TABLE_QUIZZES . " WHERE niveau = %s ORDER BY RAND() LIMIT 10",
                    $level
                ),
                ARRAY_A
            );

            if ($questions) {
                array_walk($questions, function(&$question) {
                    $question['options'] = json_decode($question['options'], true);
                });

                wp_cache_set($cacheKey, $questions, self::CACHE_GROUP, 300);
            }
        }

        return $questions ?: [];
    }

    /**
     * Handle cookie operations
    */
    private function handleCookie(string $action, ?string $value = null): ?int {
        switch ($action) {
            case 'get':
                return isset($_COOKIE[self::COOKIE_QUIZ_COUNT]) 
                    ? (int)$_COOKIE[self::COOKIE_QUIZ_COUNT] 
                    : 0;
            
            case 'set':
                $expire = strtotime('tomorrow');
                setcookie(
                    self::COOKIE_QUIZ_COUNT,
                    $value,
                    $expire,
                    COOKIEPATH,
                    COOKIE_DOMAIN,
                    true,
                    true
                );
                $_COOKIE[self::COOKIE_QUIZ_COUNT] = $value;
                return null;
            
            case 'reset':
                return $this->handleCookie('set', '0');
            
            default:
                return null;
        }
    }

    /**
     * Check if quiz access should be blocked
    */
    private function shouldBlockQuizAccess(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (!in_array('subscriber', $user->roles)) {
            return false;
        }

        return !$this->canTakeQuiz();
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
        $quizCount = $this->handleCookie('get');
        $dailyLimit = (int)($this->options['fqi3_free_trials_per_day'] ?? 3);
        
        return $quizCount <= $dailyLimit;
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
        $totalAttempts = (int)($this->options['fqi3_free_trials_per_day'] ?? 0);
        $attemptsMade = $this->handleCookie('get');
        $remainingAttempts = max(0, $totalAttempts - $attemptsMade);

        return [
            'total_attempts' => $totalAttempts,
            'attempts_made' => $attemptsMade,
            'remaining_attempts' => $remainingAttempts
        ];
    }

    /**
     * Output dynamic CSS
    */
    public function outputDynamicCSS(): void {
        $cssVars = [
            'color-text-pre-form' => $this->options['fqi3_color_text_pre_form'] ?? '',
            'color-text-top-question' => $this->options['fqi3_color_text_top_question'] ?? '',
            'color-bg-top-question' => $this->options['fqi3_color_bg_top_question'] ?? '',
            'color-text-btn' => $this->options['fqi3_color_text_btn'] ?? '',
            'color-bg-btn' => $this->options['fqi3_color_bg_btn'] ?? ''
        ];

        $css = ':root {' . PHP_EOL;
        foreach ($cssVars as $key => $value) {
            $css .= sprintf('    --%s: %s;%s', $key, esc_attr($value), PHP_EOL);
        }
        $css .= '}';

        printf('<style>%s</style>', $css);
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

        check_ajax_referer('fqi3_session_nonce', 'nonce');

        $enableTimer = $_POST['enable_timer'] ?? 'no';
        $_SESSION['fqi3_enable_timer'] = $enableTimer;

        if ($enableTimer === 'yes') {
            $_SESSION['fqi3_timer_duration'] = (int)($_POST['timer_duration'] ?? 5);
        } else {
            unset($_SESSION['fqi3_timer_duration']);
        }

        wp_send_json_success();
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
     * Sanitize level input
    */
    private function sanitizeLevel(string $level): string {
        return sanitize_text_field($level);
    }

    /**
     * Increment quiz count stored in the cookie.
    */
    private function incrementQuizCount(): void {
        $count = $this->handleCookie('get');
        $this->handleCookie('set', (string)($count + 1));
    }

    /**
     * Handle increment quiz count AJAX request
     */
    public function handleIncrementQuizCount(): void {
        check_ajax_referer('fqi3_cookies_nonce', 'nonce');
        $this->incrementQuizCount();
        wp_send_json_success();
    }
}

new FQI3_Frontend();

endif;