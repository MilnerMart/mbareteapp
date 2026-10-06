<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PermitRequest;
use App\Http\Requests\RoleRequest;
use App\Models\Permit;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class PermitController extends Controller {
    

    public function index(): JsonResponse {
        $dbconnector = $this->getDbConnector();   
        $permitList = Permit::queryList($dbconnector);
        $model=[];
        foreach ($permitList as $permit) {
            /**  @var Permit $permit */
            $permitModel = $permit->buildApiModel();
            $model[]= $permitModel;
        }
        return $this->successApiResponse($model);
    }

    public function store(PermitRequest $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $validated = $request->validated();
        $role = Role::queryByDbIdOrFail($dbConnector, $validated['roleId']);
        $permit = Permit::allocNew($validated['name'],$validated['slug'], $role->getEntityId());
        $permit->writeToDb($dbConnector);
        $this->allocRolePermit($role, $permit);
        return $this->successApiResponse($permit->buildApiModel(), 201);
    }


    private function allocRolePermit(Role $role, Permit $permit){
        //Mejorar el binding del permit luego
        $dbConnector = $this->getDbConnector();
        $rolePermits = $role->getRolePermits();
        $rolePermits[] = $permit->getSlug();
        $role->setRolePermits($rolePermits);
        $role->writeToDb($dbConnector);
    }


}