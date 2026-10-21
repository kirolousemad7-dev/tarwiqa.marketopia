<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\SocialLoginServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\SocialLoginRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly SocialLoginServiceInterface $socialLogin) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();
        if (! $user || ! Hash::check($request->validated('password'), $user->password) || $user->status !== 'active') {
            return apiResponse(message: 'The provided credentials are invalid.', status: 422, errors: ['credentials' => ['Invalid email or password.']]);
        }

        return $this->tokenResponse($user);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['password_confirmation', 'device_name']);
        $user = User::query()->create([...$data, 'password' => $request->validated('password'), 'role' => 'customer', 'status' => 'active']);

        return $this->tokenResponse($user, 201);
    }

    public function socialLogin(SocialLoginRequest $request): JsonResponse
    {
        $user = $this->socialLogin->authenticate(
            $request->validated('provider'),
            $request->validated('token'),
            $request->validated('name'),
        );

        return $this->tokenResponse($user);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink(['email' => $request->validated('email')]);

        return apiResponse(message: 'If an account exists for this email, a password reset link has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return apiResponse(message: 'The password reset token is invalid or expired.', status: 422, errors: ['token' => [$status]]);
        }

        return apiResponse(message: 'Password reset successfully.');
    }

    public function resetLink(Request $request, string $token): JsonResponse
    {
        return apiResponse(
            data: ['token' => $token, 'email' => $request->query('email')],
            message: 'Use this token with the password reset endpoint.',
        );
    }

    public function profile(Request $request): JsonResponse
    {
        return apiResponse(data: (new UserResource($request->user()->load('governorate:id,name')))->resolve($request), message: 'Profile retrieved successfully.');
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['avatar', 'password', 'password_confirmation']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }
        $request->user()->update($data);
        if ($request->hasFile('avatar')) {
            $request->user()->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        }

        return apiResponse(data: (new UserResource($request->user()->refresh()->load('governorate:id,name')))->resolve($request), message: 'Profile updated successfully.');
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['status' => 'inactive', 'ban_type' => null])->save();
        auth('api')->logout(true);

        return apiResponse(message: 'Account deactivated. History was preserved.');
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $token = auth('api')->login($user);

        $data = [
            'user' => (new UserResource($user->load('governorate:id,name')))->resolve(),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];

        return apiResponse(
            data: $data,
            message: $status === 201 ? 'Registration successful.' : 'Login successful.',
            status: $status
        );
    }
}
