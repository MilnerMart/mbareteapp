<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return $this->notFoundError('No implementado');   
    }
   
    public function store(Request $request)
    {
        return $this->notFoundError('No implementado');   
    }

    public function show(string $id)
    {
        $user = User::find($id);

        if(!$user){
            return $this->notFoundError('Usuario no encontrado');
        }

        return $this->successApiResponse(UserResource::make($user));
    }
    
    public function update(Request $request, string $id)
    {
        return $this->notFoundError('No implementado');   
    }

    public function destroy(string $id)
    {
        return $this->notFoundError('No implementado');   
    }
}
