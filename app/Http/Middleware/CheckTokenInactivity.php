<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenInactivity
{
    private const INACTIVE_TIMEOUT = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && $token->getKey()) {
            $lastActivity = $token->last_used_at;

            if (
                $lastActivity &&
                $lastActivity->diffInSeconds(now()) >= self::INACTIVE_TIMEOUT
            ) {
                $token->delete();

                return response()->json([
                    'message' => 'Sesi berakhir karena tidak ada aktivitas selama 1 menit.',
                    'code' => 'TOKEN_INACTIVE',
                ], 401);
            }

            $token->forceFill([
                'last_used_at' => now(),
            ])->save();
        }

        return $next($request);
    }
}
