<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileImageRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\PublicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(){
        throw PublicException::notFoundError('No implementado');   
    }
   
    public function store(Request $request){
        throw PublicException::notFoundError('No implementado');   
    }

    public function show(string $id): JsonResponse {
        $user = User::find($id);

        if(!$user){
            throw PublicException::notFoundError('Usuario no encontrado');
        }

        return $this->successApiResponse(UserResource::make($user));
    }
    
    public function update(Request $request, string $id){
        throw PublicException::notFoundError('No implementado');   
    }

    public function updateProfileImage(ProfileImageRequest $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if(!$user){
            throw PublicException::notFoundError('Usuario no encontrado');
        }

        if((int) $request->user()->id !== (int) $user->id){
            throw PublicException::unAuthorizedError('No puedes cambiar la foto de otro usuario');
        }

        $directory = public_path('images/profiles');
        File::ensureDirectoryExists($directory);

        $file = $request->file('profile_image');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        if($user->profile_image_url){
            $previousPath = public_path($user->profile_image_url);
            if(File::exists($previousPath)){
                File::delete($previousPath);
            }
        }

        $user->profile_image_url = 'images/profiles/'.$filename;
        $user->save();

        return $this->successApiResponse(UserResource::make($user));
    }

    public function destroy(string $id){
        throw PublicException::notFoundError('No implementado');   
    }
}
