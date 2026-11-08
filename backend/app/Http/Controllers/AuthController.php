<?php

namespace App\Http\Controllers;

use App\Exceptions\OtpException;
use App\Exceptions\GoogleVerificationUnavailable;
use App\Models\Student;
use App\Models\StudentActivityLog;
use App\Services\AuthOtpService;
use App\Services\TrustedDeviceService;
use App\Services\GoogleTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge([
            'first_name' => $this->normalizeName((string) $request->first_name),
            'last_name' => $this->normalizeName((string) $request->last_name),
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => ['required', 'email', 'max:191', 'unique:students,email'],
            'password' => 'required|min:8|confirmed',
            'phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $challenge = $this->otpService()->startRegistration($validator->validated());

            return response()->json(array_merge([
                'success' => true,
                'otp_required' => true,
                'message' => 'A verification code was sent to your email.',
            ], $this->otpService()->metadata($challenge)), 202);
        } catch (OtpException $exception) {
            return $this->otpErrorResponse($exception);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Unable to start email verification. Please try again.',
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'trusted_device_token' => 'nullable|string|max:128',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student = Student::where('email', $request->email)->first();

        if (!$student
            || $student->is_google_account
            || $student->account_status !== 'active'
            || empty($student->password)
            || !Hash::check((string) $request->password, (string) $student->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        if ($this->trustedDeviceService()->validFor($student, $request->input('trusted_device_token'))) {
            $token = $student->createToken('auth-token')->plainTextToken;
            $this->recordStudentActivity($request, $student, 'login', 'trusted_device');

            return response()->json([
                'success' => true,
                'otp_required' => false,
                'trusted_device' => true,
                'message' => 'Login successful on this trusted device.',
                'student' => $student->makeHidden(['exam_score', 'recommended_program']),
                'token' => $token,
                'student_number' => $student->student_number,
                'admission_year' => $student->admission_year,
            ]);
        }

        try {
            $challenge = $this->otpService()->startLogin($student);

            return response()->json(array_merge([
                'success' => true,
                'otp_required' => true,
                'message' => 'A login verification code was sent to your email.',
            ], $this->otpService()->metadata($challenge)), 202);
        } catch (OtpException $exception) {
            return $this->otpErrorResponse($exception);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json([
                'success' => false,
                'message' => 'Unable to start login verification. Please try again.',
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_id' => 'required|uuid',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->otpService()->verify(
                (string) $request->challenge_id,
                (string) $request->otp
            );
            $student = $result['student'];
            if ($student->account_status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'This student account is not active.',
                ], 403);
            }
            $token = $student->createToken('auth-token')->plainTextToken;
            $trustedDeviceToken = $this->trustedDeviceService()->issue($student, $request);
            $this->recordStudentActivity(
                $request,
                $student,
                $result['purpose'] === AuthOtpService::PURPOSE_REGISTRATION ? 'registered' : 'login',
                $result['purpose'] === AuthOtpService::PURPOSE_REGISTRATION ? 'email_otp' : 'password_otp'
            );

            return response()->json([
                'success' => true,
                'message' => $result['purpose'] === AuthOtpService::PURPOSE_REGISTRATION
                    ? 'Student account verified and created successfully.'
                    : 'Login verified successfully.',
                'student' => $student->makeHidden(['exam_score', 'recommended_program']),
                'token' => $token,
                'trusted_device_token' => $trustedDeviceToken,
                'trusted_device_expires_in_days' => (int) config('otp.trusted_device_days', 30),
                'student_number' => $student->student_number,
                'admission_year' => $student->admission_year,
            ]);
        } catch (OtpException $exception) {
            return $this->otpErrorResponse($exception);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json([
                'success' => false,
                'message' => 'The verification code could not be confirmed. Please try again.',
            ], 500);
        }
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $challenge = $this->otpService()->resend((string) $request->challenge_id);

            return response()->json(array_merge([
                'success' => true,
                'otp_required' => true,
                'message' => 'A new verification code was sent.',
            ], $this->otpService()->metadata($challenge)));
        } catch (OtpException $exception) {
            return $this->otpErrorResponse($exception);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json([
                'success' => false,
                'message' => 'The verification code could not be resent. Please try again.',
            ], 500);
        }
    }

    public function googleLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'google_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $googleData = $this->verifyGoogleToken($request->google_token);

            if (!$googleData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token'
                ], 401);
            }

            $email = strtolower(trim((string) ($googleData['email'] ?? '')));

            if (!$this->isVerifiedGoogleAccount($googleData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only a verified Google account can sign in.',
                ], 403);
            }

            $student = Student::where('google_id', $googleData['sub'])->first();
            if (!$student) {
                $student = Student::where('email', $email)->first();
                if ($student && $student->google_id && !hash_equals((string) $student->google_id, (string) $googleData['sub'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email is already linked to a different Google account.',
                    ], 409);
                }
            }

            if (!$student) {
                $student = $this->createStudentWithNumber([
                    'first_name' => $googleData['given_name'] ?? '',
                    'last_name' => $googleData['family_name'] ?? '',
                    'email' => $email,
                    'google_id' => $googleData['sub'],
                    'is_google_account' => true,
                    'profile_picture' => $googleData['picture'] ?? null,
                ])->refresh();
            } elseif (!$student->google_id) {
                $student->update(['google_id' => $googleData['sub']]);
            }

            if ($student->account_status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'This student account is not active.',
                ], 403);
            }

            if (!$student->email_verified_at) {
                $student->forceFill(['email_verified_at' => now()])->save();
            }

            $token = $student->createToken('auth-token')->plainTextToken;
            $this->recordStudentActivity($request, $student, 'login', 'google');

            return response()->json([
                'success' => true,
                'message' => 'Google login successful',
                'student' => $student->fresh()->makeHidden(['exam_score', 'recommended_program']),
                'token' => $token,
                'student_number' => $student->student_number,
                'admission_year' => $student->admission_year,
            ], 200);
        } catch (GoogleVerificationUnavailable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Google sign-in could not be completed. Please try again.',
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $this->recordStudentActivity($request, $request->user(), 'logout');
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ], 200);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'student' => $request->user()->makeHidden(['exam_score', 'recommended_program']),
        ], 200);
    }

    private function generateStudentNumber($admissionYear)
    {
        $year = now()->year;
        $highestSequence = Student::where('admission_year', $admissionYear)
            ->lockForUpdate()
            ->pluck('student_number')
            ->reduce(function (int $highest, string $studentNumber): int {
                if (preg_match('/-(\d+)$/', $studentNumber, $matches)) {
                    return max($highest, (int) $matches[1]);
                }

                return $highest;
            }, 0);

        return 'CDM-' . $year . '-' . str_pad($highestSequence + 1, 5, '0', STR_PAD_LEFT);
    }

    private function createStudentWithNumber(array $attributes): Student
    {
        if (array_key_exists('first_name', $attributes)) {
            $attributes['first_name'] = $this->normalizeName((string) $attributes['first_name']);
        }
        if (array_key_exists('last_name', $attributes)) {
            $attributes['last_name'] = $this->normalizeName((string) $attributes['last_name']);
        }

        return DB::transaction(function () use ($attributes) {
            $admissionYear = now()->year . '-' . (now()->year + 1);

            return Student::create(array_merge($attributes, [
                'student_number' => $this->generateStudentNumber($admissionYear),
                'admission_year' => $admissionYear,
            ]));
        }, 3);
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->trim()->lower()->title()->toString();
    }

    private function otpService(): AuthOtpService
    {
        return app(AuthOtpService::class);
    }

    private function trustedDeviceService(): TrustedDeviceService
    {
        return app(TrustedDeviceService::class);
    }

    private function otpErrorResponse(OtpException $exception)
    {
        $payload = [
            'success' => false,
            'message' => $exception->getMessage(),
        ];

        if ($exception->retryAfter() !== null) {
            $payload['retry_after'] = $exception->retryAfter();
        }

        return response()->json($payload, $exception->statusCode());
    }

    private function verifyGoogleToken(string $token): ?array
    {
        return app(GoogleTokenVerifier::class)->verify($token);
    }

    private function isVerifiedGoogleAccount(array $claims): bool
    {
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $emailVerified
            && $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && !empty($claims['sub']);
    }

    private function recordStudentActivity(Request $request, Student $student, string $action, ?string $authMethod = null): void
    {
        try {
            StudentActivityLog::create([
                'student_id' => $student->id,
                'action' => $action,
                'auth_method' => $authMethod,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $exception) {
            // Logging must not prevent a student from signing in or out.
            report($exception);
        }
    }
}
