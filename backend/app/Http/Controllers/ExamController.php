<?php

namespace App\Http\Controllers;

use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\ExamAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    private const TIME_LIMIT_MINUTES = 120;
    private const PASSING_SCORE = 75;
    private function examQuestions()
    {
        $bank = ExamQuestion::where('is_active', true)->orderBy('question_number')->orderBy('id')->get()->groupBy('category');
        return collect(\App\Services\ProgramMatcher::INTEREST_CATEGORIES)
            ->flatMap(fn ($category) => ($bank->get($category) ?? collect())->take(20))->values();
    }
    public function getQuestions()
    {
        try {
            // Instructions disclose topic counts; question content is released only by starting/resuming a session.
            $questions = $this->examQuestions()->map(fn ($question) => $question->only(['id', 'category']));

            return response()->json([
                'success' => true,
                'total_questions' => $questions->count(),
                'passing_score' => self::PASSING_SCORE,
                'total_score' => 100,
                'time_limit' => self::TIME_LIMIT_MINUTES,
                'categories' => $questions->countBy('category'),
                'questions' => $questions,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getExamResult(Request $request)
    {
        try {
            $student = $request->user();
            $attempts = ExamResult::where('student_id', $student->id)->latest('id')->get();
            $examResult = $attempts->first();
            $session = $examResult ? \App\Models\ExamSession::where('exam_result_id', $examResult->id)->first() : null;

            if (!$examResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'No exam result found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'exam_completed' => true,
                'terminated_at' => $session?->terminated_at?->toIso8601String(),
                'termination_reason' => $session?->termination_reason,
                'attempts_used' => $attempts->count(),
                'can_retake' => $attempts->count() === 1 && $examResult->allowsRetake(),
                'official_status' => $examResult->official_status,
                'official_result' => $examResult->official_status === 'published' ? [
                    'score' => $examResult->registrar_pass ? null : $examResult->official_score,
                    'percentage' => $examResult->registrar_pass ? null : $examResult->official_score . '%',
                    'outcome' => $examResult->registrar_pass || $examResult->official_score >= self::PASSING_SCORE ? 'PASSED' : ($attempts->count() < 2 ? 'RETAKE' : 'FAILED'),
                    'remarks' => $examResult->registrar_pass ? null : $examResult->registrar_remarks,
                    'published_at' => $examResult->published_at?->toIso8601String(),
                ] : null,
                'message' => $examResult->official_status === 'published'
                    ? 'Your official result has been published by the CDM Registrar.'
                    : 'Your submission is awaiting publication by the CDM Registrar.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
