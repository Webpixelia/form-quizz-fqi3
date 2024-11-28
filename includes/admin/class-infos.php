<?php
namespace Form_Quizz_FQI3;
/**
 * Class FQI3_Infos_Page
 *
 * Responsible for rendering the "Changelog and User Guide" page in the WordPress admin for the Form Quizz FQI3 plugin.
 *
 * This class handles two main functionalities:
 * 1. Renders the user guide, providing information about the plugin and shortcodes available for use.
 * 2. Displays the changelog, retrieving content from the `readme.txt` file, extracting version history, and formatting it
 *    as HTML. It also manages error handling if the changelog file cannot be read or processed correctly.
 *
 * The changelog is sorted in descending order by version number, and only the latest six entries are shown.
 *
 * @package Form_Quizz_FQI3
 * @subpackage Admin Pages
 * @since      1.0.0
 * @version    2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Infos_Page' ) ) :
    class FQI3_Infos_Page {
        /**
         * @var array Cached shortcode data
         */
        private array $shortcodes;
    
        /**
         * @var array Cached API endpoint data
         */
        private array $api_endpoints;
    
        /**
         * Constructor to initialize the class
         */
        public function __construct() {
            $this->init_shortcodes();
            $this->init_api_endpoints();
        }
    
        /**
         * Initialize shortcode data
         */
        private function init_shortcodes(): void {
            $this->shortcodes = [
                'fqi3_user_statistics' => __('Display detailed user statistics across posts, pages, or widgets.', 'form-quizz-fqi3'),
                'fqi3_remaining_attempts' => __('Show the number of remaining free quiz attempts with a user-friendly message.', 'form-quizz-fqi3'),
                'fqi3_comparative_statistics' => __('Display comparative statistics between users.', 'form-quizz-fqi3'),
                'free_quiz_form' => __('Renders the quiz form.', 'form-quizz-fqi3'),
                'fqi3_current_user_badges' => __('Display the badges awarded to the currently logged-in user.', 'form-quizz-fqi3'),
                'fqi3_periodic_stats' => __('Display the periodic statistics (weekly and monthly) of a user, including success rates and quiz performance by level.', 'form-quizz-fqi3') . self::get_new_badge()
            ];
        }

        /**
        * Render New badge
        */
        private static function get_new_badge(): string {
            return ' <span class="badge text-bg-warning">' . __('New', 'form-quizz-fqi3') . '</span>';
        }
    
        /**
         * Initialize API endpoint data
        */
        private function init_api_endpoints(): void {
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
                    'User Data:' => [
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
                            'description' => __('This API updates or inserts performance metrics for a specific user at a particular quiz level. The data includes quiz attempts, correct answers, success rates, and best scores for the user, along with the timestamp of the last update.', 'form-quizz-fqi3'),
                            'response_fields' => [
                                'user_id' => __('The unique identifier for the user whose performance is being updated. This is a required parameter.', 'form-quizz-fqi3'),
                                'level' => __('The quiz level (e.g., "beginner", "intermediate", "advanced") for which the performance is being recorded. This is a required parameter.', 'form-quizz-fqi3'),
                                'total_quizzes' => __('The total number of quizzes attempted by the user at the specified level. This is a required parameter.', 'form-quizz-fqi3'),
                                'total_questions_answered' => __('The total number of questions answered by the user at the specified level. This is a required parameter.', 'form-quizz-fqi3'),
                                'total_good_answers' => __('The total number of correct answers provided by the user at the specified level. This is a required parameter.', 'form-quizz-fqi3'),
                                'success_rate' => __('The percentage of correct answers calculated as (total good answers / total questions answered) * 100. This is a required parameter.', 'form-quizz-fqi3'),
                                'best_score' => __('The highest score achieved by the user at the specified level. This is a required parameter.', 'form-quizz-fqi3')
                            ],
                            'example_response' => [
                                [
                                    "user_id" => "1",
                                    "level" => "beginner",
                                    "total_quizzes" => "111",
                                    "total_questions_answered" => "136",
                                    "total_good_answers" => "101",
                                    "success_rate" => "74.26",
                                    "best_score" => "100",
                                ]
                            ]
                        ]
                    ],
                    'Badges:' => [
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
                    ]
            ];
        }
    
        /**
         * Render the admin page
         */
        public function render_infos_page(): void {
            ?>
            <div class="wrap">
                <h2 class="page-title"><?php _e('Changelog and User Guide', 'form-quizz-fqi3'); ?></h2>
                
                <?php
                $this->render_user_guide_section();
                $this->render_api_documentation();
                $this->render_changelog_section();
                ?>
            </div>
            <?php
        }
    
        /**
         * Render the user guide section
         */
        private function render_user_guide_section(): void {
            ?>
            <div id="guide_doc" class="fqi3-section-options">
                <h3><?php _e('User Guide', 'form-quizz-fqi3'); ?></h3>
                <hr>
                <p><?php _e('Here you can find the user guide for the plugin.', 'form-quizz-fqi3'); ?></p>
                
                <h4><?php _e('Available Shortcodes:', 'form-quizz-fqi3'); ?></h4>
                <ul>
                    <?php $this->render_shortcodes_list(); ?>
                </ul>
            </div>
            <?php
        }
    
        /**
         * Render the shortcodes list
         */
        private function render_shortcodes_list(): void {
            foreach ($this->shortcodes as $shortcode => $description) {
                ?>
                <li>
                    <span class="copy-icon" data-shortcode="[<?php echo esc_attr($shortcode); ?>]">
                        <i class="bi bi-copy" title="<?php esc_attr_e('Copy shortcode', 'form-quizz-fqi3'); ?>"></i>
                    </span>
                    <strong>[<?php echo esc_html($shortcode); ?>]</strong>:
                    <?php echo wp_kses_post($description); ?>
                </li>
                <?php
            }
        }

        /**
         * Renders the API documentation section in the admin interface
         */
        private function render_api_documentation(): void {
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

        /**
         * Renders the changelog section in the admin interface
         * 
         * @return void
         */
        private function render_changelog_section(): void {
            ?>
            <div id="changelog_doc" class="fqi3-section-options overflow-y h500">
                <h3><?php _e('Changelog', 'form-quizz-fqi3'); ?></h3>
                <hr>
                <div class="changelog-container">
                    <?php 
                    echo '<div class="changelog-content">';
                    echo self::get_changelog();
                    echo '</div>';
                    ?>
                </div>
            </div>
            <?php
        }
    
        /**
         * Get and parse changelog content
         *
         * @return string Formatted changelog HTML
         */
        public static function get_changelog(): string {
            try {
                $readme_file = FQI3_PATH . '/readme.txt';
                if (!is_readable($readme_file)) {
                    throw new \Exception(__('Changelog file is not readable.', 'form-quizz-fqi3'));
                }
    
                $readme_content = file_get_contents($readme_file);
                if ($readme_content === false) {
                    throw new \Exception(__('Failed to read changelog file.', 'form-quizz-fqi3'));
                }
    
                return self::parse_changelog($readme_content);
            } catch (\Exception $e) {
                return sprintf('<p class="alert alert-danger">%s</p>', esc_html($e->getMessage()));
            }
        }
    
        /**
         * Parse changelog content from readme file
         *
         * @param string $content Raw readme content
         * @return string Formatted changelog HTML
         */
        private static function parse_changelog(string $content): string {
            $changelog_pattern = '/== Changelog ==(.*?)(?=== |$)/s';
            if (!preg_match($changelog_pattern, $content, $matches)) {
                return '<p>' . __('No changelog section found.', 'form-quizz-fqi3') . '</p>';
            }
    
            $changelog_content = $matches[1];
            $version_pattern = '/=\s*(\d+\.\d+\.\d+)\s*=(.*?)(?=\=\s*\d+\.\d+\.\d+\s*\=|$)/s';
            
            preg_match_all($version_pattern, $changelog_content, $matches, PREG_SET_ORDER);
            
            if (empty($matches)) {
                return '<p>' . __('No version entries found.', 'form-quizz-fqi3') . '</p>';
            }
    
            $changelog_html = '';
            $count = 0;
            
            foreach ($matches as $match) {
                if ($count >= 6) break;
                
                $version = esc_html($match[1]);
                $changes = nl2br(esc_html(trim($match[2])));

                $version_label = $count === 0 
                    ? sprintf('Version %s %s', $version, self::get_new_badge())
                    : sprintf('Version %s', $version);
            
                $changelog_html .= sprintf(
                    '<h4 class="changelog-version">%s</h4><div class="changelog-entry">%s</div>',
                    $version_label,
                    $changes
                );
                
                $count++;
            }
    
            return $changelog_html ?: '<p>' . __('No changelog entries found.', 'form-quizz-fqi3') . '</p>';
        }
    }

endif;