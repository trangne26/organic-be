<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|string|email|max:191|unique:users',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'name.required' => 'Tên là bắt buộc.',
            'email.required' => 'Email là bắt buộc.',
            'email.unique' => 'Email đã được sử dụng.',
            'password.required' => 'Mật khẩu là bắt buộc.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'is_admin' => $user->is_admin,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember_me' => 'nullable|boolean',
        ], [
            'email.required' => 'Email là bắt buộc.',
            'password.required' => 'Mật khẩu là bắt buộc.',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.'],
            ]);
        }

        $user = Auth::user();
        
        $expiresAt = $request->boolean('remember_me') 
            ? now()->addDays(30) 
            : now()->addDays(7);
        
        $token = $user->createToken('auth-token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'is_admin' => $user->is_admin,
                ],
                'token' => $token,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'is_admin' => $user->is_admin,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Tên là bắt buộc.',
        ]);

        $user->update($request->only(['name', 'phone', 'address']));

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin thành công.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'is_admin' => $user->is_admin,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'Mật khẩu hiện tại là bắt buộc.',
            'password.required' => 'Mật khẩu mới là bắt buộc.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không chính xác.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Email không hợp lệ.',
            'email.exists' => 'Email này chưa được đăng ký trong hệ thống.',
        ]);

        $user = User::where('email', $request->email)->first();

        $token = Str::random(64);

        DB::table('password_resets')->where('email', $request->email)->delete();

        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        try {
            Mail::to($user->email)->send(new ResetPasswordMail($token, $user->email));

            return response()->json([
                'success' => true,
                'message' => 'Chúng tôi đã gửi link đặt lại mật khẩu đến email của bạn. Vui lòng kiểm tra hộp thư.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send reset password email', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi gửi email. Vui lòng thử lại sau.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:6',
        ], [
            'token.required' => 'Token là bắt buộc.',
            'password.required' => 'Mật khẩu mới là bắt buộc.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
        ]);

        $passwordResets = DB::table('password_resets')
            ->where('created_at', '>', now()->subHours(1))
            ->get();

        $passwordReset = null;
        foreach ($passwordResets as $record) {
            if (Hash::check($request->token, $record->token)) {
                $passwordReset = $record;
                break;
            }
        }

        if (!$passwordReset) {
            throw ValidationException::withMessages([
                'token' => ['Token không hợp lệ hoặc đã hết hạn.'],
            ]);
        }

        $user = User::where('email', $passwordReset->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => ['Không tìm thấy người dùng.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        DB::table('password_resets')->where('email', $passwordReset->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đặt lại mật khẩu thành công. Bạn có thể đăng nhập bằng mật khẩu mới.',
        ]);
    }
}
