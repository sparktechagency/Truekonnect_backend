<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class CommonBrandOrPerformerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            // User found & role check
            if (!$user || $user->role !== 'performer' && $user->role !== 'brand') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Unauthorized. Performer access required.',
                ], 403);
            }
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status'  => false,
                'error'   => 'Token has expired.',
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status'  => false,
                'error'   => 'Token is invalid.',
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status'  => false,
                'error'   => 'Authorization token not found.',
            ], 401);
        }
        return $next($request);
        
    }
}
