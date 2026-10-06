<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\GymEntityRequest;
use App\Http\Resources\UserResource;
use App\Models\Gym;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class GymEntityController extends Controller {
    

    public function index(): JsonResponse {
        $dbconnector = $this->getDbConnector();   
        $gymList = Gym::queryList($dbconnector);
        $model=[];
        $ownerIdList=[];
        foreach ($gymList as $gym) {
            /**  @var Gym $gym */
            //!refactor a como obtenemos los recurso para evitar n + 1
            // $ownerIdList[]= $gym->getOwnerId();
            $userOwner = User::find($gym->getOwnerId());
            $resource = Resource::queryByOwnerAndModelId($dbconnector, CoreModel::gymEntityModelId, $gym->getEntityId());
            $gymModel = $gym->buildApiModel();
            $gymModel['image_url'] = $resource?->getUrl() ?? null;
            $gymModel['refs']['owner'] = UserResource::make($userOwner);
            $model[]= $gymModel;
        }
        return $this->successApiResponse($model);
    }

    public function store(GymEntityRequest $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $validated = $request->validated();
        $gym = Gym::allocNew(
            $validated['name'],
            $validated['slug'],
            $validated['owner_id'],
            $validated['alumns_count'],
        );
        $gym->writeToDb($dbConnector);

        return $this->successApiResponse($gym->buildApiModel(), 201);
    }



}