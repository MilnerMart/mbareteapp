<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function login(Request $request){
        // $login = $this->apiClient->userLogin();
        
        return view('login.login');
    }

    public function register(Request $request){
        return view('login.register');
    }
}
