# Suivi d'audit – form-quizz-fqi3 (v3.3.0)

Dernière mise à jour : 3 novembre 2026

Branche de travail : `audit/phase-a-b` (commit `3f89f59, phases A à D + version 3.4.0`, basé sur `c71118b` – Release 3.3.0)

Légende : ☑ fait et vérifié · ◐ fait, à valider en conditions réelles · ☐ à faire · ⏸ décision requise

## 1. Vue d'ensemble

- Sévérités : CRITICAL (vulnérabilité majeure), HIGH (priorité), MEDIUM (amélioration significative), LOW (qualité).
- Périmètre audité : plugin principal. Les addons seront audités ensuite (dépôts à fournir).
- Lint PHP 8.3 : OK sur tous les fichiers. Aucun test n'existait dans le dépôt avant l'audit.
- Limite : aucun environnement WordPress/MySQL disponible côté audit. Les tests d'intégration sont à lancer chez toi.

## 2. Phase A – Sécurité sans impact fonctionnel

☑ **C1 – Export CSV des questions sans contrôle** (`class-view-questions.php`, `export_questions`). Ajout d'une vérification de droits (nouvelle méthode `FQI3_Backend::user_can_manage_questions()`) et d'un nonce `fqi3_export_questions` dans le formulaire.

☑ **H6 – Suppression de question sans nonce (CSRF)**. Nonce `fqi3_delete_question` vérifié côté serveur et envoyé par `admin-script.js`.

☑ **H7 – Nonce sans permission**. Capability ajoutée sur : ajout de question, mise à jour de question, validation AJAX d'ajout, révocation du token API (`manage_options`), envoi de l'e-mail de test (`manage_options`).

☑ **H8 – IDOR sur l'attribution de badges** (`FQI3_Awards::handle_award_badges`). Le `user_id` envoyé par le client est ignoré : seul l'utilisateur connecté est pris en compte.

◐ **H4 – Sauvegardes et rapports d'import lisibles depuis le web**. Dossiers `fqi3_backups` et `fqi3_reports` protégés (`index.php` + `.htaccess`), noms de fichiers non devinables. Sur nginx, `.htaccess` est ignoré : ajouter une règle `deny` pour ces deux dossiers.

## 3. Phase B – Sécurité des données

◐ **H3 – Import : perte de données possible**.

