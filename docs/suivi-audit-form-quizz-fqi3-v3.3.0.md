# Suivi d'audit – form-quizz-fqi3 (v3.3.0)

Dernière mise à jour : 3 novembre 2026

Branche de travail : `audit/phase-a-b` (commit `5073731, phases A à E + version 3.4.0`, basé sur `c71118b` – Release 3.3.0)

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

* Phase E : l'adresse IP utilisée par la limitation REST et les journaux est celle de la connexion directe. Derrière un proxy ou un CDN, déclarer `fqi3_trusted_proxies`, sinon tous les visiteurs partagent la même limite.
* Phase E : les nouveaux tokens API utilisent un nouveau format de chiffrement ; changer les salts de `wp-config.php` invalide le token (à régénérer). Les anciens tokens restent valables.
* Phase E : la limite de requêtes REST est maintenant exacte (elle comptait double) : les clients peuvent faire deux fois plus de requêtes qu'avant avec le même réglage.
* Phase E : un score hors 0–100 est ramené dans cet intervalle et un niveau inconnu est refusé.

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

Tests phase E (les 39 tests unitaires et les 23 vérifications d'intégration passent déjà sur l'environnement de test) :

☐ Lancer `phpunit` puis `tests/integration/smoke.sh` sur votre environnement de test (voir `tests/README.md`).

☐ Derrière un proxy ou un CDN : déclarer `fqi3_trusted_proxies`, puis vérifier la limitation REST et le journal API.

☐ Token : générer un token et appeler l'API ; vérifier qu'un token créé avant 3.4.0 fonctionne toujours.

☐ Installer un addon depuis un vrai dépôt GitHub avec votre token ; vérifier que le dossier porte le bon slug.

☐ Import/export avec un export réel de production, sur une copie : questions, niveaux gratuits, statistiques.

☐ Quiz de bout en bout dans un navigateur (confettis, tableau des réponses incorrectes).

☐ Désinstaller sur une copie avec « supprimer les données » coché.

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

## 8. Phase E – Robustesse, tests et nettoyage

Un environnement réel a été monté pour cette phase (WordPress 6.8.2, MariaDB 10.11, WP-CLI, plugin activé) : les phases A à C et les corrections de la phase E ont été vérifiées en conditions réelles, pas seulement par lecture du code.

☑ **Tests livrés** : 39 tests unitaires PHPUnit (`tests/unit/`, sans WordPress) et un test d'intégration `tests/integration/smoke.sh` (23 vérifications sur un vrai WordPress : droits des handlers, limite quotidienne, import, API REST, cron, dossiers protégés, chargement des assets). Tout passe, sans aucun warning PHP du plugin. Une régression volontaire est bien détectée. Configuration PHPCS ajoutée (`.phpcs.xml.dist`) : le standard complet remonte surtout des remarques de style (environ 34 000, corrigeables automatiquement) ; le sous-ensemble sécurité est décrit dans `tests/README.md`.

☑ **Statistiques** (`save_user_performance`) : une seule requête atomique (`INSERT … ON DUPLICATE KEY UPDATE`) et valeurs bornées. Vérifié sur MariaDB, y compris 12 quiz terminés en parallèle pour le même utilisateur et niveau : 12 quiz, 48 questions, 24 bonnes réponses, aucun écart.

☑ **Scores envoyés par le navigateur** : bornés à 0–100, niveau obligatoire parmi les niveaux configurés. Vérifié (9999 devient 100, -50 devient 0, « abc » devient 0, niveau inconnu refusé).

