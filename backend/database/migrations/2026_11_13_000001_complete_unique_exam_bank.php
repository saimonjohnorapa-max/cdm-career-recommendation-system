<?php

use App\Models\ExamQuestion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $defaults = require database_path('data/entrance_exam_questions.php');

        DB::transaction(function () use ($defaults) {
            $seen = [];
            $counts = array_fill_keys(array_keys($defaults), 0);
            $number = (int) DB::table('exam_questions')->max('question_number');
            $existing = DB::table('exam_questions')->where('is_active', true)
                ->whereIn('category', array_keys($defaults))
                ->orderBy('question_number')->orderBy('id')->lockForUpdate()->get();

            foreach ($existing as $question) {
                $key = ExamQuestion::contentKey($question->question_text);
                if (isset($seen[$key])) {
                    // Retain IDs and historical references; remove only duplicate availability.
                    DB::table('exam_questions')->where('id', $question->id)
                        ->update(['is_active' => false, 'updated_at' => now()]);
                    continue;
                }
                $seen[$key] = true;
                $counts[$question->category]++;
            }

            foreach ($defaults as $category => $questions) {
                foreach ($questions as [$text, $a, $b, $c, $d, $answer]) {
                    if ($counts[$category] >= 20) break;
                    $key = ExamQuestion::contentKey($text);
                    if (isset($seen[$key])) continue;
                    DB::table('exam_questions')->insert([
                        'question_number' => ++$number, 'category' => $category, 'question_text' => $text,
                        'option_a' => $a, 'option_b' => $b, 'option_c' => $c, 'option_d' => $d,
                        'correct_answer' => $answer, 'difficulty_level' => 'medium', 'is_active' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $seen[$key] = true;
                    $counts[$category]++;
                }
            }
        });
    }

    public function down(): void
    {
        // Preserve questions and deactivation decisions referenced by exam history.
    }
};
