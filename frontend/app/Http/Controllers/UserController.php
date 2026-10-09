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
        $data['isOwnProfile'] = (int) session('auth_user.id') === $id;
        $data['tickets'] = $data['isOwnProfile'] ? ($this->apiClient->getMyTickets() ?? []) : [];

        if($data['isOwnProfile'] && ($authUser = $this->apiClient->me())){
            // si le aprobaron el alta de entrenador, los permisos nuevos se reflejan sin volver a loguearse
            session()->put('auth_user', array_merge(session('auth_user', []), $authUser));
        }

        return $this->renderView('users.profile', compact('data'));
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
            // conserva roles y permisos que no vienen en la respuesta del perfil
            $request->session()->put('auth_user', array_merge(session('auth_user', []), $response['data']));
        }

        return back()->with('profile_image_updated', 'Foto de perfil actualizada.');
    }
}
