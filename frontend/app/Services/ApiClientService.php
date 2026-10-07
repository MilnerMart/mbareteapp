<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Http\Controllers\MediaController;
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
        $response = $this->localizeImageUrls($this->request()->get($this->baseUrl . $endpoint, $params)->json());
        return $response['data'] ?? null;
    }

    private function post($endpoint, $data = [])
    {
        return $this->localizeImageUrls($this->request()->post($this->baseUrl . $endpoint, $data)->json());
    }

    private function put($endpoint, $data = [])
    {
        return $this->localizeImageUrls($this->request()->put($this->baseUrl . $endpoint, $data)->json());
    }

    private function postWithImage($endpoint, array $data, string $field, UploadedFile $image)
    {
        $response = $this->request()
            ->attach($field, fopen($image->getRealPath(), 'r'), $image->getClientOriginalName())
            ->post($this->baseUrl . $endpoint, $data)
            ->json();
        return $this->localizeImageUrls($response);
    }

    /**
     * PHP no parsea archivos en un PUT real: con imagen se manda POST + _method=PUT.
     */
    private function putWithOptionalImage($endpoint, array $data, string $field, ?UploadedFile $image)
    {
        if(!$image){
            return $this->put($endpoint, $data);
        }

        return $this->postWithImage($endpoint, $data + ['_method' => 'PUT'], $field, $image);
    }

    /**
     * Las urls de imagenes del backend (http://backend/images/...) pasan a /images/... para que el
     * navegador las pida al front, que las sirve via MediaController.
     */
    private function localizeImageUrls($value)
    {
        if(is_array($value)){
            return array_map(fn($item) => $this->localizeImageUrls($item), $value);
        }

        $prefix = MediaController::backendOrigin().'/images/';
        if(is_string($value) && str_starts_with($value, $prefix)){
            return '/images/'.substr($value, strlen($prefix));
        }

        return $value;
    }

    private function delete($endpoint)
    {
        return $this->localizeImageUrls($this->request()->delete($this->baseUrl . $endpoint)->json());
    }

    public function getMuscles(){
        return $this->get('/muscle');
    }

    public function getMuscle(int $id){
        return $this->get('/muscle/'.$id);
    }

    public function createMuscle(array $params, UploadedFile $image){
        return $this->postWithImage('/muscle', $params, 'image', $image);
    }

    public function updateMuscle(int $id, array $params, ?UploadedFile $image = null){
        return $this->putWithOptionalImage('/muscle/'.$id, $params, 'image', $image);
    }

    public function deleteMuscle(int $id){
        return $this->delete('/muscle/'.$id);
    }

    public function getExercise(int $id){
        return $this->get('/exercise/'.$id);
    }

    public function getExerciseResources(int $id){
        return $this->get('/exercise/'.$id.'/resource');
    }

    public function createExercise(array $params, UploadedFile $image){
        return $this->postWithImage('/exercise', $params, 'image', $image);
    }

    public function updateExercise(int $id, array $params, ?UploadedFile $image = null){
        return $this->putWithOptionalImage('/exercise/'.$id, $params, 'image', $image);
    }

    public function deleteExercise(int $id){
        return $this->delete('/exercise/'.$id);
    }

    public function getExercises(){
        return $this->get('/exercise');
    }

    public function getExerciseGroup(int $muscleId){
        return $this->get('/exercise/group/'.$muscleId);
    }

    public function getUser(int $id){
        return $this->get('/user/'.$id);
    }

    public function updateProfileImage(int $id, UploadedFile $image){
        return $this->postWithImage('/user/'.$id.'/profile-image', [], 'profile_image', $image);
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
        return $this->postWithImage('/entities/gym/'.$id.'/image', [], 'gym_image', $image);
    }

    public function getMemberGyms(){
        return $this->get('/me/gyms');
    }

    public function updateMemberGymVisibility(int $id, bool $isPublic){
        return $this->put('/me/gyms/'.$id.'/visibility', ['is_public' => $isPublic]);
    }

    public function updateRoutineExercise(int $id, int $exerciseId, array $params){
        // el backend agrega o actualiza series y repeticiones del ejercicio
        return $this->post('/routines/'.$id.'/exercises', ['exercise_id' => $exerciseId] + $params);
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
