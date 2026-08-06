<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\OtpService;
use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryChannel;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        ]);

        $this->ledger->walletFor($user);

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
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

        $isKnownDevice = $user->tokens()->where('name', $data['device_name'])->exists();

        if (! $isKnownDevice) {
            $this->otp->issue($user->phone_number, OtpPurpose::NewDeviceLogin, DeliveryChannel::Sms, $user);

            return response()->json([
                'requires_otp' => true,
                'purpose' => OtpPurpose::NewDeviceLogin->value,
                'identifier' => $user->phone_number,
                'message' => 'New device detected. Enter the verification code we sent you to finish signing in.',
            ]);
        }

        // Recognised device: rotate its token so old sessions can't linger indefinitely.
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
        $deviceName = $request->string('device_name', 'unknown-device');
        $token = $user->createToken((string) $deviceName)->plainTextToken;

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ]);
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
}
