<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiClientService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.backend.url');
    }

    private function get($endpoint, $params = [])
    {   
        $resquest = Http::get($this->baseUrl . $endpoint, $params)->json();
        return $resquest ? $resquest['data']: null;
    }

    private function post($endpoint, $data = [])
    {
        return Http::post($this->baseUrl . $endpoint, $data)->json();
    }

    private function patch($endpoint, $data = [])
    {
        return Http::patch($this->baseUrl . $endpoint, $data)->json();
    }

    private function delete($endpoint, $data = [])
    {
        return Http::delete($this->baseUrl . $endpoint, $data)->json();
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
        $this->get('/user/'.$id);
    }

    public function setUser(array $params){
        $this->post('/user', $params);
    }


}