<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
class ReviewerMiddelware
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
            if (!$user || $user->role !== 'reviewer') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Unauthorized. Reviewer access required.',
                ], 403);
            }

            // Optionally pass authenticated user to request
          //  $request->merge(['auth_user' => $user]);

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
