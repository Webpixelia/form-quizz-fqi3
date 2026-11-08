<?php
use PHPUnit\Framework\TestCase;

final class StatisticsEndpointTest extends TestCase {
    protected function setUp(): void {
        WpState::reset();
        $GLOBALS['wpdb'] = new FakeWpdb();
    }

    /** The browser-driven endpoint answers success (cached front-ends keep working) but writes nothing. */
    public function testDeprecatedEndpointDoesNotTrustOrStoreBrowserValues(): void {
        global $wpdb;
        fqi3_test_load('includes/FQI3_Statistics.php');
        $stats = fqi3_instance('Form_Quizz_FQI3\FQI3_Statistics');
        WpState::$loggedIn = true; WpState::$userId = 7; WpState::$roles = ['premium_member'];
        $_POST = ['level' => 'free_lvl', 'correct_answers' => '50', 'total_questions' => '50'];

        try {
            $stats->handle_statistics_update();
            $this->fail('no response sent');
        } catch (WpJsonResponse $r) {
            $this->assertTrue($r->ok);
        }
        $this->assertSame([], $wpdb->queries);
        $this->assertSame([], $wpdb->prepared);
    }
}
