<?php
namespace Form_Quizz_FQI3;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class FQI3_OAuth {

    public function is_authenticated() {
        // Récupère le token envoyé dans l'en-tête
        $token = $this->get_token_from_request();
        
        return $token && $this->is_token_valid($token);
    }
    

    // Récupère le token depuis l'en-tête de la requête
    private function get_token_from_request() {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim(str_replace('Bearer', '', $_SERVER['HTTP_AUTHORIZATION']));
        }
        return null;
    }

    // Vérifie la validité du token
    private function is_token_valid($token) {
        $options = get_option('fqi3_api_tokens');
        
        return isset($options[$token]) && $options[$token]['expires'] > time();
    }

    // Méthode pour générer un nouveau token (à appeler lors de la création de token par l’utilisateur)
    public function generate_token() {
        $token = bin2hex(random_bytes(16)); // Génère un token aléatoire
        $expires = time() + 7 * DAY_IN_SECONDS; // Expiration dans 7 jours

        // Stocke le token dans la base de données avec l'ID utilisateur
        $tokens = get_option('fqi3_api_tokens', []);
        $tokens[$token] = [
            'expires' => $expires
        ];
        update_option('fqi3_api_tokens', $tokens);

        return $token;
    }

    // Méthode pour révoquer un token
    public function revoke_token($token) {
        $tokens = get_option('fqi3_api_tokens', []);
        if (isset($tokens[$token])) {
            unset($tokens[$token]);
            update_option('fqi3_api_tokens', $tokens);
            return true;
        }
        return false;
    }

    public function redirect_to_authorization() {
        // Vous pouvez modifier l'URL ici selon vos besoins
        $authorization_url = 'https://example.com/authorize'; // Remplacez par votre URL d'autorisation
        wp_redirect($authorization_url);
        exit; // Assurez-vous d'appeler exit après wp_redirect
    }
}