<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\OtpService;
use App\Domain\Referrals\ReferralService;
use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryChannel;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Identity & Auth domain (TRD §5). No BVN/NIN collection (PRD §8, §14) —
 * registration is name/phone/email/password only, issuing a Sanctum
 * personal access token scoped to the device (TRD §2.2, §7).
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly OtpService $otp,
        private readonly ReferralService $referrals,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'full_name' => $data['full_name'],
            'phone_number' => $data['phone_number'],
            'email' => $data['email'],
            'password' => $data['password'],
            'country_code' => $data['country_code'] ?? 'NG',
            'is_diaspora' => isset($data['country_code']) && $data['country_code'] !== 'NG',
            'account_type' => $data['account_type'] ?? 'individual',
        ]);

        $this->ledger->walletFor($user);
        if ($user->isAgentAccount()) {
            $this->referrals->ensureAgentCode($user);
        }
        $this->referrals->redeem($user, $data['referral_code'] ?? null);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'user' => UserResource::make($user),
            'requires_email_verification' => true,
            'message' => 'Account created. Check your email and verify your address before signing in.',
        ], 201);
    }

    /**
     * Two-step for a new device (TRD §7): a recognised device (a token
     * already exists with this exact device_name) logs straight in; an
     * unrecognised one gets an OTP challenge instead of a token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'] ?? null)
            ->orWhere('phone_number', $data['phone_number'] ?? null)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'These credentials do not match our records.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'This account has been deactivated. Contact support.']);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'requires_email_verification' => true,
                'email' => $user->email,
                'message' => 'Verify your email address before signing in.',
            ], 403);
        }

        $isKnownDevice = $user->tokens()->where('name', $data['device_name'])->exists();

        // config/identity.php: a temporary escape hatch for use before a
        // real SMS_DRIVER exists (until then, OTP codes only reach the log
        // file, blocking anyone without server access). Off by default —
        // see that config file's docblock before relying on this.
        if (! $isKnownDevice && ! config('identity.bypass_login_otp')) {
            $this->otp->issue($user->phone_number, OtpPurpose::NewDeviceLogin, DeliveryChannel::Sms, $user);

            return response()->json([
                'requires_otp' => true,
                'purpose' => OtpPurpose::NewDeviceLogin->value,
                'identifier' => $user->phone_number,
                'message' => 'New device detected. Enter the verification code we sent you to finish signing in.',
            ]);
        }

        if (! $isKnownDevice) {
            Log::warning('Login OTP bypassed via AUTH_BYPASS_LOGIN_OTP — new device logged straight in.', [
                'user_id' => $user->id,
                'device_name' => $data['device_name'],
            ]);
        }

        // Recognised device (or the OTP challenge is bypassed): rotate its token so old sessions can't linger indefinitely.
        $user->tokens()->where('name', $data['device_name'])->delete();
        $token = $user->createToken($data['device_name'])->plainTextToken;

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ]);
    }

    /**
     * Completes a new-device login after `login()` returned `requires_otp`.
     * Also doubles as the generic OTP-verify endpoint for other purposes
     * (password reset, high-value transaction step-up) where the caller
     * doesn't need a token back.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $purpose = OtpPurpose::from($data['purpose']);

        if (! $this->otp->verify($data['identifier'], $purpose, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is invalid or has expired.']);
        }

        if ($purpose !== OtpPurpose::NewDeviceLogin) {
            return response()->json(['verified' => true]);
        }

        $user = User::where('phone_number', $data['identifier'])->orWhere('email', $data['identifier'])->firstOrFail();
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'requires_email_verification' => true,
                'email' => $user->email,
                'message' => 'Verify your email address before signing in.',
            ], 403);
        }
        $deviceName = $request->string('device_name', 'unknown-device');
        $token = $user->createToken((string) $deviceName)->plainTextToken;

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ]);
    }

    public function resendLoginOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'in:'.OtpPurpose::NewDeviceLogin->value],
        ]);
        $user = User::where('phone_number', $data['identifier'])
            ->orWhere('email', $data['identifier'])
            ->first();

        // Keep the response generic so this public endpoint cannot be used
        // to enumerate JolaxPay accounts. A newly issued OTP becomes the
        // only valid one because OtpService verifies the latest record.
        if ($user?->is_active && $user->hasVerifiedEmail()) {
            $this->otp->issue(
                $user->phone_number,
                OtpPurpose::NewDeviceLogin,
                DeliveryChannel::Sms,
                $user,
            );
        }

        return response()->json([
            'message' => 'If the account is eligible, a new verification code has been sent by SMS.',
            'retry_after' => 60,
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('phone_number', $request->validated('phone_number'))->firstOrFail();
        $this->otp->issue($user->phone_number, OtpPurpose::PasswordReset, DeliveryChannel::Sms, $user);

        return response()->json(['message' => 'A password reset code has been sent to your registered phone number.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $this->otp->verify($data['phone_number'], OtpPurpose::PasswordReset, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is invalid or has expired.']);
        }

        $user = User::where('phone_number', $data['phone_number'])->firstOrFail();
        $user->forceFill(['password' => $data['password']])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => UserResource::make($request->user())]);
    }

    /**
     * Edits name/email/phone for the authenticated mobile user. Changing
     * email or phone drops that field's verified_at — same rule the admin
     * profile form (ProfileController::update) already applies — since the
     * new value hasn't actually been proven to belong to this user yet.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        if ($user->isDirty('phone_number')) {
            $user->phone_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['user' => UserResource::make($user)]);
    }

    public function resendEmailVerification(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->first();

        // Deliberately generic so this endpoint cannot be used to discover
        // which email addresses have JolaxPay accounts.
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'If that address has an unverified account, a new verification email has been sent.']);
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403, 'Invalid verification link.');

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $url = (string) config('app.mobile_email_verified_url', 'jolaxpay://email-verified');

        return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').'verified=1');
    }

    /** Store a small profile image for the authenticated mobile user. */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $previousPath = $user->avatar_path;
        $user->avatar_path = $data['avatar']->store("avatars/{$user->id}", 'uploads');
        $user->save();

        if ($previousPath) {
            Storage::disk('uploads')->delete($previousPath);
        }

        return response()->json(['user' => UserResource::make($user)]);
    }
}
