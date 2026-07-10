<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;

class ApiClientService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.backend.url');
    }

    private function request()
    {
        $request = Http::acceptJson();
        $token = session('auth_token');

        return $token ? $request->withToken($token) : $request;
    }

    private function get($endpoint, $params = [])
    {   
        $response = $this->request()->get($this->baseUrl . $endpoint, $params)->json();
        return $response['data'] ?? null;
    }

    private function post($endpoint, $data = [])
    {
        return $this->request()->post($this->baseUrl . $endpoint, $data)->json();
    }

    public function getMuscles(){
        return $this->get('/muscle');
    }

    public function getExercises(){
        return $this->get('/exercise');
    }

    public function getExerciseGroup(int $muscleId){
        return $this->get('/exercise/group/'.$muscleId);
    }

    public function getExerciseResources(int $exerciseId){
        return $this->get('/exercise/'.$exerciseId.'/resource');
    }

    public function getUser(int $id){
        return $this->get('/user/'.$id);
    }

    public function updateProfileImage(int $id, UploadedFile $image){
        return $this->request()
            ->attach(
                'profile_image',
                fopen($image->getRealPath(), 'r'),
                $image->getClientOriginalName()
            )
            ->post($this->baseUrl.'/user/'.$id.'/profile-image')
            ->json();
    }

    public function login(array $params){
        return $this->post('/auth/login', $params);
    }

    public function register(array $params){
        return $this->post('/auth/register', $params);
    }

    public function logout(){
        return $this->post('/auth/logout');
    }

    public function me(){
        return $this->get('/auth/me');
    }
}
