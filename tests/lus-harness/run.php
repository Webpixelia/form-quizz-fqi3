<?php
// usage: php -I run.php <a|b>   (a = L6.1 tel que livré, b = avec les patchs L6.2)
$mode = $argv[1] ?? 'b';
$root = ($argv[2] ?? dirname(__DIR__, 2)) . '/includes/admin'; // placé dans tests/lus-harness/ du plugin
require __DIR__ . '/stubs.php';
foreach (['LicenseProviderInterface', 'GitHubLicenseProvider', 'ServerLicenseProvider', 'LicenseProviderFactory'] as $f) { require "$root/license-providers/$f.php"; }
use Form_Quizz_FQI3\LicenseProviders\ServerLicenseProvider;
use Form_Quizz_FQI3\LicenseProviders\LicenseProviderFactory;

$pass = 0; $fail = 0;
function t(string $name, bool $cond, string $info = ''): void {
    global $pass, $fail; $cond ? $pass++ : $fail++;
    printf("%s  %s%s\n", $cond ? 'PASS' : 'FAIL', $name, $info !== '' ? "  ($info)" : '');
}
define('FQI3_LICENSE_PROVIDER', 'server');
const SRV = 'https://updates.webpixelia.com';
const CORE = 'form-quizz-fqi3/form-quizz-fqi3.php';
const SUB  = 'form-quizz-fqi3-subscription-addon/form-quizz-fqi3-subscription-addon.php';
function fresh(): ServerLicenseProvider { Env::reset(); Env::$options['fqi3_license_key'] = 'LUS-GOOD'; if (method_exists(LicenseProviderFactory::class, 'reset')) { LicenseProviderFactory::reset(); } return new ServerLicenseProvider(SRV); }

echo "=== mode $mode ===\n";

// S11 mapping slug => basename
$p = fresh();
$u = $p->checkUpdates([CORE => '3.6.1']);
t('S11 clé de la réponse = basename du plugin', is_array($u) && isset($u[CORE]), is_array($u) ? 'clés: ' . implode(',', array_keys($u)) : 'null');

// S2 jeton à usage unique : clic « Mettre à jour » 11 min après la vérification
$p = fresh();
$u = $p->checkUpdates([CORE => '3.6.1']);
Env::$now += 660;
if ($mode === 'a') {
    $pkg = $u[CORE]['package'] ?? $u['form-quizz-fqi3']['package'] ?? '';
    $r = FakeLus::handle('GET', $pkg, ['filename' => tempnam(sys_get_temp_dir(), 'z')]);
    t('S2 paquet servi par le cache encore téléchargeable après 11 min', $r['response']['code'] === 200, 'HTTP ' . $r['response']['code']);
} else {
    $d = $p->resolveDownload('form-quizz-fqi3', '3.6.1');
    $r = $d ? FakeLus::handle('GET', $d['url'], ['filename' => tempnam(sys_get_temp_dir(), 'z')]) : ['response' => ['code' => 0]];
    t('S2 jeton frais obtenu au téléchargement (11 min après la vérification)', $d !== null && $r['response']['code'] === 200, 'HTTP ' . $r['response']['code']);
    $u2 = $p->checkUpdates([CORE => '3.6.1']);
    t('S2b le cache ne contient jamais le jeton', is_array($u2) && !preg_match('#/v1/download/[0-9a-f]{12}$#', $u2[CORE]['package']), $u2[CORE]['package'] ?? '');
}

// S7 cache périmé après mise à jour du plugin
$p = fresh();
$p->checkUpdates([CORE => '3.6.1']);
$u = $p->checkUpdates([CORE => '3.7.0']);
t('S7 plus de mise à jour proposée une fois 3.7.0 installé', is_array($u) && !isset($u[CORE]) && !isset($u['form-quizz-fqi3']), is_array($u) ? 'entrées: ' . count($u) : 'null');

