<?php

namespace App\Services;

use App\Exceptions\OtpException;
use App\Mail\AuthOtpMail;
use App\Models\AuthOtpChallenge;
use App\Models\Student;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class AuthOtpService
{
    public const PURPOSE_REGISTRATION = 'registration';
    public const PURPOSE_LOGIN = 'login';

    public function startRegistration(array $registration): AuthOtpChallenge
    {
        $email = $this->normalizeEmail((string) $registration['email']);
        $payload = [
            'first_name' => (string) $registration['first_name'],
            'last_name' => (string) $registration['last_name'],
            'email' => $email,
            'phone' => $registration['phone'] ?? null,
            'password' => Hash::make((string) $registration['password']),
        ];

        return $this->issueChallenge(
            $email,
            self::PURPOSE_REGISTRATION,
            null,
            Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR))
        );
    }

    public function startLogin(Student $student): AuthOtpChallenge
    {
        return $this->issueChallenge(
            $this->normalizeEmail((string) $student->email),
            self::PURPOSE_LOGIN,
            $student->id
        );
    }

    public function resend(string $challengeId): AuthOtpChallenge
    {
        $this->ensureMailDeliveryConfigured();

        $existing = AuthOtpChallenge::find($challengeId);
        $this->ensureChallengeCanBeUsed($existing);

        $cooldown = $this->resendAfter($existing);
        if ($cooldown > 0) {
            throw new OtpException(
                'Please wait before requesting another verification code.',
                429,
                $cooldown
            );
        }

        if ($existing->resend_count >= $this->maxResends()) {
            throw new OtpException(
                'The resend limit has been reached. Please start again.',
                429
            );
        }

        $this->claimEmailSendAttempt($existing->email);
        $otp = $this->generateOtp();

        $challenge = DB::transaction(function () use ($challengeId, $otp) {
            $challenge = AuthOtpChallenge::whereKey($challengeId)->lockForUpdate()->first();
            $this->ensureChallengeCanBeUsed($challenge);

            $cooldown = $this->resendAfter($challenge);
            if ($cooldown > 0) {
                throw new OtpException(
                    'Please wait before requesting another verification code.',
                    429,
                    $cooldown
                );
            }

            if ($challenge->resend_count >= $this->maxResends()) {
                throw new OtpException(
                    'The resend limit has been reached. Please start again.',
                    429
                );
            }

            $challenge->forceFill([
                'otp_hash' => Hash::make($otp),
                'attempts_remaining' => $this->maxAttempts(),
                'resend_count' => $challenge->resend_count + 1,
                'expires_at' => now()->addMinutes($this->expiresMinutes()),
                'last_sent_at' => now(),
            ])->save();

            return $challenge->fresh();
        });

        $this->deliver($challenge, $otp);

        return $challenge->fresh();
    }

    /**
     * @return array{student: Student, purpose: string}
     */
    public function verify(string $challengeId, string $otp): array
    {
        $result = DB::transaction(function () use ($challengeId, $otp) {
            $challenge = AuthOtpChallenge::whereKey($challengeId)->lockForUpdate()->first();

            try {
                $this->ensureChallengeCanBeUsed($challenge);
            } catch (OtpException $exception) {
                return ['exception' => $exception];
            }

            if ($challenge->attempts_remaining <= 0) {
                return [
                    'exception' => new OtpException(
                        'Too many incorrect verification attempts. Please start again.',
                        429
                    ),
                ];
            }

            if (!Hash::check($otp, $challenge->otp_hash)) {
                $challenge->decrement('attempts_remaining');
                $challenge->refresh();

                $message = $challenge->attempts_remaining > 0
                    ? 'The verification code is incorrect.'
                    : 'Too many incorrect verification attempts. Please start again.';

                return [
                    'exception' => new OtpException(
                        $message,
                        $challenge->attempts_remaining > 0 ? 422 : 429
                    ),
                ];
            }

            try {
                $student = $challenge->purpose === self::PURPOSE_REGISTRATION
                    ? $this->createRegisteredStudent($challenge)
                    : $this->resolveLoginStudent($challenge);
            } catch (OtpException $exception) {
                $challenge->forceFill(['consumed_at' => now()])->save();

                return ['exception' => $exception];
            }

            $challenge->forceFill(['consumed_at' => now()])->save();
            AuthOtpChallenge::where('email', $challenge->email)
                ->where('purpose', $challenge->purpose)
                ->where('id', '!=', $challenge->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            return [
                'student' => $student->fresh(),
                'purpose' => $challenge->purpose,
            ];
        });

        if (isset($result['exception'])) {
            throw $result['exception'];
        }

        return $result;
    }

    /**
     * @return array{challenge_id: string, purpose: string, masked_email: string, expires_in: int, resend_after: int}
     */
    public function metadata(AuthOtpChallenge $challenge): array
    {
        return [
            'challenge_id' => $challenge->id,
            'purpose' => $challenge->purpose,
            'masked_email' => $this->maskEmail($challenge->email),
            'expires_in' => max(0, $challenge->expires_at->getTimestamp() - now()->getTimestamp()),
            'resend_after' => $this->resendAfter($challenge),
        ];
    }

    private function issueChallenge(
        string $email,
        string $purpose,
        ?int $studentId,
        ?string $registrationPayload = null
    ): AuthOtpChallenge {
        $this->ensureMailDeliveryConfigured();
        $this->claimEmailSendAttempt($email);
        $otp = $this->generateOtp();
        $now = now();

        $challenge = DB::transaction(function () use (
            $email,
            $purpose,
            $studentId,
            $registrationPayload,
            $otp,
            $now
        ) {
            AuthOtpChallenge::where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => $now, 'updated_at' => $now]);

            return AuthOtpChallenge::create([
                'id' => (string) Str::uuid(),
                'student_id' => $studentId,
                'email' => $email,
                'purpose' => $purpose,
                'registration_payload' => $registrationPayload,
                'otp_hash' => Hash::make($otp),
                'attempts_remaining' => $this->maxAttempts(),
                'resend_count' => 0,
                'expires_at' => $now->copy()->addMinutes($this->expiresMinutes()),
                'last_sent_at' => $now,
            ]);
        });

        $this->deliver($challenge, $otp);

        return $challenge->fresh();
    }

    private function deliver(AuthOtpChallenge $challenge, string $otp): void
    {
        try {
            if ((string) config('mail.default') === 'brevo') {
                $this->deliverViaBrevo($challenge, $otp);
                return;
            }

            Mail::to($challenge->email)->send(new AuthOtpMail(
                $otp,
                $challenge->purpose,
                $this->expiresMinutes()
            ));
        } catch (Throwable $exception) {
            report($exception);
            logger()->error('OTP email delivery failed.', [
                'mailer' => (string) config('mail.default'),
                'host' => (string) config('mail.mailers.smtp.host'),
                'port' => (int) config('mail.mailers.smtp.port'),
                'encryption' => (string) config('mail.mailers.smtp.encryption'),
                'username' => (string) config('mail.mailers.smtp.username'),
                'from' => (string) config('mail.from.address'),
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);
            $challenge->forceFill(['consumed_at' => now()])->save();

            throw new OtpException(
                'The verification email could not be sent. Please try again later.',
                503
            );
        }
    }

    private function deliverViaBrevo(AuthOtpChallenge $challenge, string $otp): void
    {
        $subject = $challenge->purpose === self::PURPOSE_REGISTRATION
            ? 'Verify your CDM account'
            : 'Your CDM login verification code';

        Http::timeout(20)
            ->withHeaders([
                'accept' => 'application/json',
                'api-key' => (string) config('mail.brevo.api_key'),
                'content-type' => 'application/json',
            ])
            ->post((string) config('mail.brevo.endpoint'), [
                'sender' => [
                    'email' => (string) config('mail.from.address'),
                    'name' => (string) config('mail.from.name'),
                ],
                'to' => [['email' => $challenge->email]],
                'subject' => $subject,
                'htmlContent' => view('emails.auth-otp', [
                    'otp' => $otp,
                    'purpose' => $challenge->purpose,
                    'expiresMinutes' => $this->expiresMinutes(),
                ])->render(),
            ])
            ->throw();
    }

    private function createRegisteredStudent(AuthOtpChallenge $challenge): Student
    {
        try {
            $payload = json_decode(
                Crypt::decryptString((string) $challenge->registration_payload),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable $exception) {
            report($exception);
            throw new OtpException('This verification request is no longer valid.', 410);
        }

        if (!is_array($payload) || $this->normalizeEmail((string) ($payload['email'] ?? '')) !== $challenge->email) {
            throw new OtpException('This verification request is no longer valid.', 410);
        }

        if (Student::where('email', $challenge->email)->exists()) {
            throw new OtpException('An account with this email already exists.', 409);
        }

        $admissionYear = now()->year . '-' . (now()->year + 1);
        $highestSequence = Student::where('admission_year', $admissionYear)
            ->lockForUpdate()
            ->pluck('student_number')
            ->reduce(function (int $highest, string $studentNumber): int {
                if (preg_match('/-(\d+)$/', $studentNumber, $matches)) {
                    return max($highest, (int) $matches[1]);
                }

                return $highest;
            }, 0);

        $student = Student::create([
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'email' => $challenge->email,
            'phone' => $payload['phone'] ?? null,
            'password' => $payload['password'],
            'student_number' => 'CDM-' . now()->year . '-' . str_pad($highestSequence + 1, 5, '0', STR_PAD_LEFT),
            'admission_year' => $admissionYear,
            'is_google_account' => false,
        ]);
        $student->forceFill(['email_verified_at' => now()])->save();

        return $student;
    }

    private function resolveLoginStudent(AuthOtpChallenge $challenge): Student
    {
        $student = Student::whereKey($challenge->student_id)->lockForUpdate()->first();

        if (!$student
            || $student->is_google_account
            || $this->normalizeEmail((string) $student->email) !== $challenge->email) {
            throw new OtpException('This verification request is no longer valid.', 410);
        }

        if (!$student->email_verified_at) {
            $student->forceFill(['email_verified_at' => now()])->save();
        }

        return $student;
    }

    private function ensureChallengeCanBeUsed(?AuthOtpChallenge $challenge): void
    {
        if (!$challenge || $challenge->consumed_at) {
            throw new OtpException('This verification request is no longer valid.', 410);
        }

        if ($challenge->expires_at->isPast()) {
            throw new OtpException('The verification code has expired. Please start again.', 410);
        }
    }

    private function claimEmailSendAttempt(string $email): void
    {
        $key = 'auth-otp-send:' . hash('sha256', $this->normalizeEmail($email));
        $limit = $this->maxSendsPerHour();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = max(1, RateLimiter::availableIn($key));
            throw new OtpException(
                'Too many verification codes have been requested. Please try again later.',
                429,
                $retryAfter
            );
        }

        RateLimiter::hit($key, 3600);
    }

    private function ensureMailDeliveryConfigured(): void
    {
        if ((string) config('mail.default') === 'brevo') {
            if (trim((string) config('mail.brevo.api_key')) === '') {
                throw new OtpException(
                    'Email verification is not configured. Please contact the system administrator.',
                    503
                );
            }

            return;
        }

        if ((string) config('mail.default') !== 'smtp') {
            return;
        }

        $username = trim((string) config('mail.mailers.smtp.username'));
        $password = trim((string) config('mail.mailers.smtp.password'));
        $fromAddress = trim((string) config('mail.from.address'));

        if ($username === '' || $password === '' || $fromAddress === '') {
            throw new OtpException(
                'Email verification is not configured. Please contact the system administrator.',
                503
            );
        }
    }

    private function resendAfter(AuthOtpChallenge $challenge): int
    {
        return max(
            0,
            $challenge->last_sent_at->getTimestamp() + $this->resendSeconds() - now()->getTimestamp()
        );
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        $masked = $visible . str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible)));

        return $masked . '@' . $domain;
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function expiresMinutes(): int
    {
        return max(1, (int) config('otp.expires_minutes', 10));
    }

    private function resendSeconds(): int
    {
        return max(1, (int) config('otp.resend_seconds', 60));
    }

    private function maxAttempts(): int
    {
        return max(1, min(255, (int) config('otp.max_attempts', 5)));
    }

    private function maxResends(): int
    {
        return max(0, min(255, (int) config('otp.max_resends', 5)));
    }

    private function maxSendsPerHour(): int
    {
        return max(1, (int) config('otp.max_sends_per_hour', 5));
    }
}
