<?php
namespace Form_Quizz_FQI3\LicenseProviders;

/**
 * Sélectionne l'implémentation de LicenseProviderInterface.
 *
 * Règle :
 * - Si FQI3_LICENSE_PROVIDER = 'server' ET que la clé de licence est définie
 *   (constante ou option) → ServerLicenseProvider
 * - Si FQI3_LICENSE_PROVIDER = 'server' ET que la clé est absente → fallback GitHub + warning
 * - Sinon (défaut) → GitHubLicenseProvider
 *
 * @package Form_Quizz_FQI3
 * @since 3.7.0
 */
class LicenseProviderFactory
{
    /** @var array<string, string> Map slug => repo GitHub pour le provider GitHub */
    private const GITHUB_REPOSITORIES = [
        'form-quizz-fqi3' => 'form-quizz-fqi3',
        // Les add-ons sont ajoutés dynamiquement par FQI3_Addons_Page.
    ];

    public static function make(): LicenseProviderInterface
    {
        $provider = defined('FQI3_LICENSE_PROVIDER') ? (string) FQI3_LICENSE_PROVIDER : 'github';

        if ($provider === 'server') {
            if (! self::hasLicenseKey()) {
                error_log('[FQI3/LUS] FQI3_LICENSE_PROVIDER=server mais aucune clé de licence définie. Fallback GitHub.');

                return new GitHubLicenseProvider(self::GITHUB_REPOSITORIES);
            }

            $url = defined('FQI3_LUS_SERVER_URL') && (string) FQI3_LUS_SERVER_URL !== ''
                ? (string) FQI3_LUS_SERVER_URL
                : 'https://updates.webpixelia.com';

            return new ServerLicenseProvider($url);
        }

        return new GitHubLicenseProvider(self::GITHUB_REPOSITORIES);
    }

    public static function hasLicenseKey(): bool
    {
        if (defined('FQI3_LICENSE_KEY') && (string) FQI3_LICENSE_KEY !== '') {
            return true;
        }

        $option = get_option('fqi3_license_key', '');
        return is_string($option) && $option !== '';
    }
}