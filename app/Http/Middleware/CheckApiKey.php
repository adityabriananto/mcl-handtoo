<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('Authorization');

        // Bandingkan dengan key yang ada di file .env kita
        if ($apiKey !== config('app.api_key')) {
            $statusCode = 401;
            $responseContent = [
                'success'  => 'FALSE',
                'error_code' => 'Invalid Authorization',
                'error_message'  => 'Unauthorized: Invalid Authorization.'
            ];
            ApiLog::create([
                'endpoint'    => $request->fullUrl(),
                'method'      => $request->method(),
                'payload'     => $request->all(),
                'response'    => $responseContent,
                'status_code' => $statusCode,
                'ip_address'  => $request->ip(),
            ]);
            return response()->json($responseContent, $statusCode);
        }

        return $next($request);
    }
}
