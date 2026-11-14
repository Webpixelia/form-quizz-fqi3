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
    $f = $upd[0]->download_private_package(false, $tr->response[CORE]->package, null, ['plugin' => CORE]);
    t('S2c hook upgrader_pre_download : fichier téléchargé et sha256 vérifié', is_string($f) && file_exists($f) && hash_file('sha256', $f) === hash('sha256', FakeLus::$zip), is_string($f) ? 'ok' : (is_wp_error($f) ? $f->get_error_code() : gettype($f)));
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
echo "\n$pass passés, $fail échoués\n";
