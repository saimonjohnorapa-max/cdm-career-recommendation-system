<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function getAllCourses()
    {
        try {
            $courses = Course::where('is_active', true)->orderBy('display_order')->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'total_courses' => $courses->count(),
                'courses' => $courses,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getCourseDetails($id)
    {
        try {
            $course = Course::find($id);

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'course' => $course,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function image($id)
    {
        $course = Course::findOrFail($id);
        abort_unless($course->image_data, 404);

        return response(base64_decode($course->image_data), 200, [
            'Content-Type' => $course->image_mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