- `TRUNCATE` (commit implicite, rollback inopérant) remplacé par `DELETE FROM` (3 endroits).
- Vraie sauvegarde des données existantes avant import (l'ancienne sauvegardait le fichier importé). L'import est annulé si la sauvegarde échoue.
- `catch (\Throwable)` au lieu de `\Exception`.
- Questions : `options` normalisé en JSON valide (≥ 2 réponses), `answer` borné aux options. Anciennement, un tableau était sérialisé PHP dans une colonne JSON.
- Réglages importés : sanitisation récursive (plus de `TypeError` sur les tableaux). Le token API courant est conservé.
- Vérifié : 14 tests de logique (harnais PHP avec fonctions WP simulées). Non vérifié : comportement transactionnel sur MySQL réel.

☑ **H10 – Désactivation destructive**. `deactivate()` ne supprime plus le rôle `premium_member` ; il est retiré dans `uninstall.php` uniquement si « supprimer les données » est coché. `restrictAdminAccess` ne redirige plus un utilisateur qui a `manage_options` et utilise `wp_doing_ajax()`.

☑ **LOW – Code mort supprimé** : `or_sanitize_import_data` et `sanitize_levels` (aucun appelant).

## 4. Changements de comportement à connaître

- L'import ne remet plus l'auto-incrément des questions à zéro (`DELETE` au lieu de `TRUNCATE`) : les anciens ID ne sont jamais réutilisés.
- Un import de réglages ne remplace plus le token API du site.
- Les rôles Premium restent après désactivation du plugin.
- Les anciens fichiers de `fqi3_backups` et `fqi3_reports` restent en place ; seul le dossier est protégé dès le prochain import.
- L'export des questions et la création/modification de question exigent maintenant le rôle autorisé dans les réglages d'accès (en plus de `edit_posts`).

* Phase C : un quiz démarré consomme un essai (d'après le code, l'ancien compteur pouvait en consommer deux) ; les compteurs repartent de zéro après la mise à jour.
* Phase C : un visiteur déconnecté ne peut plus ouvrir un niveau réservé aux connectés.
* Phase C : le token GitHub ne se saisit plus dans l'admin, la constante `GITHUB_PERSONAL_TOKEN` dans `wp-config.php` est requise.
* Phase C : le nombre de questions par quiz suit le réglage (10 en dur avant), les statistiques mensuelles tournent enfin.

- Phase D : les CSS/JS du front ne sont chargés que sur les pages qui utilisent le plugin (filtre `fqi3_load_frontend_assets` pour forcer). Les addons qui utilisent `fqi3Data` ou `fqi3-script` sans passer par ces handles sont à tester.
- Phase D : Chart.js et confetti sont servis par le plugin, plus de CDN sur les pages publiques.

## 5. À tester chez toi (WordPress réel)

☐ Abonné connecté : `wp-admin/admin-post.php?action=export_questions` doit refuser (403). ☐ Administrateur : bouton « Export CSV » fonctionne. ☐ Suppression d'une question depuis la liste. ☐ Ajout et modification d'une question (rôle autorisé, puis rôle non autorisé). ☐ Import d'un JSON valide : questions et réglages importés, token API conservé. ☐ Import d'un JSON invalide (options absentes, answer hors limites) : aucune donnée perdue, message d'erreur clair. ☐ Un fichier `backup_*.json` apparaît dans `uploads/fqi3_backups/` avec `.htaccess` et `index.php`, et n'est pas accessible par URL. ☐ Désactiver puis réactiver le plugin : les membres Premium gardent leur rôle. ☐ Quiz terminé par un utilisateur connecté : badges toujours attribués. ☐ Révocation du token API et e-mail de test depuis l'admin.

Tests phase C :

☐ Visiteur déconnecté : demander un niveau réservé aux connectés est refusé ; un niveau gratuit fonctionne.

☐ Abonné avec limite 3 : exactement 3 quiz par jour, le 4e refusé ; compteur affiché cohérent ; remis à zéro le lendemain.

☐ Écran Licence : état du token affiché, aucun champ de saisie ; add-ons et mises à jour fonctionnent avec la constante définie.

☐ Réglage « Number of questions per quiz » (par exemple 15) : le quiz reçoit bien 15 questions.

☐ `wp cron event list` : événements hebdomadaire et mensuel présents ; absents après désactivation.

☐ Supprimer d'éventuels `wp-config.php.backup.*` laissés par l'ancienne version.

Tests phase D :

☐ Page sans shortcode : aucun `front-script.js`, `front-style.css` ni `chart.umd.min.js` dans le code source, aucune requête vers jsDelivr côté public.

☐ Page avec `[fqi3_quizz_form]` (puis avec le bloc Gutenberg) : quiz, confettis et styles fonctionnent.

☐ Pages avec `[fqi3_user_statistics]` et `[fqi3_periodic_stats]` : graphiques affichés.

☐ Shortcode placé dans un widget, un template ou Elementor : styles présents (sinon utiliser le filtre).

☐ Chaque addon actif : ses pages front fonctionnent toujours.

☐ Site neuf : une tentative de quiz d'un connecté s'enregistre, `wp option get fqi3_attempts_table_version` renvoie 1.

## 6. Phase C – Corrections après validation

Décisions de Jonathan : tout validé sauf H9, conservé tel quel (voulu).

◐ **C2 – Niveaux réservés aux connectés accessibles aux visiteurs** (`FQI3_Frontend::handleGetQuestions`). Un visiteur non connecté ne peut plus demander qu'un niveau marqué « gratuit » ; sinon la requête est refusée côté serveur. Les connectés ne sont pas restreints. Vérifié : 4 tests de logique. À confirmer en conditions réelles (requête hors connexion sur un niveau réservé).

◐ **H5 – Écriture du token GitHub dans `wp-config.php`**. Bouton, AJAX `fqi3_save_github_token`, écriture et lecture par regex du fichier supprimés. L'écran Licence affiche l'état de la constante `GITHUB_PERSONAL_TOKEN` (4 derniers caractères) et la ligne à ajouter. Les sites où la constante existe déjà continuent de fonctionner. Les fichiers `wp-config.php.backup.*` laissés par l'ancienne version sont à supprimer à la main.

☑ **H9 – Export des tentatives (noms et e-mails)**. Conservé tel quel : accès `edit_posts` + rôles autorisés, voulu. Aucune modification.

◐ **H11 – Statistiques mensuelles jamais exécutées**. Événements uniques chaînés (chaque exécution planifie la suivante) à la place d'une récurrence `monthly` inexistante ; schedule `weekly` déclaré pour WordPress < 5.4 ; les 4 événements cron sont supprimés à la désactivation et à la désinstallation. Non testé avec WP-Cron réel.

◐ **H13 – Cache des questions**. Clé de cache par utilisateur et par nombre de questions ; le `LIMIT` suit maintenant le réglage « Number of questions per quiz » (10 en dur avant).

◐ **Limite quotidienne**. Compteur côté serveur pour les connectés (user meta `fqi3_daily_quiz_count`, date du site), test `<` au lieu de `<=`, l'appel AJAX `increment_quiz_count` ne compte plus pour les connectés (d'après le code, il doublait le décompte), cookie `Secure` seulement en HTTPS. Vérifié : 9 tests de logique. Périmètre inchangé : limite appliquée au rôle `subscriber` uniquement.

