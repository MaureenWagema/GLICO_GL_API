<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if the request is for the OAuth token endpoint
        // if ($request->is('oauth/token')) {
        //     return $response; // Skip CORS headers for the OAuth token endpoint
        // }
        //http://localhost:3000
        //$response->headers->set('Access-Control-Allow-Origin', 'http://172.26.0.56:30003');
        $response->headers->set('Access-Control-Allow-Origin', 'https://grouplife.britam.com');

        //$response->headers->set('Access-Control-Allow-Origin', 'http://localhost:3000');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        //$response->headers->set('Access-Control-Allow-Headers', 'text/html, charset=UTF-8, Content-Type, Accept, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Headers', 'text/html, charset=UTF-8, Content-Type, Accept, Authorization, X-Requested-With');
        // $allowedOrigins = [
        //     'https://grouplifeuat.com',
        //     'http://localhost:3000'
        //     // Add more origins if needed
        // ];

        // $origin = $request->headers->get('Origin');

        // // Check if the request origin is allowed
        // if (in_array($origin, $allowedOrigins)) {
        //     // Set Access-Control-Allow-Origin header
        //     $response->headers->set('Access-Control-Allow-Origin', $origin);
        //     // Set Access-Control-Allow-Methods header
        //     $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        //     // Set Access-Control-Allow-Headers header
        //     $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, application/json');
        // }


        return $response;
    }
}
