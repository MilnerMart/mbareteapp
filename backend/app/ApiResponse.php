<?php

namespace App;

trait ApiResponse
{
    public function successApiResponse(mixed $data = null, ?int $code = 200){
        return response()->json([
            'success' => true,
            'data' => $data ?? []
        ], $code);
    }

    public function errorApiResponse(mixed $data = null, int $code = 500){
        return response()->json([
            'success' => false,
            'data' => $data ? $data : null
        ], $code);
    }
}
