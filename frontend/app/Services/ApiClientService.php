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

    private function put($endpoint, $data = [])
    {
        return $this->request()->put($this->baseUrl . $endpoint, $data)->json();
    }

    private function delete($endpoint)
    {
        return $this->request()->delete($this->baseUrl . $endpoint)->json();
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

    public function getResource(int $resourceId){
        return $this->get('/resource/'.$resourceId);
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

    public function getGyms(){
        return $this->get('/entities/gym');
    }

    public function getGym(int $id){
        return $this->get('/entities/gym/'.$id);
    }

    public function createGym(array $params){
        return $this->post('/entities/gym', $params);
    }

    public function updateGym(int $id, array $params){
        return $this->put('/entities/gym/'.$id, $params);
    }

    public function getGymUsers(int $id){
        return $this->get('/entities/gym/'.$id.'/users');
    }

    public function removeGymUser(int $id, int $userId){
        return $this->delete('/entities/gym/'.$id.'/users/'.$userId);
    }

    public function updateGymImage(int $id, UploadedFile $image){
        return $this->request()
            ->attach(
                'gym_image',
                fopen($image->getRealPath(), 'r'),
                $image->getClientOriginalName()
            )
            ->post($this->baseUrl.'/entities/gym/'.$id.'/image')
            ->json();
    }

    public function getRoutines(){
        return $this->get('/routines');
    }

    public function getRoutine(int $id){
        return $this->get('/routines/'.$id);
    }

    public function createRoutine(array $params){
        return $this->post('/routines', $params);
    }

    public function updateRoutine(int $id, array $params){
        return $this->put('/routines/'.$id, $params);
    }

    public function deleteRoutine(int $id){
        return $this->delete('/routines/'.$id);
    }

    public function addRoutineExercise(int $id, array $params){
        return $this->post('/routines/'.$id.'/exercises', $params);
    }

    public function removeRoutineExercise(int $id, int $exerciseId){
        return $this->delete('/routines/'.$id.'/exercises/'.$exerciseId);
    }

    public function assignRoutine(int $id, int $userId){
        return $this->post('/routines/'.$id.'/assign', ['user_id' => $userId]);
    }

    public function unassignRoutine(int $id, int $userId){
        return $this->delete('/routines/'.$id.'/assign/'.$userId);
    }

    public function getRegisterRoles(){
        return $this->get('/auth/register/roles');
    }
}
