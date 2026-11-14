# Étude technique L6.2 / L6.3 — intégration LUS dans le core FQI3

> Périmètre : `fqi3-audit.zip` (FQI3 3.6.1 + commit L6.1) et `lus-audit.zip` (contrat réel du serveur). Les numéros de ligne sont ceux des fichiers **d'origine**.
> Les patchs joints ont été appliqués sur des copies, linés (`php -l`, PHP 8.3), vérifiés avec `patch --dry-run` sur le zip d'origine, puis exécutés contre un **faux serveur LUS** qui reproduit `WpCheckController`, `WordPressResponseFormatter`, `DownloadTokenService` et `TrackLicenseFailures`. WordPress réel non testé : voir §Tests.

## Résumé exécutif

**État des 4 fichiers L6.1 : à corriger avant branchement.** Ils sont propres sur la forme, mais `ServerLicenseProvider` a des défauts bloquants. Chacun est reproduit par le harnais (tableau ci-dessous).

| # | Défaut (fichier:ligne) | Effet constaté sur le faux LUS |
|---|---|---|
| B1 | `ServerLicenseProvider:67` envoie `plugins` = slug→slug ; `WordPressResponseFormatter:153-164` attend slug→**basename** et s'en sert comme clé | La réponse est indexée par slug : `$transient->response['form-quizz-fqi3']` serait ignoré par WordPress. **Aucune mise à jour ne s'afficherait.** |
| B2 | `:46,108,156-166` : le `package` (jeton à usage unique, 10 min : `DownloadTokenService:28,93-97`, `UpdateChecker:85,99`) est mis en cache 12 h | Clic « Mettre à jour » 11 min après la vérification → **HTTP 410**. `downloadUrl()` réutilise ce cache. |
| B3 | `:46` clé de cache sans les versions installées | Après mise à jour en 3.7.0, la mise à jour 3.7.0 reste proposée jusqu'à 12 h. |
| B4 | `:74-83` échecs jamais mis en cache | 12 rafraîchissements d'un transient avec licence révoquée = 12 requêtes, 10 échecs comptés, puis **429 pour toute l'IP** (`TrackLicenseFailures:46-52`), y compris les sites sains sur le même hébergement. Serveur injoignable : un timeout de 10 s à chaque appel. |
| B5 | Le provider n'appelle jamais `activate` ; `/wp/check` répond 409 si l'instance n'est pas activée (`WpCheckController:44-46`) | Avec seulement `FQI3_LICENSE_KEY` dans `wp-config.php` (le cas « recommandé » du doc 09 §2.1), il n'y a aucun écran pour activer : `null` silencieux, pour toujours. |
| B6 | `:186` `home_url()` | En multisite, l'instance dépend du sous-site qui déclenche le check ; l'architecture prévoit le domaine du réseau. |
| B7 | Un seul `version` invalide dans le lot (ex. add-on en `1.0.0.0.1`) | 422 sur **tout** le lot : le core n'est plus mis à jour. |

**Risques majeurs L6.2 / L6.3 (hors providers) :**
- `fqi3-config-functions.php:204` masque la page Add-ons sans `GITHUB_PERSONAL_TOKEN`. En production LUS, **la page disparaît** : le critère d'acceptation de L6 (« installer un add-on privé sans token ») est impossible tant que ce n'est pas changé.
- `Plugin_Updater` du core n'est enregistré que si `is_admin()` (`form-quizz-fqi3.php:904`). Dans WP-Cron / WP-CLI, `update_plugins` est reconstruit **sans** le filtre FQI3 (mécanisme WordPress certain ; à confirmer sur Local avec `wp cron event run wp_update_plugins`). Les add-ons, eux, sont enregistrés partout (Backend instancié à chaque requête, `FQI3_Backend.php:622`) : comportement incohérent.
- Fatal latent **déjà présent** : `class-add-ons.php:1904-1925` fait `$latest['version']` sur le retour de `force_check()` (objet `stdClass`, `class-plugin-updater.php:443`). Reproduit : `Error: Cannot use object of type stdClass as array` (non attrapé par `catch (\Exception)`). De plus `force_check()` rappelle GitHub/LUS à chaque affichage de la page.
- `extract_requirement()` (`class-plugin-updater.php:417`) lit des clés (`'Requires at least'`, `'Requires PHP'`) qui n'existent pas dans `get_plugin_data()` (qui expose `RequiresWP`, `RequiresPHP`). `requires` / `requires_php` / `tested` ont donc toujours été vides. Avec LUS, `requires_php` sera enfin renseigné et WordPress masquera la mise à jour sur un PHP trop ancien : vérifier le `compat` des manifestes.

