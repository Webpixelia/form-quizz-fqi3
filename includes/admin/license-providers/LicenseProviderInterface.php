<?php
namespace Form_Quizz_FQI3\LicenseProviders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
     * Identifiant du fournisseur : 'github' ou 'server'.
     */
    public function getId(): string;

    /**
     * Vérifie les mises à jour pour une liste de plugins.
     *
     * IMPORTANT : le champ `package` retourné par ServerLicenseProvider est un
     * *marqueur* (jamais une URL de téléchargement utilisable). Le vrai paquet
     * s'obtient avec resolveDownload(), au moment du téléchargement.
     *
     * @param array<string, string> $plugins Map plugin_basename => version courante.
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
     *   channel?: string,
     *   signature?: ?string,
     *   signature_kid?: ?string,
     * }>|null
     *   Map plugin_basename => données de mise à jour (uniquement les plugins ayant une version plus récente).
     *   null = échec (serveur injoignable, licence invalide…) : voir getLastError().
     */
    public function checkUpdates(array $plugins): ?array;

    /**
     * Informations détaillées d'un plugin (écran « Détails »).
     *
     * @return array{
     *   name: string,
     *   slug: string,
     *   version: string,
     *   author: ?string,
     *   homepage: ?string,
     *   requires: ?string,
     *   tested: ?string,
     *   requires_php: ?string,
     *   last_updated: ?string,
     *   download_link: string,
     *   sections: array{description: string, changelog: string},
     * }|null
     */
    public function getProductInfo(string $slug): ?array;

    /**
     * Résout, à l'instant T (jamais depuis un cache), le paquet à télécharger.
     * Remplace l'ancienne méthode downloadUrl().
     *
     * @param string $slug             Slug du plugin.
     * @param string $installedVersion Version installée ('0.0.0' pour une première installation).
     * @return array{url: string, sha256: string, version: string, headers: array<string, string>, signed?: array{slug: string, version: string, channel: string, signature: ?string, kid: ?string}}|null
     *   `signed` n'est fourni que par le serveur LUS (signature Ed25519 à vérifier après téléchargement).
     */
    public function resolveDownload(string $slug, string $installedVersion = '0.0.0'): ?array;

    /**
     * Dernière erreur rencontrée (null si la dernière opération a réussi).
     *
     * @return array{code: string, message: string, http: int}|null
     */
    public function getLastError(): ?array;

    /**
     * Invalide les caches du fournisseur (bouton « Actualiser », changement de clé).
     */
    public function clearCache(): void;
}
