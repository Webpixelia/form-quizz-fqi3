<?php
use PHPUnit\Framework\TestCase;

/** Records what the frontend asks the statistics manager to do. */
final class FakeStatistics {
    public array $saved = [];
    public array $awarded = [];
    public bool $saveResult = true;
    public bool $throw = false;
    public function save_user_performance(int $user, string $level, int $correct, int $total): bool {
        if ($this->throw) { throw new RuntimeException('db down'); }
        $this->saved[] = [$user, $level, $correct, $total];
        return $this->saveResult;
    }
    public function get_awards_instance(): self { return $this; }
    public function award_badges_based_on_criteria(int $user, string $level): void { $this->awarded[] = [$user, $level]; }
}

/**
 * Lifecycle of a quiz attempt: start -> questions served -> completion, and what the server refuses.
 * Handlers are driven through their AJAX entry points; wp_send_json*() is replaced by an exception (tests/bootstrap.php).
 */
final class AttemptLifecycleTest extends TestCase {
    private object $fe;
    private FakeStatistics $stats;
    private const USER = 7;

    protected function setUp(): void {
        WpState::reset();
        $GLOBALS['wpdb'] = new FakeWpdb();
        // The plugin keeps the attempt id in $_SESSION (session_start() is neutralised in tests/bootstrap.php)
        $_SESSION = [];
        $_POST = [];
        $_GET = [];

        fqi3_test_load('includes/FQI3_Frontend.php');
        if (!class_exists('TestableFrontend')) {
            // Same class, with the statistics manager replaced by a recorder
            eval('class TestableFrontend extends Form_Quizz_FQI3\FQI3_Frontend {
                public static $stats;
                protected function statisticsService(): ?object { return self::$stats; }
            }');
        }
        $this->stats = TestableFrontend::$stats = new FakeStatistics();
        $this->fe = fqi3_instance('TestableFrontend');
        $opts = (new ReflectionClass('Form_Quizz_FQI3\FQI3_Frontend'))->getProperty('options');
        $opts->setAccessible(true);
        $opts->setValue($this->fe, ['fqi3_free_trials_per_day' => 3, 'fqi3_number_questions_per_quiz' => 2]);
        WpState::$options['fqi3_quiz_levels'] = [
            'fqi3_quiz_levels_name'    => ['free_lvl', 'members_lvl'],
            'fqi3_quiz_levels_label'   => ['Free', 'Members'],
            'fqi3_quiz_levels_is_free' => [1, 0],
        ];
        WpState::$loggedIn = true;
        WpState::$userId   = self::USER;
        WpState::$roles    = ['premium_member'];
        // Two questions: the right options are 1 and 0.
        $GLOBALS['wpdb']->rows = [
            ['id' => 11, 'answer' => '1', 'options' => '["a","b"]', 'niveau' => 'free_lvl'],
            ['id' => 12, 'answer' => '0', 'options' => '["c","d"]', 'niveau' => 'free_lvl'],
        ];
        // No minimum duration unless a test asks for it
        WpState::$filters['fqi3_min_seconds_per_question'] = [static fn() => 0];
    }

    /** Run an AJAX handler and return the response it would have sent. */
    private function call(string $method): WpJsonResponse {
        try {
            $this->fe->$method();
        } catch (WpJsonResponse $r) {
            return $r;
        }
        $this->fail("$method did not send a response");
    }

    private function start(string $level = 'free_lvl'): WpJsonResponse {
        $_POST = ['quiz_level' => $level];
        return $this->call('fqi3_register_quiz_start');
    }

    private function serve(string $level = 'free_lvl'): WpJsonResponse {
        $_GET = ['niveau' => $level];
        return $this->call('handleGetQuestions');
    }

    private function complete(array $answers, string $level = 'free_lvl', array $extra = []): WpJsonResponse {
        $_POST = ['quiz_level' => $level, 'answers' => json_encode($answers)] + $extra;
        return $this->call('fqi3_register_quiz_complete_handler');
    }