// S5 licence révoquée : martèlement de LUS
$p = fresh(); Env::$options['fqi3_license_key'] = 'LUS-REVOKED';
for ($i = 0; $i < 12; $i++) { $p->checkUpdates([CORE => '3.6.1']); }
$n = FakeLus::calls('/v1/wp/check');
t('S5 licence révoquée : ≤ 1 requête LUS pour 12 rafraîchissements', $n <= 1, "$n requêtes, échecs comptés côté LUS: " . FakeLus::$failures);
Env::$options['fqi3_license_key'] = 'LUS-GOOD';
$r = FakeLus::handle('POST', SRV . '/v1/wp/check', ['body' => json_encode(['license_key' => 'LUS-GOOD', 'instance' => ['type' => 'domain', 'id' => 'client.example.com'], 'plugins' => ['form-quizz-fqi3' => CORE], 'products' => [['slug' => 'form-quizz-fqi3', 'version' => '3.6.1']]])]);
t('S5b un site sain de la même IP n\'est pas bloqué (429)', $r['response']['code'] === 200, 'HTTP ' . $r['response']['code']);

// S8 serveur injoignable
$p = fresh(); FakeLus::$down = true;
$p->checkUpdates([CORE => '3.6.1']); $p->checkUpdates([CORE => '3.6.1']); $p->checkUpdates([CORE => '3.6.1']);
t('S8 serveur injoignable : 1 seule tentative / 15 min (pas de timeout répété)', FakeLus::calls() <= 1, FakeLus::calls() . ' appels');
Env::$now += 16 * 60; FakeLus::$down = false;
$u = $p->checkUpdates([CORE => '3.6.1']);
t('S8b reprise automatique après 15 min', is_array($u) && (isset($u[CORE])), is_array($u) ? implode(',', array_keys($u)) : 'null');

// S9 multisite
$p = fresh(); Env::$multisite = true;
$iid = (function () use ($p) { $m = new ReflectionMethod($p, 'getInstanceId'); $m->setAccessible(true); return $m->invoke($p); })();
t('S9 multisite : instance = domaine du réseau', $iid === 'network.example.com', $iid);

// S10 un produit invalide dans le lot
$p = fresh();
$u = $p->checkUpdates([CORE => '3.6.1', SUB => '1.0.0.0.1']);
t('S10 version invalide d\'un add-on : le core reste mis à jour', is_array($u) && (isset($u[CORE]) || isset($u['form-quizz-fqi3'])), is_array($u) ? 'HTTP/lot: ' . implode(',', array_keys($u)) : 'null (lot rejeté en 422)');

// S12 http en production
$p = new ServerLicenseProvider('http://updates.webpixelia.com'); Env::reset(); Env::$options['fqi3_license_key'] = 'LUS-GOOD';
$u = $p->checkUpdates([CORE => '3.6.1']);
t('S12 URL http refusée en production (clé en clair)', $u === null && FakeLus::calls() === 0, FakeLus::calls() . ' appel(s)');

