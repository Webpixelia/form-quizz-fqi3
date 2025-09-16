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
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( !class_exists( 'FQI3_Quiz_API' ) ) :

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
        
        // Cache TTL constants
        private const CACHE_TTL = [
            'quiz' => 3600,        // 1 heure
            'quizzes' => 3600,     // 1 heure  
            'levels' => 86400,     // 24 heures
            'badges' => 3600,      // 1 heure
            'performance' => 300,  // 5 minutes
            'settings' => 7200     // 2 heures
        ];
        
        private const CACHE_VERSION = 'v1';
        private FQI3_API_Logger $logger;

        /**
         * Initializes the API by registering REST routes.
         * 
         * @since 1.6.0
         * @version 3.0.0 Logger added
         */
        public function __construct() {
            add_action('rest_api_init', [$this, 'register_routes']);
            $options = fqi3_get_options();
            $level = $options['fqi3_api_logger'] ?? 'off';
            $this->logger = new FQI3_API_Logger($level);
            add_action('wp_ajax_fqi3_revoke_token', [$this, 'revoke_token_handler']);
            // CORS headers for REST responses and handle OPTIONS
            add_filter('rest_pre_serve_request', [$this, 'send_cors_headers'], 0, 4);
        }

        /**
         * Get table name with prefix
         * 
         * @param string $table_key Key from TABLE_SUFFIX constant
         * @return string Full table name with prefix
         * 
         * @since 2.0.0
         */
        protected function get_table_name(string $table_key): string {
            global $wpdb;
            return $wpdb->prefix . self::TABLE_SUFFIX[$table_key];
        }

        /**
         * Centralized cache management method
         * 
         * @param string $key Cache key
         * @param string $group Cache group
         * @param callable $callback Function to execute if cache miss
         * @param int|null $ttl Time to live override
         * @return array ['data' => mixed, 'cache_hit' => bool]
         * 
         * @since 3.0.0
         */
        private function get_cached_data(string $key, string $group, callable $callback, ?int $ttl = null): array {
            $cached = wp_cache_get($key, $group);
            if ($cached !== false) {
                return ['data' => $cached, 'cache_hit' => true];
            }
            
            try {
                $data = $callback();
                if ($data !== null) {
                    $cache_ttl = $ttl ?? self::CACHE_TTL[$group] ?? 3600;
                    wp_cache_set($key, $data, $group, $cache_ttl);
                }
                return ['data' => $data, 'cache_hit' => false];
            } catch (\Exception $e) {
                return ['data' => null, 'cache_hit' => false];
            }
        }

        /**
         * Generate consistent cache key
         * 
         * @param string $prefix Cache key prefix
         * @param mixed ...$params Parameters to include in key
         * @return string Cache key
         * 
         * @since 3.0.0
         */
        private function generate_cache_key(string $prefix, ...$params): string {
            return 'fqi3_' . $prefix . '_' . md5(serialize($params)) . '_' . self::CACHE_VERSION;
        }

        /**
         * Create REST response with cache headers
         * 
         * @param mixed $data Response data
         * @param bool $cache_hit Whether data came from cache
         * @param string $cache_type Type of cache for TTL lookup
         * @param int $status HTTP status code
         * @return WP_REST_Response
         * 
         * @since 3.0.0
         */
        private function create_cached_response($data, bool $cache_hit, string $cache_type, int $status = 200): \WP_REST_Response {
            $ttl = self::CACHE_TTL[$cache_type] ?? 3600;
            
            return new \WP_REST_Response($data, $status, [
                'Cache-Control' => 'max-age=' . $ttl,
                'X-FQI3-Cache' => $cache_hit ? 'HIT' : 'MISS'
            ]);
        }

        /**
         * Invalidate cache for a specific group
         * 
         * @param string $group Cache group to invalidate
         * @return void
         * 
         * @since 3.0.0
         */
        private function invalidate_cache_group(string $group): void {
            wp_cache_flush_group($group);
        }

        /**
         * Invalidate specific cache key
         * 
         * @param string $key Cache key
         * @param string $group Cache group
         * @return void
         * 
         * @since 3.0.0
         */
        private function invalidate_cache_key(string $key, string $group): void {
            wp_cache_delete($key, $group);
        }

        /**
         * Verify the provided API token
         * 
         * Decrypts the stored token and compares it with the provided token.
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
                // cache expiration-related settings
                self::$options_cache['api_token_created_at'] = isset($options['fqi3_quiz_api_token_created_at']) ? intval($options['fqi3_quiz_api_token_created_at']) : 0;
                self::$options_cache['api_token_exp_mode'] = $options['fqi3_quiz_api_token_exp_mode'] ?? 'never';
                self::$options_cache['api_token_exp_days'] = isset($options['fqi3_quiz_api_token_exp_days']) ? intval($options['fqi3_quiz_api_token_exp_days']) : 0;
                self::$options_cache['api_token_overlap_days'] = isset($options['fqi3_quiz_api_token_overlap_days']) ? intval($options['fqi3_quiz_api_token_overlap_days']) : 0;
            }

            if (empty(self::$options_cache['api_token'])) return false;

            try {
                $token_manager = new TokenManager();
                $stored_token = $token_manager->decrypt_token(self::$options_cache['api_token']);
                
                // First, constant-time compare token value
                if (empty($stored_token) || !hash_equals($stored_token, $token)) {
                    return false;
                }

                // Then, check expiration policy if applicable
                $mode = self::$options_cache['api_token_exp_mode'] ?? 'never';
                if ($mode === 'days') {
                    $created_at = self::$options_cache['api_token_created_at'] ?? 0;
                    // Backward compatibility: if timestamp missing, accept token
                    if ($created_at > 0) {
                        $days = max(0, intval(self::$options_cache['api_token_exp_days'] ?? 0));
                        $overlap = max(0, intval(self::$options_cache['api_token_overlap_days'] ?? 0));
                        $now = time();
                        $lifetime = ($days + $overlap) * DAY_IN_SECONDS;
                        if ($days > 0 && $lifetime > 0 && ($created_at + $lifetime) < $now) {
                            return false; // expired including grace period
                        }
                    }
                }

                return true;
            } catch (\Exception $e) {
                // Log error if needed
                return false;
            }
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
            // HTTPS enforcement
            $options = function_exists('fqi3_get_options') ? (array) fqi3_get_options() : (array) get_option('fqi3_options', []);
            $https_only = !empty($options['fqi3_api_https_only']);
            if ($https_only && !is_ssl()) {
                return new \WP_Error('rest_forbidden', __('HTTPS is required for this endpoint.', 'form-quizz-fqi3'), ['status' => 403]);
            }

            // Basic IP rate limiting before token validation
            if (!$this->rate_limit_check($request)) {
                return new \WP_Error('rest_rate_limited', __('Too many requests', 'form-quizz-fqi3'), ['status' => 429]);
            }

            $auth_header = $request->get_header('X-API-Token');
            if (empty($auth_header)) {
                return new \WP_Error(
                    'rest_forbidden',
                    __(self::ERROR_MESSAGES['token_missing'], 'form-quizz-fqi3'),
                    ['status' => 401]
                );
            }

            $valid = $this->verify_token($auth_header);
            if (!$valid) {
                return new \WP_Error(
                    'rest_forbidden',
                    __(self::ERROR_MESSAGES['token_invalid'], 'form-quizz-fqi3'),
                    ['status' => 401]
                );
            }

            // Token-based rate limiting after successful token validation
            if (!$this->rate_limit_check($request, $auth_header)) {
                return new \WP_Error('rest_rate_limited', __('Too many requests', 'form-quizz-fqi3'), ['status' => 429]);
            }

            return true;
        }

        /**
         * Check if the current token has the required scope
         * 
         * @param string $required_scope The scope to check
         * @return bool True if scope is available
         * 
         * @since 3.0.0
         */
        private function has_scope(string $required_scope): bool {
            $options = function_exists('fqi3_get_options') ? (array) fqi3_get_options() : (array) get_option('fqi3_options', []);
            $allowed_scopes = isset($options['fqi3_api_scopes_allowed']) && is_array($options['fqi3_api_scopes_allowed']) 
                ? $options['fqi3_api_scopes_allowed'] 
                : ['quizzes','stats','badges','settings'];
            
            return in_array($required_scope, $allowed_scopes, true);
        }

        /**
         * Fetch rate limit settings with sane defaults.
         * 
         * @return array The rate limit settings
         * 
         * @since 3.0.0
         */
        private function get_rate_limit_settings(): array {
            if (!isset(self::$options_cache['rate'])) {
                $options = function_exists('fqi3_get_options') ? (array) fqi3_get_options() : (array) get_option('fqi3_options', []);
                self::$options_cache['rate'] = [
                    'ip'     => isset($options['fqi3_api_rate_ip']) ? max(0, intval($options['fqi3_api_rate_ip'])) : 0,
                    'token'  => isset($options['fqi3_api_rate_token']) ? max(0, intval($options['fqi3_api_rate_token'])) : 0,
                    'window' => isset($options['fqi3_api_rate_window']) ? max(1, intval($options['fqi3_api_rate_window'])) : 60,
                ];
            }
            return self::$options_cache['rate'];
        }

        /**
         * Resolve client IP from request/headers.
         * 
         * @param \WP_REST_Request $request The incoming request object
         * @return string The client IP address
         * 
         * @since 3.0.0
         */
        private function get_client_ip(\WP_REST_Request $request): string {
            $xff = $request->get_header('x-forwarded-for');
            if (!empty($xff)) {
                $parts = array_map('trim', explode(',', $xff));
                if (!empty($parts[0])) return $parts[0];
            }
            $ip = $request->get_header('x-real-ip');
            if (!empty($ip)) return $ip;
            return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        }

        /**
         * Sliding window rate limiter using WP transients.
         * 
         * When $token is null, checks IP limit only (used pre-auth).
         * When $token is provided, checks token limit only (used post-auth).
         * 
         * @param \WP_REST_Request $request The incoming request object
         * @param ?string $token The API token to check (null for IP check)
         * @return bool True if rate limit is not exceeded, false otherwise
         * 
         * @since 3.0.0
         */
        private function rate_limit_check(\WP_REST_Request $request, ?string $token = null): bool {
            $settings = $this->get_rate_limit_settings();
            $window   = (int) $settings['window'];
            $now      = time();

            // Helper closure to consume a slot
            $consume = function (string $key, int $limit) use ($window, $now): bool {
                if ($limit <= 0) return true; // disabled
                $bucket = get_transient($key);
                if (!is_array($bucket)) { $bucket = []; }
                // purge old timestamps
                $cut = $now - $window;
                $bucket = array_values(array_filter($bucket, static function ($ts) use ($cut) {
                    return is_int($ts) && $ts > $cut;
                }));
                if (count($bucket) >= $limit) {
                    return false; // over limit
                }
                $bucket[] = $now;
                // Set transient to expire at window to auto-clean
                set_transient($key, $bucket, $window);
                return true;
            };

            if ($token === null) {
                // Pre-auth: IP rate limit only
                $ip      = $this->get_client_ip($request);
                $ip_key  = 'fqi3_rate_ip_' . md5($ip);
                return $consume($ip_key, (int) $settings['ip']);
            }

            // Post-auth: token rate limit only
            $tok_key = 'fqi3_rate_token_' . md5($token);
            return $consume($tok_key, (int) $settings['token']);
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
                if (isset($options['fqi3_quiz_api_token_created_at'])) {
                    unset($options['fqi3_quiz_api_token_created_at']);
                }

                update_option('fqi3_options', $options);
                
                // Invalider les caches liés aux tokens
                self::$options_cache = [];
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
         * Send CORS headers for REST responses and handle preflight.
         * Hooked to rest_pre_serve_request.
         *
         * @param bool               $served  Whether the request has already been served.
         * @param \WP_HTTP_Response  $result  Result to send to the client. Usually a \WP_REST_Response.
         * @param \WP_REST_Request   $request Request used to generate the response.
         * @param \WP_REST_Server    $server  Server instance.
         * @return bool True to indicate the request has been served (for OPTIONS), otherwise the original $served.
         */
        public function send_cors_headers($served, $result, $request, $server) {
            // Read settings
            $options = function_exists('fqi3_get_options') ? (array) fqi3_get_options() : (array) get_option('fqi3_options', []);
            $origins_raw = isset($options['fqi3_api_cors_origins']) ? (string) $options['fqi3_api_cors_origins'] : '';
            $methods     = isset($options['fqi3_api_cors_methods']) ? (string) $options['fqi3_api_cors_methods'] : 'GET, POST, OPTIONS';
            $headers     = isset($options['fqi3_api_cors_headers']) ? (string) $options['fqi3_api_cors_headers'] : 'Content-Type, X-API-Token';

            // If origins empty => CORS disabled
            $origins = array_filter(array_map(static function ($o) {
                return trim($o);
            }, explode(',', $origins_raw)));

            // Always vary on Origin
            header('Vary: Origin', false);

            $req_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
            $origin_hdr = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';

            $allow_origin = '';
            if (!empty($origins)) {
                if (in_array('*', $origins, true)) {
                    $allow_origin = '*';
                } elseif (!empty($origin_hdr) && in_array($origin_hdr, $origins, true)) {
                    $allow_origin = $origin_hdr;
                }
            }

            if ($req_method === 'OPTIONS') {
                if ($allow_origin !== '') {
                    header('Access-Control-Allow-Origin: ' . $allow_origin);
                    header('Access-Control-Allow-Methods: ' . $methods);
                    header('Access-Control-Allow-Headers: ' . $headers);
                    header('Access-Control-Max-Age: 600');
                }
                // Preflight: short-circuit with 204 No Content
                if (!headers_sent()) {
                    status_header(204);
                }
                return true;
            }

            // Non-OPTIONS: send CORS headers for allowed origins
            if ($allow_origin !== '') {
                header('Access-Control-Allow-Origin: ' . $allow_origin);
                // Optionally expose token header if needed
                // header('Access-Control-Expose-Headers: X-API-Token');
            }

            return $served;
        }

        /**
         * Decorator to add logging to a callback
         * 
         * @param callable $callback
         * @return callable
         * 
         * @since 3.0.0
         */
        private function with_logging(callable $callback): callable {
            return function($request) use ($callback) {
                $start_time = microtime(true);
        
                $response = call_user_func($callback, $request);
        
                if ($this->logger) {
                    $duration = microtime(true) - $start_time;
                    if ($response instanceof \WP_REST_Response) {
                        $this->logger->log_request($request, $response, $duration);
                    }
                }
        
                return $response;
            };
        }        

        /**
         * Centralized route configuration
         * 
         * @since 1.6.0
         * @version 3.0.0 Settings added
         * 
         * @return array
         */
        private function get_route_config(): array {
            $get_perm  = $this->make_permission_callback('quizzes');
            $stat_perm = $this->make_permission_callback('stats');
            $badge_perm= $this->make_permission_callback('badges');
            $set_perm  = $this->make_permission_callback('settings');

            return [
                // Ping route (no specific scope required; validates token + returns scopes)
                '/ping' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'ping'],
                    'permission_callback' => [$this, 'check_token_auth'],
                ],
                // Routes for quizzes
                '/quizzes' => [
                    'methods' => 'GET',
                    'callback' => $this->with_logging([$this, 'get_quizzes']),
                    'permission_callback' => $get_perm,
                ],
                '/quizzes/(?P<id>\d+)' => [
                    'methods' => 'GET',
                    'callback' => $this->with_logging([$this, 'get_quiz_by_id']),
                    'permission_callback' => $get_perm,
                ],
                '/quizzes/level/(?P<level_name>[a-zA-Z0-9_-]+)' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_quizzes_by_level'],
                    'permission_callback' => $get_perm,
                ],
                // Routes for users data
                '/user/(?P<user_id>\d+)/badges' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_user_badges'],
                    'permission_callback' => $badge_perm,
                ],
                '/user/(?P<user_id>\d+)/performance' => [
                    'methods' => ['GET', 'POST'],
                    'callback' => [$this, 'handle_user_performance'],
                    'permission_callback' => $stat_perm,
                    'args' => [
                        'user_id' => [
                            'required' => true,
                            'validate_callback' => [$this, 'validate_positive_integer'],
                        ]
                    ]
                ],
                // Routes for badges
                '/badges/settings' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_badge_settings'],
                    'permission_callback' => $badge_perm,
                ],
                '/badges/settings/(?P<type>correct-answers-badge|completion-badge|success-rate-badge)' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_badge_settings_by_type'],
                    'permission_callback' => $badge_perm,
                ],
                '/badges/all-users' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_all_users_badges'],
                    'permission_callback' => $badge_perm,
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
                // Read-only core settings exposure (general, emails, tools)
                '/settings' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_all_settings_readonly'],
                    'permission_callback' => $set_perm,
                ],
                '/settings/(?P<group>general|emails|tools|badges)' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_settings_group_readonly'],
                    'permission_callback' => $set_perm,
                ],
                // Routes for quiz levels
                '/levels' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_quiz_levels'],
                    'permission_callback' => $get_perm,
                ],
                '/levels/(?P<name>[a-zA-Z0-9_-]+)' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_quiz_level_by_name'],
                    'permission_callback' => $get_perm,
                ],
                '/levels/type/(?P<type>free|premium)' => [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_quiz_levels_by_type'],
                    'permission_callback' => $get_perm,
                ]
            ];
        }

        /**
         * Make a permission callback that checks token, rate limits, HTTPS and scope.
         */
        private function make_permission_callback(string $required_scope): \Closure {
            return function (\WP_REST_Request $request) use ($required_scope) {
                $auth = $this->check_token_auth($request);
                if ($auth !== true) return $auth;
                if (!$this->has_scope($required_scope)) {
                    return new \WP_Error('rest_forbidden', __('Insufficient scope for this endpoint.', 'form-quizz-fqi3'), ['status' => 403]);
                }
                return true;
            };
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
         * @version 3.0.0 Caching added
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response The response object containing all quizzes
         */
        public function get_quizzes(\WP_REST_Request $request) {
            $cache_key = $this->generate_cache_key('all_quizzes');
            
            $result = $this->get_cached_data($cache_key, 'quizzes', function() {
                global $wpdb;
                
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

                return $results ?: [];
            });

            if ($result['data'] === null) {
                return new \WP_Error(
                    'database_error',
                    __('Database error occurred', 'form-quizz-fqi3'),
                    ['status' => 500]
                );
            }

            return $this->create_cached_response($result['data'], $result['cache_hit'], 'quizzes');
        }
    
        /**
         * Get quiz by ID
         * 
         * Retrieves a specific quiz by its ID.
         * 
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @param WP_REST_Request $request The incoming request object containing the quiz ID
         * @return WP_REST_Response|WP_Error The quiz data or error if not found
         */
        public function get_quiz_by_id(\WP_REST_Request $request) {
            $id = (int) $request['id'];
            $cache_key = $this->generate_cache_key('quiz', $id);
        
            $result = $this->get_cached_data($cache_key, 'quiz', function() use ($id) {
                global $wpdb;
                
                $quiz = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$this->get_table_name('quizzes')} WHERE id = %d",
                        $id
                    ),
                    ARRAY_A
                );

                if ($wpdb->last_error) {
                    throw new \Exception($wpdb->last_error);
                }

                return $quiz;
            });

            if (!$result['data']) {
                return new \WP_Error(
                    'no_quiz',
                    __(self::ERROR_MESSAGES['no_quiz'], 'form-quizz-fqi3'),
                    ['status' => 404]
                );
            }

            return $this->create_cached_response($result['data'], $result['cache_hit'], 'quiz');
        }


        /**
         * Get quizzes by level
         * 
         * Retrieves all quizzes matching a specific difficulty level.
         * 
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @param WP_REST_Request $request The incoming request object containing the level
         * @return WP_REST_Response|WP_Error The matching quizzes or error if none found
         */
        public function get_quizzes_by_level(\WP_REST_Request $request) {
            $level = sanitize_text_field($request['level_name']);
            $cache_key = $this->generate_cache_key('quizzes_level', $level);
        
            $result = $this->get_cached_data($cache_key, 'quizzes', function() use ($level) {
                global $wpdb;
                $quizzes = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$this->get_table_name('quizzes')} WHERE niveau = %s",
                        $level
                    ),
                    ARRAY_A
                );
        
                if ($wpdb->last_error) {
                    throw new \Exception($wpdb->last_error);
                }
        
                return $quizzes ?: [];
            });
        
            if (empty($result['data'])) {
                return new \WP_Error(
                    'no_quizzes',
                    __(self::ERROR_MESSAGES['no_levels'], 'form-quizz-fqi3'),
                    ['status' => 404]
                );
            }
        
            return $this->create_cached_response($result['data'], $result['cache_hit'], 'quizzes');
        }

        /**
         * Get user badges
         * 
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response|WP_Error
         */
        public function get_user_badges(\WP_REST_Request $request) {
            $user_id = (int) $request->get_param('user_id');
            $cache_key = $this->generate_cache_key('user_badges', $user_id);
        
            $result = $this->get_cached_data($cache_key, 'badges', function() use ($user_id) {
                global $wpdb;
                $badges = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$this->get_table_name('awards')} WHERE user_id = %d",
                        $user_id
                    ),
                    ARRAY_A
                );
        
                if ($wpdb->last_error) {
                    throw new \Exception($wpdb->last_error);
                }
        
                return $badges ?: [];
            });
        
            if (empty($result['data'])) {
                return new \WP_Error(
                    'no_badge',
                    __(self::ERROR_MESSAGES['no_badges'], 'form-quizz-fqi3'),
                    ['status' => 404]
                );
            }
        
            return $this->create_cached_response($result['data'], $result['cache_hit'], 'badges');
        }

        /**
         * Get user performance with optimized query and caching.
         *
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @param WP_REST_Request $request The request object containing user ID.
         * @return WP_REST_Response JSON response containing user performance data.
         */
        public function get_user_performance(\WP_REST_Request $request) {
            $user_id = absint($request->get_param('user_id'));
            $cache_key = $this->generate_cache_key('user_performance', $user_id);
        
            $result = $this->get_cached_data($cache_key, 'performance', function() use ($user_id) {
                global $wpdb;
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
        
                return $performances ?: [];
            });
        
            if (empty($result['data'])) {
                return new \WP_Error(
                    'no_performance',
                    __(self::ERROR_MESSAGES['no_performance'], 'form-quizz-fqi3'),
                    ['status' => 404]
                );
            }
        
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
            }, $result['data']);
        
            return $this->create_cached_response($formatted_performance, $result['cache_hit'], 'performance');
        }
        

        /**
         * Update the user performance data by user ID.
         *
         * @since 1.6.0
         * @version 3.1.0 Refactored
         * 
         * @param WP_REST_Request $request The request object containing user ID and performance data.
         * @return WP_REST_Response JSON response containing success message or error.
         */
        public function update_user_performance(\WP_REST_Request $request) {
            global $wpdb;
            
            try {
                // Parameter retrieval
                $user_id = absint($request->get_param('user_id'));
                $params = $request->get_params();
                
                // Data extraction
                $level = isset($params['level']) ? sanitize_text_field($params['level']) : '';
                $correct_answers = isset($params['correct_answers']) ? intval($params['correct_answers']) : 0;
                $total_questions = isset($params['total_questions']) ? intval($params['total_questions']) : 0;
        
                if (!$user_id || !$level) {
                    throw new \Exception(__('User ID and level are required.', 'form-quizz-fqi3'));
                }
        
                $statistics = new FQI3_Statistics();
                $success = $statistics->save_user_performance(
                    $user_id,
                    $level,
                    $correct_answers,
                    $total_questions
                );
        
                if (!$success) {
                    throw new \Exception(__('Failed to save user performance.', 'form-quizz-fqi3'));
                }

                // Récupérer les valeurs mises à jour depuis la BDD
                $table_name = $wpdb->prefix . FQI3_TABLE_PERFORMANCE;
                $updated_stats = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $table_name WHERE user_id = %d AND level = %s",
                        $user_id,
                        $level
                    ),
                    ARRAY_A
                );
        
                return new \WP_REST_Response([
                    'message' => __('Performance updated successfully.', 'form-quizz-fqi3'),
                    'data' => $updated_stats
                    /*'data' => [
                        'user_id' => $user_id,
                        'level' => $level,
                        'correct_answers' => $correct_answers,
                        'total_questions' => $total_questions
                    ]*/
                ], 200);
        
            } catch (\Exception $e) {
                return new \WP_Error(
                    'update_failed',
                    __('Failed to update performance', 'form-quizz-fqi3') . ': ' . $e->getMessage(),
                    ['status' => 500]
                );
            }
        }                     

        /**
         * Sanitize performance data from request
         *
         * @since 1.6.0
         * @param \WP_REST_Request $request The request object containing performance data.
         * @return array The sanitized performance data.
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
         * Get badge settings
         * 
         * Retrieves all badge settings from wp_options.
         * 
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @version 3.1.0 Modified to use new format_badge_settings method
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response The response object containing all badge settings
         */
        public function get_badge_settings(\WP_REST_Request $request) {
            $cache_key = $this->generate_cache_key('badge_settings');
        
            $result = $this->get_cached_data($cache_key, 'settings', function() {
                $settings = get_option('fqi3_badges', []);
                return $this->format_badge_settings($settings);
            });
        
            if (empty($result['data'])) {
                return new \WP_Error(
                    'no_settings',
                    self::ERROR_MESSAGES['no_settings'],
                    ['status' => 404]
                );
            }
        
            return $this->create_cached_response(
                $result['data'],
                $result['cache_hit'],
                'settings'
            );
        }

        /**
         * Get badge settings by type
         * 
         * Retrieves badge settings for a specific type (quizzes completed or success rate).
         * 
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response The response object containing specific badge settings
         */
        public function get_badge_settings_by_type(\WP_REST_Request $request) {
            $type = sanitize_text_field($request['type']);
            $cache_key = $this->generate_cache_key('badge_settings', $type);
        
            $result = $this->get_cached_data($cache_key, 'settings', function() use ($type) {
                return $this->get_type_specific_settings($type);
            });
        
            if (empty($result['data'])) {
                return new \WP_Error(
                    'invalid_type',
                    __('Invalid badge type specified', 'form-quizz-fqi3'),
                    ['status' => 400]
                );
            }
        
            return $this->create_cached_response($result['data'], $result['cache_hit'], 'settings');
        }        

        /**
         * Get badges for all users
         * 
         * Retrieves all badges awarded to users with pagination support.
         * 
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @param \WP_REST_Request $request
         * @return WP_REST_Response|WP_Error
         */
        public function get_all_users_badges(\WP_REST_Request $request) {
            global $wpdb;
        
            $pagination = $this->get_pagination_params($request);
            $cache_key = $this->generate_cache_key(
                'all_users_badges',
                $pagination['page'],
                $pagination['per_page']
            );
        
            $result = $this->get_cached_data($cache_key, 'badges', function() use ($wpdb, $pagination) {
                $badges = $this->fetch_paginated_badges($wpdb, $pagination);
                return $badges ?: [];
            });
        
            if (empty($result['data'])) {
                return new \WP_Error(
                    'no_badges',
                    self::ERROR_MESSAGES['no_badges'],
                    ['status' => 404]
                );
            }
        
            $formatted_badges = $this->format_badges_with_details($result['data']);
            $response = new \WP_REST_Response($formatted_badges, 200);
        
            $this->add_pagination_headers($response, $pagination);
        
            return $this->create_cached_response($formatted_badges, $result['cache_hit'], 'badges');
        }        

        /**
         * Get all quiz levels
         * 
         * Retrieves all quiz level settings from wp_options.
         * 
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response The response object containing all quiz levels
         */
        public function get_quiz_levels(\WP_REST_Request $request) {
            $cache_key = $this->generate_cache_key('quiz_levels');
        
            $result = $this->get_cached_data($cache_key, 'levels', function() {
                return $this->format_quiz_levels(get_option('fqi3_quiz_levels', []));
            });
        
            if (empty($result['data'])) {
                return new \WP_Error(
                    'no_levels', 
                    self::ERROR_MESSAGES['no_levels'], 
                    ['status' => 404]
                );
            }
        
            return $this->create_cached_response($result['data'], $result['cache_hit'], 'levels');
        }        

        /**
         * Ping endpoint: validate token and return scopes and token policy info.
         * 
         * @since 3.0.0
         * @param \WP_REST_Request $request
         * @return \WP_REST_Response
         */
        public function ping(\WP_REST_Request $request): \WP_REST_Response {
            $options = function_exists('fqi3_get_options') ? (array) fqi3_get_options() : (array) get_option('fqi3_options', []);
            $scopes = isset($options['fqi3_api_scopes_allowed']) && is_array($options['fqi3_api_scopes_allowed']) ? array_values($options['fqi3_api_scopes_allowed']) : ['quizzes','stats','badges','settings'];
            $created_at = isset($options['fqi3_quiz_api_token_created_at']) ? intval($options['fqi3_quiz_api_token_created_at']) : 0;
            $mode = $options['fqi3_quiz_api_token_exp_mode'] ?? 'never';
            $days = isset($options['fqi3_quiz_api_token_exp_days']) ? intval($options['fqi3_quiz_api_token_exp_days']) : 0;
            $overlap = isset($options['fqi3_quiz_api_token_overlap_days']) ? intval($options['fqi3_quiz_api_token_overlap_days']) : 0;
            $now = time();
            $expires_at = ($mode === 'days' && $created_at > 0 && $days > 0) ? ($created_at + ($days * DAY_IN_SECONDS)) : 0;
            $valid_until = ($mode === 'days' && $created_at > 0 && ($days + $overlap) > 0) ? ($created_at + (($days + $overlap) * DAY_IN_SECONDS)) : 0;
            $https_only = !empty($options['fqi3_api_https_only']);

            $payload = [
                'status' => 'ok',
                'time' => $now,
                'scopes' => $scopes,
                'token_policy' => [
                    'mode' => $mode,
                    'created_at' => $created_at,
                    'expires_at' => $expires_at,
                    'valid_until' => $valid_until,
                    'grace_days' => $overlap,
                    'https_only' => (bool) $https_only,
                ],
            ];
            return new \WP_REST_Response($payload, 200);
        }

        /**
         * Get quiz level by name
         * 
         * Retrieves a specific quiz level by its name.
         * 
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response|WP_Error The level data or error if not found
         */
        public function get_quiz_level_by_name(\WP_REST_Request $request) {
            $target_name = sanitize_text_field($request['name']);
            $cache_key = $this->generate_cache_key('quiz_level', $target_name);
        
            $result = $this->get_cached_data($cache_key, 'levels', function() use ($target_name) {
                return $this->find_quiz_level_by_name($target_name);
            });
        
            if ($result['data'] === null) {
                return new \WP_Error(
                    'level_not_found',
                    __(self::ERROR_MESSAGES['no_levels'], 'form-quizz-fqi3'),
                    ['status' => 404]
                );
            }
        
            return $this->create_cached_response($result['data'], $result['cache_hit'], 'levels');
        }        

        /**
         * Get quiz levels by type (free or premium)
         * 
         * Retrieves all quiz levels of a specific type.
         * 
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @param WP_REST_Request $request The incoming request object
         * @return WP_REST_Response|WP_Error The matching levels or error if none found
         */
        public function get_quiz_levels_by_type(\WP_REST_Request $request) {
            $type = sanitize_text_field($request['type']);
            $cache_key = $this->generate_cache_key('quiz_levels_by_type', $type);
        
            $result = $this->get_cached_data($cache_key, 'levels', function() use ($type) {
                return $this->filter_quiz_levels_by_type($type);
            });
        
            if (empty($result['data'])) {
                return new \WP_Error(
                    'level_not_found',
                    __(self::ERROR_MESSAGES['no_levels_specified_type'], 'form-quizz-fqi3'),
                    ['status' => 404]
                );
            }
        
            return $this->create_cached_response($result['data'], $result['cache_hit'], 'levels');
        }

        /**
         * Format badge data for response
         * 
         * @param array $badge The badge data
         * @param string $type The badge type
         * @return array Formatted badge data
         */
        private function format_badge_data($badge, $type) {
            return [
                'type' => $type,
                'badge_name' => $badge['badge_name'],
                'threshold' => $badge['threshold'],
                'image_id' => $badge['image_id']
            ];
        }

        /**
         * Aggregates general, emails, tools and badges settings (badges via existing formatter).
         * Exposure is strictly controlled by per-group whitelists provided via filters.
         *
         * Filters available (each returns an array of allowed option keys under fqi3_options):
         * - fqi3_api_settings_whitelist_general
         * - fqi3_api_settings_whitelist_emails
         * - fqi3_api_settings_whitelist_tools
         *
         * @since 3.0.0
         * @param \WP_REST_Request $request The request object
         * @return \WP_REST_Response
         */
        public function get_all_settings_readonly(\WP_REST_Request $request): \WP_REST_Response {
            $general = $this->get_settings_group_values('general');
            $emails  = $this->get_settings_group_values('emails');
            $tools   = $this->get_settings_group_values('tools');
            $badges  = $this->get_settings_group_values('badges');

            $payload = [
                'general' => $general,
                'emails' => $emails,
                'tools' => $tools,
                'badges' => $badges,
            ];

            return new \WP_REST_Response($payload, 200);
        }

        /**
         * Read-only: return a single exposed settings group
         *
         * @param WP_REST_Request $request
         * @return WP_REST_Response
         * @since 3.0.0
         */
        public function get_settings_group_readonly(\WP_REST_Request $request): \WP_REST_Response {
            $group = sanitize_key($request['group']);
            $values = $this->get_settings_group_values($group);
            return new \WP_REST_Response($values, 200);
        }

        /**
         * Helper: fetch whitelisted settings for a group from options storage.
         *
         * Groups: general, emails, tools → from fqi3_options (via fqi3_get_options if available).
         * Group: badges → from fqi3_badges.
         * Returns only keys present in the underlying options array and sanitizes values.
         *
         * @since 3.0.0
         */
        private function get_settings_group_values(string $group): array {
            $core_options   = function_exists('fqi3_get_options') ? (array) fqi3_get_options() : (array) get_option('fqi3_options', []);
            $badges_options = (array) get_option('fqi3_badges', []);

            $filter_map = [
                'general' => 'fqi3_api_settings_whitelist_general',
                'emails'  => 'fqi3_api_settings_whitelist_emails',
                'tools'   => 'fqi3_api_settings_whitelist_tools',
                'badges'  => 'fqi3_api_settings_whitelist_badges',
            ];

            $filter = $filter_map[$group] ?? null;
            $whitelist = is_string($filter) ? (array) apply_filters($filter, []) : [];

            // Choose the correct source array based on group
            $source = ($group === 'badges') ? $badges_options : $core_options;

            $result = [];
            foreach ($whitelist as $key) {
                if (array_key_exists($key, $source)) {
                    $result[$key] = $this->sanitize_setting_value($source[$key]);
                }
            }

            return $result;
        }

        /**
         * Helper: sanitize setting value for safe read-only exposure.
         * Scalars are escaped; arrays are recursively sanitized; objects are cast to arrays.
         *
         * @since 3.0.0
         */
        private function sanitize_setting_value($value) {
            if (is_array($value)) {
                return array_map([$this, 'sanitize_setting_value'], $value);
            }
            if (is_object($value)) {
                $value = (array) $value;
                return array_map([$this, 'sanitize_setting_value'], $value);
            }
            if (is_bool($value) || is_int($value) || is_float($value)) {
                return $value;
            }
            // Fallback for strings
            return sanitize_text_field((string) $value);
        }

        /**
         * Format badge settings into a grouped structure
         * 
         * @since 3.1.0
         * @param array $settings
         * @return array
         */
        private function format_badge_settings($settings) {
            $formatted = [
                'enabled' => !$settings['fqi3_disable_badges'],
                'show_legend' => !$settings['fqi3_disable_badges_legend'],
                'min_quizzes_for_success_rate' => $settings['fqi3_min_quizzes_for_success_rate'],
                'enable_multi_level_badges' => (bool) $settings['fqi3_enable_multi_level_badges'],
                'correct_answers' => [
                    'badges' => []
                ],
                'quizzes_completed' => [
                    'badges' => []
                ],
                'success_rate' => [
                    'badges' => []
                ]
            ];

            // Format correct answers badges
            if (!empty($settings['fqi3_participation_thresholds'])) {
                foreach ($settings['fqi3_participation_thresholds'] as $index => $threshold) {
                    $formatted['correct_answers']['badges'][] = [
                        'threshold' => $threshold,
                        'name' => $settings['fqi3_participation_badge_names'][$index] ?? '',
                        'image_id' => $settings['fqi3_participation_badge_images'][$index] ?? 0
                    ];
                }
            }

            // Format quizzes completed badges
            if (!empty($settings['fqi3_quizzes_completed_thresholds'])) {
                foreach ($settings['fqi3_quizzes_completed_thresholds'] as $index => $threshold) {
                    $formatted['quizzes_completed']['badges'][] = [
                        'threshold' => $threshold,
                        'name' => $settings['fqi3_quizzes_completed_badge_names'][$index] ?? '',
                        'image_id' => $settings['fqi3_quizzes_completed_badge_images'][$index] ?? 0
                    ];
                }
            }

            // Format success rate badges
            if (!empty($settings['fqi3_success_rate_thresholds'])) {
                foreach ($settings['fqi3_success_rate_thresholds'] as $index => $threshold) {
                    $formatted['success_rate']['badges'][] = [
                        'threshold' => $threshold,
                        'name' => $settings['fqi3_success_rate_badge_names'][$index] ?? '',
                        'image_id' => $settings['fqi3_success_rate_badge_images'][$index] ?? 0
                    ];
                }
            }

            return $formatted;
        }

        /**
         * Get type specific badge settings
         *
         * @since 1.6.0
         * @version 3.0.0 Caching added
         * @version 3.1.0 Modified to use new format_badge_settings method
         * @param string $type
         * @return array
         */
        private function get_type_specific_settings(string $type): array {
            if (self::$badge_settings_cache === null) {
                $settings = get_option('fqi3_badges', []);
                self::$badge_settings_cache = $this->format_badge_settings($settings);
            }

            $settings = [];
            switch ($type) {
                case 'correct-answers-badge':
                    $settings = self::$badge_settings_cache['participation'];
                    break;
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
            // Check correct answers badges
            foreach ($badge_settings['fqi3_participation_badge_images'] as $index => $image_id) {
                if ($badge_id == $image_id) {
                    return [
                        'type' => 'correct-answers-badge',
                        'name' => $badge_settings['fqi3_participation_badge_names'][$index] ?? 'Unknown',
                        'threshold' => $badge_settings['fqi3_participation_thresholds'][$index] ?? null,
                        'image_id' => $image_id
                    ];
                }
            }

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