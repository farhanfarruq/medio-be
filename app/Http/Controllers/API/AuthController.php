<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register — buat user + kirim OTP ke email
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Generate & kirim OTP
        $this->generateAndSendOtp($user, 'email');

        return response()->json([
            'message' => 'Registrasi berhasil! Kode verifikasi telah dikirim ke email Anda.',
            'email'   => $user->email,
        ], 201);
    }

    /**
     * Verifikasi OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        // Cari OTP yang valid (belum expired, belum diverifikasi)
        $otp = OtpCode::where('user_id', $user->id)
            ->where('code', $request->code)
            ->where('type', 'email')
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json([
                'message' => 'Kode OTP tidak valid atau sudah kadaluarsa.',
            ], 422);
        }

        // Tandai OTP sebagai terverifikasi
        $otp->update(['verified_at' => now()]);

        // Tandai email user sebagai terverifikasi
        $user->update(['email_verified_at' => now()]);

        // Berikan token (auto-login)
        $user->load('addresses');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email berhasil diverifikasi!',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    /**
     * Kirim ulang OTP
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        // Rate limiting: maks 3 OTP dalam 10 menit terakhir
        $recentCount = OtpCode::where('user_id', $user->id)
            ->where('type', 'email')
            ->where('created_at', '>', now()->subMinutes(10))
            ->count();

        if ($recentCount >= 3) {
            return response()->json([
                'message' => 'Terlalu banyak permintaan. Silakan tunggu beberapa menit.',
            ], 429);
        }

        $this->generateAndSendOtp($user, 'email');

        return response()->json([
            'message' => 'Kode verifikasi baru telah dikirim ke email Anda.',
        ]);
    }

    /**
     * Login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial tidak valid.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Cek apakah email sudah diverifikasi
        if (!$user->email_verified_at) {
            // Kirim ulang OTP otomatis
            $this->generateAndSendOtp($user, 'email');

            return response()->json([
                'message'          => 'Email belum diverifikasi. Kode OTP baru telah dikirim.',
                'requires_otp'     => true,
                'email'            => $user->email,
            ], 403);
        }

        $user->load('addresses');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout']);
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('addresses'));
    }

    // ─── Helper ───────────────────────────────────────────────

    /**
     * Generate OTP 6 digit dan kirim via email
     */
    private function generateAndSendOtp(User $user, string $type = 'email'): void
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'type'       => $type,
            'expires_at' => now()->addMinutes(10),
        ]);

        if ($type === 'email') {
            try {
                Mail::to($user->email)->send(new OtpMail($code, $user->name));
            } catch (\Exception $e) {
                Log::error('Failed to send OTP email: ' . $e->getMessage());
                // Fallback: log OTP ke file log agar tetap bisa diverifikasi saat development
                Log::info("OTP untuk {$user->email}: {$code}");
            }
        }
    }
}
