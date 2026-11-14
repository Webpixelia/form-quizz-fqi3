<?php
namespace Form_Quizz_FQI3\LicenseProviders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sélectionne (et partage) l'implémentation de LicenseProviderInterface.
 *
 * Règle :
 * - FQI3_LICENSE_PROVIDER = 'server' ET clé de licence définie (constante ou option) → ServerLicenseProvider
 * - FQI3_LICENSE_PROVIDER = 'server' ET clé absente → fallback GitHub (+ un seul avertissement par requête)
 * - Sinon (défaut) → GitHubLicenseProvider
 *
 * L'instance est partagée : tous les Plugin_Updater (core + add-ons) utilisent le même fournisseur,
 * ce qui permet une seule requête groupée vers LUS.
 *
 * @package Form_Quizz_FQI3
 * @since 3.7.0
 */
class LicenseProviderFactory
{
    public const DEFAULT_SERVER_URL = 'https://updates.webpixelia.com';
    public const OPTION_KEY         = 'fqi3_license_key';

    /** @var array<string, string> Map slug => repo GitHub (core ; les add-ons s'enregistrent via registerRepository()). */
    private static array $repositories = array( 'form-quizz-fqi3' => 'form-quizz-fqi3' );

    private static ?LicenseProviderInterface $instance = null;
    private static bool $warned = false;

    public static function make(): LicenseProviderInterface
    {
        if ( self::$instance !== null ) {
            return self::$instance;
        }

        $provider = defined( 'FQI3_LICENSE_PROVIDER' ) ? strtolower( trim( (string) FQI3_LICENSE_PROVIDER ) ) : 'github';

        if ( $provider !== 'github' && $provider !== 'server' ) {
            self::warn( '[FQI3/LUS] FQI3_LICENSE_PROVIDER inconnu ("' . $provider . '"). Fallback GitHub.' );
            $provider = 'github';
        }

        if ( $provider === 'server' && ! self::hasLicenseKey() ) {
            self::warn( '[FQI3/LUS] FQI3_LICENSE_PROVIDER=server mais aucune clé de licence définie. Fallback GitHub.' );
            $provider = 'github';
        }

        if ( $provider === 'server' ) {
            self::$instance = new ServerLicenseProvider( self::serverUrl() );
        } else {
            self::$instance = new GitHubLicenseProvider( self::$repositories );
        }

        return self::$instance;
    }

    /**
     * Fournisseur serveur, indépendamment du fallback (écran de licence : activate / validate).
     */
    public static function makeServer(): ServerLicenseProvider
    {
        return new ServerLicenseProvider( self::serverUrl() );
    }

    public static function isServerMode(): bool
    {
        return self::make()->getId() === 'server';
    }

    /**
     * Déclare le dépôt GitHub d'un add-on (mode GitHub uniquement ; sans effet en mode serveur).
     */
    public static function registerRepository( string $slug, string $repo ): void
    {
        if ( $slug === '' || $repo === '' ) {
            return;
        }

        self::$repositories[ $slug ] = $repo;

        if ( self::$instance instanceof GitHubLicenseProvider ) {
            self::$instance->addRepository( $slug, $repo );
        }
    }

    /**
     * À appeler après un changement de clé : le prochain make() ré-évalue le fournisseur.
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$warned   = false;
    }

    public static function serverUrl(): string
    {
        return defined( 'FQI3_LUS_SERVER_URL' ) && (string) FQI3_LUS_SERVER_URL !== ''
            ? (string) FQI3_LUS_SERVER_URL
            : self::DEFAULT_SERVER_URL;
    }

    /**
     * Clé de licence : constante prioritaire, puis option (réseau en multisite).
     */
    public static function getLicenseKey(): string
    {
        if ( defined( 'FQI3_LICENSE_KEY' ) && (string) FQI3_LICENSE_KEY !== '' ) {
            return trim( (string) FQI3_LICENSE_KEY );
        }

        $option = is_multisite() ? get_site_option( self::OPTION_KEY, '' ) : get_option( self::OPTION_KEY, '' );

        return is_string( $option ) ? trim( $option ) : '';
    }

    /**
     * @return string 'constant' | 'option' | 'none'
     */
    public static function licenseKeySource(): string
    {
        if ( defined( 'FQI3_LICENSE_KEY' ) && (string) FQI3_LICENSE_KEY !== '' ) {
            return 'constant';
        }

        return self::getLicenseKey() !== '' ? 'option' : 'none';
    }

    public static function hasLicenseKey(): bool
    {
        return self::getLicenseKey() !== '';
    }

    /**
     * Dernier état de licence connu (écrit par ServerLicenseProvider, lu par l'écran de licence sans requête HTTP).
     *
     * @return array<string, mixed>
     */
    public static function getStatus(): array
    {
        $status = is_multisite()
            ? get_site_option( ServerLicenseProvider::STATUS_OPTION, [] )
            : get_option( ServerLicenseProvider::STATUS_OPTION, [] );

        return is_array( $status ) ? $status : [];
    }

    /**
     * @param array<string, mixed> $status
     */
    public static function saveStatus( array $status ): void
    {
        if ( is_multisite() ) {
            update_site_option( ServerLicenseProvider::STATUS_OPTION, $status );
        } else {
            update_option( ServerLicenseProvider::STATUS_OPTION, $status, false );
        }
    }

    private static function warn( string $message ): void
    {
        if ( self::$warned ) {
            return;
        }
        self::$warned = true;
        error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}
