<?php
use PHPUnit\Framework\TestCase;

final class LevelLockStateTest extends TestCase {
    protected function setUp(): void { WpState::reset(); }

    public function testALevelIsNotLockedByDefault(): void {
        $this->assertSame(
            array('locked' => false, 'message' => '', 'cta_url' => '', 'cta_label' => ''),
            fqi3_get_level_lock_state('expert', 5)
        );
    }

    public function testAnAddonCanLockALevelWithAMessageAndALink(): void {
        add_filter('fqi3_level_lock_state', fn($state) => array_merge($state, array(
            'locked' => true, 'message' => 'Reserved', 'cta_url' => 'https://shop.test/buy', 'cta_label' => 'Buy',
        )), 10, 3);
        $state = fqi3_get_level_lock_state('expert', 5);
        $this->assertTrue($state['locked']);
        $this->assertSame('https://shop.test/buy', $state['cta_url']);
    }

    public function testUnexpectedKeysAndUnsafeValuesAreDropped(): void {
        add_filter('fqi3_level_lock_state', fn() => array(
            'locked' => 1, 'message' => '<script>x</script>Hi', 'cta_url' => 'javascript:alert(1)', 'cta_label' => 'Go', 'evil' => 'x',
        ), 10, 3);
        $state = fqi3_get_level_lock_state('expert', 0);
        $this->assertSame(array('locked', 'message', 'cta_url', 'cta_label'), array_keys($state));
        $this->assertSame('', $state['cta_url'], 'only http(s) links survive');
        $this->assertStringNotContainsString('<', $state['message']);
    }

    public function testANonArrayFromAFilterFallsBackToUnlocked(): void {
        add_filter('fqi3_level_lock_state', fn() => 'junk', 10, 3);
        $this->assertFalse(fqi3_get_level_lock_state('expert', 1)['locked']);
    }
}
