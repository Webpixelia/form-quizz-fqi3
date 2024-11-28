<?php
namespace Form_Quizz_FQI3;
/**
* FQI3_Quiz_API Class
*
* This class manages the REST API endpoints for quiz functionalities within the FQI3 plugin.
* It provides several routes to access quizzes based on criteria such as ID and level, allowing
* authorized users to retrieve quiz data securely via an API token. 
*
* @package    Form Quizz FQI3
* @subpackage API System
* @since      1.6.0
* @version    2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Quiz_API' ) ) :

    class FQI3_Quiz_API {
        // Cache storage for frequently accessed data
        private static $options_cache = [];
        private static $badge_settings_cache = null;
        
        // Constants for better maintainability
        private const NAMESPACE = 'fqi3/v1';
        private const TABLE_SUFFIX = [
            'quizzes' => FQI3_TABLE_QUIZZES,
            'awards' => FQI3_TABLE_AWARDS,
            'performances' => FQI3_TABLE_PERFORMANCE
        ];
        private const ERROR_MESSAGES = [
            'token_missing' => 'Missing API token.',
            'token_invalid' => 'Invalid API token.',
            'no_quiz' => 'Quiz not found',
            'no_levels' => 'No quiz levels found',
            'no_levels_specified_type' => 'No quiz levels found for the specified type',
            'no_badges' => 'No badges found',
            'no_settings' => 'No badge settings found',
            'no_performance' => 'Performance not found'
        ];
        /**
         * Initializes the API by registering REST routes.
         * 
         * @since 1.6.0
         */
        public function __construct() {
            add_action('rest_api_init', [$this, 'register_routes']);
            add_action('wp_ajax_fqi3_revoke_token', [$this, 'revoke_token_handler']);
        }

        /**
         * Get table name with prefix
         * 
         * @param string $table_key Key from TABLE_SUFFIX constant
         * @return string Full table name with prefix
         * 
         * @since 2.0.0
         */
        private function get_table_name(string $table_key): string {
            global $wpdb;
            return $wpdb->prefix . self::TABLE_SUFFIX[$table_key];
        }

        /**
         * Verify the provided API token
         * 
         * Compares the provided token against the stored token in WordPress options.
         * 
         * @since 1.6.0
         * @param string $token The token to verify
         * @return boolean True if token is valid, false otherwise
         */
        private function verify_token(string $token): bool {
            if (empty($token)) return false;
    
            if (!isset(self::$options_cache['api_token'])) {
                $options = fqi3_get_options();
                self::$options_cache['api_token'] = $options['fqi3_quiz_api_token'] ?? '';
            }
    
            return !empty(self::$options_cache['api_token']) && 
                   hash_equals(self::$options_cache['api_token'], $token);
        }

        /**
         * Check if the request has a valid token
         * 
         * Validates the API token from the request headers.
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object
         * @return true|WP_Error True if authentication succeeded, WP_Error if it failed
         */
        public function check_token_auth(\WP_REST_Request $request): \WP_Error|bool {
            $auth_header = $request->get_header('X-API-Token');
            
            if (empty($auth_header)) {
                return new \WP_Error(
                    'rest_forbidden',
                    __(self::ERROR_MESSAGES['token_missing'], 'form-quizz-fqi3'),
                    ['status' => 401]
                );
            }
    
            return $this->verify_token($auth_header) ? true : new \WP_Error(
                'rest_forbidden',
                __(self::ERROR_MESSAGES['token_invalid'], 'form-quizz-fqi3'),
                ['status' => 401]
            );
        }

        /**
         * Revoke the API token
         * 
         * Clears the stored API token from the options in the database.
         * 
         * @since 1.6.0
         * @return void
         */
        public function revoke_token_handler(): void {
            // Vérifiez le nonce pour la sécurité
            check_ajax_referer('fqi3_admin_cookies_nonce', 'nonce');

            // Logique pour révoquer le jeton
            $options = fqi3_get_options();
            if (isset($options['fqi3_quiz_api_token'])) {
                unset($options['fqi3_quiz_api_token']); 
        
                update_option('fqi3_options', $options);
            }

            // Retourner une réponse JSON
            wp_send_json_success(__('Token successfully revoked.', 'form-quizz-fqi3'));
        }
    
         /**
         * Register REST API routes
         * 
         * Sets up all the REST API routes for the quiz system.
         * 
         * @since 1.6.0
         */
        public function register_routes() {
            $routes = $this->get_route_config();
            
            foreach ($routes as $endpoint => $config) {
                register_rest_route(self::NAMESPACE, $endpoint, $config);
            }
        }

        /**
         * Centralized route configuration
         */
        private function get_route_config(): array {
            $common_args = [
                'methods' => 'GET',
                'permission_callback' => [$this, 'check_token_auth'],
            ];

            return [
                // Routes for quizzes
                '/quizzes' => $common_args + ['callback' => [$this, 'get_quizzes']],
                '/quizzes/(?P<id>\d+)' => $common_args + ['callback' => [$this, 'get_quiz_by_id']],
                '/quizzes/level/(?P<level_name>[a-zA-Z0-9_-]+)' => $common_args + [
                    'callback' => [$this, 'get_quizzes_by_level']
                ],
                // Routes for users data
                '/user/(?P<user_id>\d+)/badges' => $common_args + ['callback' => [$this, 'get_user_badges']],
                '/user/(?P<user_id>\d+)/performance' => [
                    'methods' => ['GET', 'POST'],
                    'callback' => [$this, 'handle_user_performance'],
                    'permission_callback' => [$this, 'check_token_auth'],
                    'args' => [
                        'user_id' => [
                            'required' => true,
                            'validate_callback' => [$this, 'validate_positive_integer'],
                        ]
                    ]
                ],
                // Routes for badges
                '/badges/settings' => $common_args + ['callback' => [$this, 'get_badge_settings']],
                '/badges/settings/(?P<type>completion-badge|success-rate-badge)' => $common_args + ['callback' => [$this, 'get_badge_settings_by_type']],
                '/badges/all-users' => $common_args + [
                    'callback' => [$this, 'get_all_users_badges'],
                    'args' => [
                        'page' => [
                            'required' => false,
                            'default' => 1,
                            'validate_callback' => [$this, 'validate_positive_integer'],
                        ],
                        'per_page' => [
                            'required' => false,
                            'default' => 10,
                            'validate_callback' => [$this, 'validate_pagination_limit'],
                        ]
                    ]
                ],
                // Routes for quiz levels
                '/levels' => $common_args + ['callback' => [$this, 'get_quiz_levels']],
                '/levels/(?P<name>[a-zA-Z0-9_-]+)' => $common_args + ['callback' => [$this, 'get_quiz_level_by_name']],
                '/levels/type/(?P<type>free|premium)' => $common_args + ['callback' => [$this, 'get_quiz_levels_by_type']]
            ];
        }
    
        /**
         * Validation callback for positive integers
         */
        public function validate_positive_integer($param): bool {
            return is_numeric($param) && intval($param) > 0;
        }

        /**
         * Validation callback for pagination limit
         */
        public function validate_pagination_limit($param): bool {
            return is_numeric($param) && intval($param) > 0 && intval($param) <= 100;
        }

        /**
        * Handle user performance requests (GET and POST)
        */
        public function handle_user_performance(\WP_REST_Request $request) {
            return $request->get_method() === 'GET' 
                ? $this->get_user_performance($request)
                : $this->update_user_performance($request);
        }

        /**
         * Get all quizzes
         * 
         * Retrieves a list of all quizzes from the database.
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response The response object containing all quizzes
         */
        public function get_quizzes(\WP_REST_Request $request) {
            global $wpdb;
            
            try {
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$this->get_table_name('quizzes')} WHERE 1 = %d",
                        1
                    ),
                    ARRAY_A
                );

                if ($wpdb->last_error) {
                    throw new \Exception($wpdb->last_error);
                }

                return new \WP_REST_Response($results ?: [], 200);
            } catch (\Exception $e) {
                return new \WP_Error(
                    'database_error',
                    __('Database error occurred', 'form-quizz-fqi3'),
                    ['status' => 500]
                );
            }
        }
    
        /**
         * Get quiz by ID
         * 
         * Retrieves a specific quiz by its ID.
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object containing the quiz ID
         * @return WP_REST_Response|WP_Error The quiz data or error if not found
         */
        public function get_quiz_by_id(\WP_REST_Request $request) {
            global $wpdb;
        
            $id = (int) $request['id'];
            $cache_key = 'quiz_' . $id;
        
            try {
                // Vérifier d'abord dans le cache
                $quiz = wp_cache_get($cache_key, 'quiz_cache');
                
                // Si le cache est vide, récupérer le quiz depuis la base de données
                if (false === $quiz) {
                    $quiz = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$this->get_table_name('quizzes')} WHERE id = %d",
                            $id
                        ),
                        ARRAY_A
                    );
        
                    // Si le quiz est trouvé, le mettre en cache pour une heure
                    if ($quiz) {
                        wp_cache_set($cache_key, $quiz, 'quiz_cache', 3600);
                    }
                }
        
                // Vérifier si le quiz a bien été trouvé et retourner une réponse
                if ($quiz) {
                    return new \WP_REST_Response($quiz, 200);
                } else {
                    throw new \Exception(self::ERROR_MESSAGES['no_quiz']);
                }
                
            } catch (\Exception $e) {
                // Gérer les erreurs en retournant une WP_Error
                return new \WP_Error(
                    'no_quiz',
                    __('Failed to retrieve quiz: ', 'form-quizz-fqi3') . $e->getMessage(),
                    ['status' => 500]
                );
            }
        }

        /**
         * Get quizzes by level
         * 
         * Retrieves all quizzes matching a specific difficulty level.
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object containing the level
         * @return WP_REST_Response|WP_Error The matching quizzes or error if none found
         */
        public function get_quizzes_by_level(\WP_REST_Request $request) {
            global $wpdb;
        
            try {
                $level = sanitize_text_field($request['level_name']);
                $cache_key = 'quizzes_level_' . md5($level);
        
                // Essayer de récupérer depuis le cache d'abord
                $quizzes = wp_cache_get($cache_key, 'quiz_cache');
                if (false === $quizzes) {
                    $quizzes = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$this->get_table_name('quizzes')} WHERE niveau = %s",
                            $level
                        ),
                        ARRAY_A
                    );
        
                    // Mettre en cache les résultats si des quiz sont trouvés
                    if (!empty($quizzes)) {
                        wp_cache_set($cache_key, $quizzes, 'quiz_cache', 3600);
                    }
                }
        
                // Vérification et réponse
                if (!empty($quizzes)) {
                    return new \WP_REST_Response($quizzes, 200);
                }
        
                return new \WP_Error(
                    'no_quizzes', 
                    __(self::ERROR_MESSAGES['no_levels'], 'form-quizz-fqi3'), 
                    ['status' => 404]
                );            
                
            } catch (\Exception $e) {
                return new \WP_Error(
                    'database_error',
                    __('Failed to retrieve quizzes due to a database error', 'form-quizz-fqi3'),
                    ['status' => 500, 'message' => $e->getMessage()]
                );
            }
        }

        /**
         * Get user badges
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response|WP_Error
         */
        public function get_user_badges(\WP_REST_Request $request) {
            global $wpdb;

            try {
                $user_id = (int) $request->get_param('user_id');
                $cache_key = 'user_badges_' . $user_id;

                // Try to get from cache first
                $badges = wp_cache_get($cache_key, 'badge_cache');
                if (false === $badges) {
                    $badges = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$this->get_table_name('awards')} WHERE user_id = %d",
                            $user_id
                        ),
                        ARRAY_A
                    );

                    if (!empty($badges)) {
                        wp_cache_set($cache_key, $badges, 'badge_cache', 3600);
                    }
                }

                if (!empty($badges)) {
                    return new \WP_REST_Response($badges, 200);
                }

                return new \WP_Error(
                    'no_badge',
                    __(self::ERROR_MESSAGES['no_badges'], 'form-quizz-fqi3'), 
                    ['status' => 404]
                );

            } catch (\Exception $e) {
                // Handle the exception and return a generic error response
                return new \WP_Error(
                    'database_error',
                    __('An error occurred while retrieving user badges.', 'form-quizz-fqi3'),
                    ['status' => 500, 'details' => $e->getMessage()]
                );
            }
        }

        /**
         * Get user performance with optimized query and caching.
         *
         * @since 1.6.0
         * @param WP_REST_Request $request The request object containing user ID.
         * @return WP_REST_Response JSON response containing user performance data.
         */
        public function get_user_performance(\WP_REST_Request $request) {
            global $wpdb;
            
            try {
                $user_id = absint($request->get_param('user_id'));
                $cache_key = "fqi3_user_performance_{$user_id}";
                
                // Try to get from cache first
                $performances = wp_cache_get($cache_key);
                
                if ($performances === false) {
                    $performances = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT 
                                level,
                                total_quizzes,
                                total_questions_answered,
                                total_good_answers,
                                success_rate,
                                best_score,
                                last_updated
                            FROM {$this->get_table_name('performances')} 
                            WHERE user_id = %d",
                            $user_id
                        ),
                        ARRAY_A
                    );

                    if ($wpdb->last_error) {
                        throw new \Exception($wpdb->last_error);
                    }

                    // Cache the results for 5 minutes
                    wp_cache_set($cache_key, $performances, '', 300);
                }

                if (empty($performances)) {
                    return new \WP_Error(
                        'no_performance',
                        __(self::ERROR_MESSAGES['no_performance'], 'form-quizz-fqi3'),
                        ['status' => 404]
                    );
                }

                // Format the response data
                $formatted_performance = array_map(function($performance) {
                    return [
                        'level' => $performance['level'],
                        'stats' => [
                            'total_quizzes' => intval($performance['total_quizzes']),
                            'total_questions' => intval($performance['total_questions_answered']),
                            'correct_answers' => intval($performance['total_good_answers']),
                            'success_rate' => floatval($performance['success_rate']),
                            'best_score' => floatval($performance['best_score'])
                        ],
                        'last_updated' => $performance['last_updated']
                    ];
                }, $performances);

                return new \WP_REST_Response($formatted_performance, 200);

            } catch (\Exception $e) {
                return new \WP_Error(
                    'database_error',
                    __('Failed to retrieve performance data', 'form-quizz-fqi3'),
                    ['status' => 500]
                );
            }
        }

        /**
         * Update the user performance data by user ID.
         *
         * @since 1.6.0
         */
        public function update_user_performance(\WP_REST_Request $request) {
            global $wpdb;
            
            try {
                $user_id = absint($request->get_param('user_id'));
                $level = sanitize_text_field($request->get_param('level'));
                $table_name = $this->get_table_name('performances');
                
                $data = $this->sanitize_performance_data($request);
                $where = ['user_id' => $user_id, 'level' => $level];
                
                $result = $wpdb->update($table_name, $data, $where);
                
                if ($result === false) {
                    throw new \Exception($wpdb->last_error);
                }
                
                if ($result === 0) {
                    $data = array_merge($data, $where);
                    $result = $wpdb->insert($table_name, $data);
                    
                    if ($result === false) {
                        throw new \Exception($wpdb->last_error);
                    }
                }
                
                return new \WP_REST_Response([
                    'message' => __('Performance updated successfully', 'form-quizz-fqi3')
                ], 200);
                
            } catch (\Exception $e) {
                return new \WP_Error(
                    'update_failed',
                    __('Failed to update performance', 'form-quizz-fqi3'),
                    ['status' => 500]
                );
            }
        }

        /**
         * Helper method to sanitize performance data
         */
        private function sanitize_performance_data(\WP_REST_Request $request): array {
            return [
                'total_quizzes' => absint($request->get_param('total_quizzes')),
                'total_questions_answered' => absint($request->get_param('total_questions_answered')),
                'total_good_answers' => absint($request->get_param('total_good_answers')),
                'success_rate' => floatval($request->get_param('success_rate')),
                'best_score' => floatval($request->get_param('best_score')),
                'last_updated' => current_time('mysql')
            ];
        }
        
        /**
         * Get all badge settings
         * 
         * Retrieves all badge-related settings from wp_options.
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response The response object containing badge settings
         */
        public function get_badge_settings(\WP_REST_Request $request) {
            if (self::$badge_settings_cache === null) {
                self::$badge_settings_cache = $this->load_badge_settings();
            }
            
            return empty(self::$badge_settings_cache)
                ? new \WP_Error('no_settings', self::ERROR_MESSAGES['no_settings'], ['status' => 404])
                : new \WP_REST_Response(self::$badge_settings_cache, 200);
        }

        /**
         * Get badge settings by type
         * 
         * Retrieves badge settings for a specific type (quizzes completed or success rate).
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response The response object containing specific badge settings
         */
        public function get_badge_settings_by_type(\WP_REST_Request $request) {
            $type = $request['type'];
            $settings = $this->get_type_specific_settings($type);
            
            if (empty($settings)) {
                return new \WP_Error(
                    'invalid_type',
                    __('Invalid badge type specified', 'form-quizz-fqi3'),
                    ['status' => 400]
                );
            }
            
            return new \WP_REST_Response($settings, 200);
        }

        /**
         * Get badges for all users
         * 
         * Retrieves all badges awarded to users with pagination support.
         * 
         * @since 1.6.0
         * @param \WP_REST_Request $request
         * @return WP_REST_Response|WP_Error
         */
        public function get_all_users_badges(\WP_REST_Request $request) {
            global $wpdb;
            
            $pagination = $this->get_pagination_params($request);
            $badges = $this->fetch_paginated_badges($wpdb, $pagination);
            
            if (empty($badges)) {
                return new \WP_Error('no_badges', self::ERROR_MESSAGES['no_badges'], ['status' => 404]);
            }
            
            $formatted_badges = $this->format_badges_with_details($badges);
            $response = new \WP_REST_Response($formatted_badges, 200);
            
            $this->add_pagination_headers($response, $pagination);
            return $response;
        }
        

        /**
         * Get all quiz levels
         * 
         * Retrieves all quiz level settings from wp_options.
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response The response object containing all quiz levels
         */
        public function get_quiz_levels(\WP_REST_Request $request) {
            $levels = $this->format_quiz_levels(get_option('fqi3_quiz_levels', []));
            
            return empty($levels)
                ? new \WP_Error(
                    'no_levels', 
                    self::ERROR_MESSAGES['no_levels'], 
                    ['status' => 404]
                )
                : new \WP_REST_Response($levels, 200);
        }

        /**
         * Get quiz level by name
         * 
         * Retrieves a specific quiz level by its name.
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response|WP_Error The level data or error if not found
         */
        public function get_quiz_level_by_name(\WP_REST_Request $request) {
            $target_name = $request['name'];
            $level = $this->find_quiz_level_by_name($target_name);
            
            return $level === null
                ? new \WP_Error(
                    'level_not_found',
                    __(self::ERROR_MESSAGES['no_levels'], 'form-quizz-fqi3'),
                    ['status' => 404]
                )
                : new \WP_REST_Response($level, 200);
        }

        /**
         * Get quiz levels by type (free or premium)
         * 
         * Retrieves all quiz levels of a specific type.
         * 
         * @since 1.6.0
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response|WP_Error The matching levels or error if none found
         */
        public function get_quiz_levels_by_type(\WP_REST_Request $request) {
            $type = $request['type'];
            $levels = $this->filter_quiz_levels_by_type($type);
            
            return empty($levels)
                ? new \WP_Error(
                    'level_not_found',
                    __(self::ERROR_MESSAGES['no_levels_specified_type'], 'form-quizz-fqi3'),
                    ['status' => 404]
                )
                : new \WP_REST_Response($levels, 200);
        }

         // Private helper methods
        /**
         * Load and format badge settings
         *
         * @return array
         */
        private function load_badge_settings(): array {
            $options = get_option('fqi3_badges', []);
            
            return [
                'enabled' => !isset($options['fqi3_disable_badges']) || !$options['fqi3_disable_badges'],
                'show_legend' => !isset($options['fqi3_disable_badges_legend']) || !$options['fqi3_disable_badges_legend'],
                'min_quizzes_for_success_rate' => $options['fqi3_min_quizzes_for_success_rate'] ?? 20,
                'quizzes_completed' => $this->format_badge_type_settings($options, 'quizzes_completed'),
                'success_rate' => $this->format_badge_type_settings($options, 'success_rate'),
            ];
        }

        /**
         * Format badge type settings
         *
         * @param array $options
         * @param string $type
         * @return array
         */
        private function format_badge_type_settings(array $options, string $type): array {
            return [
                'thresholds' => $options["fqi3_{$type}_thresholds"] ?? [],
                'badge_names' => $options["fqi3_{$type}_badge_names"] ?? [],
                'badge_images' => $options["fqi3_{$type}_badge_images"] ?? [],
            ];
        }

        /**
         * Get type specific badge settings
         *
         * @param string $type
         * @return array
         */
        private function get_type_specific_settings(string $type): array {
            if (self::$badge_settings_cache === null) {
                self::$badge_settings_cache = $this->load_badge_settings();
            }

            $settings = [];
            switch ($type) {
                case 'completion-badge':
                    $settings = self::$badge_settings_cache['quizzes_completed'];
                    break;
                case 'success-rate-badge':
                    $settings = self::$badge_settings_cache['success_rate'];
                    $settings['min_quizzes_required'] = self::$badge_settings_cache['min_quizzes_for_success_rate'];
                    break;
            }
            
            return $settings;
        }

        /**
         * Get pagination parameters
         *
         * @param WP_REST_Request $request
         * @return array
         */
        private function get_pagination_params(\WP_REST_Request $request): array {
            $page = max(1, intval($request->get_param('page') ?? 1));
            $per_page = max(1, intval($request->get_param('per_page') ?? 10));
            
            return [
                'page' => $page,
                'per_page' => $per_page,
                'offset' => ($page - 1) * $per_page
            ];
        }

        /**
         * Fetch paginated badges
         *
         * @param wpdb $wpdb
         * @param array $pagination
         * @return array
         */
        private function fetch_paginated_badges($wpdb, array $pagination): array {        
            return $wpdb->get_results($wpdb->prepare("
                SELECT 
                    a.*,
                    u.display_name,
                    u.user_email
                FROM {$this->get_table_name('awards')} a
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                ORDER BY a.awarded_at DESC
                LIMIT %d OFFSET %d
            ", $pagination['per_page'], $pagination['offset']), ARRAY_A);
        }

        /**
         * Format badges with details
         *
         * @param array $badges
         * @return array
         */
        private function format_badges_with_details(array $badges): array {
            $badge_settings = get_option('fqi3_badges', []);
            
            return array_map(function($badge) use ($badge_settings) {
                $badge_id = intval($badge['badge_id']);
                $badge_details = $this->find_badge_details($badge_id, $badge_settings);
                
                return [
                    'id' => $badge['id'],
                    'user' => [
                        'id' => $badge['user_id'],
                        'name' => $badge['display_name'],
                        'email' => $badge['user_email']
                    ],
                    'badge' => [
                        'type' => $badge_details ? $badge_details['type'] : 'unknown',
                        'name' => $badge_details ? $badge_details['name'] : $badge['badge_name'],
                        'threshold' => $badge_details ? $badge_details['threshold'] : null,
                        'image_id' => $badge_details ? $badge_details['image_id'] : null,
                        'image_url' => $badge_details && $badge_details['image_id'] ? 
                            wp_get_attachment_url($badge_details['image_id']) : null
                    ],
                    'date_earned' => $badge['awarded_at'],
                    'achievement_value' => $badge_id
                ];
            }, $badges);
        }

        /**
         * Find badge details
         *
         * @param int $badge_id
         * @param array $badge_settings
         * @return array|null
         */
        private function find_badge_details(int $badge_id, array $badge_settings): ?array {
            // Check completion badges
            foreach ($badge_settings['fqi3_quizzes_completed_badge_images'] as $index => $image_id) {
                if ($badge_id == $image_id) {
                    return [
                        'type' => 'completion-badge',
                        'name' => $badge_settings['fqi3_quizzes_completed_badge_names'][$index] ?? 'Unknown',
                        'threshold' => $badge_settings['fqi3_quizzes_completed_thresholds'][$index] ?? null,
                        'image_id' => $image_id
                    ];
                }
            }
            
            // Check success rate badges
            foreach ($badge_settings['fqi3_success_rate_badge_images'] as $index => $image_id) {
                if ($badge_id == $image_id) {
                    return [
                        'type' => 'success-rate-badge',
                        'name' => $badge_settings['fqi3_success_rate_badge_names'][$index] ?? 'Unknown',
                        'threshold' => $badge_settings['fqi3_success_rate_thresholds'][$index] ?? null,
                        'image_id' => $image_id
                    ];
                }
            }
            
            return null;
        }

        /**
         * Add pagination headers
         *
         * @param WP_REST_Response $response
         * @param array $pagination
         */
        private function add_pagination_headers(\WP_REST_Response $response, array $pagination): void {
            global $wpdb;
            
            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->get_table_name('awards')}");
            $total_pages = ceil($total_items / $pagination['per_page']);
            
            $response->header('X-WP-Total', $total_items);
            $response->header('X-WP-TotalPages', $total_pages);
        }

        /**
         * Format quiz levels
         *
         * @param array $options
         * @return array
         */
        private function format_quiz_levels(array $options): array {
            if (empty($options)) {
                return [];
            }

            $formatted_levels = [];
            $names = $options['fqi3_quiz_levels_name'] ?? [];
            $labels = $options['fqi3_quiz_levels_label'] ?? [];
            $is_free = $options['fqi3_quiz_levels_is_free'] ?? [];

            foreach ($names as $index => $name) {
                $formatted_levels[] = [
                    'name' => $name,
                    'label' => $labels[$index] ?? '',
                    'is_free' => (bool)($is_free[$index] ?? false)
                ];
            }

            return $formatted_levels;
        }

        /**
         * Find quiz level by name
         *
         * @param string $target_name
         * @return array|null
         */
        private function find_quiz_level_by_name(string $target_name): ?array {
            $options = get_option('fqi3_quiz_levels', []);
            if (empty($options)) {
                return null;
            }

            $names = $options['fqi3_quiz_levels_name'] ?? [];
            $index = array_search($target_name, $names);

            if ($index === false) {
                return null;
            }

            return [
                'name' => $names[$index],
                'label' => ($options['fqi3_quiz_levels_label'][$index] ?? ''),
                'is_free' => (bool)($options['fqi3_quiz_levels_is_free'][$index] ?? false)
            ];
        }

        /**
         * Filter quiz levels by type
         *
         * @param string $type
         * @return array
         */
        private function filter_quiz_levels_by_type(string $type): array {
            $options = get_option('fqi3_quiz_levels', []);
            if (empty($options)) {
                return [];
            }

            $names = $options['fqi3_quiz_levels_name'] ?? [];
            $labels = $options['fqi3_quiz_levels_label'] ?? [];
            $is_free = $options['fqi3_quiz_levels_is_free'] ?? [];

            $filtered_levels = [];
            foreach ($names as $index => $name) {
                $level_is_free = (bool)($is_free[$index] ?? false);
                if (($type === 'free' && $level_is_free) || ($type === 'premium' && !$level_is_free)) {
                    $filtered_levels[] = [
                        'name' => $name,
                        'label' => $labels[$index] ?? '',
                        'is_free' => $level_is_free
                    ];
                }
            }

            return $filtered_levels;
        }
    }
endif;