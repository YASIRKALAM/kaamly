<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\Role;
use App\Domains\Customer\Models\CustomerProfile;
use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Identity\Models\OtpVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new Customer or Provider
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|max:20|unique:users,phone_number',
            'email' => 'nullable|email|max:150|unique:users,email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'password' => 'required|string|min:8',
            'user_type' => 'required|in:customer,provider',
            'business_name' => 'required_if:user_type,provider|string|max:150',
            'cnic_number' => 'nullable|string|max:20|unique:provider_profiles,cnic_number',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Registration failed validation.', $validator->errors(), 422);
        }

        $validated = $validator->validated();

        $user = User::create([
            'phone_number' => $validated['phone_number'],
            'email' => $validated['email'] ?? null,
            'password_hash' => Hash::make($validated['password']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'user_type' => $validated['user_type'],
            'status' => 'pending_verification',
        ]);

        $role = Role::where('name', $validated['user_type'])->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        if ($validated['user_type'] === 'customer') {
            CustomerProfile::create(['user_id' => $user->id]);
        } elseif ($validated['user_type'] === 'provider') {
            ProviderProfile::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'] ?? null,
                'cnic_number' => $validated['cnic_number'] ?? null,
                'status' => 'draft',
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user->load('roles'),
            'token' => $token,
        ], 'User successfully registered.', [], 201);
    }

    /**
     * User Login with Phone or Email
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // phone or email
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid credentials format.', $validator->errors(), 422);
        }

        $login = $request->input('login');
        $user = User::where('phone_number', $login)
            ->orWhere('email', $login)
            ->first();

        if (!$user || !Hash::check($request->input('password'), $user->password_hash)) {
            return $this->errorResponse('INVALID_CREDENTIALS', 'Invalid phone number/email or password.', null, 401);
        }

        if ($user->status === 'suspended' || $user->status === 'banned') {
            return $this->errorResponse('ACCOUNT_SUSPENDED', 'Your account has been suspended or banned. Please contact support.', null, 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user->load(['roles', 'customerProfile', 'providerProfile']),
            'token' => $token,
        ], 'Login successful.');
    }

    /**
     * Send OTP for phone or email verification
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_or_email' => 'required|string|max:150',
            'purpose' => 'required|in:registration,login,password_reset,verification',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid OTP request parameters.', $validator->errors(), 422);
        }

        $otp = (string) rand(100000, 999999);
        OtpVerification::create([
            'phone_or_email' => $request->input('phone_or_email'),
            'purpose' => $request->input('purpose'),
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
            'max_attempts' => 3,
        ]);

        // In production, send via SMS / Mail. In dev/testing, we return acknowledgment
        return $this->successResponse([
            'phone_or_email' => $request->input('phone_or_email'),
            'expires_in_seconds' => 300,
            'debug_otp' => app()->environment('local', 'testing') ? $otp : null,
        ], 'OTP successfully sent.');
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_or_email' => 'required|string|max:150',
            'purpose' => 'required|in:registration,login,password_reset,verification',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid verification format.', $validator->errors(), 422);
        }

        $record = OtpVerification::where('phone_or_email', $request->input('phone_or_email'))
            ->where('purpose', $request->input('purpose'))
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (!$record) {
            return $this->errorResponse('OTP_EXPIRED', 'OTP has expired or does not exist.', null, 422);
        }

        if ($record->attempts >= $record->max_attempts) {
            return $this->errorResponse('OTP_MAX_ATTEMPTS', 'Maximum verification attempts exceeded. Please request a new OTP.', null, 429);
        }

        if (!Hash::check($request->input('otp'), $record->otp_hash)) {
            $record->increment('attempts');
            return $this->errorResponse('OTP_INVALID', 'The provided OTP code is incorrect.', null, 422);
        }

        $record->update(['verified_at' => now()]);

        // If user exists, mark phone as verified
        $user = User::where('phone_number', $request->input('phone_or_email'))->first();
        if ($user) {
            $user->update([
                'phone_verified_at' => now(),
                'status' => 'active',
            ]);
        }

        return $this->successResponse(['verified' => true], 'OTP verified successfully.');
    }

    /**
     * Current authenticated user profile
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse([
            'user' => $request->user()->load(['roles.permissions', 'customerProfile.defaultAddress', 'providerProfile']),
        ]);
    }

    /**
     * Logout and revoke token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully.');
    }
}
