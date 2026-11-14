# Décisions L6 (FQI3 ↔ LUS)

Statut : **proposées le 2026-11-14, valables tant que tu ne les contredis pas.** Elles complètent `docs/04-SUIVI-LOTS.md` (L6) et `docs/10-AUDIT-LUS.md` (DEC-01 à 03).

| ID | Question | Décision | Pourquoi / conséquence |
|---|---|---|---|
| D-L6-01 | Activation quand la clé est en constante | **Automatique**, 1 tentative/h, désactivable (`add_filter('fqi3_lus_auto_activate','__return_false')`). Clé saisie dans l'écran : activation manuelle (bouton). | Avec une constante il n'y a aucun écran où cliquer ; sans cela 409 silencieux. |
| D-L6-02 | `sha256` absent côté GitHub | Accepté en mode GitHub (dev, comportement historique). **Refusé** en mode serveur (fail closed). Pas de constante `FQI3_REQUIRE_SHA256` pour l'instant. | Le serveur fournit toujours le hash. |
| D-L6-03 | `package` du transient | Marqueur `{serveur}/v1/download/pending` ; vrai jeton demandé dans `upgrader_pre_download`. | Le jeton LUS vit 10 min et sert une fois. **À valider par le test M2** ; sinon repli : URL réelle + rafraîchissement du transient. |
| D-L6-04 | Multisite | **Une licence par réseau** : instance = `network_home_url()`, clé en `site_option`, réservée au super-admin. | Cohérent avec l'architecture §8. |
| D-L6-05 | Add-ons installables en mode LUS | Tout add-on `available => true` est tenté ; LUS refuse s'il n'est pas couvert, avec un message clair. Affinage (liste `license.products` du `validate`) en L6.3. **Reste à faire en L6.3** : la condition « Coming Soon » (`class-add-ons.php:693`) dépend encore de `github_repo`. | Évite d'exposer un état licence dans L6.2. |
| D-L6-06 | Sites Local / staging | `FQI3_LICENSE_INSTANCE` pour ne pas consommer d'activation (ex. `dev.webpixelia.com`). Pas de type d'instance « dev » côté LUS. | Aucun changement serveur. |
| D-L6-07 | Interface | `downloadUrl()` remplacée par `resolveDownload()` ; ajout de `getId`, `getLastError`, `clearCache`. | Interface jamais publiée. |
| D-L6-08 | Signature Ed25519 (E-08) | Point d'insertion = `Plugin_Updater::download_verified()`. Première version « vérifiante » : **3.8.0** (proposé). Hors L6.2. | Suit DEC-02 : le serveur ne bloque personne. |
| D-L6-09 | Côté LUS | Rien à changer pour L6.2. À traiter plus tard : M-05 (`/wp/info`), F-01. | Le client n'utilise plus `download_link`. |
| D-L6-10 | Chargement des classes | Les 4 providers sont `require_once` en tête de `class-plugin-updater.php` (pas dans `loadDependencies()` comme le disait le doc 09 §3.3). | Un seul point, pas de chargement inutile sur le front. |
| D-L6-11 | Hooks d'updater | Core enregistré aussi en WP-Cron et WP-CLI. | Sinon l'entrée du core disparaît de `update_plugins` après le cron. |

À reporter dans le doc 09 : corriger §1.2 (flux avec marqueur + jeton frais), ajouter l'étape d'activation, `FQI3_LICENSE_INSTANCE`, `FQI3_LUS_ALLOW_INSECURE`, multisite.