☑ **IP** : `fqi3_get_client_ip()` ignore `X-Forwarded-For` sauf derrière un proxy déclaré via le filtre `fqi3_trusted_proxies` (limitation REST, journal API, journal d'import). Vérifié.

☑ **Token API** : AES-256-GCM avec une clé dérivée des salts WordPress. Les anciens tokens (AES-CBC) restent lisibles, une altération est détectée. Vérifié de bout en bout avec l'API REST (token valide 200, invalide 401).

☑ **Bug trouvé par les tests réels : limitation REST**. Une requête consommait deux créneaux (limite effective moitié de celle configurée). Corrigé : limite 3 donne exactement 3 requêtes, un `X-Forwarded-For` usurpé n'a plus d'effet.

☑ **Bug trouvé : import des niveaux**. L'import passait par le sanitizer du formulaire, qui supprimait le drapeau « gratuit » du dernier niveau (et produisait des warnings PHP). Corrigé, et `fqi3_get_free_quiz_levels()` tolère les valeurs manquantes.

☑ **Bug trouvé : statistiques périodiques**. L'enregistrement pour un utilisateur lisait et dupliquait les lignes de tous les utilisateurs. Corrigé (`AND user_id = %d`), 2 tests.

◐ **Installateur d'addons** : le dossier extrait est renommé par `upgrader_source_selection` (plus de renommage d'un dossier quelconque), versions échappées, variable `$github_username` non définie supprimée. Vérifié avec un zip local au format GitHub (dossier renommé, plugin tiers intact). Non testé avec un vrai dépôt GitHub privé.

◐ **JS** : tableau des réponses incorrectes construit avec le DOM. Vérifié avec jsdom (balises affichées comme texte, aucune injection). Pas de test dans un vrai navigateur.

☑ **CSV** : cellules neutralisées contre les formules (questions et tentatives), plus d'échappement HTML dans le CSV, pagination avec `ORDER BY id`.

☑ **Désinstallation** : options, transients, table des tentatives, dossiers d'export et compteurs quotidiens supprimés si « supprimer les données » est coché ; événements cron toujours supprimés. Vérifié.

☑ **Divers** : redirections internes en `wp_safe_redirect`, `unserialize` avec `allowed_classes => false`. Analyse WPCS sécurité de tout le plugin : aucune injection SQL ni XSS réelle trouvée (SQL paramétré, seules les tables sont concaténées via `$wpdb->prefix`).

☑ **Version 3.4.0** : changelog complété avec la phase E (`readme.txt`, visible aussi dans la page Changelog du plugin) et README (filtre proxies, token, tests). Les en-têtes WordPress minimum 6.8 et testé jusqu'à 7.1.4 ont été alignés par Jonathan.

Reporté, avec la raison :

☐ `session_start()` côté front : le remplacer impose de repenser le stockage de l'état du quiz (tentative en cours, minuteur). Risque de régression élevé, à traiter dans une version dédiée avec tests navigateur.

☐ Schéma (`dbDelta` avec `IF NOT EXISTS`, version de schéma, multisite) : le fonctionnement actuel est correct (testé), mais toute modification demande une matrice de tests de mise à jour sur d'anciennes installations. Multisite ni supporté ni testé.

◐ Validation serveur des scores : traitée en phase F (voir section 8 bis). Reste hors périmètre : les bonnes réponses sont toujours envoyées au navigateur avec les questions (un utilisateur déterminé peut les lire) et `update_quiz_statistics` / `award_badges` reçoivent encore des valeurs du navigateur.

☐ Traductions : les nouvelles chaînes ne sont pas dans les `.pot`/`.po` FR et ES, à régénérer avec `wp i18n make-pot`.

☐ Mises à jour du plugin : pas de vérification d'intégrité (hash ou signature) des paquets téléchargés.

## 8 bis. Phase F – Retours de l'équipe de l'addon Leads CRM

Six points transmis par l'équipe de l'addon FQI3 Leads CRM, tous revérifiés dans le code 3.4.0. Les points 1 à 5 se confirment ; le 6 est exact mais l'explication est à corriger. Correctifs dans `FQI3_Frontend.php`, `class-plugin-updater.php`, `form-quizz-fqi3.php`, le JS front, le gabarit des résultats et `uninstall.php`.

