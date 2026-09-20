<?php
namespace App\Services;

use App\Models\{ExamSession, ExamResult, ExamQuestion, ExamAnswer, Student};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamSessionService
{
    public const TIME_LIMIT_MINUTES = 120;
    public const MAX_ATTEMPTS = 2;
    public function bank()
    {
        $bank = ExamQuestion::where('is_active', true)
            ->orderBy('question_number')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('category')
            ->map(function ($questions) {
                $unique = $questions->unique('question_text')->values();
                return $unique->count() >= 20 ? $unique : $questions->values();
            });
        return collect(ProgramMatcher::INTEREST_CATEGORIES)->shuffle()->flatMap(fn ($category) => ($bank->get($category) ?? collect())->shuffle()->take(20))->values();
    }

    public function start(Student $student): ExamSession
    {
        return DB::transaction(function () use ($student) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $active = ExamSession::where('student_id', $student->id)->whereNull('exam_result_id')->first();
            if ($active) return $active;
            $attempts = ExamResult::where('student_id', $student->id)->latest('id')->get();
            abort_if($attempts->count() >= self::MAX_ATTEMPTS || ($attempts->isNotEmpty() && !$attempts->first()->allowsRetake()) || ($attempts->isEmpty() && $student->exam_taken), 409, 'No exam attempt is available.');
            $questions = $this->bank()->map->only(['id', 'category', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'])->all();
            abort_unless(count($questions) === 100, 422, 'The exam bank must contain 20 questions in each of the five topics before starting.');
            $session = ExamSession::create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id, 'attempt_number' => $attempts->count() + 1,
                'bank_version' => hash('sha256', json_encode($questions)), 'questions' => $questions,
                'answers' => array_fill_keys(array_column($questions, 'id'), null),
                'started_at' => now(), 'deadline' => now()->addMinutes(self::TIME_LIMIT_MINUTES),
            ]);
            DB::table('exam_session_questions')->insert(array_map(fn ($q) => ['exam_session_id' => $session->id, 'exam_question_id' => $q['id']], $questions));
            return $session;
        });
    }

    public function finish(ExamSession $session): ExamResult
    {
        // Caller holds the student and session locks. The same path handles manual and timed submission.
        if ($session->exam_result_id) return ExamResult::findOrFail($session->exam_result_id);
        $scores = $maximums = [];
        $correct = 0;
        foreach ($session->questions as $question) {
            $category = $question['category'];
            $maximums[$category] = ($maximums[$category] ?? 0) + 1;
            $isCorrect = ($session->answers[$question['id']] ?? null) === $question['correct_answer'];
            $scores[$category] = ($scores[$category] ?? 0) + (int) $isCorrect;
            $correct += (int) $isCorrect;
        }
        $percentage = round($correct / count($session->questions) * 100, 2);
        $result = ExamResult::create([
            'student_id' => $session->student_id, 'total_score' => (int) round($percentage), 'percentage' => $percentage,
            'time_spent' => min(self::TIME_LIMIT_MINUTES * 60, max(0, now()->timestamp - $session->started_at->timestamp)),
            'exam_date' => now(), 'is_passed' => $percentage >= 75, 'category_scores' => $scores, 'category_maximums' => $maximums,
        ]);
        foreach ($session->questions as $question) {
            ExamAnswer::create(['exam_result_id' => $result->id, 'exam_question_id' => $question['id'],
                'student_answer' => $session->answers[$question['id']] ?? null,
                'is_correct' => ($session->answers[$question['id']] ?? null) === $question['correct_answer']]);
        }
        Student::whereKey($session->student_id)->update(['exam_taken' => true, 'exam_score' => $result->total_score]);
        $session->update(['exam_result_id' => $result->id]);
        return $result;
    }

    public function withLockedSession(Student $student, string $id, callable $action)
    {
        return DB::transaction(function () use ($student, $id, $action) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $session = ExamSession::where('student_id', $student->id)->whereKey($id)->lockForUpdate()->firstOrFail();
            if (!$session->exam_result_id && now()->gte($session->deadline)) $this->finish($session);
            return $action($session);
        });
    }

    public function payload(ExamSession $session): array
    {
        return ['success' => true, 'session_id' => $session->id, 'attempt_number' => $session->attempt_number,
            'bank_version' => $session->bank_version, 'revision' => $session->revision, 'position' => $session->position,
            'exam_completed' => (bool) $session->exam_result_id, 'server_now' => now()->toIso8601String(),
            'deadline' => $session->deadline->toIso8601String(), 'answers' => $session->answers,
            'questions' => array_map(fn ($q) => array_diff_key($q, ['correct_answer' => true]), $session->questions),
            'time_limit' => self::TIME_LIMIT_MINUTES];
    }
}