## 7. Phase D – Performance

◐ **H12a – Table des tentatives** (`ensure_attempts_table`). Plus de `CREATE TABLE IF NOT EXISTS` à chaque requête : l'option `fqi3_attempts_table_version` mémorise la création. Si l'insertion échoue (table supprimée), la table est recréée une fois et l'insertion relancée. L'option est supprimée à la désinstallation. Vérifié : 3 tests de logique.

◐ **H12b – Chargement des assets front**. Scripts et styles restent enregistrés partout (les addons qui en dépendent continuent de fonctionner) mais ne sont chargés que sur les pages qui contiennent un shortcode ou un bloc du plugin. Les rendus via constructeur de pages, widget ou template chargent leurs assets au moment du rendu. Nouveau filtre `fqi3_load_frontend_assets` pour forcer le chargement. Chart.js et la requête des statistiques ne s'exécutent que lorsqu'un graphique est affiché. Vérifié : 7 tests de détection. Non testé dans un navigateur.

◐ **CDN**. Chart.js 4.5.1 (jusqu'ici non versionné) et canvas-confetti 1.9.3 sont embarqués dans `assets/vendor/` (licences MIT et ISC jointes, mise à jour décrite dans `assets/vendor/README.md`). Bootstrap, Bootstrap Icons et Driver.js (admin uniquement) restent chargés depuis jsDelivr en versions épinglées, sans SRI.

Risque connu : pour un shortcode rendu par un constructeur de pages, les styles arrivent en pied de page (flash de contenu non stylé possible). Le filtre `fqi3_load_frontend_assets` permet de les charger dans l'en-tête.

## 8. Phase E – Qualité, tests, nettoyage

☐ Mise en place PHPUnit (tests unitaires purs exécutables ici ; intégration WP/REST/AJAX à lancer chez toi) et PHPCS (WordPress-Extra). ☐ Tests prévus : permissions des handlers, import (valide/invalide/rollback), REST (token, scopes, rate-limit), limite quotidienne. ☐ `save_user_performance` : bornes (0 ≤ bonnes réponses ≤ total) et mise à jour atomique (UPSERT). ☐ Scores envoyés par le client : bornage, puis réflexion sur une validation serveur. ☐ Token API : hash au lieu de AES-CBC non authentifié avec clé en base (si réaffichage inutile). ☐ IP : ne pas faire confiance à `X-Forwarded-For` par défaut (rate-limit REST, logger API, log d'import). ☐ Installateur d'addons : utiliser le dossier renvoyé par l'upgrader au lieu de renommer par recherche de sous-chaîne ; corriger `$github_username` non défini ; `activate_plugins` pour activer/désactiver ; échapper les versions affichées. ☐ Schéma : `dbDelta` sans `IF NOT EXISTS`, version de schéma, multisite, cohérence README (MySQL 5.6 vs colonne JSON). ☐ `uninstall.php` : ajouter `fqi3_quiz_attempts`, options manquantes, événements cron, dossiers de backups, multisite. ☐ JS : remplacer les `innerHTML` par `textContent` (résultats du quiz, messages admin). ☐ CSV : neutraliser les formules, retirer l'échappement HTML, ajouter `ORDER BY` à la pagination. ☐ Session PHP (`session_start`) côté front : remplacer par transients ou meta utilisateur. ☐ Divers : `class-abstract-settings.php` inexistant dans `loadDependencies`, textes français en dur (JS et PHP), `wp_redirect` → `wp_safe_redirect`, `unserialize` dans `FQI3_Awards`, version écrite à trois endroits, `Tested up to`, README et `composer.json`.

◐ `uninstall.php` : table `fqi3_quiz_attempts`, compteurs quotidiens et événements cron ajoutés ; options manquantes et multisite restent à faire.

☑ `README.md` aligné sur le code (shortcodes réels, MySQL 5.7.8+/MariaDB 10.2.7+, pas de build NPM, modèle d'accès, sécurité, tâches cron, notes de mise à jour 3.3.1). Reste à décider : `readme.txt` indique « Requires at least: 5.6 » alors que l'en-tête du plugin indique 5.0 ; entrée de changelog 3.3.1 à écrire à la release.

◐ **Version 3.4.0** : en-tête du plugin, constante `FQI3_VERSION`, `Stable tag` de `readme.txt`, entrée de changelog (affichée aussi dans la page Changelog du plugin) et notes de mise à jour du README. À compléter avec les résultats de la phase E avant la release.

☐ Traductions : les nouvelles chaînes (messages d'import, « Please log in to access this level. », écran Licence, « Once Weekly », etc.) ne sont pas dans les `.pot`/`.po` FR et ES : régénérer avec `wp i18n make-pot`.

☐ `readme.txt` : « Tested up to » (6.8.2) diffère de l'en-tête du plugin (6.7.1), et « Requires at least » (5.6) diffère de l'en-tête (5.0). À aligner après tests.

## 9. Non relu en détail

☐ `FQI3_Emails`, `FQI3_PeriodicStatistics`, `FQI3_Blocks`, classes de réglages (sanitisation), `class-plugin-updater`, `class-api-logger`, reste de `FQI3_Api`, templates, CSS, éditeur de blocs.

## 10. Addons

☐ Dépôts à fournir, puis audit selon la même méthode (Webhooks, Lead Scoring, Question Bank, WooCommerce, Certificates, Multilingual, Email Marketing, Affiliate, Social Login, Subscription, Notification).

## 11. Journal des modifications

- 2 nov. 2026 – Audit du plugin principal, 13 points HIGH/CRITICAL identifiés.
- 2 nov. 2026 – Phases A et B appliquées sur la branche `audit/phase-a-b` (11 fichiers, lint OK, 14 tests de logique OK).

* 2 nov. 2026 – Phase C validée (H9 conservé, voulu), puis appliquée (commit 83f8eaa) : C2, H5, H11, H13, limite quotidienne ; 9 tests de logique OK.
* 2 nov. 2026 – README.md aligné sur le code (commit d5b358d).

- 3 nov. 2026 – Phase D appliquée (commit a78600f) : table des tentatives, chargement conditionnel des assets, Chart.js et confetti embarqués. 10 tests de logique OK.
- 3 nov. 2026 – Version 3.4.0, changelog et notes README (commit 3f89f59). Patch livré : `fqi3-audit-phase-d.patch`, à appliquer sur l'état A+B+C.