◐ **1 (HIGH) – Début et fin de quiz sans contrôle**. Le serveur mémorise, dans la méta utilisateur `fqi3_open_attempt`, la tentative en cours et les questions servies (`handleGetQuestions`). La complétion est refusée sans tentative, sans questions servies, sur un autre niveau que celui servi, ou plus tôt que `fqi3_min_seconds_per_question` (1 s par question par défaut, filtrable, 0 désactive). La limite quotidienne n'est pas dupliquée : servir les questions la consomme déjà et chaque complétion en exige. `fqi3_register_quiz_start` applique aussi la limite quotidienne.

◐ **4 (MEDIUM) – Score calculé par le navigateur**. Le JS envoie `answers` (`{id de question: index choisi}`) ; le serveur recalcule le score à partir des bonnes réponses stockées et ignore `quiz_score`. Sans `answers` valide, la complétion est refusée. Limite assumée : les bonnes réponses sont toujours envoyées au navigateur (le JS corrige localement), donc un utilisateur déterminé peut les lire. Il doit tout de même faire servir les questions (essai consommé) et attendre la durée minimale.

◐ **2 (HIGH) – Hooks de complétion rejoués**. `completeAttempt` devient un seul `UPDATE … WHERE id = %d AND user_id = %d AND completed_at IS NULL` et renvoie vrai uniquement si cette requête a fermé la tentative (sûr contre le rejeu et les requêtes parallèles). `fqi3_quiz_completed` et `fqi3_quiz_attempt_completed` ne partent qu'alors, avec un `attempt_id` toujours > 0. L'`attempt_id` est ajouté en 3e argument de `fqi3_quiz_completed` et `fqi3_quiz_started` (les callbacks déclarés avec 2 arguments ne sont pas affectés).