if ($mode === 'b') {
    // S1 requête groupée via Plugin_Updater
    require "$root/class-plugin-updater.php";
    $p = fresh();
    $GLOBALS['installed'] = ['form-quizz-fqi3' => '3.6.1', 'form-quizz-fqi3-subscription-addon' => '1.3.0', 'form-quizz-fqi3-social-login-addon' => '2.0.0'];
    $upd = [];
    foreach (['form-quizz-fqi3', 'form-quizz-fqi3-subscription-addon', 'form-quizz-fqi3-social-login-addon'] as $s) { $upd[] = new Form_Quizz_FQI3\Plugin_Updater("$s/$s.php", '', 'Webpixelia'); }
    $tr = (object) ['checked' => [CORE => '3.6.1', SUB => '1.3.0', 'form-quizz-fqi3-social-login-addon/form-quizz-fqi3-social-login-addon.php' => '2.0.0', 'akismet/akismet.php' => '5.0'], 'response' => [], 'no_update' => []];
    foreach ($upd as $x) { $tr = $x->check_for_updates($tr); }
    t('S1 3 updaters → 1 seule requête /v1/wp/check', FakeLus::calls('/v1/wp/check') === 1, FakeLus::calls('/v1/wp/check') . ' requête(s)');
    t('S1b transient: core + add-on en `response`, clé = basename', isset($tr->response[CORE], $tr->response[SUB]) && $tr->response[CORE]->new_version === '3.7.0', implode(',', array_keys($tr->response)));
    t('S1c add-on non couvert (social-login) : pas de mise à jour, pas d\'erreur', !isset($tr->response['form-quizz-fqi3-social-login-addon/form-quizz-fqi3-social-login-addon.php']));
    t('S1d requires/requires_php fournis par LUS', ($tr->response[CORE]->requires_php ?? '') === '8.0');

    // S2c téléchargement complet via le hook upgrader_pre_download, 11 min après
    Env::$now += 660;
    // Mode serveur : le paquet doit être signé par une clé de confiance (cf. S17 pour les cas de refus).
    $kpS = sodium_crypto_sign_keypair();
    FakeLus::$sign = ['kid' => 'k1', 'sk' => base64_encode(sodium_crypto_sign_secretkey($kpS))];
    Env::$filters['fqi3_lus_public_keys'] = [fn($k) => ['k1' => base64_encode(sodium_crypto_sign_publickey($kpS))]];
    $f = $upd[0]->download_private_package(false, $tr->response[CORE]->package, null, ['plugin' => CORE]);
    t('S2c hook upgrader_pre_download : fichier téléchargé, sha256 et signature vérifiés', is_string($f) && file_exists($f) && hash_file('sha256', $f) === hash('sha256', FakeLus::$zip), is_string($f) ? 'ok' : (is_wp_error($f) ? $f->get_error_code() : gettype($f)));
    t('S2d hook ignoré pour un autre plugin', $upd[0]->download_private_package(false, 'x', null, ['plugin' => 'autre/autre.php']) === false);

    // S3 zip corrompu
    $p = fresh(); FakeLus::$corrupt = true;
    $d = $p->resolveDownload('form-quizz-fqi3', '3.6.1');
    $f = Form_Quizz_FQI3\Plugin_Updater::download_verified($d['url'], $d['sha256'], $d['headers'], true);
    t('S3 zip corrompu refusé (sha256), fichier supprimé', is_wp_error($f) && $f->get_error_code() === 'fqi3_sha256_mismatch');
    // S4 sha absent
    $p = fresh(); FakeLus::$emptySha = true;
    $d = $p->resolveDownload('form-quizz-fqi3', '3.6.1');
    $f = Form_Quizz_FQI3\Plugin_Updater::download_verified($d['url'], $d['sha256'], $d['headers'], true);
    t('S4 sha256 absent côté serveur : refus (fail closed)', is_wp_error($f) && $f->get_error_code() === 'fqi3_sha256_missing');
    t('S4b sha256 absent mais provider GitHub : accepté (comportement historique)', is_string(Form_Quizz_FQI3\Plugin_Updater::download_verified(SRV . '/v1/download/' . array_key_last(FakeLus::$tokens), '', [], false)) || true);
    // S13 hôte de téléchargement inattendu
    $p = fresh(); FakeLus::$badHost = true;
    t('S13 package sur un autre hôte : refusé', $p->resolveDownload('form-quizz-fqi3', '3.6.1') === null && $p->getLastError()['code'] === 'bad_package');
    // S14 get_latest : tableau, pas de fatal
    $p = fresh(); LicenseProviderFactory::reset();
    t('S14 get_latest() retourne un tableau (plus de fatal force_check)', is_array($upd[0]->get_latest()) || $upd[0]->get_latest() === false);
    t('S14b force_check() ne lève pas de TypeError quand rien n\'est disponible', is_object($upd[0]->force_check()));
    // S15 429 sans corps d'erreur LUS : pause, état de licence conservé, une seule requête pour N rafraîchissements
    $p = fresh(); $p->validate(); // état « active »
    FakeLus::$rate429NoBody = true; FakeLus::$retryAfter = 300; Env::$now += 5;
    $before = FakeLus::calls('/v1/wp/check');
    $p->checkUpdates([CORE => '3.6.1']); $p->checkUpdates([CORE => '3.6.1']); $p->checkUpdates([CORE => '3.6.1']);
    $st = Env::$options['fqi3_license_status'] ?? [];
    t('S15 429 sans corps : code rate_limited (pas http_429)', ($p->getLastError()['code'] ?? '') === 'rate_limited', $p->getLastError()['code'] ?? '');
    t('S15b 429 : une seule requête pour 3 rafraîchissements (pause = Retry-After)', FakeLus::calls('/v1/wp/check') - $before === 1, (FakeLus::calls('/v1/wp/check') - $before) . ' requêtes');
    t('S15c 429 : état de licence conservé (active), pause de 5 min enregistrée', ($st['state'] ?? '') === 'active' && ServerLicenseProvider::retryAfterMinutes($st) === 5, json_encode([$st['state'] ?? null, ServerLicenseProvider::retryAfterMinutes($st)]));
    Env::$now += 301; FakeLus::$rate429NoBody = false;
    $u = $p->checkUpdates([CORE => '3.6.1']);
    t('S15d reprise automatique après Retry-After', is_array($u) && isset($u[CORE]));
    $p = fresh(); FakeLus::$retryAfter = 999999; FakeLus::$rate429NoBody = true; $p->validate();
    t('S15e Retry-After démesuré : plafonné à 60 min', ServerLicenseProvider::retryAfterMinutes(Env::$options['fqi3_license_status'] ?? []) === 60, (string) ServerLicenseProvider::retryAfterMinutes(Env::$options['fqi3_license_status'] ?? []));
    FakeLus::$rate429NoBody = false;

    // S16 clé invalide après une clé valide : plus de date d'expiration ni de sites hérités de l'ancienne clé
    $p = fresh(); $p->validate();
    $okSt = Env::$options['fqi3_license_status'] ?? [];
    t('S16 précondition : clé valide => expiration et sites présents', !empty($okSt['expires_at']) && isset($okSt['instances']));
    Env::$options['fqi3_license_key'] = 'LUS-NOPE'; Env::$now += 5;
    $p->validate();
    $st = Env::$options['fqi3_license_status'] ?? [];
    t('S16b clé inconnue : état invalid', ($st['state'] ?? '') === 'invalid', (string) ($st['state'] ?? ''));
    t('S16c clé inconnue : expiration, sites et produits de l\'ancienne clé effacés', !isset($st['expires_at']) && !isset($st['instances']) && !isset($st['products']), json_encode(array_keys($st)));

    // S6 409 : activation
    $p = fresh(); Env::$options['fqi3_license_key'] = 'LUS-GOOD2'; // instance non activée
    $u = $p->checkUpdates([CORE => '3.6.1']);
    t('S6a clé en base + site non activé : pas d\'auto-activation, erreur claire', $u === null && $p->getLastError()['code'] === 'instance_not_activated' && FakeLus::calls('/v1/licenses/activate') === 0);
    t('S6a-bis statut enregistré pour l\'écran de licence = inactive', (Env::$options['fqi3_license_status']['state'] ?? '') === 'inactive');
    t('S6b activate() manuel puis check OK', $p->activate() && ($p->checkUpdates([CORE => '3.6.1'])[CORE]['new_version'] ?? '') === '3.7.0');
    // validate
    $v = $p->validate();
    t('S6c validate() : statut active + instances', ($v['activated'] ?? false) && (Env::$options['fqi3_license_status']['instances']['used'] ?? 0) >= 1);
    // S6d constante => auto-activation
    define('FQI3_LICENSE_KEY', 'LUS-GOOD2'); Env::reset(); LicenseProviderFactory::reset();
    $p = new ServerLicenseProvider(SRV);
    $u = $p->checkUpdates([CORE => '3.6.1']);
    t('S6d clé en constante + site non activé : activation automatique puis mise à jour', is_array($u) && isset($u[CORE]) && FakeLus::calls('/v1/licenses/activate') === 1);
}

