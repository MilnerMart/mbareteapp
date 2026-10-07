<?php

namespace App\Services;

use App\Http\Requests\ResourceRequest;
use App\Core\EntityStatus;
use App\Models\Resource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ResourceService extends BaseService {


    public function allocResource(int $modelId, int $ownerId, array $resourceOpts): Resource{
        $dbConnector = $this->getDbConnecto();
        $resource = Resource::allocNew($resourceOpts['name'], $resourceOpts['slug'],
            $resourceOpts['kind'], $modelId, $ownerId, $resourceOpts['url'], 
            $resourceOpts['status']);

        $resource->writeToDb($dbConnector);   
        
        return $resource;
    }

    /**
     * Guarda la imagen principal de una entidad en public/$directory. Si ya tenia una la reemplaza.
     */
    public function saveEntityImage(UploadedFile $file, string $directory, int $modelId, int $ownerId, string $name, string $slug): Resource{
        $dbConnector = $this->getDbConnecto();
        File::ensureDirectoryExists(public_path($directory));
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move(public_path($directory), $filename);
        $url = $directory.'/'.$filename;

        $resource = Resource::queryByOwnerAndModelId($dbConnector, $modelId, $ownerId);
        if($resource){
            $previousPath = public_path($resource->getUrl());
            if(File::exists($previousPath)){
                File::delete($previousPath);
            }
            $resource->setUrl($url);
        } else {
            $resource = Resource::allocNew($name, $slug.'-image', Resource::kindImg, $modelId, $ownerId, $url, EntityStatus::statusIdActive);
        }
        $resource->writeToDb($dbConnector);

        return $resource;
    }

    public function AllocResourceOpts(ResourceRequest $request):array{
        $request->validated();
        return $request->toArray();
    }
}