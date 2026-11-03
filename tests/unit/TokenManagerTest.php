<?php
use PHPUnit\Framework\TestCase;

final class TokenManagerTest extends TestCase {
    public static function setUpBeforeClass(): void {
        // TokenManager is the last class of this file; load it without the settings page classes.
        $src = file_get_contents(dirname(__DIR__, 2) . '/includes/admin/class-api-settings.php');
        $class = substr($src, strpos($src, 'class TokenManager {'));
        eval('namespace Form_Quizz_FQI3; ' . $class);
    }

    protected function setUp(): void {
        WpState::reset();
        WpState::$options['fqi3_encryption_key'] = bin2hex(random_bytes(32));
    }

    private function manager(): object { return new Form_Quizz_FQI3\TokenManager(); }

    public function testNewTokensUseAuthenticatedEncryptionAndRoundTrip(): void {
        $tm = $this->manager();
        $plain = $tm->generate_token();
        $stored = $tm->encrypt_token($plain);
        $this->assertStringStartsWith('v2:', $stored);
        $this->assertSame($plain, $tm->decrypt_token($stored));
    }

    public function testTokensCreatedBeforeTheUpgradeStillWork(): void {
        $tm = $this->manager();
        $plain = $tm->generate_token();
        $key = hex2bin(WpState::$options['fqi3_encryption_key']);
        $iv = random_bytes(16);
        $legacy = base64_encode($iv . openssl_encrypt($plain, 'aes-256-cbc', $key, 0, $iv));
        $this->assertSame($plain, $tm->decrypt_token($legacy));
    }

    public function testTamperedOrMalformedTokensAreRejected(): void {
        $tm = $this->manager();
        $raw = base64_decode(substr($tm->encrypt_token('secret'), 3));
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
        $this->assertFalse($tm->decrypt_token('v2:' . base64_encode($raw)));
        $this->assertFalse($tm->decrypt_token('v2:abc'));
        $this->assertFalse($tm->decrypt_token(''));
    }
}
