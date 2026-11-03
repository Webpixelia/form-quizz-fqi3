<?php
use PHPUnit\Framework\TestCase;

final class PeriodicStatisticsTest extends TestCase {
    private object $stats;

    protected function setUp(): void {
        WpState::reset();
        $GLOBALS['wpdb'] = new FakeWpdb();
        fqi3_test_load('includes/FQI3_Statistics.php');
        fqi3_test_load('includes/FQI3_PeriodicStatistics.php');
        $this->stats = fqi3_instance('Form_Quizz_FQI3\FQI3_PeriodicStatistics');
    }

    public function testRecordingForOneUserOnlyReadsThatUsersRows(): void {
        global $wpdb;
        fqi3_call($this->stats, 'recordPeriodicStatistics', 'weekly', '2026-10-26', '2026-11-01', 42);

        $call = end($wpdb->prepared);
        $this->assertStringContainsString('AND user_id = %d', $call['sql']);
        $this->assertSame(['2026-10-26', '2026-11-01', 42], $call['args']);
        $this->assertSame(substr_count($call['sql'], '%'), count($call['args']), 'one argument per placeholder');
    }

    public function testRecordingForEveryoneHasNoUserFilter(): void {
        global $wpdb;
        fqi3_call($this->stats, 'recordPeriodicStatistics', 'monthly', '2026-10-01', '2026-10-31');

        $call = end($wpdb->prepared);
        $this->assertStringNotContainsString('user_id = %d', $call['sql']);
        $this->assertCount(2, $call['args']);
    }
}
