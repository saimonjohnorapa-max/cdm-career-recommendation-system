<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $categories = [
            'General Mathematics',
            'Science',
            'Reading Comprehension',
            'Logical Reasoning',
            'Digital Literacy',
        ];

        DB::transaction(function () use ($categories) {
            foreach ($categories as $category) {
                $activeQuestions = DB::table('exam_questions')
                    ->where('category', $category)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get();

                if ($activeQuestions->isEmpty()) {
                    continue;
                }

                $number = (int) DB::table('exam_questions')->max('question_number');
                $sourceIndex = 0;

                while ($activeQuestions->count() < 20) {
                    $source = $activeQuestions[$sourceIndex % $activeQuestions->count()];
                    DB::table('exam_questions')->insert([
                        'question_number' => ++$number,
                        'question_text' => $source->question_text,
                        'category' => $category,
                        'option_a' => $source->option_a,
                        'option_b' => $source->option_b,
                        'option_c' => $source->option_c,
                        'option_d' => $source->option_d,
                        'correct_answer' => $source->correct_answer,
                        'difficulty_level' => $source->difficulty_level,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $activeQuestions->push($source);
                    $sourceIndex++;
                }
            }
        });
    }

    public function down(): void
    {
        // Keep questions that may already be referenced by exam sessions or results.
    }
};
