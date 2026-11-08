<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\Course;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class RecommendationFlowTest extends \Illuminate\Foundation\Testing\TestCase
{
    private array $sessions = [];
    public function test_student_removal_cleans_linked_records_and_retains_audit_history(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $student = $this->examStudent('remove-student');
        $student->update(['profile_picture' => 'profile_pictures/student-photo.png']);
        \Illuminate\Support\Facades\Storage::disk('public')->put($student->profile_picture, 'test image');
        $session = app(\App\Services\ExamSessionService::class)->start($student);
        app(\App\Services\ExamSessionService::class)->withLockedSession($student, $session->id,
            fn ($session) => app(\App\Services\ExamSessionService::class)->finish($session));
        $student->createToken('student-device');
        $student->trustedDevices()->create(['token_hash' => hash('sha256', 'test-device'), 'expires_at' => now()->addDay()]);
        \App\Models\Recommendation::create(['student_id' => $student->id, 'course_id' => Course::firstOrFail()->id, 'confidence_score' => 80, 'reasoning' => 'Test recommendation']);
        DB::table('portal_notifications')->insert(['student_id' => $student->id, 'message' => 'Test notification']);
        DB::table('student_password_resets')->insert(['email' => $student->email, 'token_hash' => 'test-hash', 'expires_at' => now()->addHour()]);
        DB::table('auth_otp_challenges')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'student_id' => null,
            'email' => $student->email, 'purpose' => 'registration', 'otp_hash' => 'test-hash', 'attempts_remaining' => 5,
            'resend_count' => 0, 'expires_at' => now()->addHour(), 'last_sent_at' => now()]);
        $studentLog = \App\Models\StudentActivityLog::create(['student_id' => $student->id, 'action' => 'login']);
        $other = Student::create(['first_name' => 'Keep', 'last_name' => 'Student', 'email' => 'keep@example.com', 'student_number' => 'KEEP', 'admission_year' => 'TEST', 'account_status' => 'active']);
        $other->createToken('other-device');
        $admin = \App\Models\Admin::create(['name' => 'Super Admin', 'email' => 'remove-admin@example.com', 'role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/admin/students/'.$student->id)->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        foreach (['exam_sessions', 'exam_results', 'recommendations', 'portal_notifications', 'trusted_devices'] as $table) {
            $this->assertDatabaseMissing($table, ['student_id' => $student->id]);
        }
        $this->assertDatabaseCount('exam_answers', 0);
        $this->assertDatabaseCount('exam_session_questions', 0);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_type' => Student::class, 'tokenable_id' => $student->id]);
        $this->assertDatabaseMissing('auth_otp_challenges', ['email' => $student->email]);
        $this->assertDatabaseMissing('student_password_resets', ['email' => $student->email]);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('profile_pictures/student-photo.png');
        $this->assertNull($studentLog->fresh()->student_id);
        $log = \App\Models\AdminActivityLog::where('action', 'student.deleted')->sole();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame($student->id, $log->subject_id);
        $this->assertSame('remove-student', $log->details['student_number']);
        $this->assertSame(1, $log->details['exam_results']);
        $this->assertDatabaseHas('students', ['id' => $other->id]);
        $this->assertSame(1, $other->tokens()->count());
        $this->assertGreaterThan(0, ExamQuestion::count());
        $this->deleteJson('/api/admin/students/'.$student->id)->assertNotFound();
    }

    public function test_student_removal_requires_an_active_admin_with_student_management_permission(): void
    {
        $student = $this->examStudent('protected-student');
        $this->deleteJson('/api/admin/students/'.$student->id)->assertForbidden();
        foreach (['viewer', 'exam_manager'] as $role) {
            $admin = \App\Models\Admin::create(['name' => $role, 'email' => $role.'-remove@example.com', 'role' => $role, 'status' => 'active']);
            Sanctum::actingAs($admin);
            $this->deleteJson('/api/admin/students/'.$student->id)->assertForbidden();
        }
        $admin->update(['role' => 'admissions_staff', 'status' => 'suspended']);
        $this->deleteJson('/api/admin/students/'.$student->id)->assertForbidden();
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $admin->update(['status' => 'active']);
        $this->deleteJson('/api/admin/students/'.$student->id)->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_student_removal_rolls_back_when_audit_record_cannot_be_saved(): void
    {
        $student = $this->examStudent('rollback-student');
        $student->createToken('keep-on-failure');
        DB::table('student_password_resets')->insert(['email' => $student->email, 'token_hash' => 'test-hash', 'expires_at' => now()->addHour()]);
        $admin = \App\Models\Admin::create(['name' => 'Admissions', 'email' => 'rollback-admin@example.com', 'role' => 'admissions_staff', 'status' => 'active']);
        Sanctum::actingAs($admin);
        DB::statement("CREATE TRIGGER reject_delete_audit BEFORE INSERT ON admin_activity_logs WHEN NEW.action = 'student.deleted' BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->deleteJson('/api/admin/students/'.$student->id)->assertStatus(500);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertSame(1, $student->tokens()->count());
        $this->assertDatabaseHas('student_password_resets', ['email' => $student->email]);
        $this->assertDatabaseMissing('admin_activity_logs', ['action' => 'student.deleted']);
    }

    private function examStudent(string $name = 'policy'): Student
    {
        $student = Student::create(['first_name' => 'Policy', 'last_name' => 'Test', 'email' => $name.'@example.com', 'student_number' => $name, 'admission_year' => '2026-2027', 'account_status' => 'active']);
        Sanctum::actingAs($student);
        return $student;
    }

    public function test_examination_requires_campus_network_and_cannot_trust_spoofed_forwarding(): void
    {
        $this->examStudent();
        config(['exam.allowed_networks' => []]);
        $this->getJson('/api/exam/access')->assertOk()->assertJsonPath('allowed', false);
        $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertForbidden();
        config(['exam.allowed_networks' => ['203.0.113.0/24']]);
        $this->withHeader('X-Forwarded-For', '203.0.113.10')->getJson('/api/exam/questions')->assertForbidden();
        $this->assertSame(0, \App\Models\ExamSession::count());
        config(['exam.trusted_proxies' => ['127.0.0.1', '::1']]);
        $this->getJson('/api/exam/questions')->assertOk();
        $this->withHeader('X-Forwarded-For', '203.0.113.10, 198.51.100.20')->getJson('/api/exam/questions')->assertForbidden();
    }

    public function test_mobile_and_tablets_cannot_access_exam_but_can_view_results(): void
    {
        $this->examStudent();
        foreach (['Mozilla/5.0 (iPhone) Mobile', 'Mozilla/5.0 (Linux; Android 14)', 'Mozilla/5.0 (iPad)'] as $agent) {
            $this->withHeader('User-Agent', $agent);
            $this->getJson('/api/exam/access')->assertOk()->assertJsonPath('allowed', false);
            $this->getJson('/api/exam/questions')->assertForbidden();
            $this->getJson('/api/exam/session')->assertForbidden();
            $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertForbidden();
            $this->putJson('/api/exam/session', [])->assertForbidden();
            $this->postJson('/api/exam/submit', [])->assertForbidden();
            $this->getJson('/api/exam/result')->assertNotFound();
        }
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh)', 'X-Exam-Device' => 'mobile'])->getJson('/api/exam/questions')->assertForbidden();
        $this->assertSame(0, \App\Models\ExamSession::count());
    }

    public function test_exam_requires_explicit_rules_acceptance(): void
    {
        $this->examStudent();
        $this->postJson('/api/exam/session')->assertUnprocessable()->assertJsonValidationErrors('rules_accepted');
        $this->postJson('/api/exam/session', ['rules_accepted' => false])->assertUnprocessable();
        $this->assertSame(0, \App\Models\ExamSession::count());
    }

    public function test_termination_finalizes_saved_answers_once_and_prevents_resuming(): void
    {
        $student = $this->examStudent();
        $start = $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertOk()->json();
        $answers = $start['answers'];
        $firstId = $start['questions'][0]['id'];
        $answers[$firstId] = ExamQuestion::findOrFail($firstId)->correct_answer;
        $this->putJson('/api/exam/session', ['session_id' => $start['session_id'], 'revision' => 0, 'answers' => $answers])->assertOk();
        // Finalization must still work if the client moves off-site or is detected as mobile.
        config(['exam.allowed_networks' => []]);
        $this->withHeader('User-Agent', 'iPhone');
        $body = ['session_id' => $start['session_id'], 'reason' => 'tab_hidden'];
        $this->postJson('/api/exam/session/terminate', $body)->assertOk()->assertJsonPath('exam_completed', true)->assertJsonPath('termination_reason', 'tab_hidden');
        $this->postJson('/api/exam/session/terminate', $body)->assertOk();
        $this->getJson('/api/exam/result')->assertOk()->assertJsonPath('termination_reason', 'tab_hidden')->assertJsonPath('can_retake', false);
        config(['exam.allowed_networks' => ['127.0.0.1', '::1']]);
        $this->withHeader('User-Agent', 'Desktop');
        $this->getJson('/api/exam/session')->assertNotFound();
        $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertConflict();
        $this->postJson('/api/exam/submit', ['session_id' => $start['session_id'], 'revision' => 0, 'answers' => array_fill_keys(array_keys($answers), 'D')])->assertOk();
        $this->assertSame(1, ExamResult::where('student_id', $student->id)->count());
        $this->assertSame(1, (int) ExamResult::firstOrFail()->total_score);
    }

    public function test_another_student_cannot_terminate_an_exam(): void
    {
        $this->examStudent('owner');
        $start = $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertOk()->json();
        $this->examStudent('other');
        $this->postJson('/api/exam/session/terminate', ['session_id' => $start['session_id'], 'reason' => 'page_left'])->assertNotFound();
        $this->assertSame(0, ExamResult::count());
    }

    public function test_admin_guidance_summary_counts_latest_attempt_and_ties(): void
    {
        $student = Student::create(['first_name' => 'Summary', 'last_name' => 'Test', 'email' => 'summary@example.com', 'student_number' => 'SUMMARY', 'admission_year' => '2026-2027', 'account_status' => 'active']);
        $base = ['student_id' => $student->id, 'total_score' => 80, 'percentage' => 80, 'time_spent' => 50, 'exam_date' => now(), 'is_passed' => true];
        ExamResult::create($base + ['recommendation_payload' => ['status' => 'ready', 'generated_at' => now()->toIso8601String(), 'ai_status' => 'generated', 'tied_top_codes' => ['OLD']]]);
        ExamResult::create($base + ['recommendation_payload' => ['status' => 'ready', 'generated_at' => now()->toIso8601String(), 'ai_status' => 'unavailable', 'tied_top_codes' => ['BSIT', 'BSCPE']]]);
        Sanctum::actingAs($student);
        $this->getJson('/api/admin/dashboard')->assertStatus(403);
        $admin = \App\Models\Admin::create(['name' => 'Summary', 'email' => 'summary-admin@example.com', 'role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('recommendation_summary.generated_students', 1)->assertJsonPath('recommendation_summary.ai_generated_students', 0)->assertJsonCount(2, 'recommendation_summary.top_programs')->assertJsonMissing(['code' => 'OLD']);
    }
    private function submitAnswers(array $data)
    {
        $key = auth()->id().':'.($data['attempt_number'] ?? 1);
        if (!isset($this->sessions[$key])) {
            $start = $this->postJson('/api/exam/session', ['rules_accepted' => true]);
            if ($start->status() !== 200) return $start;
            $this->sessions[$key] = $start->json('session_id');
        }
        return $this->postJson('/api/exam/submit', ['session_id' => $this->sessions[$key], 'revision' => 0, 'answers' => $data['answers']]);
    }
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set('cache.default', 'array');
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        config(['exam.allowed_networks' => ['127.0.0.1', '::1'], 'exam.trusted_proxies' => []]);
        // All migrations execute against an isolated, in-memory database only.
        Artisan::call('migrate', ['--force' => true]);
        // A fresh database has samples only; build a complete bank as an isolated test fixture.
        $number = (int) ExamQuestion::max('question_number');
        foreach (\App\Services\ProgramMatcher::INTEREST_CATEGORIES as $category) {
            $sample = ExamQuestion::where('category', $category)->firstOrFail();
            for ($count = ExamQuestion::where('category', $category)->where('is_active', true)->count(); $count < 20; $count++) {
                $copy = $sample->replicate(); $copy->question_number = ++$number; $copy->is_active = true; $copy->save();
            }
        }
        Http::preventStrayRequests();
    }

    public function test_randomized_attempts_preserve_topics_resume_and_snapshot_grading(): void
    {
        $number = (int) ExamQuestion::max('question_number');
        foreach (\App\Services\ProgramMatcher::INTEREST_CATEGORIES as $category) {
            $sample = ExamQuestion::where('category', $category)->firstOrFail();
            for ($i = 0; $i < 10; $i++) {
                $copy = $sample->replicate(); $copy->question_number = ++$number; $copy->save();
            }
        }
        $orders = []; $topicOrders = [];
        for ($i = 0; $i < 5; $i++) {
            $student = Student::create(['first_name' => 'Shuffle', 'last_name' => 'Test', 'email' => "shuffle{$i}@example.com", 'student_number' => "SHUFFLE-{$i}", 'admission_year' => '2026-2027', 'account_status' => 'active']);
            Sanctum::actingAs($student);
            $start = $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertOk()->json();
            $orders[] = array_column($start['questions'], 'id');
            $topics = array_column($start['questions'], 'category');
            $topicOrders[] = array_values(array_unique($topics));
            $this->assertCount(100, array_unique($orders[$i]));
            $this->assertCount(5, $topicOrders[$i]);
            foreach (array_chunk($topics, 20) as $chunk) $this->assertCount(1, array_unique($chunk));
            $this->getJson('/api/exam/session')->assertJsonPath('questions', $start['questions']);
            $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertJsonPath('questions', $start['questions']);
        }
        $this->assertGreaterThan(1, count(array_unique(array_map('json_encode', $orders))));
        $this->assertGreaterThan(1, count(array_unique(array_map('json_encode', $topicOrders))));
        $this->postJson('/api/exam/submit', ['session_id' => $start['session_id'], 'revision' => 0, 'answers' => $start['answers']])->assertOk();
        ExamResult::where('student_id', $student->id)->update(['official_status' => 'published', 'official_score' => 0, 'published_at' => now()]);
        $retake = $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertOk()->assertJsonPath('attempt_number', 2)->json();
        $this->assertNotSame(array_column($start['questions'], 'id'), array_column($retake['questions'], 'id'));
        $answers = ExamQuestion::whereIn('id', array_column($retake['questions'], 'id'))->pluck('correct_answer', 'id')->all();
        ExamQuestion::query()->update(['correct_answer' => 'A']);
        $this->postJson('/api/exam/submit', ['session_id' => $retake['session_id'], 'revision' => 0, 'answers' => $answers])->assertOk();
        $this->assertSame(100, (int) ExamResult::where('student_id', $student->id)->latest('id')->first()->total_score);
        $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertStatus(409);
    }

    public function test_full_bank_is_restored_and_stale_five_question_submissions_are_rejected(): void
    {
        $sample = ExamQuestion::firstOrFail();
        ExamQuestion::query()->delete();
        for ($number = 1; $number <= 100; $number++) {
            $question = $sample->replicate();
            $question->question_number = $number;
            $question->category = match (true) {
                $number <= 21 => 'General Mathematics',
                $number <= 47 => 'Science',
                $number <= 68 => 'Reading Comprehension',
                $number <= 84 => 'Logical Reasoning',
                default => 'Technical Aptitude',
            };
            $question->is_active = false;
            $question->save();
        }
        $migration = require __DIR__.'/../../database/migrations/2026_09_07_000002_restore_full_existing_exam_bank.php';
        $migration->up();
        $this->assertSame(100, ExamQuestion::where('is_active', true)->count());
        $this->assertSame(0, ExamQuestion::where('category', 'Technical Aptitude')->count());
        $this->assertSame($sample->question_text, ExamQuestion::first()->question_text);
        $balance = require __DIR__.'/../../database/migrations/2026_09_07_000003_complete_twenty_question_topics.php';
        $balance->up();
        $student = Student::create(['first_name' => 'Exam', 'last_name' => 'Test', 'email' => 'full@example.com', 'student_number' => 'FULL-1', 'admission_year' => 2026, 'account_status' => 'active']);
        Sanctum::actingAs($student);
        $response = $this->getJson('/api/exam/questions')->assertOk()->assertJsonCount(100, 'questions')->assertJsonPath('time_limit', 120);
        $selected = $response->json('questions');
        // This legacy grading fixture uses exactly 100 questions; larger randomized pools are tested separately.
        ExamQuestion::whereNotIn('id', array_column($selected, 'id'))->update(['is_active' => false]);
        foreach (\App\Services\ProgramMatcher::INTEREST_CATEGORIES as $index => $category) {
            $this->assertSame(array_fill(0, 20, $category), array_column(array_slice($selected, $index * 20, 20), 'category'));
        }
        $this->assertSame(array_column($selected, 'id'), array_column($this->getJson('/api/exam/questions')->json('questions'), 'id'));
        $answers = ExamQuestion::whereIn('id', array_column($selected, 'id'))->pluck('correct_answer', 'id')->all();
        $this->submitAnswers(['answers' => array_slice($answers, 0, 5, true), 'time_spent' => 50])->assertStatus(422);
        $this->assertSame(0, ExamResult::count());
        $this->submitAnswers(['answers' => $answers, 'time_spent' => 50])->assertOk();
        $exam = ExamResult::firstOrFail();
        $this->assertSame(100, array_sum($exam->category_maximums));
        $this->assertSame(100, (int) $exam->total_score);
        $this->getJson('/api/exam/questions')->assertJsonPath('passing_score', 75);
        foreach ([75 => true, 74 => false] as $score => $passed) {
            $candidate = Student::create(['first_name' => 'Boundary', 'last_name' => 'Test', 'email' => "boundary{$score}@example.com", 'student_number' => "BOUNDARY-{$score}", 'admission_year' => 2026, 'account_status' => 'active']);
            Sanctum::actingAs($candidate);
            $submission = $answers;
            $index = 0;
            foreach ($submission as $id => $answer) {
                if ($index++ >= $score) $submission[$id] = $answer === 'A' ? 'B' : 'A';
            }
            $this->submitAnswers(['answers' => $submission, 'time_spent' => 50])->assertOk();
            $result = ExamResult::where('student_id', $candidate->id)->firstOrFail();
            $this->assertSame($passed, $result->is_passed);
            $result->update(['official_score' => $score, 'official_status' => 'published']);
            $this->getJson('/api/exam/result')->assertJsonPath('official_result.outcome', $passed ? 'PASSED' : 'RETAKE');
        }
    }

    public function test_only_one_retake_is_allowed_after_a_published_failure(): void
    {
        $student = Student::create(['first_name' => 'Retake', 'last_name' => 'Test', 'email' => 'retake@example.com', 'student_number' => 'RETAKE-1', 'admission_year' => 2026, 'account_status' => 'active']);
        Sanctum::actingAs($student);
        $ids = array_column($this->getJson('/api/exam/questions')->json('questions'), 'id');
        $submission = ['answers' => array_fill_keys($ids, null), 'time_spent' => 50];
        $this->submitAnswers($submission)->assertOk();
        $first = ExamResult::firstOrFail();
        $this->getJson('/api/exam/result')->assertJsonPath('can_retake', false);
        $this->submitAnswers($submission + ['attempt_number' => 2])->assertStatus(409);
        $first->update(['official_status' => 'approved', 'official_score' => 74]);
        $this->submitAnswers($submission + ['attempt_number' => 2])->assertStatus(409);
        $first->update(['official_status' => 'published', 'official_score' => 75]);
        $this->getJson('/api/exam/result')->assertJsonPath('can_retake', false);
        $this->submitAnswers($submission + ['attempt_number' => 2])->assertStatus(409);
        $first->update(['official_score' => 74]);
        $this->getJson('/api/exam/result')->assertJsonPath('can_retake', true)->assertJsonPath('attempts_used', 1);
        // A delayed duplicate of the original submission must not consume the retake.
        $this->submitAnswers($submission)->assertOk();
        $this->submitAnswers($submission + ['attempt_number' => 2])->assertOk();
        $second = ExamResult::latest('id')->firstOrFail();
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(count($ids), $first->examAnswers()->count());
        $this->assertSame(count($ids), $second->examAnswers()->count());
        $this->getJson('/api/exam/result')->assertJsonPath('attempts_used', 2)->assertJsonPath('can_retake', false)->assertJsonPath('official_result', null);
        $second->update(['official_status' => 'published', 'official_score' => 50]);
        $this->getJson('/api/exam/result')->assertJsonPath('official_result.outcome', 'FAILED')->assertJsonPath('can_retake', false);
        $this->submitAnswers($submission + ['attempt_number' => 2])->assertOk(); // Idempotent replay of the same session.
        $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertStatus(409);
        $this->assertSame(2, ExamResult::where('student_id', $student->id)->count());
    }

    public function test_bulk_registrar_pass_preserves_scores_and_keeps_decision_private(): void
    {
        $admin = \App\Models\Admin::create(['name' => 'Registrar', 'email' => 'registrar@example.com', 'role' => 'admissions_staff', 'status' => 'active']);
        $students = [];
        $eligible = [];
        foreach ([1, 2, 3] as $number) {
            $student = Student::create(['first_name' => 'Decision', 'last_name' => 'Test', 'email' => "decision{$number}@example.com", 'student_number' => "DECISION-{$number}", 'admission_year' => 2026, 'account_status' => 'active']);
            $students[] = $student;
            $first = ExamResult::create(['student_id' => $student->id, 'total_score' => 40, 'percentage' => 40, 'time_spent' => 50, 'exam_date' => now(), 'is_passed' => false, 'official_score' => 40, 'official_status' => 'published']);
            if ($number < 3) {
                $eligible[] = ExamResult::create(['student_id' => $student->id, 'total_score' => 50, 'percentage' => 50, 'time_spent' => 50, 'exam_date' => now(), 'is_passed' => false, 'official_score' => 50, 'official_status' => $number === 1 ? 'published' : 'pending']);
            }
        }
        $ids = array_map(fn ($exam) => $exam->id, $eligible);
        $payload = ['result_ids' => $ids, 'reason' => 'Internal committee decision'];
        Sanctum::actingAs($students[0]);
        $this->patchJson('/api/admin/results/registrar-pass', $payload)->assertStatus(403);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/results?registrar_pass_eligible=1')->assertJsonPath('total', 2)->assertJsonCount(2, 'eligible_ids');
        $this->patchJson('/api/admin/results/registrar-pass', ['result_ids' => $ids, 'reason' => ' '])->assertStatus(422);
        $this->patchJson('/api/admin/results/registrar-pass', ['result_ids' => [$ids[0], $first->id], 'reason' => 'Invalid selection'])->assertStatus(409);
        $this->assertFalse($eligible[0]->fresh()->registrar_pass);
        $this->patchJson('/api/admin/results/registrar-pass', $payload)->assertOk()->assertJsonPath('published', 2);
        $this->patchJson('/api/admin/results/registrar-pass', $payload)->assertStatus(409);
        $this->getJson('/api/admin/results?registrar_pass_eligible=1')->assertJsonPath('total', 0);
        $this->getJson('/api/admin/results')->assertJsonFragment(['registrar_pass_reason' => 'Internal committee decision']);
        $this->assertSame(2, \App\Models\AdminActivityLog::where('action', 'result.registrar_pass')->count());
        foreach ($eligible as $index => $exam) {
            $exam->refresh();
            $this->assertSame(50, (int) $exam->total_score);
            $this->assertSame(50, $exam->official_score);
            $this->assertFalse($exam->is_passed);
            $this->assertSame($admin->id, $exam->registrar_pass_by);
            Sanctum::actingAs($students[$index]);
            $response = $this->getJson('/api/exam/result')->assertOk()->assertJsonPath('official_result.outcome', 'PASSED')
                ->assertJsonPath('official_result.score', null)->assertJsonPath('official_result.remarks', null)->assertJsonPath('can_retake', false);
            $this->assertStringNotContainsString('Internal committee decision', $response->getContent());
            $response->assertJsonMissingPath('registrar_pass_reason')->assertJsonMissingPath('official_result.registrar_pass');
        }
    }

    public function test_submission_snapshot_ai_failure_retry_persistence_and_validation(): void
    {
        $student = Student::create(['first_name' => 'Test', 'last_name' => 'Student', 'email' => 'test@example.com', 'password' => 'unused', 'student_number' => 'TEST-1', 'admission_year' => 2026]);
        $student->update(['account_status' => 'active']);
        Sanctum::actingAs($student);
        $this->getJson('/api/results/recommendation')->assertStatus(403);
        $questions = ExamQuestion::where('is_active', true)->get();
        $answers = $questions->pluck('correct_answer', 'id')->all();
        $this->getJson('/api/exam/questions')->assertJsonCount($questions->count(), 'questions')->assertJsonMissingPath('questions.0.correct_answer');
        $this->submitAnswers(['answers' => $answers, 'time_spent' => 50])->assertOk();
        $exam = ExamResult::firstOrFail();
        $this->assertSame(100, (int) $exam->total_score);
        $this->assertEquals($questions->countBy('category')->all(), $exam->category_maximums);
        // Current bank changes must not change saved evidence.
        ExamQuestion::query()->update(['is_active' => false]);
        $this->getJson('/api/results/recommendation')->assertOk()->assertJsonPath('recommendation.status', 'ready');
        $this->postJson('/api/results/recommendation', ['interests' => []])->assertStatus(422);
        $interests = array_fill_keys(\App\Services\ProgramMatcher::INTEREST_CATEGORIES, 5);
        config(['services.gemini.api_key' => 'fake-key', 'services.gemini.model' => 'test-model']);
        Http::fake(['*' => Http::response([], 503)]);
        $this->postJson('/api/results/recommendation', ['interests' => $interests])->assertOk()->assertJsonPath('recommendation.ai_status', 'unavailable');
        $this->assertNotNull($exam->fresh()->recommendation_payload);
        $programs = Course::where('is_active', true)->where('is_recommendable', true)->get()->map(fn ($course) => ['course_code' => $course->code, 'explanation' => 'Explore this program with an adviser.', 'next_step' => 'Review the subjects.'])->all();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode(['programs' => $programs])]]]]]])]);
        $this->postJson('/api/results/recommendation', ['interests' => $interests])->assertOk()->assertJsonPath('recommendation.ai_status', 'generated');
        $this->assertNull($student->fresh()->recommended_program); // All scores tie.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake();
        $this->getJson('/api/results/recommendation')->assertOk()->assertJsonPath('recommendation.ai_status', 'generated');
        $this->postJson('/api/results/recommendation', ['interests' => $interests])->assertOk()->assertJsonPath('recommendation.ai_status', 'generated');
        Http::assertNothingSent();
    }

    public function test_server_session_freezes_answers_enforces_deadline_and_is_idempotent(): void
    {
        $student = Student::create(['first_name' => 'Session', 'last_name' => 'Test', 'email' => 'session@example.com', 'student_number' => 'SESSION-1', 'admission_year' => '2026-2027', 'account_status' => 'active']);
        Sanctum::actingAs($student);
        $start = $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertOk()->assertJsonMissingPath('questions.0.correct_answer')->json();
        $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertJsonPath('session_id', $start['session_id'])->assertJsonPath('deadline', $start['deadline']);
        $answers = ExamQuestion::whereIn('id', array_column($start['questions'], 'id'))->pluck('correct_answer', 'id')->all();
        $this->putJson('/api/exam/session', ['session_id' => $start['session_id'], 'revision' => 0, 'answers' => $answers, 'position' => 12])->assertOk()->assertJsonPath('revision', 1);
        $this->putJson('/api/exam/session', ['session_id' => $start['session_id'], 'revision' => 0, 'answers' => $answers])->assertStatus(409);
        ExamQuestion::query()->update(['correct_answer' => 'D', 'is_active' => false]);
        $this->getJson('/api/exam/session')->assertJsonPath('position', 12)->assertJsonPath('revision', 1);
        $this->travel(121)->minutes();
        $late = ['session_id' => $start['session_id'], 'revision' => 1, 'answers' => array_fill_keys(array_keys($answers), null)];
        $this->postJson('/api/exam/submit', $late)->assertOk()->assertJsonPath('exam_completed', true);
        $this->postJson('/api/exam/submit', $late)->assertOk();
        $this->assertSame(1, ExamResult::count());
        $this->assertSame(100, (int) ExamResult::first()->total_score);
        $this->assertSame(7200, (int) ExamResult::first()->time_spent);
        $this->travelBack();
    }

    public function test_session_cannot_be_read_or_written_by_another_student(): void
    {
        $first = Student::create(['first_name' => 'One', 'last_name' => 'Test', 'email' => 'one@example.com', 'student_number' => 'ONE', 'admission_year' => '2026-2027', 'account_status' => 'active']);
        $second = Student::create(['first_name' => 'Two', 'last_name' => 'Test', 'email' => 'two@example.com', 'student_number' => 'TWO', 'admission_year' => '2026-2027', 'account_status' => 'active']);
        Sanctum::actingAs($first);
        $session = $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertOk()->json();
        Sanctum::actingAs($second);
        $this->getJson('/api/exam/session')->assertNotFound();
        $this->postJson('/api/exam/submit', ['session_id' => $session['session_id'], 'revision' => 0, 'answers' => $session['answers']])->assertNotFound();
        $this->assertSame(0, ExamResult::count());
    }

    public function test_password_reset_is_single_use_and_revokes_sessions(): void
    {
        $student = Student::create(['first_name' => 'Reset', 'last_name' => 'Test', 'email' => 'reset@example.com', 'student_number' => 'RESET', 'admission_year' => '2026-2027', 'account_status' => 'active', 'password' => \Illuminate\Support\Facades\Hash::make('old-password')]);
        $student->createToken('old');
        $token = str_repeat('x', 64);
        DB::table('student_password_resets')->insert(['email' => $student->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(30)]);
        $data = ['email' => $student->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        $this->postJson('/api/auth/reset-password', array_merge($data, ['token' => str_repeat('y', 64)]))->assertStatus(422);
        $this->postJson('/api/auth/reset-password', $data)->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password', $student->fresh()->password));
        $this->assertSame(0, $student->tokens()->count());
        $this->postJson('/api/auth/reset-password', $data)->assertStatus(422);
    }

    public function test_bulk_approval_and_correction_require_permissions_and_create_notifications(): void
    {
        $student = Student::create(['first_name' => 'Batch', 'last_name' => 'Test', 'email' => 'batch@example.com', 'student_number' => 'BATCH', 'admission_year' => '2026-2027', 'account_status' => 'active']);
        $result = ExamResult::create(['student_id' => $student->id, 'total_score' => 80, 'percentage' => 80, 'time_spent' => 50, 'exam_date' => now(), 'is_passed' => true]);
        $admin = \App\Models\Admin::create(['name' => 'Registrar', 'email' => 'batch-admin@example.com', 'role' => 'exam_manager', 'status' => 'active']);
        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/results/approve-batch', ['result_ids' => [$result->id]])->assertStatus(403);
        $admin->update(['role' => 'admissions_staff']);
        $this->patchJson('/api/admin/results/approve-batch', ['result_ids' => [$result->id]])->assertOk();
        $this->patchJson('/api/admin/results/publish-approved')->assertOk();
        $result->refresh();
        $this->assertSame('PASSED', $result->official_outcome);
        $version = $result->result_version;
        $this->patchJson('/api/admin/results/'.$result->id.'/correct', ['official_score' => 70, 'reason' => 'Verified correction', 'version' => $version])->assertOk();
        $this->patchJson('/api/admin/results/'.$result->id.'/correct', ['official_score' => 90, 'reason' => 'Stale edit', 'version' => $version])->assertStatus(409);
        $this->assertSame(80, (int) $result->fresh()->total_score);
        $this->assertSame('RETAKE', $result->fresh()->official_outcome);
        Sanctum::actingAs($student);
        $this->getJson('/api/student/status')->assertJsonPath('status', 'RETAKE')->assertJsonCount(2, 'notifications');
    }

    public function test_match_reasons_remain_available_and_are_saved_when_ai_is_unavailable(): void
    {
        $student = $this->examStudent('match-reason');
        $scores = array_fill_keys(\App\Services\ProgramMatcher::INTEREST_CATEGORIES, 1);
        $scores['Digital Literacy'] = 4;
        $scores['Logical Reasoning'] = 3;
        $exam = ExamResult::create(['student_id' => $student->id, 'total_score' => 50, 'percentage' => 50, 'time_spent' => 50, 'exam_date' => now(), 'is_passed' => false,
            'category_scores' => $scores, 'category_maximums' => array_fill_keys(array_keys($scores), 4)]);
        Sanctum::actingAs($student);
        $preview = $this->getJson('/api/results/recommendation')->assertOk()->json('recommendation.ranked_programs.0');
        $this->assertNotEmpty($preview['match_basis']['summary']);
        $this->assertNull($preview['match_basis']['interest_points']);
        config(['services.gemini.api_key' => 'fake-key', 'services.gemini.model' => 'test-model']);
        Http::fake(['*' => Http::response([], 503)]);
        $interests = array_fill_keys(array_keys($scores), 3);
        $interests['Digital Literacy'] = 5;
        $response = $this->postJson('/api/results/recommendation', ['interests' => $interests])->assertOk()->assertJsonPath('recommendation.ai_status', 'unavailable');
        $top = $response->json('recommendation.ranked_programs.0');
        $this->assertSame('BSIT', $top['course_code']);
        $this->assertGreaterThan(0, $top['match_basis']['comparison']['score_gap']);
        $this->assertSame($top['match_basis']['summary'], \App\Models\Recommendation::where('student_id', $student->id)->sole()->reasoning);
        $this->assertSame($top['match_basis'], $exam->fresh()->recommendation_payload['ranked_programs'][0]['match_basis']);
        $this->getJson('/api/results/recommendation')->assertOk()->assertJsonPath('recommendation.ranked_programs.0.match_basis', $top['match_basis']);
    }

    public function test_override_guidance_does_not_disclose_original_scores(): void
    {
        $student = Student::create(['first_name' => 'Private', 'last_name' => 'Test', 'email' => 'private@example.com', 'student_number' => 'PRIVATE', 'admission_year' => '2026-2027', 'account_status' => 'active']);
        $scores = array_fill_keys(\App\Services\ProgramMatcher::INTEREST_CATEGORIES, 10);
        ExamResult::create(['student_id' => $student->id, 'total_score' => 50, 'percentage' => 50, 'time_spent' => 50, 'exam_date' => now(), 'is_passed' => false, 'registrar_pass' => true, 'category_scores' => $scores, 'category_maximums' => array_fill_keys(array_keys($scores), 20)]);
        Sanctum::actingAs($student);
        $this->getJson('/api/results/recommendation')->assertOk()->assertJsonMissingPath('recommendation.evidence')->assertJsonMissingPath('recommendation.ranked_programs.0.score')->assertJsonMissingPath('recommendation.ranked_programs.0.exam_match')->assertJsonMissingPath('recommendation.ranked_programs.0.match_basis')->assertJsonMissingPath('recommendation.ai_explanations');
        config(['services.gemini.api_key' => null]);
        $this->postJson('/api/results/recommendation', ['interests' => array_fill_keys(array_keys($scores), 5)])
            ->assertOk()->assertJsonMissingPath('recommendation.ranked_programs.0.match_basis');
        $this->assertNotEmpty($student->examResults()->first()->recommendation_payload['ranked_programs'][0]['match_basis']);
        $this->getJson('/api/results/recommendation')->assertOk()->assertJsonMissingPath('recommendation.ranked_programs.0.match_basis');
    }

    public function test_import_preview_is_non_mutating_and_stale_commits_roll_back(): void
    {
        $student = Student::create(['first_name' => 'Import', 'last_name' => 'Test', 'email' => 'import@example.com', 'student_number' => 'IMPORT', 'admission_year' => '2026-2027', 'account_status' => 'active']);
        $result = ExamResult::create(['student_id' => $student->id, 'total_score' => 80, 'percentage' => 80, 'time_spent' => 50, 'exam_date' => now(), 'is_passed' => true]);
        $admin = \App\Models\Admin::create(['name' => 'Registrar', 'email' => 'import-admin@example.com', 'role' => 'admissions_staff', 'status' => 'active']);
        Sanctum::actingAs($admin);
        $upload = function () use ($result) {
            $result->refresh();
            $csv = "applicant_number,result_id,result_version,score,remarks\nIMPORT,{$result->id},{$result->result_version},85,Verified\n";
            return \Illuminate\Http\UploadedFile::fake()->createWithContent('results.csv', $csv);
        };
        $preview = $this->postJson('/api/admin/results/import', ['file' => $upload()])->assertOk()->assertJsonCount(1, 'rows')->json('preview_id');
        $this->assertNull($result->fresh()->official_score);
        $result->update(['official_score' => 81]);
        $this->postJson('/api/admin/results/import/commit', ['preview_id' => $preview])->assertStatus(409);
        $this->assertSame(81, $result->fresh()->official_score);
        $preview = $this->postJson('/api/admin/results/import', ['file' => $upload()])->assertOk()->json('preview_id');
        $this->postJson('/api/admin/results/import/commit', ['preview_id' => $preview])->assertOk();
        $this->assertSame(85, $result->fresh()->official_score);
        $this->postJson('/api/admin/results/import/commit', ['preview_id' => $preview])->assertStatus(422);
    }

    public function test_shared_network_has_separate_account_login_limits(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/auth/login', ['email' => "campus{$i}@example.com", 'password' => 'incorrect'])->assertStatus(401);
        }
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/auth/login', ['email' => 'repeated@example.com', 'password' => 'incorrect'])->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'repeated@example.com', 'password' => 'incorrect'])->assertStatus(429);
    }

    public function test_scheduler_finalizes_expired_session_without_student_returning(): void
    {
        $student = Student::create(['first_name' => 'Expiry', 'last_name' => 'Test', 'email' => 'expiry@example.com', 'student_number' => 'EXPIRY', 'admission_year' => '2026-2027', 'account_status' => 'active']);
        Sanctum::actingAs($student);
        $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertOk();
        $this->travel(121)->minutes();
        $this->artisan('portal:maintain', ['--no-mail' => true])->assertSuccessful();
        $this->assertSame(1, ExamResult::count());
        $this->artisan('portal:maintain', ['--no-mail' => true])->assertSuccessful();
        $this->assertSame(1, ExamResult::count());
        $this->travelBack();
    }

    public function test_question_content_is_released_at_start_and_used_questions_cannot_be_deleted(): void
    {
        $student = Student::create(['first_name' => 'Bank', 'last_name' => 'Test', 'email' => 'bank@example.com', 'student_number' => 'BANK', 'admission_year' => 'TEST', 'account_status' => 'active']);
        Sanctum::actingAs($student);
        $this->getJson('/api/exam/questions')->assertOk()->assertJsonMissingPath('questions.0.question_text')->assertJsonMissingPath('questions.0.correct_answer');
        $session = $this->postJson('/api/exam/session', ['rules_accepted' => true])->assertOk()->json();
        $admin = \App\Models\Admin::create(['name' => 'Exam manager', 'email' => 'bank-admin@example.com', 'role' => 'exam_manager', 'status' => 'active']);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/admin/questions/'.$session['questions'][0]['id'])->assertStatus(422);
    }

    public function test_sqlite_backup_can_be_opened_and_verified_in_isolation(): void
    {
        $directory = storage_path('framework/testing/backup-'.\Illuminate\Support\Str::uuid());
        config(['services.backup_directory' => $directory]);
        try {
            $this->artisan('portal:backup')->assertSuccessful();
            $files = glob($directory.'/*.sqlite');
            $this->assertCount(1, $files);
            $this->assertSame(trim(file_get_contents($files[0].'.sha256')), hash_file('sha256', $files[0]));
            $restored = new \PDO('sqlite:'.$files[0]);
            $this->assertSame('ok', $restored->query('PRAGMA integrity_check')->fetchColumn());
            $this->assertSame(ExamQuestion::count(), (int) $restored->query('SELECT COUNT(*) FROM exam_questions')->fetchColumn());
            $restored = null;
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) unlink($file);
            if (is_dir($directory)) rmdir($directory);
        }
    }
}