    private function openAttempt(): array {
        return WpState::$userMeta[self::USER]['fqi3_open_attempt'] ?? [];
    }

    // --- start ---------------------------------------------------------------------------------

    public function testStartRefusesAnUnknownLevel(): void {
        $r = $this->start('hacked');
        $this->assertFalse($r->ok);
        $this->assertSame(0, WpState::fired('fqi3_quiz_started'));
        $this->assertSame([], $this->openAttempt());
    }

    public function testStartRespectsTheDailyLimitOfSubscribers(): void {
        WpState::$roles = ['subscriber'];
        for ($i = 0; $i < 3; $i++) { fqi3_call($this->fe, 'incrementQuizCount'); }
        $this->assertFalse($this->start()->ok);
    }

    public function testRepeatedStartReusesTheAttemptAndFiresTheHooksOnce(): void {
        global $wpdb;
        $first  = $this->start();
        $second = $this->start();
        $this->assertTrue($first->ok);
        $this->assertSame($first->payload['attempt_id'], $second->payload['attempt_id']);
        $this->assertSame(1, WpState::fired('fqi3_quiz_started'));
        $this->assertSame(1, WpState::fired('fqi3_quiz_attempt_started'));
        $this->assertSame(4 + 1, $wpdb->insert_id, 'a single row was inserted');
    }

    public function testStartOnAnotherLevelRetargetsTheUnservedAttempt(): void {
        global $wpdb;
        $first = $this->start('free_lvl');
        $wpdb->queries = [];
        $second = $this->start('members_lvl');
        $this->assertSame($first->payload['attempt_id'], $second->payload['attempt_id']);
        $this->assertSame('members_lvl', $this->openAttempt()['level']);
        $this->assertStringContainsString('UPDATE', $wpdb->queries[0]);
    }

    public function testStartLegacyHookCarriesTheAttemptIdAsExtraArgument(): void {
        $r = $this->start();
        $this->assertSame([self::USER, 'free_lvl', $r->payload['attempt_id']], WpState::lastArgs('fqi3_quiz_started'));
    }

    // --- questions served -----------------------------------------------------------------------

    public function testServedQuestionsAreBoundToTheAttempt(): void {
        $attempt = $this->start()->payload['attempt_id'];
        $this->serve();
        $open = $this->openAttempt();
        $this->assertSame($attempt, $open['attempt_id']);
        $this->assertSame([11, 12], $open['question_ids']);
        $this->assertGreaterThan(0, $open['served_at']);
        $this->assertSame(['source' => 'core', 'attempt_id' => $attempt], WpState::lastArgs('fqi3_quiz_questions_drawn')[3]);
    }

    public function testServingAgainAfterQuestionsWereServedOpensANewAttempt(): void {
        $first = $this->start()->payload['attempt_id'];
        $this->serve();
        $this->serve();
        $this->assertNotSame($first, $this->openAttempt()['attempt_id']);
    }

    // --- completion -----------------------------------------------------------------------------

    public function testCompletionWithoutServedQuestionsIsRefused(): void {
        $this->start();
        $r = $this->complete([11 => 1, 12 => 0]);
        $this->assertFalse($r->ok);
        $this->assertSame(0, WpState::fired('fqi3_quiz_completed'));
        $this->assertSame(0, WpState::fired('fqi3_quiz_attempt_completed'));
    }

    public function testCompletionWithoutAnyAttemptIsRefused(): void {
        $this->assertFalse($this->complete([11 => 1])->ok);
        $this->assertSame(0, WpState::fired('fqi3_quiz_attempt_completed'));
    }

    public function testScoreIsComputedByTheServerAndTheBrowserScoreIsIgnored(): void {
        $attempt = $this->start()->payload['attempt_id'];
        $this->serve();
        // One right answer out of two, while the browser claims 100
        $r = $this->complete([11 => 1, 12 => 1], 'free_lvl', ['quiz_score' => '100']);
        $this->assertTrue($r->ok);
        $this->assertSame(50.0, $r->payload['score']);
        $this->assertSame($attempt, $r->payload['attempt_id']);
    }

