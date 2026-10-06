<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller {
    

    public function index(): JsonResponse {
        $dbconnector = $this->getDbConnector();   
        $roleList = Role::queryList($dbconnector);
        $model=[];
        foreach ($roleList as $role) {
            /**  @var Role $role */
            $roleModel = $role->buildApiModel();
            $model[]= $roleModel;
        }
        return $this->successApiResponse($model);
    }

    public function store(RoleRequest $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $validated = $request->validated();
        $role = Role::allocNew($validated['name'],$validated['slug']);
        $role->writeToDb($dbConnector);

        return $this->successApiResponse($role->buildApiModel(), 201);
    }



}