<?php

namespace App\Http\Middleware;

use App\Services\ExamAccessPolicy;
use Closure;
use Illuminate\Http\Request;

class EnsureExamAccess
{
    public function handle(Request $request, Closure $next)
    {
        $denial = app(ExamAccessPolicy::class)->denial($request);
        if ($denial) return response()->json(['message' => $denial, 'exam_access_denied' => true], 403);
        return $next($request);
    }
}
