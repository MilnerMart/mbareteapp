<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResourceRequest;
use App\Models\Resource;
use App\PublicException;
use App\Services\ResourceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class ResourceController extends Controller{

     public function index(Request $request): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $dbconnector = $this->getDbConnector();   
        $resourceList = Resource::queryList($dbconnector);
        $model=[];
        foreach ($resourceList as $resource) {
            /**  @var Resource $resource */
            $model[]= $resource->buildApiModel();
        }
        return $this->successApiResponse($model);
    }

    public function store(ResourceRequest $request): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $service = new ResourceService($dbConnector);
        $resourceOpts = $service->AllocResourceOpts($request);
        $modelId = $request->input('model_id');
        $ownerId = $request->input('owner_id');
        $resource = $service->allocResource($modelId, $ownerId, $resourceOpts);
        return $this->successApiResponse($resource->buildApiModel(), 201);
    }

    public function show(Request $request, string $id): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $resource = Resource::queryByDbId($dbConnector,$id);

        if(!$resource){
            throw PublicException::notFoundError('recurso no encontrado');
        }

        return $this->successApiResponse($resource->buildApiModel());
    }
}