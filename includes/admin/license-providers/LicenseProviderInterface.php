<?php
namespace Form_Quizz_FQI3\LicenseProviders;

/**
 * Interface d'un fournisseur de mises à jour pour un plugin WordPress.
 *
 * Deux implémentations :
 * - ServerLicenseProvider : interroge le serveur LUS (POST /v1/wp/check)
 * - GitHubLicenseProvider : interroge l'API GitHub (comportement historique)
 *
 * Le fournisseur est sélectionné par LicenseProviderFactory selon la constante
 * FQI3_LICENSE_PROVIDER ('server' ou 'github').
 *
 * @package Form_Quizz_FQI3
 * @since 3.7.0
 */
interface LicenseProviderInterface
{
    /**
     * Vérifie les mises à jour pour une liste de plugins.
     *
     * @param array<string, string> $plugins Map plugin_basename => version courante.
     *                                       Ex. ['form-quizz-fqi3/form-quizz-fqi3.php' => '3.6.1']
     * @return array<string, array{
     *   slug: string,
     *   plugin: string,
     *   new_version: string,
     *   package: string,
     *   url: ?string,
     *   tested: ?string,
     *   requires: ?string,
     *   requires_php: ?string,
     *   sha256: string,
     * }>|null
     *   Map plugin_basename => données de mise à jour WordPress.
     *   Retourne null si le serveur est injoignable ou la licence invalide.
     */
    public function checkUpdates(array $plugins): ?array;

    /**
     * Retourne les informations détaillées d'un plugin (pour l'écran "Détails").
     *
     * @param string $slug Slug du plugin (ex. 'form-quizz-fqi3').
     * @return array{
     *   name: string,
     *   slug: string,
     *   version: string,
     *   author: ?string,
     *   homepage: ?string,
     *   requires: ?string,
     *   tested: ?string,
     *   requires_php: ?string,
     *   sections: array{description: string, changelog: string},
     * }|null
     */
    public function getProductInfo(string $slug): ?array;

    /**
     * Retourne l'URL de téléchargement d'un artefact.
     *
     * @param string $slug    Slug du plugin.
     * @param string $version Version cible.
     * @return string|null URL de téléchargement, ou null si indisponible.
     */
    public function downloadUrl(string $slug, string $version): ?string;
}