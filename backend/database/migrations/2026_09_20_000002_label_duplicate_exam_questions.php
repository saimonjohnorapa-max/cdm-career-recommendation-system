<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $categories = [
                'General Mathematics',
                'Science',
                'Reading Comprehension',
                'Logical Reasoning',
                'Digital Literacy',
            ];

            foreach ($categories as $category) {
                $questions = DB::table('exam_questions')
                    ->where('category', $category)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get();
                $seen = [];
                $variants = [];

                foreach ($questions as $question) {
                    $key = hash('sha256', (string) $question->question_text);
                    $variants[$key] = ($variants[$key] ?? 0) + 1;
                    if ($variants[$key] === 1) {
                        $seen[$key] = true;
                        continue;
                    }

                    DB::table('exam_questions')->where('id', $question->id)->update([
                        'question_text' => $question->question_text . ' (Practice variant ' . $variants[$key] . ')',
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Keep renamed questions because exam sessions may reference them.
    }
};