◐ **3 (MEDIUM) – `fqi3_process_quiz_hooks` ouvert aux visiteurs**. Pour un utilisateur connecté, niveau et score viennent du résultat enregistré par le serveur (méta `fqi3_last_result`, consommable une fois) et les valeurs du navigateur sont ignorées. Pour un visiteur, le comportement est conservé (l'action reste disponible) mais l'action reçoit un 4e argument `$verified = false`. Le gabarit `quiz-results.php` déclenchait aussi cette action au rendu de la page avec des valeurs factices (niveau « Level not defined », score 0) : elle passe maintenant `$verified = false`. À transmettre à l'équipe Leads CRM : ne rien attribuer sur un appel non vérifié.

◐ **5 (LOW) – `fqi3_register_quiz_start`**. Niveau validé par `isKnownLevel`, `wp_unslash` appliqué, tentative ouverte réutilisée tant qu'aucune question n'a été servie (le niveau est réaligné), donc plus de ligne créée à chaque appel. Les actions de démarrage ne partent qu'à la création d'une tentative.

◐ **6 (LOW) – Intégration des addons**. Deux causes distinctes. (a) Ordre de chargement : WordPress trie les plugins actifs par chemin et `form-quizz-fqi3-xxx/` passe avant `form-quizz-fqi3/` (`-` avant `/`), même avec le bon nom de dossier. Les constantes du parent n'existent donc pas pendant l'inclusion du fichier d'un addon. Nouvelle action `fqi3_loaded` (sur `plugins_loaded`, priorité 5) et documentée dans la page développeur. (b) Nom de dossier : l'archive fournie (`form-quizz-fqi3-3.4.0/`) et le zipball GitHub ne donnent pas `form-quizz-fqi3/`. La mise à jour automatique du core renomme maintenant le dossier extrait (`upgrader_source_selection`, limité au plugin concerné). Pour une installation manuelle, il faut publier une archive de release dont le dossier racine est `form-quizz-fqi3/` : à faire côté processus de release (non couvert par le code).

Tests : `tests/unit/AttemptLifecycleTest.php` (22 tests : démarrage, liaison des questions, score serveur, rejeu, requête parallèle perdante, durée minimale, hook de résultat). Les stubs de `tests/bootstrap.php` ont été étendus. Lancés sous PHPUnit 9.6 : les 3 tests à data provider par attribut (`#[DataProvider]`) échouent sous 9.6 et exigent PHPUnit 10 (préexistant). Non testé : WordPress et MySQL réels, navigateur.

Changements de comportement : la complétion sans `answers` (client JS en cache, front-end tiers) est refusée ; les actions de démarrage ne partent plus à chaque appel ; une tentative ne peut plus être fermée deux fois.

À tester chez toi : ☐ quiz complet connecté (démarrage, questions, résultats, badges, ligne de la table des tentatives avec le bon score) ; ☐ rejouer la requête `fqi3_register_quiz_complete` : refusée, aucun hook ; ☐ requête de complétion sans passer par les questions : refusée ; ☐ abonné avec limite 3 : le 4e démarrage est refusé ; ☐ mise à jour du core depuis une release sans asset `.zip` : le dossier reste `form-quizz-fqi3/` ; ☐ un addon qui s'initialise sur `fqi3_loaded`.

## 9. Non relu en détail

◐ Relus depuis l'audit initial : `FQI3_Emails` (planification, destinataires), `class-plugin-updater` (requête de release GitHub), `class-api-logger` (IP), `FQI3_PeriodicStatistics` (bug corrigé), sorties des templates et des pages d'admin (échappement contrôlé par WPCS puis lecture des cas à données variables : aucun XSS trouvé). Non relus en détail : `FQI3_Blocks` (rendu), classes de réglages hors niveaux (sanitisation), pages `dashboard`, `developper` et `infos`, CSS, éditeur de blocs, réponses de chaque endpoint REST (contrôle d'accès et limitation testés, pas chaque réponse).

## 10. Addons

☐ Dépôts à fournir, puis audit selon la même méthode (Webhooks, Lead Scoring, Question Bank, WooCommerce, Certificates, Multilingual, Email Marketing, Affiliate, Social Login, Subscription, Notification).

## 11. Journal des modifications

- 2 nov. 2026 – Audit du plugin principal, 13 points HIGH/CRITICAL identifiés.
- 2 nov. 2026 – Phases A et B appliquées sur la branche `audit/phase-a-b` (11 fichiers, lint OK, 14 tests de logique OK).

* 2 nov. 2026 – Phase C validée (H9 conservé, voulu), puis appliquée (commit 83f8eaa) : C2, H5, H11, H13, limite quotidienne ; 9 tests de logique OK.
* 2 nov. 2026 – README.md aligné sur le code (commit d5b358d).

- 3 nov. 2026 – Phase D appliquée (commit a78600f) : table des tentatives, chargement conditionnel des assets, Chart.js et confetti embarqués. 10 tests de logique OK.
- 3 nov. 2026 – Version 3.4.0, changelog et notes README (commit 3f89f59). Patch livré : `fqi3-audit-phase-d.patch`, à appliquer sur l'état A+B+C.

* 3 nov. 2026 – En-têtes alignés par Jonathan : WordPress 6.8 minimum, testé jusqu'à 7.1.4.
* 3 nov. 2026 – Environnement réel monté (WordPress 6.8.2, MariaDB 10.11, WP-CLI) : les phases A à C sont vérifiées en conditions réelles. La phase E y a révélé trois bugs préexistants (limitation REST, import des niveaux, statistiques périodiques), corrigés.
* 8 nov. 2026 – Phase F : retours de l'équipe Leads CRM (6 points) vérifiés et corrigés, 22 tests ajoutés. Pas de bump de version dans ce lot.
* 3 nov. 2026 – Phase E appliquée (commit 5073731) : 39 tests unitaires et 23 vérifications d'intégration. Patch livré : `fqi3-audit-phase-e.patch`, à appliquer sur l'état A+B+C+D (avec votre en-tête).
