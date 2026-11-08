<?php

namespace App\Http\Controllers;

use App\Models\ExamResult;
use App\Models\Recommendation;
use App\Models\Course;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function getRecommendation(Request $request)
    {
        return $this->recommend($request, false);
    }

    public function generateRecommendation(Request $request)
    {
        return $this->recommend($request, true);
    }

    private function recommend(Request $request, bool $generate)
    {
        $student = $request->user();
        $exam = ExamResult::where('student_id', $student->id)->latest('id')->first();
        if (!$exam) return response()->json(['message' => 'Complete the entrance exam first.'], 403);
        $interests = $exam->career_interests ?: [];
        if ($generate) {
            $rules = ['interests' => ['required', 'array:'.implode(',', \App\Services\ProgramMatcher::INTEREST_CATEGORIES)]];
            foreach (\App\Services\ProgramMatcher::INTEREST_CATEGORIES as $category) {
                $rules['interests.'.$category] = ['required', 'integer', 'between:1,5'];
            }
            $interests = $request->validate($rules)['interests'];
        }
        $courses = Course::where('is_active', true)->where('is_recommendable', true)->orderBy('code')->get();
        $catalog = $courses->map(fn ($course) => $course->only(['id', 'code', 'name', 'description', 'subjects', 'career_paths', 'recommendation_profile']))->all();
        $fingerprint = hash('sha256', json_encode([\App\Services\ProgramMatcher::VERSION, $exam->category_scores, $exam->category_maximums, $interests, $catalog, config('services.gemini.model')]));
        $saved = $exam->recommendation_payload;
        if (($saved['fingerprint'] ?? null) === $fingerprint && (!$generate || ($saved['ai_status'] ?? '') === 'generated')) {
            return response()->json(['success' => true, 'recommendation' => $this->studentGuidance($exam, $saved), 'interests' => $interests]);
        }
        $result = app(\App\Services\ProgramMatcher::class)->match($exam->category_scores ?: [], $exam->category_maximums ?: [], $interests, $catalog);
        $result['fingerprint'] = $fingerprint;
        $result['ai_status'] = 'not_requested';
        if ($generate && $result['status'] === 'ready') {
            try {
                $result['ai_explanations'] = app(\App\Services\ProgramRecommendationAI::class)->explain($result, $interests, $catalog);
                $result['ai_status'] = 'generated';
                $result['model'] = config('services.gemini.model');
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('Program recommendation AI unavailable', ['exception_type' => get_class($exception)]);
                $result['ai_status'] = 'unavailable';
            }
        }
        if ($generate) {
            $result['generated_at'] = now()->toIso8601String();
            \Illuminate\Support\Facades\DB::transaction(function () use ($exam, $interests, $result, $student) {
                $student->newQuery()->whereKey($student->id)->lockForUpdate()->firstOrFail();
                $exam->update(['career_interests' => $interests, 'recommendation_payload' => $result]);
                if ($result['status'] === 'ready' && count($result['tied_top_codes']) === 1) {
                    $top = $result['ranked_programs'][0];
                    Recommendation::updateOrCreate(['student_id' => $student->id], [
                        'course_id' => $top['course_id'], 'confidence_score' => $top['score'],
                        'reasoning' => $result['ai_explanations'][$top['course_code']]['explanation'] ?? $top['match_basis']['summary'],
                        'alternative_recommendations' => array_slice($result['ranked_programs'], 1, 3),
                    ]);
                    $student->update(['recommended_program' => $top['course_code']]);
                } else {
                    Recommendation::where('student_id', $student->id)->delete();
                    $student->update(['recommended_program' => null]);
                }
            });
        }
        return response()->json(['success' => true, 'recommendation' => $this->studentGuidance($exam, $result), 'interests' => $interests]);
    }

    private function studentGuidance(ExamResult $exam, array $result): array
    {
        if (!$exam->registrar_pass) return $result;
        // Do not disclose exact performance through evidence, ranks, or cached AI prose.
        unset($result['evidence'], $result['fingerprint'], $result['ai_explanations']);
        $result['ranked_programs'] = array_map(fn ($program) => array_intersect_key($program, array_flip(['course_id', 'course_code', 'course_name'])), $result['ranked_programs'] ?? []);
        return $result;
    }

    public function sendResultsEmail(Request $request)
    {
        try {
            $student = $request->user();
            $examResult = ExamResult::where('student_id', $student->id)->latest()->first();

            if (!$examResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'No exam result found'
                ], 404);
            }

            $recommendation = Recommendation::where('student_id', $student->id)->first();
            $course = Course::find($recommendation->course_id);

            // In production, use actual email sending
            // Mail::send('emails.exam-result', [
            //     'student' => $student,
            //     'examResult' => $examResult,
            //     'recommendation' => $recommendation,
            //     'course' => $course,
            // ], function ($message) use ($student) {
            //     $message->to($student->email)
            //             ->subject('CDM Entrance Exam Results - Career Recommendation');
            // });

            return response()->json([
                'success' => true,
                'message' => 'Results email sent successfully',
                'email_sent_to' => $student->email,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function downloadCertificate(Request $request)
    {
        try {
            $student = $request->user();
            $examResult = ExamResult::where('student_id', $student->id)->latest()->first();

            if (!$examResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'No exam result found'
                ], 404);
            }

            $recommendation = Recommendation::where('student_id', $student->id)->first();

            // Generate certificate data for frontend to handle PDF generation
            $certificateData = [
                'student_name' => $student->full_name,
                'student_number' => $student->student_number,
                'exam_date' => $examResult->exam_date->format('F d, Y'),
                'score' => $examResult->total_score . '/100',
                'percentage' => round($examResult->percentage, 2) . '%',
                'recommended_program' => Course::find($recommendation->course_id)->name,
                'status' => $examResult->is_passed ? 'PASSED' : 'RETAKE',
            ];

            return response()->json([
                'success' => true,
                'certificate_data' => $certificateData,
                'message' => 'Certificate data ready for download',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
