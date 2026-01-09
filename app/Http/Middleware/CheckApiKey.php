<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Models\ClientApi;
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
        $client = ClientApi::where('access_token',$apiKey)->first();
        if (!$client) {
            $statusCode = 401;
            $responseContent = [
                'success'  => 'FALSE',
                'error_code' => 'Invalid Authorization',
                'error_message'  => 'Unauthorized: Invalid Authorization. or API not registered yet'
            ];
            ApiLog::create([
                'client_name' => 'unknown',
                'api_type'    => 'authorization',
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
