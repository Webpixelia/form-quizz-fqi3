<?php
use PHPUnit\Framework\TestCase;

final class FrontendLogicTest extends TestCase {
    private object $fe;

    protected function setUp(): void {
        WpState::reset();
        $GLOBALS['wpdb'] = new FakeWpdb();
        fqi3_test_load('includes/FQI3_Frontend.php');
        $this->fe = fqi3_instance('Form_Quizz_FQI3\FQI3_Frontend');
        $opts = (new ReflectionClass($this->fe))->getProperty('options');
        $opts->setAccessible(true);
        $opts->setValue($this->fe, ['fqi3_free_trials_per_day' => 3]);
        WpState::$options['fqi3_quiz_levels'] = [
            'fqi3_quiz_levels_name'    => ['free_lvl', 'members_lvl'],
            'fqi3_quiz_levels_label'   => ['Free', 'Members'],
            'fqi3_quiz_levels_is_free' => [1, 0],
        ];
    }

    public function testVisitorCanOnlyOpenFreeLevels(): void {
        $this->assertTrue(fqi3_call($this->fe, 'canAccessLevel', 'free_lvl'));
        $this->assertFalse(fqi3_call($this->fe, 'canAccessLevel', 'members_lvl'));
        $this->assertTrue(fqi3_call($this->fe, 'canAccessLevel', 'unknown'), 'unknown levels fall through to "no questions found"');
    }

    public function testLoggedInUserCanOpenMembersLevels(): void {
        WpState::$loggedIn = true; WpState::$userId = 7;
        $this->assertTrue(fqi3_call($this->fe, 'canAccessLevel', 'members_lvl'));
    }

    public function testDailyLimitAllowsExactlyTheConfiguredNumberOfQuizzes(): void {
        WpState::$loggedIn = true; WpState::$userId = 7;
        $allowed = 0;
        for ($i = 0; $i < 6; $i++) {
            if ($this->fe->canTakeQuiz()) { $allowed++; fqi3_call($this->fe, 'incrementQuizCount'); }
        }
        $this->assertSame(3, $allowed);
        $this->assertSame(['total_attempts' => 3, 'attempts_made' => 3, 'remaining_attempts' => 0], $this->fe->getAttemptsInfo());
    }

    public function testDailyCounterResetsTheNextDayAndIsPerUser(): void {
        WpState::$loggedIn = true; WpState::$userId = 7;
        for ($i = 0; $i < 3; $i++) { fqi3_call($this->fe, 'incrementQuizCount'); }
        $this->assertFalse($this->fe->canTakeQuiz());

        WpState::$userId = 8;
        $this->assertSame(0, $this->fe->getAttemptsInfo()['attempts_made']);

        WpState::$userId = 7; WpState::$today = '2026-11-04';
        $this->assertTrue($this->fe->canTakeQuiz());
    }

    public function testAjaxIncrementDoesNotDoubleCountLoggedInUsers(): void {
        WpState::$loggedIn = true; WpState::$userId = 7;
        try { $this->fe->handleIncrementQuizCount(); } catch (RuntimeException $e) { /* json_success */ }
        $this->assertSame(0, $this->fe->getAttemptsInfo()['attempts_made']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('scores')]
    public function testScoresAreClampedToAPercentage($raw, float $expected): void {
        $this->assertSame($expected, fqi3_call($this->fe, 'sanitizeQuizScore', $raw));
    }

    public static function scores(): array {
        return [[9999, 100.0], [-50, 0.0], ['abc', 0.0], ['72.456', 72.46], [null, 0.0]];
    }

    public function testOnlyConfiguredLevelsAreKnown(): void {
        $this->assertTrue(fqi3_call($this->fe, 'isKnownLevel', 'free_lvl'));
        $this->assertFalse(fqi3_call($this->fe, 'isKnownLevel', 'hacked'));
        $this->assertFalse(fqi3_call($this->fe, 'isKnownLevel', ''));
    }

    public function testAttemptsTableIsCreatedOnceThenRecreatedIfInsertFails(): void {
        global $wpdb;
        $this->fe->ensure_attempts_table();
        for ($i = 0; $i < 50; $i++) { $this->fe->ensure_attempts_table(); }
        $this->assertCount(1, $wpdb->queries, 'one DDL query, then no more on later requests');

        $wpdb->failNextInsert = true;
        $_SESSION = [];
        $attemptId = fqi3_call($this->fe, 'createAttempt', 3, 'easy');
        $this->assertSame(5, $attemptId);
        $this->assertCount(2, $wpdb->queries, 'table recreated once, insert retried');
    }
}
