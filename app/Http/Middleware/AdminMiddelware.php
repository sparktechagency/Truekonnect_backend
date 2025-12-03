<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use App\Traits\ApiResponse;

class AdminMiddelware
{

    use ApiResponse;
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
            if (!$user || $user->role !== 'admin') {
                return $this->errorResponse('Unauthorized. Admin access required.',null, Response::HTTP_BAD_REQUEST);
            }

        } catch (TokenExpiredException $e) {
            return $this->errorResponse('Token has expired. ',$e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (TokenInvalidException $e) {
            return $this->errorResponse('Token is invalid. ',$e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (JWTException $e) {
            return $this->errorResponse('Authorization token not found. ',$e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        return $next($request);
    }
}
