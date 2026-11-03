<?php
use PHPUnit\Framework\TestCase;

final class PluginHelpersTest extends TestCase {
    protected function setUp(): void { WpState::reset(); }

    public function testForwardedHeadersAreIgnoredByDefault(): void {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6, 203.0.113.50';
        $this->assertSame('198.51.100.7', fqi3_get_client_ip());
    }

    public function testForwardedHeadersAreHonoredBehindATrustedProxy(): void {
        add_filter('fqi3_trusted_proxies', fn() => ['198.51.100.7']);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6, 203.0.113.50, 198.51.100.7';
        $this->assertSame('203.0.113.50', fqi3_get_client_ip(), 'client = first untrusted address from the right');

        $_SERVER['REMOTE_ADDR'] = '192.0.2.99'; // not a trusted proxy
        $this->assertSame('192.0.2.99', fqi3_get_client_ip());
    }

    public function testInvalidRemoteAddrFallsBack(): void {
        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->assertSame('0.0.0.0', fqi3_get_client_ip());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cells')]
    public function testCsvCellsCannotBeSpreadsheetFormulas($in, $out): void {
        $this->assertSame($out, fqi3_csv_safe($in));
    }

    public static function cells(): array {
        return [['=cmd()', "'=cmd()"], ['+1', "'+1"], ['-2', "'-2"], ['@SUM(A1)', "'@SUM(A1)"], ['normal', 'normal'], ['', ''], [12, 12]];
    }

    public function testLevelsWithAMissingFreeFlagDoNotRaiseWarnings(): void {
        WpState::$options['fqi3_quiz_levels'] = [
            'fqi3_quiz_levels_name'    => ['a', 'b'],
            'fqi3_quiz_levels_label'   => ['A', 'B'],
            'fqi3_quiz_levels_is_free' => [1], // last flag missing (old import bug)
        ];
        $levels = fqi3_get_free_quiz_levels();
        $this->assertTrue($levels['a']['free']);
        $this->assertFalse($levels['b']['free']);
    }

    public function testFrontendAssetsAreDetectedOnPagesUsingThePlugin(): void {
        fqi3_test_load('form-quizz-fqi3.php', ["require_once __DIR__ . '/vendor/autoload.php';" => '']);
        $plugin = fqi3_instance('Form_Quizz_FQI3\FormQuizzFQI3');

        $uses = static function (string $content) use ($plugin): bool {
            WpState::$postContent = $content;
            return fqi3_call($plugin, 'currentPageUsesPlugin');
        };

        $this->assertTrue($uses('Hello [fqi3_quizz_form] world'));
        $this->assertTrue($uses('[free_quiz_form level="x"]'));
        $this->assertTrue($uses('[fqi3_periodic_stats]'));
        $this->assertTrue($uses('<!-- wp:fqi3/quiz-form {"a":1} /-->'));
        $this->assertFalse($uses('A post about [gallery] mentioning fqi3_quizz_form in plain text'));

        WpState::$singular = false;
        $this->assertFalse($uses('[fqi3_quizz_form]'), 'archives rely on the render-time enqueue');
    }
}
