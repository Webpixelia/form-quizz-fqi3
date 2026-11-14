<?php
// usage: php tests/lus-harness/settings.php   — handlers AJAX de l'écran licence (L6.3)
$root = dirname(__DIR__, 2) . '/includes/admin';
require __DIR__ . '/stubs.php';
foreach (['LicenseProviderInterface', 'GitHubLicenseProvider', 'ServerLicenseProvider', 'LicenseProviderFactory'] as $f) { require "$root/license-providers/$f.php"; }
define('FQI3_LICENSE_PROVIDER', 'server');

// --- stubs complémentaires (AJAX / droits) ---
class JsonDone extends Exception { public function __construct(public bool $ok, public array $data, public int $http) { parent::__construct(); } }
$GLOBALS['can'] = true; $GLOBALS['uid'] = 7; $GLOBALS['nonce_ok'] = true;
function check_ajax_referer($a, $k) { if (!$GLOBALS['nonce_ok']) { throw new JsonDone(false, ['message' => 'bad nonce'], 403); } return 1; }
function current_user_can($c) { $GLOBALS['cap_asked'] = $c; return $GLOBALS['can']; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_send_json_error($d = null, $s = 200) { throw new JsonDone(false, (array) $d, $s); }
function wp_send_json_success($d = null) { throw new JsonDone(true, (array) $d, 200); }
function wp_unslash($v) { return $v; }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function fqi3_options_settings_sections() { return []; }
function fqi3_get_options_page_slug() { return 'x'; }
require "$root/class-license-settings.php";
use Form_Quizz_FQI3\FQI3_License_Settings;
use Form_Quizz_FQI3\LicenseProviders\LicenseProviderFactory;

$pass = 0; $fail = 0;
function t(string $n, bool $c, string $i = ''): void { global $pass, $fail; $c ? $pass++ : $fail++; printf("%s  %s%s\n", $c ? 'PASS' : 'FAIL', $n, $i !== '' ? "  ($i)" : ''); }
function call(string $method, array $post = []): JsonDone {
    $_POST = $post; $s = new FQI3_License_Settings();
    try { $s->$method(); } catch (JsonDone $r) { return $r; }
    throw new RuntimeException("$method n'a pas répondu");
}
const K1 = 'LUS-AAAAA-BBBBB-CCCCC-DDDDD-EEEEE';
const K2 = 'LUS-11111-22222-33333-44444-55555';
function fresh(): void {
    Env::reset(); $GLOBALS['can'] = true; $GLOBALS['nonce_ok'] = true; $GLOBALS['uid'] = 7;
    FakeLus::$licenses[K1] = ['status' => 'active', 'max' => 3, 'products' => ['form-quizz-fqi3']]; FakeLus::$activations[K1] = [];
    FakeLus::$licenses[K2] = ['status' => 'active', 'max' => 3, 'products' => ['form-quizz-fqi3']]; FakeLus::$activations[K2] = [];
    LicenseProviderFactory::reset();
}

// T1 enregistrement + activation
fresh(); $r = call('ajax_save', ['license_key' => ' ' . strtolower(K1) . ' ']);
t('T1 clé valide : succès, normalisée en majuscules', $r->ok && Env::$options['fqi3_license_key'] === K1);
t('T1b instance activée côté LUS', in_array('client.example.com', FakeLus::$activations[K1], true));
t('T1c réponse masquée (jamais la clé en clair)', !str_contains(json_encode($r->data), K1) && str_ends_with($r->data['key'], 'EEEE'));
t('T1d statut stocké = active', (LicenseProviderFactory::getStatus()['state'] ?? '') === 'active');

// T2 format invalide : aucun appel réseau, rien de stocké
fresh(); $r = call('ajax_save', ['license_key' => 'abc']);
t('T2 format invalide refusé sans appel LUS ni écriture', !$r->ok && FakeLus::calls() === 0 && !isset(Env::$options['fqi3_license_key']));

// T3 échec : l'ancienne clé est restaurée
fresh(); Env::$options['fqi3_license_key'] = K2; LicenseProviderFactory::reset();
FakeLus::$licenses['LUS-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ'] = ['status' => 'revoked', 'max' => 1, 'products' => []]; FakeLus::$activations['LUS-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ'] = [];
$r = call('ajax_save', ['license_key' => 'LUS-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ']);
t('T3 activation refusée : clé précédente restaurée', !$r->ok && Env::$options['fqi3_license_key'] === K2, $r->data['message'] ?? '');
t('T3b message d\'erreur lisible', ($r->data['message'] ?? '') !== '');

// T5 droits et nonce
fresh(); $GLOBALS['can'] = false; $r = call('ajax_save', ['license_key' => K1]);
t('T5 sans droit : 403 et rien d\'écrit', !$r->ok && $r->http === 403 && !isset(Env::$options['fqi3_license_key']) && FakeLus::calls() === 0);
fresh(); $GLOBALS['nonce_ok'] = false; $r = call('ajax_validate');
t('T5b nonce invalide : refus avant tout appel LUS', !$r->ok && FakeLus::calls() === 0);
fresh(); Env::$multisite = true; call('ajax_validate');
t('T5c multisite : capacité manage_network_options', $GLOBALS['cap_asked'] === 'manage_network_options');
fresh(); call('ajax_validate'); t('T5d mono-site : manage_options', $GLOBALS['cap_asked'] === 'manage_options');

// T6 throttle : 5 essais / 10 min / utilisateur
fresh(); $codes = [];
for ($i = 0; $i < 7; $i++) { $codes[] = call('ajax_save', ['license_key' => 'bad'])->http; }
t('T6 6e essai bloqué (429), pas avant', $codes[4] === 200 && $codes[5] === 429 && $codes[6] === 429, implode(',', $codes));
Env::$now += 601; t('T6b déblocage après 10 min', call('ajax_save', ['license_key' => 'bad'])->http === 200);
$GLOBALS['uid'] = 8; t('T6c autre utilisateur non impacté', call('ajax_save', ['license_key' => 'bad'])->http === 200);

// T7 validate met à jour le statut
fresh(); Env::$options['fqi3_license_key'] = K1; FakeLus::$activations[K1] = ['client.example.com']; LicenseProviderFactory::reset();
$r = call('ajax_validate');
t('T7 validate OK : statut + expiration', $r->ok && !empty($r->data['expires_at']) && (LicenseProviderFactory::getStatus()['state'] ?? '') === 'active');
FakeLus::$licenses[K1]['status'] = 'revoked'; Env::$now += 5;
$r = call('ajax_validate');
t('T7b licence révoquée : erreur + état invalide/expiré', !$r->ok && (LicenseProviderFactory::getStatus()['state'] ?? '') !== 'active', LicenseProviderFactory::getStatus()['state'] ?? '');

// T8 suppression
fresh(); call('ajax_save', ['license_key' => K1]); $r = call('ajax_remove');
t('T8 suppression : clé et statut effacés', $r->ok && (Env::$options['fqi3_license_key'] ?? '') === '' && LicenseProviderFactory::getStatus() === []);
t('T8b activation libérée côté LUS', !in_array('client.example.com', FakeLus::$activations[K1], true));
fresh(); call('ajax_save', ['license_key' => K1]); FakeLus::$down = true; $r = call('ajax_remove');
t('T8c LUS injoignable : la clé locale est quand même supprimée', $r->ok && (Env::$options['fqi3_license_key'] ?? '') === '');

// T9 multisite : stockage réseau
fresh(); Env::$multisite = true; LicenseProviderFactory::reset(); call('ajax_save', ['license_key' => K1]);
t('T9 multisite : clé dans site_option, pas dans option', (Env::$siteOptions['fqi3_license_key'] ?? '') === K1 && !isset(Env::$options['fqi3_license_key']));

// T10 masquage / étiquettes
t('T10 mask_key', FQI3_License_Settings::mask_key(K1) === '••••••••EEEE' && FQI3_License_Settings::mask_key('') === '');

// T4 clé en constante (en dernier : define() est irréversible dans le process) : modification refusée
fresh(); define('FQI3_LICENSE_KEY', K2); LicenseProviderFactory::reset();
$r = call('ajax_save', ['license_key' => K1]);
t('T4 clé définie en constante : enregistrement refusé', !$r->ok && !isset(Env::$options['fqi3_license_key']));
$r = call('ajax_remove');
t('T4b suppression refusée aussi', !$r->ok);

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
