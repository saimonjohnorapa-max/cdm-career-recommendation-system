<?php

namespace Tests\Unit;

use App\Services\ProgramMatcher;
use App\Services\ProgramRecommendationAI;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;

class ProgramRecommendationTest extends TestCase
{
    private function courses(): array
    {
        return [
            ['id' => 1, 'code' => 'IT', 'name' => 'Computing', 'recommendation_profile' => ['Technical Aptitude' => 3, 'Science' => 1]],
            ['id' => 2, 'code' => 'SCI', 'name' => 'Science', 'recommendation_profile' => ['Science' => 3, 'Technical Aptitude' => 1]],
        ];
    }

    public function test_normalizes_categories_and_combines_interest_with_exam_evidence(): void
    {
        $result = (new ProgramMatcher)->match(['Digital Literacy' => 4, 'Science' => 1], ['Digital Literacy' => 4, 'Science' => 4], ['Digital Literacy' => 5, 'Science' => 1], $this->courses());
        $this->assertSame('IT', $result['ranked_programs'][0]['course_code']);
        $this->assertSame(81.25, $result['ranked_programs'][0]['exam_match']);
        $this->assertSame(75.0, $result['ranked_programs'][0]['interest_match']);
        $this->assertSame(80.0, $result['ranked_programs'][0]['score']);
        $basis = $result['ranked_programs'][0]['match_basis'];
        $this->assertSame(65.0, $basis['exam_points']);
        $this->assertSame(15.0, $basis['interest_points']);
        $this->assertSame(['Digital Literacy', 'Science'], array_column($basis['factors'], 'category'));
        $this->assertSame([75.0, 5.0], array_column($basis['factors'], 'points'));
        $this->assertSame([75.0, 25.0], array_column($basis['factors'], 'weight'));
        $this->assertSame(4, $basis['factors'][0]['exam_correct']);
        $this->assertSame(5, $basis['factors'][0]['interest_rating']);
        $this->assertSame('SCI', $basis['comparison']['course_code']);
        $this->assertSame(40.0, $basis['comparison']['score_gap']);
        $this->assertSame([['category' => 'Digital Literacy', 'points' => 50.0]], $basis['comparison']['advantages']);
        $this->assertNull($result['ranked_programs'][1]['match_basis']['comparison']);
    }

    public function test_all_zero_missing_or_impossible_evidence_cannot_rank_programs(): void
    {
        foreach ([[['Science' => 0], ['Science' => 1]], [['Science' => 1], []], [['Science' => 2], ['Science' => 1]]] as [$scores, $maximums]) {
            $result = (new ProgramMatcher)->match($scores, $maximums, [], $this->courses());
            $this->assertSame('insufficient_evidence', $result['status']);
            $this->assertSame([], $result['ranked_programs']);
        }
    }

    public function test_ties_are_explicit_and_exam_only_does_not_invent_interests(): void
    {
        $result = (new ProgramMatcher)->match(['Digital Literacy' => 1, 'Science' => 1], ['Digital Literacy' => 1, 'Science' => 1], [], $this->courses());
        $this->assertSame(['IT', 'SCI'], $result['tied_top_codes']);
        $this->assertSame(100.0, $result['ranked_programs'][0]['score']);
        $this->assertNull($result['ranked_programs'][0]['interest_match']);
        foreach ($result['ranked_programs'] as $program) {
            $this->assertStringContainsString('shares the highest', $program['match_basis']['summary']);
            $this->assertNull($program['match_basis']['comparison']);
            $this->assertNull($program['match_basis']['interest_points']);
            $this->assertSame([null, null], array_column($program['match_basis']['factors'], 'interest_rating'));
        }
    }

    public function test_exam_only_explanation_uses_the_full_exam_weight(): void
    {
        $result = (new ProgramMatcher)->match(['Digital Literacy' => 4, 'Science' => 1], ['Digital Literacy' => 4, 'Science' => 4], [], $this->courses());
        $top = $result['ranked_programs'][0];
        $this->assertSame(81.25, $top['score']);
        $this->assertSame(81.25, $top['match_basis']['exam_points']);
        $this->assertNull($top['match_basis']['interest_points']);
        $this->assertSame([75.0, 6.25], array_column($top['match_basis']['factors'], 'points'));
        $this->assertSame(37.5, $top['match_basis']['comparison']['score_gap']);
    }

    public function test_zero_contributions_are_not_named_as_leading_evidence_and_single_program_is_explicit(): void
    {
        $result = (new ProgramMatcher)->match(['Digital Literacy' => 0, 'Science' => 1], ['Digital Literacy' => 4, 'Science' => 4], [], [$this->courses()[0]]);
        $basis = $result['ranked_programs'][0]['match_basis'];
        $this->assertStringContainsString('only program', $basis['summary']);
        $this->assertStringContainsString('Science', $basis['summary']);
        $this->assertStringNotContainsString('Digital Literacy', $basis['summary']);
        $this->assertNull($basis['comparison']);
        $this->assertSame(0.0, $basis['factors'][1]['points']);
    }

    public function test_a_program_with_unmeasured_categories_is_excluded(): void
    {
        $result = (new ProgramMatcher)->match(['Science' => 1], ['Science' => 1], [], $this->courses());
        $this->assertSame('insufficient_evidence', $result['status']);
    }

    private function bootAI(): void
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        restore_error_handler();
        restore_exception_handler();
        config(['services.gemini.api_key' => 'test-only', 'services.gemini.model' => 'test-model']);
        Http::preventStrayRequests();
    }

    public function test_ai_explanations_are_validated_and_request_excludes_identity(): void
    {
        $this->bootAI();
        Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode(['programs' => [['course_code' => 'IT', 'explanation' => 'Explore computing based on your assessment.', 'next_step' => 'Try a programming exercise.']]])]]]]]])]);
        $matches = (new ProgramMatcher)->match(['Digital Literacy' => 4, 'Science' => 1], ['Digital Literacy' => 4, 'Science' => 4], [], [$this->courses()[0]]);
        $result = (new ProgramRecommendationAI)->explain($matches, [], []);
        $this->assertArrayHasKey('IT', $result);
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-only') && isset($request['generationConfig']['responseJsonSchema']) && !str_contains($request->body(), 'student_id'));
        Http::assertSent(function ($request) {
            $evidence = json_decode($request['contents'][0]['parts'][0]['text'], true);
            return $evidence['assessment']['ranked_programs'][0]['match_basis']['factors'][0]['category'] === 'Digital Literacy'
                && str_contains($request['systemInstruction']['parts'][0]['text'], 'begin by explaining why it ranks highest');
        });
    }

    public function test_unknown_ai_program_is_rejected(): void
    {
        $this->bootAI();
        Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"programs":[{"course_code":"INVENTED","explanation":"x","next_step":"y"}]}']]]]]])]);
        $this->expectException(\RuntimeException::class);
        (new ProgramRecommendationAI)->explain(['ranked_programs' => [['course_code' => 'IT']]], [], []);
    }

    public function test_incomplete_ai_response_is_rejected(): void
    {
        $this->bootAI();
        Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'MAX_TOKENS']]])]);
        $this->expectException(\RuntimeException::class);
        (new ProgramRecommendationAI)->explain(['ranked_programs' => [['course_code' => 'IT']]], [], []);
    }
}
