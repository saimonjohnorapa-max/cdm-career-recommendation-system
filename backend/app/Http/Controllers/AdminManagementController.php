<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\Course;
use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\Student;
use App\Models\StudentActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminManagementController extends Controller
{
    public function dashboard()
    {
        $latestResults = ExamResult::with('student:id,first_name,last_name,student_number')
            ->latest('exam_date')->limit(6)->get();

        $guidance = ['generated_students' => 0, 'ai_generated_students' => 0, 'top_programs' => []];
        $programCounts = [];
        foreach (ExamResult::whereRaw('id = (SELECT MAX(r.id) FROM exam_results r WHERE r.student_id = exam_results.student_id)')->select(['id', 'recommendation_payload'])->cursor() as $exam) {
            $payload = $exam->recommendation_payload;
            if (($payload['status'] ?? null) !== 'ready' || empty($payload['generated_at'])) continue;
            $guidance['generated_students']++;
            if (($payload['ai_status'] ?? null) === 'generated') $guidance['ai_generated_students']++;
            foreach (array_unique($payload['tied_top_codes'] ?? []) as $code) $programCounts[$code] = ($programCounts[$code] ?? 0) + 1;
        }
        arsort($programCounts);
        foreach ($programCounts as $code => $count) $guidance['top_programs'][] = ['code' => $code, 'students' => $count];

        return response()->json([
            'success' => true,
            'stats' => [
                'students' => Student::count(),
                'active_students' => Student::where('account_status', 'active')->count(),
                'exam_submissions' => ExamResult::count(),
                'passed' => ExamResult::whereRaw('id = (SELECT MAX(r.id) FROM exam_results r WHERE r.student_id = exam_results.student_id)')->where('official_status', 'published')->where(fn ($q) => $q->where('registrar_pass', true)->orWhere('official_score', '>=', 75))->count(),
                'exam_students' => ExamResult::distinct()->count('student_id'),
                'pending_results' => ExamResult::where('official_status', 'pending')->count(),
                'programs' => Course::count(),
                'questions' => ExamQuestion::count(),
            ],
            'latest_results' => $latestResults,
            'recommendation_summary' => $guidance,
        ]);
    }

    public function students(Request $request)
    {
        $query = Student::query()->withCount('examResults');
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%");
            });
        }
        if ($status = $request->query('status')) {
            $query->where('account_status', $status);
        }
        return response()->json($query->latest()->paginate(min((int) $request->query('per_page', 15), 50)));
    }

    public function student(Student $student)
    {
        return response()->json(['success' => true, 'student' => $student->load(['examResults' => fn ($q) => $q->latest(), 'recommendation.course'])]);
    }

    public function updateStudentStatus(Request $request, Student $student)
    {
        $validated = $request->validate(['account_status' => ['required', Rule::in(['active', 'suspended', 'archived'])]]);
        $before = $student->account_status;
        $student->update($validated);
        if ($validated['account_status'] !== 'active') {
            $student->tokens()->delete();
        }
        $this->log($request, 'student.status_updated', $student, ['from' => $before, 'to' => $student->account_status]);
        return response()->json(['success' => true, 'student' => $student->fresh(), 'message' => 'Student status updated.']);
    }

    public function results(Request $request)
    {
        $query = ExamResult::with(['student:id,first_name,last_name,email,student_number', 'student.recommendation.course']);
        if ($search = trim((string) $request->query('search'))) $query->whereHas('student', fn ($q) => $q->where(fn ($s) => $s->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('student_number', 'like', "%{$search}%")));
        if ($year = $request->query('admission_year')) $query->whereHas('student', fn ($q) => $q->where('admission_year', $year));
        if ($request->boolean('latest_only')) $query->whereRaw('exam_results.id = (SELECT MAX(r.id) FROM exam_results r WHERE r.student_id = exam_results.student_id)');
        if ($request->boolean('registrar_pass_eligible')) {
            $query->eligibleForRegistrarPass();
            $ids = (clone $query)->pluck('id');
            return response()->json(array_merge($query->latest('id')->paginate(50)->toArray(), ['eligible_ids' => $ids]));
        }
        if ($request->filled('passed')) {
            $query->where('is_passed', filter_var($request->query('passed'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('official_status')) {
            $query->where('official_status', $request->query('official_status'));
        }
        $ids = (clone $query)->pluck('id');
        return response()->json(array_merge($query->latest('id')->paginate(50)->toArray(), ['matching_ids' => $ids]));
    }

    public function publishApprovedResults(Request $request)
    {
        $count = DB::transaction(function () use ($request) {
            $results = ExamResult::where('official_status', 'approved')->whereNotNull('official_score')->orderBy('id')->lockForUpdate()->get();
            foreach ($results as $result) {
                $result->update(['official_status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now()]);
                \App\Services\ResultPublication::notify($result);
            }
            return $results->count();
        });

        $this->logRaw($request, 'results.bulk_published', ExamResult::class, null, ['count' => $count]);

        return response()->json([
            'success' => true,
            'published' => $count,
            'message' => $count > 0
                ? "{$count} approved official result(s) were published successfully."
                : 'There are no approved results ready to publish.',
        ]);
    }

    public function registrarPassResults(Request $request)
    {
        $validated = $request->validate([
            'result_ids' => 'required|array|min:1|max:10000',
            'result_ids.*' => 'required|integer|distinct',
            'reason' => 'required|string|max:2000',
        ]);
        if (trim($validated['reason']) === '') {
            return response()->json(['message' => 'Enter a reason for the Registrar decision.'], 422);
        }
        return DB::transaction(function () use ($request, $validated) {
            $results = ExamResult::eligibleForRegistrarPass()->whereIn('id', $validated['result_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($results->count() !== count($validated['result_ids'])) {
                return response()->json(['message' => 'Some selected results are no longer eligible. Refresh the list and select again.'], 409);
            }
            foreach ($results as $result) {
                $result->update([
                    'registrar_pass' => true,
                    'registrar_pass_reason' => trim($validated['reason']),
                    'registrar_pass_by' => $request->user()->id,
                    'registrar_pass_at' => now(),
                    'official_status' => 'published',
                    'published_by' => $request->user()->id,
                    'published_at' => now(),
                ]);
                $this->log($request, 'result.registrar_pass', $result, [
                    'reason' => trim($validated['reason']),
                    'system_score' => $result->total_score,
                    'official_score' => $result->official_score,
                ]);
                \App\Services\ResultPublication::notify($result);
            }
            return response()->json(['success' => true, 'published' => $results->count(), 'message' => $results->count().' result(s) published as PASSED by Registrar decision.']);
        });
    }

    public function downloadResultTemplate(Request $request)
    {
        $students = Student::query()
            ->whereHas('examResults', fn ($q) => $q->where('official_status', '!=', 'published')->whereRaw('exam_results.id = (SELECT MAX(r.id) FROM exam_results r WHERE r.student_id = exam_results.student_id)'))
            ->with(['examResults' => fn ($query) => $query->latest('id')])
            ->orderBy('student_number')
            ->limit(1000)
            ->get();
        $filename = 'cdm-registrar-import-template-' . now()->format('Y-m-d-His') . '.csv';
        $this->logRaw($request, 'results.template_downloaded', ExamResult::class, null, ['applicants' => $students->count()]);

        return response()->streamDownload(function () use ($students) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['applicant_number', 'result_id', 'result_version', 'score', 'remarks']);
            foreach ($students as $student) {
                $latestResult = $student->examResults->first();
                if ($latestResult && $latestResult->official_status !== 'published') fputcsv($output, [$student->student_number, $latestResult->id, ResultReviewController::version($latestResult), $latestResult->total_score, '']);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function readResultImport($file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();
        if (in_array($extension, ['csv', 'tsv', 'txt'], true)) {
            $delimiter = $extension === 'csv' ? ',' : "\t";
            $handle = fopen($path, 'rb');
            if (!$handle) throw new \InvalidArgumentException('The import file could not be read.');
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
                if (count($rows) > 1001) { fclose($handle); throw new \InvalidArgumentException('Import up to 1,000 data rows per batch.'); }
            }
            fclose($handle);
            return $rows;
        }
        if ($extension === 'json') {
            $data = json_decode((string) file_get_contents($path), true);
            if (!is_array($data) || !$data) throw new \InvalidArgumentException('The JSON file must contain a non-empty array of result objects.');
            foreach ($data as $row) if (!is_array($row)) throw new \InvalidArgumentException('Every JSON result must be an object.');
            $header = array_keys((array) reset($data));
            return array_merge([$header], array_map(fn ($row) => array_map(fn ($key) => $row[$key] ?? null, $header), $data));
        }
        if ($extension === 'xlsx') return $this->readXlsxRows($path);
        throw new \InvalidArgumentException('Supported files are CSV, XLSX, TSV, TXT, and JSON. Legacy XLS files must be saved as XLSX first.');
    }

    private function readXlsxRows(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) throw new \InvalidArgumentException('XLSX support requires the PHP zip extension to be enabled.');
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new \InvalidArgumentException('The XLSX workbook could not be opened.');
        $expanded = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) $expanded += $zip->statIndex($i)['size'];
        if ($expanded > 20 * 1024 * 1024) { $zip->close(); throw new \InvalidArgumentException('The expanded workbook exceeds 20 MB. Use a smaller batch.'); }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
            if (!$document) throw new \InvalidArgumentException('The XLSX shared-string data is invalid.');
            foreach ($document->xpath('//*[local-name()="si"]') ?: [] as $item) {
                $shared[] = trim(implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]') ?: [])));
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) throw new \InvalidArgumentException('The XLSX workbook has no readable first worksheet.');
        $sheet = simplexml_load_string($sheetXml, \SimpleXMLElement::class, LIBXML_NONET);
        if (!$sheet) throw new \InvalidArgumentException('The XLSX worksheet data is invalid.');
        $rows = [];
        foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $values = [];
            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $match);
                $column = 0;
                foreach (str_split($match[0] ?? 'A') as $letter) $column = $column * 26 + ord($letter) - 64;
                $type = (string) $cell['t'];
                $raw = (string) (($cell->xpath('./*[local-name()="v"]')[0] ?? null));
                $value = $type === 's' ? ($shared[(int) $raw] ?? '') : ($type === 'inlineStr' ? implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]') ?: [])) : $raw);
                $values[$column - 1] = trim((string) $value);
            }
            if ($values) { ksort($values); $rows[] = array_replace(array_fill(0, max(array_keys($values)) + 1, null), $values); }
        }
        return $rows;
    }

    public function courses()
    {
        return response()->json(['success' => true, 'courses' => Course::withCount('recommendations')->orderBy('display_order')->orderBy('name')->get()]);
    }

    public function storeCourse(Request $request)
    {
        $course = Course::create($this->storeCourseImage($request, $this->validateCourse($request)));
        if ($course->image_data) {
            $course->update(['image_path' => 'course-image/' . $course->id]);
        }
        $this->log($request, 'course.created', $course);
        return response()->json(['success' => true, 'course' => $course], 201);
    }

    public function updateCourse(Request $request, Course $course)
    {
        $course->update($this->storeCourseImage($request, $this->validateCourse($request, $course), $course));
        if ($course->image_data && $course->image_path !== 'course-image/' . $course->id) {
            $course->update(['image_path' => 'course-image/' . $course->id]);
        }
        $this->log($request, 'course.updated', $course);
        return response()->json(['success' => true, 'course' => $course->fresh()]);
    }

    public function destroyCourse(Request $request, Course $course)
    {
        if ($course->recommendations()->exists()) {
            return response()->json(['success' => false, 'message' => 'This program is used by recommendations and cannot be deleted.'], 409);
        }
        $snapshot = ['code' => $course->code, 'name' => $course->name];
        $id = $course->id;
        $course->delete();
        $this->logRaw($request, 'course.deleted', Course::class, $id, $snapshot);
        return response()->json(['success' => true, 'message' => 'Program deleted.']);
    }

    public function questions(Request $request)
    {
        $query = ExamQuestion::query();
        if ($request->filled('category')) $query->where('category', $request->query('category'));
        if ($search = trim((string) $request->query('search'))) $query->where('question_text', 'like', "%{$search}%");
        return response()->json($query->orderBy('question_number')->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function storeQuestion(Request $request)
    {
        $question = ExamQuestion::create($this->validateQuestion($request));
        $this->log($request, 'question.created', $question);
        return response()->json(['success' => true, 'question' => $question], 201);
    }

    public function updateQuestion(Request $request, ExamQuestion $question)
    {
        $question->update($this->validateQuestion($request, $question));
        $this->log($request, 'question.updated', $question);
        return response()->json(['success' => true, 'question' => $question->fresh()]);
    }

    public function destroyQuestion(Request $request, ExamQuestion $question)
    {
        if (DB::table('exam_session_questions')->where('exam_question_id', $question->id)->exists()) {
            return response()->json(['message' => 'This question belongs to a saved exam session. Deactivate it instead of deleting it.'], 422);
        }
        if ($question->examResults()->exists()) {
            return response()->json(['success' => false, 'message' => 'This question is part of submitted exams and cannot be deleted.'], 409);
        }
        $id = $question->id;
        $question->delete();
        $this->logRaw($request, 'question.deleted', ExamQuestion::class, $id);
        return response()->json(['success' => true, 'message' => 'Question deleted.']);
    }

    public function admins()
    {
        return response()->json(['success' => true, 'admins' => Admin::latest()->get()]);
    }

    public function inviteAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:191|unique:admins,email',
            'role' => ['required', Rule::in(['super_admin', 'admissions_staff', 'exam_manager', 'viewer'])],
        ]);
        $validated['email'] = strtolower(trim($validated['email']));
        $validated['status'] = 'pending';
        $admin = Admin::create($validated);
        $this->log($request, 'admin.invited', $admin, ['role' => $admin->role]);
        return response()->json(['success' => true, 'admin' => $admin, 'message' => 'Administrator invitation created.'], 201);
    }

    public function updateAdmin(Request $request, Admin $admin)
    {
        if ($admin->id === $request->user()->id && $request->input('status') === 'suspended') {
            return response()->json(['success' => false, 'message' => 'You cannot suspend your own account.'], 422);
        }
        $validated = $request->validate([
            'role' => ['sometimes', Rule::in(['super_admin', 'admissions_staff', 'exam_manager', 'viewer'])],
            'status' => ['sometimes', Rule::in(['pending', 'active', 'suspended'])],
        ]);
        $removesActiveSuperAdmin = $admin->role === 'super_admin'
            && $admin->status === 'active'
            && (($validated['role'] ?? $admin->role) !== 'super_admin' || ($validated['status'] ?? $admin->status) !== 'active');
        if ($removesActiveSuperAdmin && Admin::where('role', 'super_admin')->where('status', 'active')->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'At least one active super administrator is required.'], 422);
        }
        $admin->update($validated);
        if ($admin->status === 'suspended') $admin->tokens()->delete();
        $this->log($request, 'admin.updated', $admin, $validated);
        return response()->json(['success' => true, 'admin' => $admin->fresh()]);
    }

    public function destroyAdmin(Request $request, Admin $admin)
    {
        if ($admin->id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'You cannot remove your own administrator account.'], 422);
        }

        if ($admin->role === 'super_admin'
            && $admin->status === 'active'
            && Admin::where('role', 'super_admin')->where('status', 'active')->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'The last active super administrator cannot be removed.'], 422);
        }

        $snapshot = ['name' => $admin->name, 'email' => $admin->email, 'role' => $admin->role, 'status' => $admin->status];
        $id = $admin->id;

        DB::transaction(function () use ($admin) {
            $admin->tokens()->delete();
            $admin->delete();
        });

        $this->logRaw($request, 'admin.deleted', Admin::class, $id, $snapshot);

        return response()->json(['success' => true, 'message' => 'Administrator account removed permanently.']);
    }

    public function activityLogs(Request $request)
    {
        return response()->json(AdminActivityLog::with('admin:id,name,email')->latest()->paginate(min((int) $request->query('per_page', 25), 100)));
    }

    public function studentLogs(Request $request)
    {
        $query = StudentActivityLog::with('student:id,first_name,last_name,email,student_number');

        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas('student', function ($studentQuery) use ($search) {
                $studentQuery->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        return response()->json($query->latest()->paginate($perPage));
    }

    private function validateCourse(Request $request, ?Course $course = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')->ignore($course?->id)],
            'name' => 'required|string|max:191', 'description' => 'required|string', 'duration' => 'required|string|max:50',
            'tuition_fee' => 'nullable|numeric|min:0', 'ideal_score_range' => 'nullable|string|max:50', 'subjects' => 'nullable|array',
            'institute' => 'nullable|string|max:191', 'career_paths' => 'nullable|array',
            'program_type' => ['required', Rule::in(['degree', 'certificate'])],
            'is_active' => 'required|boolean', 'is_recommendable' => 'required|boolean',
            'display_order' => 'required|integer|min:0', 'recommendation_profile' => 'nullable|array',
            'image' => 'nullable|image|max:8192',
        ]);
    }

    private function storeCourseImage(Request $request, array $data, ?Course $course = null): array
    {
        unset($data['image']);
        if (!$request->hasFile('image')) return $data;
        if ($course?->image_path) Storage::disk('public')->delete($course->image_path);
        $file = $request->file('image');
        $data['image_path'] = null;
        $data['image_data'] = base64_encode(file_get_contents($file->getRealPath()));
        $data['image_mime_type'] = $file->getMimeType();
        return $data;
    }

    private function validateQuestion(Request $request, ?ExamQuestion $question = null): array
    {
        return $request->validate([
            'question_number' => ['required', 'integer', 'min:1', Rule::unique('exam_questions', 'question_number')->ignore($question?->id)],
            'question_text' => 'required|string', 'category' => ['required', Rule::in(\App\Services\ProgramMatcher::INTEREST_CATEGORIES)],
            'is_active' => 'sometimes|boolean',
            'option_a' => 'required|string', 'option_b' => 'required|string', 'option_c' => 'required|string', 'option_d' => 'required|string',
            'correct_answer' => ['required', Rule::in(['A', 'B', 'C', 'D'])], 'difficulty_level' => ['required', Rule::in(['easy', 'medium', 'hard'])],
        ]);
    }

    private function log(Request $request, string $action, $subject, array $details = []): void
    {
        $this->logRaw($request, $action, get_class($subject), $subject->id, $details);
    }

    private function logRaw(Request $request, string $action, ?string $type = null, ?int $id = null, array $details = []): void
    {
        AdminActivityLog::create(['admin_id' => $request->user()->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'details' => $details ?: null, 'ip_address' => $request->ip()]);
    }
}