    public function testAllWrongOrMissingAnswersScoreZero(): void {
        $this->start(); $this->serve();
        $this->assertSame(0.0, $this->complete([11 => 0])->payload['score']);
    }

    public function testAnswersToQuestionsThatWereNotServedAreIgnored(): void {
        $this->start(); $this->serve();
        $r = $this->complete([11 => 1, 12 => 0, 999 => 3]);
        $this->assertSame(100.0, $r->payload['score']);
    }

    public function testCompletionNeedsAnAnswersPayload(): void {
        $this->start(); $this->serve();
        $_POST = ['quiz_level' => 'free_lvl', 'quiz_score' => '100'];
        $this->assertFalse($this->call('fqi3_register_quiz_complete_handler')->ok);
        $_POST = ['quiz_level' => 'free_lvl', 'answers' => 'not json'];
        $this->assertFalse($this->call('fqi3_register_quiz_complete_handler')->ok);
        $this->assertSame(0, WpState::fired('fqi3_quiz_attempt_completed'));
    }

    public function testCompletionOnAnotherLevelThanTheServedOneIsRefused(): void {
        $this->start(); $this->serve('free_lvl');
        $this->assertFalse($this->complete([11 => 1, 12 => 0], 'members_lvl')->ok);
    }

    public function testTooFastCompletionIsRefused(): void {
        WpState::$filters['fqi3_min_seconds_per_question'] = [static fn() => 5];
        $this->start(); $this->serve();
        $this->assertFalse($this->complete([11 => 1, 12 => 0])->ok, 'served just now, 10 seconds required');

        // The same completion passes once enough time has elapsed
        $meta = $this->openAttempt();
        $meta['served_at'] -= 11;
        WpState::$userMeta[self::USER]['fqi3_open_attempt'] = $meta;
        $this->assertTrue($this->complete([11 => 1, 12 => 0])->ok);
    }

    public function testHooksFireExactlyOnceAndAReplayDoesNothing(): void {
        $attempt = $this->start()->payload['attempt_id'];
        $this->serve();
        $this->assertTrue($this->complete([11 => 1, 12 => 0])->ok);
        $this->assertFalse($this->complete([11 => 1, 12 => 0])->ok, 'replay after completion');

        $this->assertSame(1, WpState::fired('fqi3_quiz_completed'));
        $this->assertSame(1, WpState::fired('fqi3_quiz_attempt_completed'));
        $this->assertSame([self::USER, 'free_lvl', $attempt], WpState::lastArgs('fqi3_quiz_completed'));
        $this->assertSame([$attempt, self::USER, 'free_lvl', 100.0], WpState::lastArgs('fqi3_quiz_attempt_completed'));
        $this->assertSame([], $this->openAttempt());
    }

    public function testAParallelRequestThatLosesTheRaceFiresNothing(): void {
        global $wpdb;
        $this->start(); $this->serve();
        $wpdb->queryReturn = 0; // the conditional UPDATE touched no row: another request already closed the attempt
        $r = $this->complete([11 => 1, 12 => 0]);
        $this->assertFalse($r->ok);
        $this->assertSame(0, WpState::fired('fqi3_quiz_attempt_completed'));
    }

    public function testClosingAnAttemptIsConditionalOnItBeingOpenAndOwnedByTheUser(): void {
        global $wpdb;
        $this->assertTrue(fqi3_call($this->fe, 'completeAttempt', 5, self::USER, 80.0));
        $this->assertStringContainsString('completed_at IS NULL', $wpdb->queries[0]);
        $this->assertStringContainsString('user_id = %d', $wpdb->queries[0]);
        $this->assertFalse(fqi3_call($this->fe, 'completeAttempt', 0, self::USER, 80.0));
    }

    public function testVisitorsCannotComplete(): void {
        WpState::$loggedIn = false; WpState::$userId = 0;
        $this->assertFalse($this->complete([11 => 1])->ok);
    }

    // --- statistics and badges -----------------------------------------------------------------

