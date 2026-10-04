<?php

namespace App\Services;

use App\Http\Requests\ResourceRequest;
use App\Models\Resource;

class ResourceService extends BaseService {


    public function allocResource(int $modelId, int $ownerId, array $resourceOpts): Resource{
        $dbConnector = $this->getDbConnecto();
        $resource = Resource::allocNew($resourceOpts['name'], $resourceOpts['slug'],
            $resourceOpts['kind'], $modelId, $ownerId, $resourceOpts['url'], 
            $resourceOpts['status']);

        $resource->writeToDb($dbConnector);   
        
        return $resource;
    }

    public function AllocResourceOpts(ResourceRequest $request):array{
        $request->validated();
        return $request->toArray();
    }
}