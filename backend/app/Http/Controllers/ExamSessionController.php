<?php
namespace App\Http\Controllers;
use App\Models\ExamSession;
use App\Services\ExamSessionService;
use Illuminate\Http\Request;

class ExamSessionController extends Controller
{
    public function access()
    {
        return response()->json(['allowed' => true, 'message' => null]);
    }
    public function current(Request $request, ExamSessionService $service)
    {
        $session = ExamSession::where('student_id', $request->user()->id)->whereNull('exam_result_id')->first();
        if (!$session) return response()->json(['message' => 'No active exam.'], 404);
        return $service->withLockedSession($request->user(), $session->id, fn ($s) => response()->json($service->payload($s)));
    }
    public function start(Request $request, ExamSessionService $service)
    {
        $request->validate(['rules_accepted' => 'required|accepted']);
        $session = $service->start($request->user());
        return $service->withLockedSession($request->user(), $session->id, fn ($s) => response()->json($service->payload($s)));
    }
    public function save(Request $request, ExamSessionService $service)
    {
        return $this->write($request, $service, false);
    }
    public function submit(Request $request, ExamSessionService $service)
    {
        return $this->write($request, $service, true);
    }
    public function terminate(Request $request, ExamSessionService $service)
    {
        $data = $request->validate(['session_id' => 'required|uuid', 'reason' => 'required|in:tab_hidden,focus_lost,page_left']);
        return $service->withLockedSession($request->user(), $data['session_id'], function ($session) use ($data, $service) {
            if (!$session->exam_result_id) {
                $session->update(['terminated_at' => now(), 'termination_reason' => $data['reason']]);
                $service->finish($session);
            }
            return response()->json($service->payload($session));
        });
    }
    private function write(Request $request, ExamSessionService $service, bool $submit)
    {
        $data = $request->validate(['session_id' => 'required|uuid', 'revision' => 'required|integer|min:0',
            'answers' => 'required|array', 'answers.*' => 'nullable|string|in:A,B,C,D', 'position' => 'sometimes|integer|min:0|max:99']);
        return $service->withLockedSession($request->user(), $data['session_id'], function ($session) use ($data, $service, $submit) {
            if ($session->exam_result_id) return response()->json($service->payload($session));
            if ((int) $data['revision'] !== (int) $session->revision) return response()->json(['message' => 'This exam was updated in another tab or device. Reload to continue with the saved answers.', 'conflict' => true], 409);
            $ids = array_column($session->questions, 'id');
            abort_if(array_diff(array_keys($data['answers']), $ids) || count($data['answers']) !== count($ids), 422, 'Answers must match the assigned examination.');
            $session->update(['answers' => $data['answers'], 'position' => $data['position'] ?? $session->position, 'revision' => $session->revision + 1]);
            if ($submit) $service->finish($session);
            return response()->json($service->payload($session));
        });
    }
}