**Recommandations prioritaires**
1. Appliquer `fqi3-L6.2a-providers.patch` (corrige B1-B7, étend l'interface).
2. Ne jamais mettre le jeton de téléchargement dans un cache ni dans `update_plugins` : `package` = marqueur, jeton frais dans `upgrader_pre_download` + vérification `sha256` (patch L6.2b).
3. Un fournisseur **partagé** et **une seule requête groupée** pour core + add-ons.
4. Remplacer le garde-fou « token GitHub » de la page Add-ons par « source de distribution utilisable ».
5. Décider les questions ouvertes (activation automatique, sha256 GitHub, multisite) avant L6.3.

**Résultat des tests (harnais, faux LUS) :** L6.1 tel que livré : 0/10 des scénarios de robustesse passent ; avec les patchs : 28/28.

---

## Étude fichier par fichier

### LicenseProviderInterface.php
- **Rôle** : contrat commun. **Forces** : `?array` partout, pas d'exception, PHP 8.0 OK.
- **Faiblesses** : `null` mélange « serveur HS », « licence invalide », « pas de mise à jour » : impossible d'afficher un message (L6.3). `downloadUrl()` (`:65`) ne peut pas porter `sha256` ni en-têtes (token GitHub), et son paramètre `$version` est ambigu (cible dans la doc, *installée* dans l'implémentation serveur `:156`). Pas de `clearCache()` : le bouton « refresh » de `refresh_addon_cache()` ne vide donc pas le cache des providers.
- **Patch** : ajout `getId()`, `resolveDownload()` (remplace `downloadUrl`, jamais en cache, retourne `url/sha256/version/headers`), `getLastError()`, `clearCache()`. Interface non publiée → changement sans coût de compatibilité.

### GitHubLicenseProvider.php
- **Rôle** : encapsule l'ancien `get_latest_release()`.
- **Comparé à `Plugin_Updater` (`:292-409`)**
  - *Conservé* : cache 12 h / 1 h / 24 h (404), asset `.zip` préféré, token, `ltrim('v')`.
  - *Perdu (régressions)* : `last_updated` (`published_at`, `:350` d'origine) ; message « No changelog available » (`:393`, remplacé par `''` donc section vide) ; `$github_username` configurable (constante en dur `:16`) ; `download_link` pour la modale.
  - *Perdu ou changé silencieusement* : clé de cache `fqi3_github_release_*` ≠ `fqi3_release_check_*`. `clear_cache()` (`:427`) supprime d'ailleurs une clé `fqi3_update_*` **jamais écrite** (code mort). Le bouton « Refresh » ne servait donc à rien pour le cache GitHub (sauf via `force_check`).
  - *Amélioré* : plus de duplication (une classe), `str_contains`, `?? $content` sur `preg_replace`.
  - *Hérité* : `parseChangelog` enveloppe de `<ul>` du premier au dernier `<li>` (regex gloutonne `/s`), `- item` non géré (le rendu final est filtré par `wp_kses` côté WordPress, pas de XSS).
- **Manque** : aucun moyen d'enregistrer les dépôts des add-ons (`LicenseProviderFactory.php:21` dit « ajoutés dynamiquement » mais `$repositories` est privé et la factory recrée un provider à chaque `make()`) : pour un add-on, le provider GitHub renverrait toujours `null`.
- **Patch** : `getId`, `addRepository`, `resolveDownload` (en-têtes `Authorization` + `Accept: application/octet-stream` pour `/releases/assets/`, comme `download_private_package`), `clearCache`, `last_updated`, `download_link`, message de changelog.

### ServerLicenseProvider.php
- **Rôle** : client LUS. **Forces** : clé jamais loguée, aucun fatal, normalisation du domaine cohérente avec `DomainNormalizer`.
- **Constats** : B1-B7 ci-dessus, plus :
  - `timeout => 10` dans un filtre exécuté sur le chargement d'admin ; `redirection` par défaut (5) : un 307/308 rejouerait le corps (donc la clé) vers un autre hôte ;
  - pas d'en-tête `User-Agent` / `X-Client` (recommandés par l'architecture §4) ;
  - `http://` accepté : la clé voyage en clair ;
  - `package` non validé (hôte, schéma) ;
  - `getProductInfo` sans cache (un appel serveur par ouverture de modale) ; `/wp/info` renvoie un `download_link` factice (`WpInfoController:115`) à ne pas utiliser ;
  - `error_log` à chaque échec (inondation des logs).
