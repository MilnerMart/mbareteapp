<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApiClientService;

class MuscleController extends Controller
{
    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function index(){
        $muscleList = $this->apiClient->getMuscles();
        $data['muscles'] = $muscleList;
        return view('muscles.index', compact('data'));
    }
    
}
