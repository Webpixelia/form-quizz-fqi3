<?php
/**
 * FQI3_API_Logger Class
 *
 * This class provides a logging system for API requests.
 *
 * @package    Form Quizz FQI3
 * @subpackage Admin
 * @since      3.0.0 
*/
namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class FQI3_API_Logger {
    private $log_level; // 'off', 'error', 'verbose'

    public function __construct(string $level = 'error') {
        $this->log_level = $level;
    }

    /**
     * Log request info
     *
     * @param WP_REST_Request $request
     * @param WP_REST_Response $response
     * @param float $duration in seconds
     */
    public function log_request(\WP_REST_Request $request, \WP_REST_Response $response, float $duration): void {
        if ($this->log_level === 'off') return;

        // IP client
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }

        $log_data = [
            'time' => current_time('mysql'),
            'endpoint' => $request->get_route(),
            'method' => $request->get_method(),
            'status' => $response->get_status(),
            'duration' => round($duration, 4),
            'user_id' => get_current_user_id(),
            'client_ip' => $ip,
        ];

        // Optionnel : infos détaillées uniquement en verbose
        if ($this->log_level === 'verbose') {
            $log_data['params'] = $this->sanitize_params($request->get_params());
            $log_data['body']   = $this->sanitize_params(json_decode($request->get_body(), true) ?? []);
            $log_data['query']  = $this->sanitize_params($request->get_query_params());
        }

        // Log dans debug.log (sécurisé, pas de IP ni données sensibles)
        error_log('FQI3 API Request: ' . wp_json_encode($log_data));
    }

    /**
     * Sanitize request parameters for logging (strip sensitive info)
     *
     * @param array $params
     * @return array
     */
    private function sanitize_params(array $params): array {
        // Supprime les mots de passe, tokens ou données sensibles
        $sensitive_keys = ['password', 'token', 'api_key', 'secret'];
        foreach ($sensitive_keys as $key) {
            if (isset($params[$key])) {
                $params[$key] = '[REDACTED]';
            }
        }
        return $params;
    }
}