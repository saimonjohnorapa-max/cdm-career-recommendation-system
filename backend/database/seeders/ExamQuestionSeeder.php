<?php

namespace Database\Seeders;

use App\Models\ExamQuestion;
use Illuminate\Database\Seeder;

class ExamQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = require database_path('data/entrance_exam_questions.php');

        // Preserve the question bank maintained by exam managers.
        if (ExamQuestion::query()->exists()) {
            return;
        }
        $number = 0;
        foreach ($questions as $category => $rows) {
            foreach ($rows as [$text, $a, $b, $c, $d, $answer]) {
                ExamQuestion::create([
                    'question_number' => ++$number, 'category' => $category, 'question_text' => $text,
                    'option_a' => $a, 'option_b' => $b, 'option_c' => $c, 'option_d' => $d,
                    'correct_answer' => $answer, 'difficulty_level' => 'medium', 'is_active' => true,
                ]);
            }
        }
    }
}
