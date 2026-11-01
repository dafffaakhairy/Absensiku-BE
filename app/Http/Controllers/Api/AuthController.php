<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const INACTIVE_TIMEOUT = 60; // 1 menit

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

if (! Auth::attempt($credentials)) {
    throw ValidationException::withMessages([
        'email' => ['Email atau password salah.'],
    ]);
}

$user = Auth::user();

if (! $user->is_active) {
    Auth::logout();

    return response()->json([
        'message' => 'Akun karyawan tidak aktif.',
    ], 403);
}

        // Hapus token lama untuk login web ini, jika ada.
        $user->tokens()
            ->where('name', 'absensi-web')
            ->delete();

        // Buat token baru.
        $token = $user->createToken('absensi-web');

        // Simpan waktu aktivitas terakhir.
        $token->accessToken->forceFill([
            'last_used_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token->plainTextToken,
            'user' => $user->only([
                'id',
                'name',
                'email',
                'employee_number',
                'role',
            ]),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->only([
                'id',
                'name',
                'email',
                'employee_number',
                'role',
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
