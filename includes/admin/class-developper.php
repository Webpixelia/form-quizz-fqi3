<?php
namespace Form_Quizz_FQI3;
/**
 * Class FQI3_Developper_Page
 *
 * Responsible for rendering the "Changelog and User Guide" page in the WordPress admin for the Form Quizz FQI3 plugin.
 *
 * This class handles two main functionalities:
 * 1. Renders the API guide, providing information about the plugin and shortcodes available for use.
 * 2. Displays the developper section
 *
 * @package Form_Quizz_FQI3
 * @subpackage Admin Pages
 * @since      3.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Developper_Page' ) ) :
    class FQI3_Developper_Page {
        /**
         * @var array Cached API endpoint data
         */
        private array $api_endpoints;
    
        /**
         * Constructor to initialize the class
         */
        public function __construct() {
            // Initialize properties to avoid "must not be accessed before initialization" error
            $this->api_endpoints = [];
            
            add_action('init', [$this, 'init_api_endpoints']);
        }

        /**
        * Render New badge
        */
        public static function get_new_badge(): string {
            return ' <span class="badge text-bg-warning">' . __('New', 'form-quizz-fqi3') . '</span>';
        }
    
        /**
         * Initialize API endpoint data
        */
        public function init_api_endpoints(): void {
            $this->api_endpoints = [
                __('Questions & Levels:', 'form-quizz-fqi3') => [
                    [
                        'title' => __('Get All Questions:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/quizzes',
                        'description' => __('This API retrieves all questions from the quizzes. It returns a list of questions containing information about each question, including the level, the text of the question in different languages, available options, and the correct answer.', 'form-quizz-fqi3'),
                        'response_fields' => [
                            'id' => __('The unique identifier of the question.', 'form-quizz-fqi3'),
                            'niveau' => __('The level of the question (e.g., "beginner", "intermediate").', 'form-quizz-fqi3'),
                            'q' => __('The text of the question in the primary language.', 'form-quizz-fqi3'),
                            'q2' => __('The text of the question in an alternate language.', 'form-quizz-fqi3'),
                            'options' => __('A JSON string array of available options for the question.', 'form-quizz-fqi3'),
                            'answer' => __('The index of the correct answer in the options array (zero-based).', 'form-quizz-fqi3')
                        ],
                        'example_response' => [
                            [
                                'id' => '1',
                                'niveau' => 'beginner',
                                'q' => 'What color is a banana?',
                                'q2' => 'ما لون الموز؟',
                                'options' => '["Red", "Blue", "Yellow", "Green"]',
                                'answer' => '2'
                            ],
                            [
                                'id' => '2',
                                'niveau' => "'intermediate'",
                                'q' => 'Which planet is known as the Red Planet?',
                                'q2' => 'ما هو الكوكب المعروف باسم الكوكب الأحمر؟',
                                'options' => '["Earth", "Mars", "Jupiter", "Venus"]',
                                'answer' => '1'
                            ]
                        ]
                    ],
                    [
                        'title' => __('Get Question by ID:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/quizzes/{id}'
                    ],
                    [
                        'title' => __('Get Questions by Level:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/quizzes/level/{level_name}'
                    ],
                    [
                        'title' => __('Get All Quiz Levels:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/levels',
                        'description' => __('This API retrieves all available levels for quizzes. It returns a list of levels, including their names, labels, and whether they are free or paid.', 'form-quizz-fqi3'),
                        'response_fields' => [
                            'name' => __('The unique identifier for the level (e.g., "beginner").', 'form-quizz-fqi3'),
                            'label' => __('The display name of the level (e.g., "Beginner").', 'form-quizz-fqi3'),
                            'is_free' => __('A boolean indicating whether the level is free (true) or paid (false).', 'form-quizz-fqi3')
                        ],
                        'example_response' => [
                            [
                                "name" => "beginner",
                                "label" => "Beginner",
                                "is_free" => false
                            ],
                            [
                                "name" => "intermediate",
                                "label" => "Intermediate",
                                "is_free" => false
                            ],
                            [
                                "name" => "advanced",
                                "label" => "Advanced",
                                "is_free" => false
                            ],
                            [
                                "name" => "beginner-free",
                                "label" => "Free Beginner",
                                "is_free" => true
                            ],
                            [
                                "name" => "advanced-free",
                                "label" => "Free Advanced",
                                "is_free" => true
                            ],
                            [
                                "name" => "intermediate-free",
                                "label" => "Free Intermediate",
                                "is_free" => true
                            ]
                        ]
                    ],
                    [
                        'title' => __('Get Quiz Level by Name:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/levels/{name}'
                    ],
                    [
                        'title' => __('Get Quiz Levels by Type:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/levels/type/{type}',
                        'att' => '(free|premium)'
                    ]
                ],
                __('User Data:', 'form-quizz-fqi3') => [
                    [
                        'title' => __('Get User Performance:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/user/{user_id}/performance',
                        'description' => __('This API retrieves the performance metrics for a specific user, including their quiz attempts and success rates across different levels.', 'form-quizz-fqi3'),
                        'response_fields' => [
                            'entry_id' => __('The unique identifier for the performance entry.', 'form-quizz-fqi3'),
                            'user_id' => __('The identifier of the user whose performance is being retrieved.', 'form-quizz-fqi3'),
                            'level' => __('The quiz level for which the performance is recorded.', 'form-quizz-fqi3'),
                            'total_quizzes' => __('The total number of quizzes attempted by the user at this level.', 'form-quizz-fqi3'),
                            'total_questions_answered' => __('The total number of questions answered by the user at this level.', 'form-quizz-fqi3'),
                            'total_good_answers' => __('The total number of correct answers provided by the user.', 'form-quizz-fqi3'),
                            'success_rate' => __('The percentage of correct answers.', 'form-quizz-fqi3'),
                            'best_score' => __('The highest score achieved by the user in quizzes at this level.', 'form-quizz-fqi3'),
                            'last_updated' => __('The date and time when the performance data was last updated.', 'form-quizz-fqi3')
                        ],
                        'example_response' => [
                            [
                                "entry_id" => "6",
                                "user_id" => "1",
                                "level" => "beginner",
                                "total_quizzes" => "111",
                                "total_questions_answered" => "136",
                                "total_good_answers" => "101",
                                "success_rate" => "74.26",
                                "best_score" => "100",
                                "last_updated" => "2024-11-02 16:06:14"
                            ],
                            [
                                "entry_id" => "8",
                                "user_id" => "1",
                                "level" => "intermediate",
                                "total_quizzes" => "3",
                                "total_questions_answered" => "3",
                                "total_good_answers" => "2",
                                "success_rate" => "66.6667",
                                "best_score" => "100",
                                "last_updated" => "2024-11-01 16:57:15"
                            ]
                        ]
                    ],
                    [
                        'title' => __('Get User Badges:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/user/{user_id}/badges'
                    ],
                    [
                        'title' => __('Update User Performance:', 'form-quizz-fqi3'),
                        'endpoint' => 'POST /wp-json/fqi3/v1/user/{user_id}/performance',
                        'description' => __('This API updates or inserts performance metrics for a specific user at a particular quiz level. The request should include the level, correct answers, and total questions. The response returns a confirmation message along with the submitted data.', 'form-quizz-fqi3'),
                        'request_fields' => [
                            'level' => __('The quiz level (e.g., "beginner", "intermediate", "advanced"). Required.', 'form-quizz-fqi3'),
                            'correct_answers' => __('The number of correct answers for this quiz attempt. Required.', 'form-quizz-fqi3'),
                            'total_questions' => __('The total number of questions in this quiz attempt. Required.', 'form-quizz-fqi3'),
                        ],
                        'response_fields' => [
                            'message' => __('Confirmation message indicating that the performance was updated successfully.', 'form-quizz-fqi3'),
                            'data' => __('An object containing the submitted performance data.', 'form-quizz-fqi3'),
                            'data.user_id' => __('The unique identifier for the user.', 'form-quizz-fqi3'),
                            'data.level' => __('The quiz level for which the performance was submitted.', 'form-quizz-fqi3'),
                            'data.correct_answers' => __('The number of correct answers submitted.', 'form-quizz-fqi3'),
                            'data.total_questions' => __('The total number of questions submitted.', 'form-quizz-fqi3'),
                        ],
                        'example_response' => [
                            'message' => 'Performance updated successfully.',
                            'data' => [
                                "entry_id" => "9",
                                "user_id" => "1",
                                "level" => "advance",
                                "total_quizzes" => "7",
                                "total_questions_answered" => "44",
                                "total_good_answers" => "31",
                                "success_rate" => "70.4545",
                                "best_score" => "90",
                                "last_updated" => "2025-08-28 19:22:50"
                            ]
                        ]
                    ]
                ],
                __('Badges:', 'form-quizz-fqi3') => [
                    [
                        'title' => __('Get Badge Settings:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/badges/settings'
                    ],
                    [
                        'title' => __('Get Specific Badge Type Settings:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/badges/settings/{type}',
                        'att' => '(completion-badge|success-rate-badge)'
                    ],
                    [
                        'title' => __('Get All Users Badges:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/badges/all-users',
                        'description' => __('This API retrieves all badges awarded to users. It returns a list of badges containing information about each user who has received one or more badges, along with details about those badges.', 'form-quizz-fqi3'),
                        'query_param' => [
                            'page' =>  __('(optional) The page number to retrieve. Defaults to the first page.', 'form-quizz-fqi3'),
                            'per_page' => __('(optional) The number of badges to return per page. Defaults to 10 badges.', 'form-quizz-fqi3')
                        ],
                        'response_fields' => [
                            'id' => __('The unique identifier of the badge/user association in the database.', 'form-quizz-fqi3'),
                            'user' => [
                                'description' => __('An object containing information about the user:', 'form-quizz-fqi3'),
                                'fields' => [
                                    'id' => __('The user identifier.', 'form-quizz-fqi3'),
                                    'name' => __('The display name of the user.', 'form-quizz-fqi3'),
                                    'email' => __('The user\'s email address.', 'form-quizz-fqi3')
                                ]
                            ],
                            'badge' => [
                                'description' => __('An object containing details about the badge:', 'form-quizz-fqi3'),
                                'fields' => [
                                    'type' => __('The type of badge (e.g., "completion-badge" or "success-rate-badge").', 'form-quizz-fqi3'),
                                    'name' => __('The name of the badge.', 'form-quizz-fqi3'),
                                    'threshold' => __('The threshold required to earn the badge, or null if not applicable.', 'form-quizz-fqi3'),
                                    'image_id' => __('The ID of the image associated with the badge.', 'form-quizz-fqi3'),
                                    'image_url' => __('The URL of the badge image, or null if not available.', 'form-quizz-fqi3')
                                ]
                            ],
                            'date_earned' => __('The date the badge was awarded.', 'form-quizz-fqi3'),
                            'achievement_value' => __('The achievement value that triggered the badge award.', 'form-quizz-fqi3')
                        ],
                        'example_response' => [
                            [   
                                "id" => '1',
                                "user" => [
                                    "id" => '123',
                                    "name" => "John Doe",
                                    "email" => "john.doe@example.com"
                                ],
                                "badge" => [
                                    "type" => "completion-badge",
                                    "name" => "Level 1",
                                    "threshold" => "60",
                                    "image_id" => "29",
                                    "image_url" => "https://example.com/wp-content/uploads/2023/01/badge-level1.png"
                                ],
                                "date_earned" => "2023-01-01 12:00:00",
                                "achievement_value" => "60"
                            ]
                        ]
                    ]
                ],
                __('Settings', 'form-quizz-fqi3') => [
                    [
                        'title' => __('Get Settings:', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/settings',
                        'att' => __('Requires header: X-API-Token', 'form-quizz-fqi3'),
                        'description' => __('Returns a merged object of whitelisted settings grouped by section. Read-only. The exposed keys are controlled via filters (fqi3_api_settings_whitelist_*).', 'form-quizz-fqi3'),
                        'response_fields' => [
                            'general' => [
                                'description' => __('Object of exposed general settings.', 'form-quizz-fqi3'),
                                'fields' => [
                                    'site_name' => __('Example key from general group (actual keys depend on whitelist).', 'form-quizz-fqi3'),
                                ]
                            ],
                            'emails' => [
                                'description' => __('Object of exposed email settings.', 'form-quizz-fqi3'),
                                'fields' => [
                                    'sender_name' => __('Example key from emails group.', 'form-quizz-fqi3'),
                                ]
                            ],
                            'tools' => [
                                'description' => __('Object of exposed tools settings.', 'form-quizz-fqi3'),
                                'fields' => [
                                    'enable_debug' => __('Example key from tools group.', 'form-quizz-fqi3'),
                                ]
                            ],
                            'badges' => [
                                'description' => __('Object of exposed badges settings.', 'form-quizz-fqi3'),
                                'fields' => [
                                    'completion_badge_threshold' => __('Example key from badges group.', 'form-quizz-fqi3'),
                                ]
                            ]
                        ],
                        'example_response' => [
                            'general' => [
                                'quiz_timer_seconds' => 60,
                                'max_free_attempts_per_day' => 3
                            ],
                            'emails' => [
                                'sender_name' => 'Form Quizz Bot',
                                'sender_email' => 'noreply@example.com'
                            ],
                            'tools' => [
                                'enable_debug' => false
                            ],
                            'badges' => [
                                'completion_badge_threshold' => 60,
                                'success_rate_badge_threshold' => 80
                            ]
                        ]
                    ],
                    [
                        'title' => __('Get Settings (by group):', 'form-quizz-fqi3'),
                        'endpoint' => 'GET /wp-json/fqi3/v1/settings/{group}',
                        'att' => __('Requires header: X-API-Token  |  {group} = general|emails|tools|badges', 'form-quizz-fqi3'),
                        'description' => __('Returns only the whitelisted keys for the requested group. Read-only.', 'form-quizz-fqi3'),
                        'response_fields' => [
                            'key' => __('Each property corresponds to an exposed setting in the selected group.', 'form-quizz-fqi3')
                        ],
                        'example_response' => [
                            'quiz_timer_seconds' => 60,
                            'max_free_attempts_per_day' => 3
                        ]
                    ],
                ],
            ];
        }
    
        /**
         * Render the admin page
         */
        public function render_developper_page(): void {
            ?>
            <div class="wrap container-fluid">
                <h2 class="wp-heading-inline page-title"><?php _e('Changelog and User Guide', 'form-quizz-fqi3'); ?></h2>
                
                <?php
                $this->render_developer_section();
                $this->render_api_documentation();
                ?>
            </div>
            <?php
        }

        /**
         * Render the developer section (AJAX, Cron, User Meta Keys)
         */
        private function render_developer_section(): void {
            ?>
            <div id="developer_doc" class="fqi3-section-options">
                <h3><?php _e('Developer', 'form-quizz-fqi3'); ?></h3>
                <hr>
                <p><?php _e('This section lists useful technical hooks exposed by the parent plugin.', 'form-quizz-fqi3'); ?></p>

                <h4><?php _e('AJAX Endpoints', 'form-quizz-fqi3'); ?></h4>
                <ul>
                    <li><code>wp_ajax_get_questions</code> / <code>wp_ajax_nopriv_get_questions</code></li>
                    <li><code>wp_ajax_increment_quiz_count</code> / <code>wp_ajax_nopriv_increment_quiz_count</code></li>
                    <li><code>wp_ajax_save_timer_settings</code> / <code>wp_ajax_nopriv_save_timer_settings</code></li>
                    <li><code>wp_ajax_fqi3_register_quiz_start</code> / <code>wp_ajax_nopriv_fqi3_register_quiz_start</code></li>
                    <li><code>wp_ajax_fqi3_register_quiz_complete</code> / <code>wp_ajax_nopriv_fqi3_register_quiz_complete</code></li>
                    <li><code>wp_ajax_fqi3_process_quiz_hooks</code> / <code>wp_ajax_nopriv_fqi3_process_quiz_hooks</code></li>
                    <li><code>wp_ajax_fqi3_revoke_token</code></li>
                    <li><code>wp_ajax_award_badges</code> / <code>wp_ajax_nopriv_award_badges</code></li>
                    <li><code>wp_ajax_fqi3_send_test_email</code></li>
                    <li><code>wp_ajax_refresh_statistics</code></li>
                    <li><code>wp_ajax_search_addons</code>, <code>wp_ajax_filter_addons</code>, <code>wp_ajax_fqi3_addon_action</code><?php echo self::get_new_badge(); ?></li>
                    <li><code>wp_ajax_update_quiz_statistics</code> / <code>wp_ajax_nopriv_update_quiz_statistics</code></li>
                </ul>

                <h4><?php _e('Cron Events', 'form-quizz-fqi3'); ?></h4>
                <ul>
                    <li><code>fqi3_weekly_statistics_event</code></li>
                    <li><code>fqi3_monthly_statistics_event</code></li>
                </ul>

                <h4><?php _e('User Meta Keys', 'form-quizz-fqi3'); ?></h4>
                <ul>
                    <li><?php _e('No specific user meta keys are written directly by the parent plugin; data is primarily stored in custom tables (performance, periodic statistics).', 'form-quizz-fqi3'); ?></li>
                </ul>

                <h4 class="mt-4"><?php _e('Hooks', 'form-quizz-fqi3'); ?><?php echo self::get_new_badge(); ?></h4>
                <p class="description"><?php _e('Core exposes runtime hooks and configuration filters for addons. Below is the consolidated list.', 'form-quizz-fqi3'); ?></p>
                <ul>
                    <li>
                        <code>fqi3_quiz_questions_draw</code> — <?php _e('(filter) Allow addons to provide a deterministic question set for a level.', 'form-quizz-fqi3'); ?><br>
                        <small><code>apply_filters('fqi3_quiz_questions_draw', ['question_ids'=>[],'meta'=>[]], $level, $user_id, $context)</code></small>
                    </li>
                    <li>
                        <code>fqi3_quiz_questions_drawn</code> — <?php _e('(action) Fired when the final set is fixed; consume context for logging.', 'form-quizz-fqi3'); ?><br>
                        <small><code>do_action('fqi3_quiz_questions_drawn', $user_id, $level, $question_ids, ['source'=>'core','attempt_id'=>N])</code></small>
                    </li>
                    <li>
                        <code>fqi3_quiz_attempt_started</code> — <?php _e('(action) Server‑side attempt created.', 'form-quizz-fqi3'); ?><br>
                        <small><code>do_action('fqi3_quiz_attempt_started', $attempt_id, $user_id, $level)</code></small>
                    </li>
                    <li>
                        <code>fqi3_quiz_attempt_completed</code> — <?php _e('(action) Attempt completed with score.', 'form-quizz-fqi3'); ?><br>
                        <small><code>do_action('fqi3_quiz_attempt_completed', $attempt_id, $user_id, $level, $score)</code></small>
                    </li>
                    <li>
                        <code>fqi3_admin_quiz_questions_top_panel</code> — <?php _e('(action) Inject a top panel into the core questions screen.', 'form-quizz-fqi3'); ?><br>
                        <small><code>do_action('fqi3_admin_quiz_questions_top_panel')</code></small>
                    </li>
                    <li>
                        <code>fqi3_admin_quiz_question_actions</code> — <?php _e('(filter) Append custom actions HTML to each question row (View Questions table).', 'form-quizz-fqi3'); ?><br>
                        <small><code>apply_filters('fqi3_admin_quiz_question_actions', '', $question)</code></small>
                    </li>
                    <li>
                        <code>fqi3_options_settings_sections</code> — <?php _e('(filter) Inject tabs/sections into the core Settings page. Defined in', 'form-quizz-fqi3'); ?>
                        <code>includes/fqi3-config-functions.php</code>
                        <details>
                            <summary><?php _e('Example', 'form-quizz-fqi3'); ?></summary>
            <pre><code>add_filter('fqi3_options_settings_sections', function($config){
                $config['sections']['my_addon'] = [
                    'id' => 'fqi3_my_addon',
                    'title' => __('My Addon','textdomain'),
                    'icon' => 'bi-puzzle',
                ];
                return $config;
            });</code></pre>
                        </details>
                    </li>
                    <li class="mt-3">
                        <code>fqi3_admin_quiz_questions_filter_fields</code>
                        <div class="small text-muted">
                            <?php _e('(action) Inject extra filter controls into the Quizzes → View Questions filter form (e.g., Language dropdown).', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="mt-2">
                            <strong><?php _e('Signature', 'form-quizz-fqi3'); ?>:</strong>
                            <pre class="bg-light p-2 overflow-auto"><code>do_action('fqi3_admin_quiz_questions_filter_fields')</code></pre>
                        </div>
                    </li>
                    <li class="mt-3">
                        <code>fqi3_admin_quiz_questions_where_parts</code>
                        <div class="small text-muted">
                            <?php _e('(filter) Modify the array of SQL WHERE parts before they are imploded. Useful to constrain by language, custom flags, etc.', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="mt-2">
                            <strong><?php _e('Signature', 'form-quizz-fqi3'); ?>:</strong>
                            <pre class="bg-light p-2 overflow-auto"><code>apply_filters('fqi3_admin_quiz_questions_where_parts', array $where_parts, array $context)</code></pre>
                            <strong><?php _e('Context', 'form-quizz-fqi3'); ?>:</strong>
                            <ul class="small">
                                <li><code>$context</code> — <?php _e('Associative array with current filters (e.g., level, search term, selected lang).', 'form-quizz-fqi3'); ?></li>
                            </ul>
                        </div>
                    </li>
                    <li class="mt-3">
                        <code>fqi3_admin_quiz_questions_where_sql</code>
                        <div class="small text-muted">
                            <?php _e('(filter) Override or post-process the final WHERE SQL string built from the parts.', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="mt-2">
                            <strong><?php _e('Signature', 'form-quizz-fqi3'); ?>:</strong>
                            <pre class="bg-light p-2 overflow-auto"><code>apply_filters('fqi3_admin_quiz_questions_where_sql', string $where_sql, array $context)</code></pre>
                        </div>
                    </li>
                    <li class="mt-3">
                        <code>fqi3_admin_quiz_questions_sql</code>
                        <div class="small text-muted">
                            <?php _e('(filter) Filter the final SELECT SQL for the questions list (pagination query).', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="mt-2">
                            <strong><?php _e('Signature', 'form-quizz-fqi3'); ?>:</strong>
                            <pre class="bg-light p-2 overflow-auto"><code>apply_filters('fqi3_admin_quiz_questions_sql', string $sql, array $context)</code></pre>
                        </div>
                    </li>
                    <li class="mt-3">
                        <code>fqi3_admin_quiz_questions_count_sql</code>
                        <div class="small text-muted">
                            <?php _e('(filter) Filter the final COUNT(*) SQL used to compute pagination totals.', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="mt-2">
                            <strong><?php _e('Signature', 'form-quizz-fqi3'); ?>:</strong>
                            <pre class="bg-light p-2 overflow-auto"><code>apply_filters('fqi3_admin_quiz_questions_count_sql', string $count_sql, array $context)</code></pre>
                        </div>
                    </li>
                    <li class="mt-3">
                        <code>fqi3_api_settings_whitelist_general</code>
                        <div class="small text-muted">
                            <?php _e('(filter) Define which keys from fqi3_options are exposed via REST for the general group.', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="mt-2">
                            <strong><?php _e('Signature', 'form-quizz-fqi3'); ?>:</strong>
                            <pre class="bg-light p-2 overflow-auto"><code>apply_filters('fqi3_api_settings_whitelist_general', array $keys)</code></pre>
                        </div>
                    </li>
                    <li class="mt-3">
                        <code>fqi3_api_settings_whitelist_emails</code>
                        <div class="small text-muted">
                            <?php _e('(filter) Define which keys from fqi3_options are exposed via REST for the emails group.', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="mt-2">
                            <strong><?php _e('Signature', 'form-quizz-fqi3'); ?>:</strong>
                            <pre class="bg-light p-2 overflow-auto"><code>apply_filters('fqi3_api_settings_whitelist_emails', array $keys)</code></pre>
                        </div>
                    </li>
                    <li class="mt-3">
                        <code>fqi3_api_settings_whitelist_tools</code>
                        <div class="small text-muted">
                            <?php _e('(filter) Define which keys from fqi3_options are exposed via REST for the tools group.', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="mt-2">
                            <strong><?php _e('Signature', 'form-quizz-fqi3'); ?>:</strong>
                            <pre class="bg-light p-2 overflow-auto"><code>apply_filters('fqi3_api_settings_whitelist_tools', array $keys)</code></pre>
                        </div>
                    </li>
                    <li class="mt-3">
                        <code>fqi3_api_settings_whitelist_badges</code>
                        <div class="small text-muted">
                            <?php _e('(filter) Define which keys from fqi3_options are exposed via REST for the badges group.', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="mt-2">
                            <strong><?php _e('Signature', 'form-quizz-fqi3'); ?>:</strong>
                            <pre class="bg-light p-2 overflow-auto"><code>apply_filters('fqi3_api_settings_whitelist_badges', array $keys)</code></pre>
                        </div>
                    </li>
                </ul>
            </div>
            <?php
        }

        /**
         * Renders the API documentation section in the admin interface
         */
        private function render_api_documentation(): void {
            // Ensure API endpoints are initialized
            if (empty($this->api_endpoints)) {
                $this->init_api_endpoints();
            }
            
            ?>
            <div id="api_doc" class="fqi3-section-options">
                <h3>
                    <?php _e('API Documentation:', 'form-quizz-fqi3'); ?>
                    <?php echo self::get_new_badge(); ?>
                </h3>
                <hr>
                <p><?php _e('The plugin provides a REST API for accessing quiz data. Below are the available routes:', 'form-quizz-fqi3'); ?></p>
                
                <h4><?php _e('Available Routes:', 'form-quizz-fqi3'); ?></h4>
                <ul class="api-routes">
                    <?php foreach ($this->api_endpoints as $section => $endpoints): ?>
                        <li>
                            <strong><?php echo esc_html($section); ?></strong>
                            <ul>
                                <?php foreach ($endpoints as $endpoint): ?>
                                    <li>
                                        <strong><?php echo esc_html($endpoint['title']); ?></strong>
                                        <code><?php echo esc_html($endpoint['endpoint']); ?></code>
                                        <?php if (!empty($endpoint['att'])): ?>
                                            <small><?php echo esc_html($endpoint['att']); ?></small>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($endpoint['description'])): ?>
                                            <details>
                                                <summary><?php _e('API Description', 'form-quizz-fqi3'); ?></summary>
                                                <p><?php echo esc_html($endpoint['description']); ?></p>
                                            </details>
                                        <?php endif; ?>

                                        <?php if (!empty($endpoint['query_param'])): ?>
                                            <details>
                                                <summary><?php _e('Query Parameters', 'form-quizz-fqi3'); ?></summary>
                                                <ul>
                                                <?php foreach ($endpoint['query_param'] as $param => $param_description): ?>
                                                        <li>
                                                            <code><?php echo esc_html($param); ?></code>: 
                                                            <?php echo esc_html($param_description); ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <ul>
                                            </details>
                                        <?php endif; ?>

                                        <?php if (!empty($endpoint['request_fields'])): ?>
                                            <details>
                                                <summary><?php _e('Request Fields', 'form-quizz-fqi3'); ?></summary>
                                                <ul>
                                                <?php foreach ($endpoint['request_fields'] as $field => $description): ?>
                                                        <li>
                                                            <code><?php echo esc_html($field); ?></code>: 
                                                            <?php echo esc_html($description); ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <ul>
                                            </details>
                                        <?php endif; ?>

                                        <?php if (!empty($endpoint['response_fields'])): ?>
                                            <details>
                                                <summary><?php _e('Response', 'form-quizz-fqi3'); ?></summary>
                                                <ul>
                                                    <li><?php _e('The response is an array of objects containing the following informations:', 'form-quizz-fqi3'); ?>
                                                    <?php foreach ($endpoint['response_fields'] as $field => $description): ?>
                                                        <li>
                                                            <code><?php echo esc_html($field); ?></code>: 
                                                            <?php echo $this->renderDescription($description); ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>

                                        <?php if (!empty($endpoint['example_response'])): ?>
                                            <details>
                                                <summary><?php _e('Example Response', 'form-quizz-fqi3'); ?></summary>
                                                <pre><code><?php echo esc_html(json_encode($endpoint['example_response'], JSON_PRETTY_PRINT)); ?></code></pre>
                                            </details>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h4><?php _e('API Security', 'form-quizz-fqi3'); ?></h4>
                <div class="alert alert-info">
                    <p><strong><?php _e('Overview', 'form-quizz-fqi3'); ?>:</strong> <?php _e('The API includes multiple security layers that you can configure in the settings.', 'form-quizz-fqi3'); ?></p>
                    <ul>
                        <li><strong><?php _e('HTTPS-only', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Reject non-HTTPS requests when enabled. Default: disabled (HTTP allowed).', 'form-quizz-fqi3'); ?></li>
                        <li><strong><?php _e('CORS allowlist', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Restrict cross-origin access by specifying allowed Origins, Methods, and Headers. Defaults:', 'form-quizz-fqi3'); ?>
                            <ul>
                                <li><code><?php _e('Origins', 'form-quizz-fqi3'); ?></code>: <em><?php _e('empty (CORS disabled)', 'form-quizz-fqi3'); ?></em></li>
                                <li><code><?php _e('Methods', 'form-quizz-fqi3'); ?></code>: <code>GET, POST, OPTIONS</code></li>
                                <li><code><?php _e('Headers', 'form-quizz-fqi3'); ?></code>: <code>Content-Type, X-API-Token</code></li>
                            </ul>
                        </li>
                        <li><strong><?php _e('Rate limiting', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Sliding window limits per IP and per Token.', 'form-quizz-fqi3'); ?>
                            <ul>
                                <li><code><?php _e('Per IP', 'form-quizz-fqi3'); ?></code>: <em><?php _e('default 0 (disabled)', 'form-quizz-fqi3'); ?></em></li>
                                <li><code><?php _e('Per Token', 'form-quizz-fqi3'); ?></code>: <em><?php _e('default 0 (disabled)', 'form-quizz-fqi3'); ?></em></li>
                                <li><code><?php _e('Window (seconds)', 'form-quizz-fqi3'); ?></code>: <code>60</code></li>
                            </ul>
                        </li>
                        <li><strong><?php _e('Token scopes', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Gate access to route groups: quizzes, stats, badges, settings. Default: all enabled.', 'form-quizz-fqi3'); ?></li>
                        <li><strong><?php _e('Token expiration', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Choose Never or Expire after N days; optional grace period (overlap days) keeps the token valid slightly longer. Defaults: Never, 0 days overlap.', 'form-quizz-fqi3'); ?></li>
                        <li><strong><?php _e('/ping endpoint', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Returns token validity and the effective scopes for quick health checks.', 'form-quizz-fqi3'); ?></li>
                    </ul>
                </div>

                <h4><?php _e('Example Request:', 'form-quizz-fqi3'); ?></h4>
                <pre>curl -X GET \ '<?php echo esc_url(get_site_url()); ?>/wp-json/fqi3/v1/quizzes' \ -H 'X-API-Token: YOUR_TOKEN'</pre>
                
                <h4><?php _e('Response Format:', 'form-quizz-fqi3'); ?></h4>
                <p><?php _e('Responses are returned in JSON format and contain either the requested data or appropriate error messages.', 'form-quizz-fqi3'); ?></p>
            </div>
            <?php
        }

        /**
         * Renders a description which can be either a simple string or an array containing
         * a description and additional fields.
         *
         * @param array|string $description The description to render:
         *                                 - If string: The description text
         *                                 - If array: [
         *                                     'description' => string,
         *                                     'fields' => array<string, string>
         *                                 ]
         * @return string HTML-formatted description with escaped content
        */
        private function renderDescription(array|string $description): string {
            if (!is_array($description)) {
                return esc_html($description);
            }
            
            $output = esc_html($description['description'] ?? '');
            
            if (!empty($description['fields'])) {
                $output .= '<ul>';
                foreach ($description['fields'] as $field => $desc) {
                    $output .= sprintf(
                        '<li><code>%s</code>: %s</li>',
                        esc_html($field),
                        esc_html($desc)
                    );
                }
                $output .= '</ul>';
            }
            
            return $output;
        } 
    }

endif;