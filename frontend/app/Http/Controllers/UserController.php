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

    public function updateProfileImage(Request $request, int $id){
        $validated = $request->validate([
            'profile_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $response = $this->apiClient->updateProfileImage($id, $validated['profile_image']);

        if(!($response['success'] ?? false)){
            return back()->withErrors([
                'profile_image' => $response['message'] ?? 'No pudimos actualizar la foto de perfil.',
            ]);
        }

        if((int) session('auth_user.id') === $id){
            $request->session()->put('auth_user', $response['data']);
        }

        return back()->with('profile_image_updated', 'Foto de perfil actualizada.');
    }
}