- **Patch** : réécriture ciblée (voir patch L6.2a) : mapping slug→basename + re-association par **notre** table (on ne fait pas confiance aux clés du serveur), cache de métadonnées **sans** `package`, clé de cache avec versions + « époque » (`clearCache()` = incrément d'époque), cache négatif (403 : 12 h, 409/422 : 1 h, 429 : `Retry-After` plafonné à 1 h, réseau/5xx : 15 min), timeout 6 s, `redirection => 0`, HTTPS obligatoire hors `wp_get_environment_type()` local/dev (ou `FQI3_LUS_ALLOW_INSECURE`), hôte du paquet vérifié (filtre `fqi3_lus_trusted_package_hosts`), filtrage des versions invalides, `activate()` et `validate()` publics (réutilisés par L6.3), **auto-activation** si la clé vient de la constante (filtre `fqi3_lus_auto_activate`, 1 essai/h), `FQI3_LICENSE_INSTANCE` pour surcharger l'instance, statut persisté dans `fqi3_license_status`.

### LicenseProviderFactory.php
- **Rôle** : choix du provider. **Constats** : `error_log` à chaque `make()` (`:30`) ; valeur inconnue de la constante ignorée sans alerte (`'Server'` → GitHub) ; pas de partage d'instance ; `hasLicenseKey()` lit `get_option` (`:51`) alors que la clé devra être réseau en multisite ; clé lue à deux endroits (ici et `ServerLicenseProvider:171`).
- **Patch** : instance partagée + `reset()`, `registerRepository()`, `getLicenseKey()` / `licenseKeySource()` uniques (constante > option, `site_option` en multisite), `isServerMode()`, `makeServer()` (l'écran de licence doit parler à LUS même si le fallback GitHub est actif), un seul avertissement par requête, normalisation de la constante.

### class-plugin-updater.php (existant)
- Constats : voir risques majeurs. Aussi : `load_plugin_data()` appelée avant le test de l'action dans `plugin_info` (`:232`, lecture de fichier pour *chaque* appel `plugins_api` de tout plugin) ; `$args->slug` sans garde (`:234`) ; `update_message` (`:271`) n'échappe pas `Name` / `new_version` et double le message natif de WordPress (hors périmètre) ; le helper `init_plugin_updater()` (`:482`) n'est utilisé nulle part.

### class-add-ons.php (existant)
- `install_addon()` (`:1545-1685`) : exige le token GitHub (`:1561`), télécharge en mémoire (`wp_remote_get` sans `stream`, `strlen < 100`), prend le `zipball_url` alors que l'updater préfère l'asset `.zip` (le zipball est une archive de **sources** : un `vendor/` généré par la CI en est absent ; à vérifier pour chaque add-on), aucune vérification d'intégrité.
- `init_updaters()` (`:1767`) : exige `github_repo` (`:1780`) → les add-ons sans dépôt n'ont jamais d'updater ; un `Plugin_Updater` par add-on = un appel serveur par add-on.
- `render_add_ons_page()` : « Coming Soon » décidé par `github_repo` vide (`:693`) : à revoir en mode LUS (voir Questions).
- Le garde `class_exists('FQI3_Addons_Page')` (`:26`) est sans effet (la classe est dans un namespace). À vérifier : `get_addon_status()` appelle `get_plugins()` (`:78`) sur chaque requête (Backend instancié partout) ; s'assurer que `wp-admin/includes/plugin.php` est chargé hors admin.

### class-license-settings.php (existant)
- Écran en lecture seule (token GitHub masqué, aucun champ clé, aucune action). Le `FQI3_License_Settings` est instancié à `admin_init` (`FQI3_Backend::init_register_settings`), y compris pour `admin-ajax.php` : on peut donc y enregistrer les handlers AJAX. La section est **dans** `<form action="options.php">` (`class-options.php:89`) : pas de `<form>` imbriqué, d'où AJAX (déjà le style du plugin).

### form-quizz-fqi3.php (existant)
- `loadDependencies()` (`:313`) charge à chaque requête ; les providers n'y sont pas encore (les 4 `require` du doc 09 §3.3 sont absents). **Choix retenu** : les charger en tête de `class-plugin-updater.php` (déjà chargé par le Backend et par `class-add-ons.php:1749`) : un seul point, pas de dépendance à l'ordre. Le doc 09 dit « ajouter à `loadDependencies()` » : cela fonctionnerait aussi, mais charge 4 fichiers sur le front.

---

## Lecture critique de `docs/09-WORDPRESS-CLIENT.md` (§1-3)

| Point | Verdict |
|---|---|
| Constantes `FQI3_LICENSE_PROVIDER`, `FQI3_LUS_SERVER_URL`, `FQI3_LICENSE_KEY` | Cohérentes avec `GITHUB_PERSONAL_TOKEN` (même mécanisme). Manquent : `FQI3_LICENSE_INSTANCE` (sites Local), `FQI3_LUS_ALLOW_INSECURE` (dev). |
| Flux §1.2 `upgrader_pre_download → vérifie sha256 → installe` | Réalisable **mais incomplet** : il suppose que `package` du transient est valide au clic. Faux (jeton 10 min, usage unique). Le flux correct : transient = marqueur → `upgrader_pre_download` demande un jeton frais, télécharge, vérifie. |
| « Aucune dépendance à WordPress dans l'interface » (§3.2.1) | Vrai pour l'interface, faux pour les implémentations (transients, options) ; sans conséquence. |
| Étape d'activation | **Absente** du flux. Sans `activate`, `/wp/check` → 409. |
| Erreurs | §2.2 « log un warning et retourne null » : insuffisant (pas de cache négatif, pas de message admin). Le §6 « dégradation » annoncé n'existe pas encore. |
| Multisite | Non traité ; à documenter (instance = `network_home_url()`). |
| Quotas | Un site Local (`*.local`) consomme une activation : prévoir `FQI3_LICENSE_INSTANCE` ou désactivation. |
| Chargement §3.3 | Voir ci-dessus ; la note sur l'ordre est exacte (interface avant implémentations). |
| §4 / §5 « voir section 5 / 6 » | Renvois vers des sections inexistantes. |

**Applicable tel quel ? Non** : corriger §1.2 (flux), ajouter l'activation, `FQI3_LICENSE_INSTANCE`, le cas multisite, et réécrire §3.2.1 pour la nouvelle interface.

---

## Préparation L6.2

Fichiers : `fqi3-L6.2a-providers.patch` (4 providers) puis `fqi3-L6.2b-updater-addons.patch`. Les diffs complets sont dans ces fichiers ; voici les points d'intégration et les extraits clés.

### Patch `Plugin_Updater`

**Injection sans casser les appels** (`form-quizz-fqi3.php:906`, `class-add-ons.php:1789` passent 3 arguments) :

```php
// avant
public function __construct( $plugin_file, $github_repo, $github_username = 'Webpixelia' ) {
// après
public function __construct( $plugin_file, $github_repo = '', $github_username = 'Webpixelia', $provider = null ) {
    ...
    $this->injected_provider = $provider instanceof LicenseProviderInterface ? $provider : null;
    self::$registry[ $plugin_file ] = true;          // plugins gérés : une seule requête groupée
    LicenseProviderFactory::registerRepository( $this->plugin_slug, $this->github_repo );
```
Le 4ᵉ argument ne sert qu'aux tests ; sinon `LicenseProviderFactory::make()` est appelé **à l'usage** (pas à la construction : pas de lecture d'option au chargement, et une clé saisie dans la même requête est prise en compte après `reset()`).

**Méthodes qui délèguent** : `check_for_updates` → `checkUpdates()` ; `plugin_info` et `get_latest_release` → `getProductInfo()` ; `download_private_package` → `resolveDownload()` (mode serveur) ; `clear_cache` → `clearCache()` ; `is_update_available` → `checkUpdates()`.
**Restent spécifiques WordPress** : `update_message`, `extract_requirement` (corrigé : `RequiresWP` / `RequiresPHP`), `load_plugin_data`, `fix_source_dir` (no-op pour un zip CI dont la racine est le slug), `download_private_package` en mode GitHub (inchangé).

**`check_for_updates`** (`:176-220`) :
```php
$current_version = (string) $transient->checked[ $this->plugin_file ];
$plugins         = array_intersect_key( $transient->checked, self::$registry );
$updates         = $this->provider()->checkUpdates( $plugins );
if ( null === $updates ) { return $transient; }   // échec : aucun avis, aucun fatal
$update = $updates[ $this->plugin_file ] ?? null;
// response : valeurs du provider, repli sur les en-têtes locaux ; sinon no_update
```

**`upgrader_pre_download`** (mode serveur) :
```php
if ( 'server' === $this->provider()->getId() ) { return $this->download_from_provider(); }
// ... resolveDownload( slug, version installée ) → jeton frais
// → Plugin_Updater::download_verified( url, sha256, headers, true )
```
`download_verified()` (statique, partagée avec `install_addon`) : `wp_safe_remote_get` en streaming, taille minimale, `hash_equals` sur `hash_file('sha256')`, fichier supprimé si mismatch.

**sha256 absent côté GitHub** : `sha256 = ''` accepté **uniquement** si `require_sha256 = false` (mode GitHub = dev, comportement historique). En mode serveur, un `sha256` absent ou mal formé est **refusé** (fail closed ; le serveur le fournit toujours : `UpdateChecker:97`). La signature Ed25519 (DEC-01 à 03) s'ajoutera dans `download_verified()` sans changer les appelants.

**Cache** : (1) `update_plugins` reste le cache WordPress, avec `package` = marqueur (`{serveur}/v1/download/pending`) ; (2) cache provider 12 h des métadonnées seulement ; (3) `cache_key` d'origine était mort, supprimé du chemin utile (la méthode `clear_cache()` reste pour compatibilité). Un jeton par update est créé côté LUS à chaque `check` (`UpdateChecker:85`), inévitable ; la requête groupée limite cela à 1 par rafraîchissement.

**Points d'attention** :
- **À valider sur Local** : WordPress accepte un `package` non vide quelconque et appelle `upgrader_pre_download` avant toute lecture de l'URL (comportement du cœur, non testé ici). Si une version de WP rejetait le marqueur, repli : URL réelle + rafraîchissement du transient à la demande.
- Priorité/hook : aucun changement (`pre_set_site_transient_update_plugins` 10, `plugins_api` 20, `upgrader_pre_download` 10). Chaque instance se filtre par `$hook_extra['plugin']`.
- `form-quizz-fqi3.php:904` : `is_admin() || wp_doing_cron() || WP_CLI`.
- Non-régression : `$transient->checked` est désormais la source de la version courante (plus fiable que `get_plugin_data`).

### Patch `FQI3_Addons_Page`

**Provider partagé (recommandé) vs un par add-on** : partagé. Le serveur n'a pas besoin de dépôt, la requête est groupée (max 20 produits : `CheckRequest:117`) et le cache est unique. Seul le mode GitHub a besoin du dépôt par add-on (`registerRepository`).

**`init_updaters()`** (`:1767-1800`) :
```php
// après
$use_lus = LicenseProviderFactory::isServerMode();
foreach ( $this->available_addons as $addon ) {
    if ( 'installed' === $this->get_addon_status( $addon['slug'] ) && ( $use_lus || ! empty( $addon['github_repo'] ) ) ) {
        $github_repo = $this->extract_github_repo( (string) ( $addon['github_repo'] ?? '' ) );
        $this->updaters[ $addon['slug'] ] = new \Form_Quizz_FQI3\Plugin_Updater( $plugin_file, $github_repo, 'Webpixelia' );
```
Add-ons sans `github_repo` : en mode serveur ils sont enregistrés ; s'ils ne sont pas dans la licence, LUS répond `not_covered` (aucun effet, vérifié).

**`install_addon()`** : le bloc « token GitHub + `releases/latest` + `wp_remote_get` + `file_put_contents` » (≈ 100 lignes) devient :
```php
$provider = LicenseProviderFactory::make();
$package  = $provider->resolveDownload( $slug, '0.0.0' );      // jeton frais, jamais en cache
if ( null === $package ) { /* WP_Error avec ServerLicenseProvider::messageForCode( code ) */ }
$tmp_file = \Form_Quizz_FQI3\Plugin_Updater::download_verified( $package['url'], $package['sha256'], $package['headers'], $is_server );
```
La suite (filtre `rename_package_source`, `Plugin_Upgrader::install`, `activate_plugin_in_directory`) est inchangée. En mode GitHub, le token reste exigé et le dépôt est enregistré. Changement de comportement assumé : asset `.zip` plutôt que `zipball_url` (aligné sur l'updater).

**Autres corrections du même patch** : `get_addon_update_info()` utilise `get_latest()` (tableau, en cache) au lieu de `force_check()` (fatal + appel réseau à chaque page) ; `force_check()` ne retourne plus `false` sous un type `object` ; `fqi3_get_admin_pages()` utilise `fqi3_has_distribution_access()` (token GitHub **ou** mode serveur).

**Points d'attention** : la page Add-ons doit aussi décider « Coming Soon » sans `github_repo` (`:693`) en mode LUS ; voir Questions. `refresh_addon_cache()` appelle bien `clear_cache()` → `provider->clearCache()` désormais.

---

## Préparation L6.3

Squelette complet et linté : `L6.3-license-settings-skeleton.php`.

### Écran de licence
**Structure** (section existante `fqi3_license_section`) : clé masquée (`••••••••` + 4 derniers caractères, ou « défini dans wp-config.php »), statut, champ de saisie + **Activer**, **Vérifier le statut**, zone de message (`aria-live`), identifiant d'instance affiché (aide au support). Le champ GitHub n'est affiché qu'en mode GitHub / si le token est défini.

**Flux**
- *Activer* : AJAX `fqi3_license_save` → format `^[A-Z0-9]{2,10}(-[A-Z0-9]{5}){5}$` → stockage → `POST /v1/licenses/activate` → statut. **En cas d'échec, l'ancienne clé est restaurée** (une faute de frappe ne casse pas une licence qui marche).
- *Vérifier* : AJAX `fqi3_license_validate` → `POST /v1/licenses/validate` (`product` obligatoire : `form-quizz-fqi3`) → `activated`, `expires_at`, `instances.used/max`.
- Mapping des états : 200 + `activated` → **actif** ; 200 + `!activated` → **non activé sur ce site** ; 403 `license_expired` → **expiré** ; 403 `license_invalid` → **invalide** (inclut révoquée, suspendue, produit non couvert : F-01 de l'audit LUS) ; réseau/5xx/429 → état précédent conservé + message ; jamais vérifié → **inconnu**.

**Stockage** : constante `FQI3_LICENSE_KEY` prioritaire (champ lecture seule) ; sinon option `fqi3_license_key`, `autoload = false`, **hors** `fqi3_options` (cette option alimente les whitelists de l'API REST) ; en multisite `site_option`. Texte brut en base (comme la plupart des plugins) : recommander la constante en production.

**Sécurité** : `check_ajax_referer('fqi3_license_action')`, capability `manage_options` (`manage_network_options` en multisite), `sanitize_text_field(wp_unslash())` + regex stricte, champ **sans attribut `name`** (le bouton « Save settings » ne l'envoie pas), verrou 5 essais / 10 min / utilisateur (LUS bloque l'IP à 10 échecs/h : un utilisateur qui s'acharne bloquerait aussi les autres sites de l'hébergement), clé jamais renvoyée au navigateur (masque seulement).

**Statut sans bloquer la page** : l'écran ne fait **aucune requête HTTP**. Il lit `fqi3_license_status`, alimenté par les checks d'arrière-plan (cache 12 h, écriture ≤ 1/h) et par le bouton. Afficher la date de dernière vérification, et un avertissement si > 12 h.

**Migration** : sites actuels (GitHub) : aucun changement tant que `FQI3_LICENSE_PROVIDER` n'est pas défini. Bascule d'un site : (1) définir `FQI3_LICENSE_PROVIDER='server'` + clé ; (2) l'activation se fait seule (constante) ou via l'écran ; (3) vérifier la mise à jour ; (4) retirer `GITHUB_PERSONAL_TOKEN`. Ordre recommandé côté LUS : E-08 (signature) **avant** la bascule des sites clients (L9).

---

## Risques et cas limites

| Cas | Comportement attendu | Comportement L6.1 | Correction (patchs) |
|---|---|---|---|
| `server` sans `FQI3_LICENSE_KEY` | Fallback GitHub, 1 warning, message admin | Fallback + `error_log` à chaque `make()` | Warning unique ; écran de licence signale « aucune clé » |
| LUS 403 (invalide/expirée) | Aucune mise à jour, statut « invalide/expiré », 1 requête / 12 h | 1 requête à chaque rafraîchissement ; blocage IP à 10 | Cache négatif 12 h, `fqi3_license_status` |
| LUS injoignable / 500 | Silence, aucune attente répétée, reprise automatique | 10 s de timeout à chaque appel | Timeout 6 s, cache 15 min ; état de licence conservé |
| 429 | Respecter `Retry-After` | Traité comme un échec quelconque | Cache = `Retry-After` plafonné à 1 h |
| Clé régénérée (ancienne révoquée) | « Invalide » + message pour saisir la nouvelle | Idem 403 sans message | Statut `invalid` ; `clearCache()` au changement de clé (clé de cache dérivée de la clé) |
| Site non activé (409) | Activation auto si clé en constante, sinon message + bouton | `null` silencieux | `fetch()` auto-active (1 essai/h) ; sinon statut `inactive` |
| Add-on avec `github_repo` sans produit LUS | Pas de mise à jour, pas d'erreur | N/A (jamais branché) | `not_covered` ignoré ; `install_addon` : `product_not_found`/`license_invalid` traduit en message |
| Quota d'instances atteint | Message clair | N/A | `instance_limit_reached` → statut `inactive` + message |
| Multisite | Instance = domaine du réseau, clé réseau, super-admin seul | `home_url()` du sous-site | `network_home_url()`, `site_option`, `manage_network_options` |
| Site Local / staging | Ne pas consommer d'activation | Consomme | `FQI3_LICENSE_INSTANCE` |
| Version non conforme d'un add-on | Ignorer cet add-on | Lot entier en 422 | Filtrage client (même regex que `VersionComparator::PATTERN`) |
| LUS en `http://` en production | Refus | Accepté (clé en clair) | `insecure_url` hors local/dev |
| Paquet renvoyé sur un autre hôte | Refus | Accepté | `bad_package` |
| Zip corrompu / `sha256` absent | Refus, fichier supprimé | Pas de contrôle | `fqi3_sha256_mismatch` / `fqi3_sha256_missing` |
| WP-Cron / WP-CLI | Entrée du core conservée | Filtre absent hors admin | `is_admin() \|\| wp_doing_cron() \|\| WP_CLI` |
| Page Add-ons sans token GitHub | Visible en mode serveur | Masquée | `fqi3_has_distribution_access()` |
| Port non standard (LUS local `:8000`) | Téléchargement possible en dev | N/A | À configurer : `wp_safe_remote_get` n'accepte que 80/443/8080 par défaut (filtre `http_allowed_safe_ports`) et refuse les hôtes locaux/privés (`http_request_host_is_external`) |

---

## Tests à ajouter

### Manuels (Local by Flywheel)
Prérequis commun : LUS joignable en HTTPS (ou `FQI3_LUS_ALLOW_INSECURE` + `WP_ENVIRONMENT_TYPE=local`), URL présignée MinIO joignable depuis PHP (cf. E-05 de l'audit : hôte ≠ `127.0.0.1`), produit + licence créés, deux releases publiées (N et N+1).

| # | Prérequis | Action | Attendu |
|---|---|---|---|
| M1 | Mode serveur, clé en constante, site non activé | Ouvrir *Tableau de bord → Mises à jour* | Activation auto, mise à jour du core proposée |
| M2 | Idem | Attendre > 10 min puis cliquer « Mettre à jour » | Succès (jeton frais) |
| M3 | Idem | Altérer le zip en stockage | Échec « checksum mismatch », plugin intact |
| M4 | Clé en option | Écran licence : clé valide → Activer | Statut actif, instances 1/N |
| M5 | Clé en option | Faute de frappe | Message clair, ancienne clé conservée |
| M6 | Licence révoquée côté Filament | « Vérifier le statut » ; ouvrir 5× la page Mises à jour | Statut invalide ; 1 seule requête LUS (logs) |
| M7 | LUS arrêté | Recharger admin et page Mises à jour | Pas d'erreur, pas de lenteur au-delà de 6 s, 1 tentative/15 min |
| M8 | Sans `GITHUB_PERSONAL_TOKEN`, add-on privé couvert | Page Add-ons → Installer | Installé et activé ; page Add-ons visible |
| M9 | Add-on non couvert par la licence | Installer | Message licence, rien d'installé |
| M10 | Mode `github` (défaut) avec token | Mise à jour core + install add-on | Comportement historique inchangé |
| M11 | Cron | `wp cron event run wp_update_plugins` puis page Plugins | L'avis de mise à jour du core est toujours là |
| M12 | Multisite (sous-domaines) | Check depuis sous-site et réseau | Même instance (domaine du réseau) ; un seul site activé |
| M13 | Notice de mise à jour d'add-on | Page Add-ons avec update dispo | Pas de fatal, pas d'appel réseau à chaque affichage |

### Automatisés
- **Fait ici (à réutiliser)** : `harness/run.php` + `harness/stubs.php` : faux LUS en mémoire, horloge simulée, 28 scénarios (S1-S14) ; `php harness/run.php b`. Portable en PHPUnit dans `tests/unit/` (le `bootstrap.php` actuel n'a ni transients ni `wp_remote_*` : ajouter les stubs de `stubs.php`).
- **Tests d'intégration** : script `smoke.sh` existant + faux serveur (`php -S` avec un routeur reprenant `FakeLus`) pour `wp_remote_*` réels ; ou contre le LUS de staging avec une licence de test.
- **Contrat** : un test qui appelle le vrai `/v1/wp/check` du dépôt LUS (Laravel) et passe la réponse à `ServerLicenseProvider` (détecterait B1 immédiatement). Dans le dépôt LUS, un test de contrat « clés de `plugins` = basenames envoyés ».
- **Régression** : (a) GitHubLicenseProvider avec token + asset `.zip` (URL API) → `download_private_package` inchangé ; (b) `Plugin_Updater` 3 arguments ; (c) `force_check()` ne retourne jamais `false` ; (d) `get_addon_update_info()` avec un `stdClass` ; (e) aucun appel HTTP au rendu de la page Add-ons ni de l'écran de licence.

---

## Questions ouvertes (à trancher avant L6.2): docs/12-DECISIONS-L6.md

1. **Activation automatique** quand la clé est en constante : retenue (filtre `fqi3_lus_auto_activate` pour la couper). Elle consomme un quota sans clic : acceptable ?
2. **sha256 absent côté GitHub** : accepté (dev). Faut-il une constante `FQI3_REQUIRE_SHA256` pour l'imposer en staging ?
3. **Marqueur de `package`** : valider sur Local (voir §L6.2). Sinon, repli à prévoir.
4. **Multisite** : licence par réseau (retenu) ou par sous-site ? L'architecture dit « domaine du réseau » ; confirmer côté produit (quota).
5. **Page Add-ons en mode LUS** : « Coming Soon » vient de `github_repo` vide. Source de vérité souhaitée : un champ `'distribution'` par add-on, ou la liste `license.products` renvoyée par `validate` (mise en cache avec le statut) ?
6. **Sites de dev / Local** : `FQI3_LICENSE_INSTANCE` suffit-il, ou prévoir côté LUS un type d'instance « dev » hors quota ?
7. **Interface** : OK pour remplacer `downloadUrl()` par `resolveDownload()` (jamais publiée) ?
8. **Signature (E-08)** : numéro de la première version du plugin qui vérifie ; `download_verified()` est le point d'insertion.
9. **Côté LUS (doc 10)** : `WpInfoController` ignore le canal et renvoie un `download_link` factice (M-05) ; le provider ne s'en sert plus. Le 403 `license_invalid` regroupe plusieurs causes (F-01) : l'écran dira « invalide, révoquée ou non couverte ».

## Fichiers livrés
- `fqi3-L6.2a-providers.patch` : correctifs L6.1 + interface étendue (`patch  -p1` à la racine du plugin, dans cet ordre)
- `fqi3-L6.2b-updater-addons.patch` : `Plugin_Updater`, `FQI3_Addons_Page`, garde de la page Add-ons, démarrage cron
- `L6.3-license-settings-skeleton.php` : squelette de l'écran de licence
- `harness/` : faux serveur LUS et scénarios