    private function playOneRightOneWrong(array $extra = []): WpJsonResponse {
        $this->start(); $this->serve();
        // The browser claims 2/2 correct and a perfect score
        return $this->complete([11 => 1, 12 => 1], 'free_lvl', $extra + ['correct_answers' => '2', 'total_questions' => '2', 'quiz_score' => '100']);
    }

    public function testStatisticsAndBadgesUseTheVerifiedResultNotTheBrowserValues(): void {
        $this->assertTrue($this->playOneRightOneWrong()->ok);
        $this->assertSame([[self::USER, 'free_lvl', 1, 2]], $this->stats->saved);
        $this->assertSame([[self::USER, 'free_lvl']], $this->stats->awarded);
    }

    public function testStatisticsAreRecordedOnlyOncePerAttempt(): void {
        $this->playOneRightOneWrong();
        $this->complete([11 => 1, 12 => 1]); // replay
        $this->assertCount(1, $this->stats->saved);
    }

    public function testNoStatisticsForUsersWithoutAPremiumRole(): void {
        WpState::$roles = ['subscriber'];
        $this->assertTrue($this->playOneRightOneWrong()->ok);
        $this->assertSame([], $this->stats->saved);
        $this->assertSame([], $this->stats->awarded);
    }

    public function testNoStatisticsWhenTheyAreDisabledInTheSettings(): void {
        $opts = (new ReflectionClass('Form_Quizz_FQI3\FQI3_Frontend'))->getProperty('options');
        $opts->setAccessible(true);
        $opts->setValue($this->fe, ['fqi3_number_questions_per_quiz' => 2, 'fqi3_disable_statistics' => 1]);
        $this->assertTrue($this->playOneRightOneWrong()->ok);
        $this->assertSame([], $this->stats->saved);
    }

    public function testBadgesAreNotAwardedWhenTheStatisticsCouldNotBeSaved(): void {
        $this->stats->saveResult = false;
        $this->assertTrue($this->playOneRightOneWrong()->ok);
        $this->assertSame([], $this->stats->awarded);
    }

    public function testAStatisticsFailureNeverBlocksTheCompletion(): void {
        $this->stats->throw = true;
        $r = $this->playOneRightOneWrong();
        $this->assertTrue($r->ok);
        $this->assertSame(1, WpState::fired('fqi3_quiz_attempt_completed'));
    }

    // --- fqi3_before_results_quiz_form ---------------------------------------------------------

    public function testResultHookUsesTheServerResultForLoggedInUsersAndIsOneShot(): void {
        $attempt = $this->start()->payload['attempt_id'];
        $this->serve();
        $this->complete([11 => 1, 12 => 1]); // 50 %

        // The browser lies about level and score
        $_POST = ['quiz_level' => 'members_lvl', 'quiz_score' => '100'];
        $this->assertTrue($this->call('process_quiz_hooks_handler')->ok);
        $this->assertSame([self::USER, 'free_lvl', 50.0, true], WpState::lastArgs('fqi3_before_results_quiz_form'));

        $this->assertFalse($this->call('process_quiz_hooks_handler')->ok, 'consumed once');
        $this->assertSame(1, WpState::fired('fqi3_before_results_quiz_form'));
        $this->assertGreaterThan(0, $attempt);
    }

    public function testResultHookIsRefusedForALoggedInUserWithoutACompletedQuiz(): void {
        $_POST = ['quiz_level' => 'free_lvl', 'quiz_score' => '100'];
        $this->assertFalse($this->call('process_quiz_hooks_handler')->ok);
        $this->assertSame(0, WpState::fired('fqi3_before_results_quiz_form'));
    }

    public function testResultHookForVisitorsIsMarkedUnverified(): void {
        WpState::$loggedIn = false; WpState::$userId = 0;
        $_POST = ['quiz_level' => 'free_lvl', 'quiz_score' => '9999'];
        $this->assertTrue($this->call('process_quiz_hooks_handler')->ok);
        $this->assertSame([0, 'free_lvl', 100.0, false], WpState::lastArgs('fqi3_before_results_quiz_form'));
    }
}