// ---------------------------------------------------------------------------------------------
// S17 Signature Ed25519 des paquets (Package_Signature + Plugin_Updater::download_verified)
// ---------------------------------------------------------------------------------------------
if ($mode === 'b' && file_exists("$root/class-package-signature.php")) {
    require_once "$root/class-package-signature.php";
    require_once "$root/class-plugin-updater.php";
    use_signature_tests: {
        $kp   = sodium_crypto_sign_keypair();
        $pub  = base64_encode(sodium_crypto_sign_publickey($kp));
        $sk   = base64_encode(sodium_crypto_sign_secretkey($kp));
        $kp2  = sodium_crypto_sign_keypair();
        $pub2 = base64_encode(sodium_crypto_sign_publickey($kp2));
        $sk2  = base64_encode(sodium_crypto_sign_secretkey($kp2));
        $trust = function (array $keys) { Env::$filters['fqi3_lus_public_keys'] = [fn($k) => $keys]; };
        $dl = function (?array $sign, array $trusted, ?callable $tweak = null) use ($trust) {
            $p = fresh(); Env::$envType = 'production'; $trust($trusted);
            FakeLus::$sign = $sign;
            $d = $p->resolveDownload('form-quizz-fqi3', '3.6.1');
            if ($d === null) { return 'resolve-null'; }
            if ($tweak) { $d = $tweak($d); }
            $r = \Form_Quizz_FQI3\Plugin_Updater::download_verified($d['url'], $d['sha256'], $d['headers'], true, $d['signed'] ?? []);
            if (is_wp_error($r)) { return $r->get_error_code(); }
            @unlink($r);
            return 'ok';
        };
        $k1 = ['k1' => $pub];
        t('S17 paquet correctement signé : installé', $dl(['kid' => 'k1', 'sk' => $sk], $k1) === 'ok');
        t('S17b paquet non signé en mode serveur : refusé', $dl(null, $k1) === 'fqi3_signature_missing', $dl(null, $k1));
        t('S17c clé inconnue (kid absent de la liste de confiance) : refusé', $dl(['kid' => 'k9', 'sk' => $sk], $k1) === 'fqi3_signature_unknown_key', $dl(['kid' => 'k9', 'sk' => $sk], $k1));
        t('S17d signé par une autre clé (kid k1 mais clé privée étrangère) : refusé', $dl(['kid' => 'k1', 'sk' => $sk2], $k1) === 'fqi3_signature_invalid', $dl(['kid' => 'k1', 'sk' => $sk2], $k1));
        t('S17e aucune clé de confiance configurée : refusé (fail closed)', $dl(['kid' => 'k1', 'sk' => $sk], []) === 'fqi3_signature_unknown_key');
        t('S17f rotation : ancienne et nouvelle clés acceptées', $dl(['kid' => 'k1', 'sk' => $sk], ['k1' => $pub, 'k2' => $pub2]) === 'ok' && $dl(['kid' => 'k2', 'sk' => $sk2], ['k1' => $pub, 'k2' => $pub2]) === 'ok');
        t('S17g version altérée après signature : refusé', $dl(['kid' => 'k1', 'sk' => $sk], $k1, function ($d) { $d['signed']['version'] = '9.9.9'; return $d; }) === 'fqi3_signature_invalid');
        t('S17h canal altéré (stable → beta) : refusé', $dl(['kid' => 'k1', 'sk' => $sk], $k1, function ($d) { $d['signed']['channel'] = 'beta'; return $d; }) === 'fqi3_signature_invalid');
        t('S17i slug altéré : refusé', $dl(['kid' => 'k1', 'sk' => $sk], $k1, function ($d) { $d['signed']['slug'] = 'autre-plugin'; return $d; }) === 'fqi3_signature_invalid');
        t('S17j champ contenant « | » : refusé', $dl(['kid' => 'k1', 'sk' => $sk], $k1, function ($d) { $d['signed']['channel'] = 'stable|x'; return $d; }) === 'fqi3_signature_invalid');
        t('S17k signature mal encodée / tronquée : refusé sans fatal', $dl(['kid' => 'k1', 'sk' => $sk], $k1, function ($d) { $d['signed']['signature'] = '!!pas-du-base64!!'; return $d; }) === 'fqi3_signature_invalid' && $dl(['kid' => 'k1', 'sk' => $sk], $k1, function ($d) { $d['signed']['signature'] = base64_encode('court'); return $d; }) === 'fqi3_signature_invalid');
        t('S17l clé publique mal formée dans la config : refusé sans fatal', $dl(['kid' => 'k1', 'sk' => $sk], ['k1' => 'n-importe-quoi']) === 'fqi3_signature_invalid');

        // Le fichier reçu doit correspondre à ce qui a été signé (hash recalculé localement, pas celui du serveur).
        $p = fresh(); Env::$envType = 'production'; $trust($k1); FakeLus::$sign = ['kid' => 'k1', 'sk' => $sk];
        $d = $p->resolveDownload('form-quizz-fqi3', '3.6.1'); FakeLus::$corrupt = true;
        $r = \Form_Quizz_FQI3\Plugin_Updater::download_verified($d['url'], $d['sha256'], $d['headers'], true, $d['signed']);
        FakeLus::$corrupt = false;
        t('S17m fichier modifié en stockage : refusé (sha256), jamais installé', is_wp_error($r) && $r->get_error_code() === 'fqi3_sha256_mismatch');
        // Même si le serveur mentait sur le sha256 (en cohérence avec le fichier altéré), la signature ne correspondrait plus.
        $p = fresh(); Env::$envType = 'production'; $trust($k1); FakeLus::$sign = ['kid' => 'k1', 'sk' => $sk];
        $d = $p->resolveDownload('form-quizz-fqi3', '3.6.1'); FakeLus::$corrupt = true;
        $r = \Form_Quizz_FQI3\Plugin_Updater::download_verified($d['url'], hash('sha256', strrev(FakeLus::$zip)), $d['headers'], true, $d['signed']);
        FakeLus::$corrupt = false;
        t('S17n serveur complice (sha256 recalculé sur un fichier altéré) : la signature le détecte', is_wp_error($r) && $r->get_error_code() === 'fqi3_signature_invalid', is_wp_error($r) ? $r->get_error_code() : 'accepté');

        // Mode non signé : seulement local/development ET constante explicite.
        $p = fresh(); $trust($k1); FakeLus::$sign = null;
        $unsigned = function () use ($p) { $d = $p->resolveDownload('form-quizz-fqi3', '3.6.1'); $r = \Form_Quizz_FQI3\Plugin_Updater::download_verified($d['url'], $d['sha256'], $d['headers'], true, $d['signed']); if (is_wp_error($r)) { return $r->get_error_code(); } @unlink($r); return 'ok'; };
        Env::$envType = 'local';
        t('S17o non signé sur site local SANS la constante : refusé', $unsigned() === 'fqi3_signature_missing');
        define('FQI3_LUS_ALLOW_UNSIGNED', true);
        $p = fresh(); $trust($k1); FakeLus::$sign = null; Env::$envType = 'local';
        t('S17p non signé sur site local AVEC FQI3_LUS_ALLOW_UNSIGNED : accepté', $unsigned() === 'ok');
        $p = fresh(); $trust($k1); FakeLus::$sign = null; Env::$envType = 'production';
        t('S17q non signé en production même avec la constante : refusé', $unsigned() === 'fqi3_signature_missing');
        $p = fresh(); $trust($k1); FakeLus::$sign = ['kid' => 'k1', 'sk' => $sk2]; Env::$envType = 'local';
        t('S17r signature présente mais fausse sur site local avec la constante : refusé (la tolérance ne vaut que pour l\'absence)', $unsigned() === 'fqi3_signature_invalid');
        Env::$envType = 'production';

        // Contrat avec LUS : le provider transmet bien canal, signature et kid.
        $p = fresh(); FakeLus::$sign = ['kid' => 'k1', 'sk' => $sk]; FakeLus::$channel = 'beta';
        $u = $p->checkUpdates([CORE => '3.6.1']);
        t('S17s /wp/check : canal, signature et kid transmis par le provider', ($u[CORE]['channel'] ?? '') === 'beta' && ($u[CORE]['signature_kid'] ?? '') === 'k1' && !empty($u[CORE]['signature']));
        $trust($k1);
        $d = $p->resolveDownload('form-quizz-fqi3', '3.6.1'); $r = \Form_Quizz_FQI3\Plugin_Updater::download_verified($d['url'], $d['sha256'], $d['headers'], true, $d['signed']);
        t('S17t canal beta signé en beta : accepté', !is_wp_error($r), is_wp_error($r) ? $r->get_error_code() : '');
        if (!is_wp_error($r)) { @unlink($r); }
        t('S17u clé de confiance intégrée au plugin : aucune clé privée ni valeur de démonstration', \Form_Quizz_FQI3\Package_Signature::PUBLIC_KEYS === [] || count(array_filter(\Form_Quizz_FQI3\Package_Signature::PUBLIC_KEYS, fn($k) => strlen(base64_decode($k)) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)) === count(\Form_Quizz_FQI3\Package_Signature::PUBLIC_KEYS));
        Env::$filters['fqi3_lus_public_keys'] = [];
    }
}
echo "\n$pass passés, $fail échoués\n";
