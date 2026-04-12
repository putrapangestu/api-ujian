<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $key = config('services.socket_io.key');
        
        if (empty($key)) {
            // Jika tidak ada key dikonfigurasi, tolak untuk keamanan
            return response()->json(['error' => 'Internal API key is not configured'], 500);
        }

        if ($request->header('x-internal-key') !== $key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}