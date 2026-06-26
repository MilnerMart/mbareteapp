<?php

namespace App;

use Illuminate\Http\Resources\Json\JsonResource;

trait ApiResponse
{
    public function successApiResponse(?JsonResource $data = null, ?int $code = 200){
        return response()->json([
            'sucess' => true, 
            'data' => $data ?? []
        ], $code);
    }

    public function errorApiResponse(?JsonResource $data, int $code = 500){
        return response()->json([
            'sucess' => false, 
            'data' => $data ? $data : null
        ], $code);
    }
}
