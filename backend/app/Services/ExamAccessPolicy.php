<?php

namespace App\Services;

use Illuminate\Http\Request;

class ExamAccessPolicy
{
    public function denial(Request $request): ?string
    {
        $agent = $request->userAgent() ?? '';
        if ($request->header('Sec-CH-UA-Mobile') === '?1'
            || $request->header('X-Exam-Device') === 'mobile'
            || preg_match('/android|iphone|ipad|ipod|mobile|tablet|kindle|silk|opera mini|windows phone/i', $agent)) {
            return 'Mobile phones and tablets cannot take the entrance examination. Use a desktop or laptop with an internet connection. You can still view your results on mobile.';
        }

        return null;
    }
}
