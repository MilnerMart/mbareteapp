<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use Illuminate\Http\Request;

class UserController extends Controller{

    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }
    public function getProfile(int $id){
        $userInfo = $this->apiClient->getUser($id);

        $data['profileInfo'] = $userInfo;

        return view('users.profile', compact('data'));
    }
}
