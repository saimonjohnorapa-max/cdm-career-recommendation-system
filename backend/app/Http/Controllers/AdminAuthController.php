<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Exceptions\GoogleVerificationUnavailable;
use App\Services\GoogleTokenVerifier;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function googleLogin(Request $request)
    {
        $validated = $request->validate(['google_token' => 'required|string']);
        try {
            $claims = app(GoogleTokenVerifier::class)->verify($validated['google_token']);
        } catch (GoogleVerificationUnavailable $exception) {
            report($exception);
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 503);
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!is_array($claims) || !$verified || $email === '' || empty($claims['sub'])) {
            return response()->json(['success' => false, 'message' => 'Google could not verify this account.'], 401);
        }

        $admin = Admin::where('email', $email)->first();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'This Google account has not been invited as an administrator.'], 403);
        }
        if ($admin->status === 'suspended') {
            return response()->json(['success' => false, 'message' => 'This administrator account is suspended.'], 403);
        }
        if ($admin->google_id && !hash_equals((string) $admin->google_id, (string) $claims['sub'])) {
            return response()->json(['success' => false, 'message' => 'This invitation is linked to a different Google account.'], 409);
        }

        $admin->forceFill([
            'name' => $admin->name ?: ($claims['name'] ?? $email),
            'google_id' => $claims['sub'],
            'profile_picture' => $claims['picture'] ?? $admin->profile_picture,
            'email_verified_at' => $admin->email_verified_at ?: now(),
            'status' => 'active',
            'last_login_at' => now(),
        ])->save();

        $token = $admin->createToken('admin-portal', ['admin'])->plainTextToken;
        AdminActivityLog::create([
            'admin_id' => $admin->id,
            'action' => 'admin.login',
            'subject_type' => Admin::class,
            'subject_id' => $admin->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true, 'admin' => $admin->fresh(), 'token' => $token]);
    }

    public function profile(Request $request)
    {
        return response()->json(['success' => true, 'admin' => $request->user()]);
    }

    public function logout(Request $request)
    {
        AdminActivityLog::create(['admin_id' => $request->user()->id, 'action' => 'admin.logout', 'ip_address' => $request->ip()]);
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }
}
