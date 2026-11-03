<?php
use PHPUnit\Framework\TestCase;

final class ImportSanitizationTest extends TestCase {
    private object $page;

    protected function setUp(): void {
        WpState::reset();
        fqi3_test_load('includes/admin/class-import-export.php');
        $this->page = fqi3_instance('Form_Quizz_FQI3\FQI3_Import_Export_Page');
    }

    private function sanitize(array $data): array {
        return fqi3_call($this->page, 'sanitize_import_data', $data);
    }

    public function testValidQuestionKeepsJsonOptionsAndCastsAnswer(): void {
        $out = $this->sanitize(['questions' => [['niveau' => 'easy', 'q' => '<b>Q?</b>', 'q2' => '', 'options' => '["a","b","c","d"]', 'answer' => '2']]]);
        $this->assertSame('["a","b","c","d"]', $out['questions'][0]['options']);
        $this->assertSame(2, $out['questions'][0]['answer']);
        $this->assertSame('Q?', $out['questions'][0]['q']);
    }

    public function testArrayOptionsBecomeSanitizedJson(): void {
        $out = $this->sanitize(['questions' => [['niveau' => 'x', 'q' => 'q', 'options' => ['x', 'y', '<i>z</i>'], 'answer' => 0]]]);
        $this->assertSame('["x","y","z"]', $out['questions'][0]['options']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidQuestions')]
    public function testInvalidQuestionsAreRejected(array $questions): void {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitize(['questions' => $questions]);
    }

    public static function invalidQuestions(): array {
        return [
            'missing options'      => [[['niveau' => 'a', 'q' => 'q', 'answer' => 0]]],
            'invalid JSON options' => [[['niveau' => 'a', 'q' => 'q', 'options' => '{oops', 'answer' => 0]]],
            'single option'        => [[['niveau' => 'a', 'q' => 'q', 'options' => ['only'], 'answer' => 0]]],
            'answer out of range'  => [[['niveau' => 'a', 'q' => 'q', 'options' => ['a', 'b'], 'answer' => 5]]],
            'entry not an array'   => [['str']],
        ];
    }

    public function testQuestionsMustBeAList(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitize(['questions' => 'nope']);
    }

    public function testNestedOptionArraysDoNotCauseATypeError(): void {
        $out = $this->sanitize(['options' => ['scopes' => ['quizzes', 'stats'], 'flag' => true, 'n' => 5, 'x' => ['a' => ['b' => '<s>c</s>']]]]);
        $this->assertSame(['quizzes', 'stats'], $out['options']['scopes']);
        $this->assertSame(1, $out['options']['flag']);
        $this->assertSame(5, $out['options']['n']);
        $this->assertSame('c', $out['options']['x']['a']['b']);
    }

    public function testOptionsMustBeAnObject(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitize(['options' => 'x']);
    }
}
