<?php

namespace App\Traits;

trait ApiResponse
{
    public function successResponse($data, $message ,$code)
    {
        return response()->json([
            'status'    => true,
            'message'   => $message,
            'data'      => $data,
        ],$code);
    }

    public function errorResponse($message, $code){
        return response()->json([
            'status'    => false,
            'message'   => $message,
        ],$code);
    }
}
