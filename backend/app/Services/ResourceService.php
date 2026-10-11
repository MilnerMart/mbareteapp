<?php

namespace App\Services;

use App\Http\Requests\ResourceRequest;
use App\Core\EntityStatus;
use App\Models\CoreConfig;
use App\Models\Resource;
use App\PublicException;
use App\ServiceApi\ProviderApiClient;
use App\ServiceApi\ResourceApi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResourceService extends BaseService {

    private ?ResourceApi $storeClient = null;

    private ResourceApi|false|null $fetchClient = null;

    public function allocResource(int $modelId, int $ownerId, array $resourceOpts): Resource{
        $dbConnector = $this->getDbConnecto();
        $resource = Resource::allocNew($resourceOpts['name'], $resourceOpts['slug'],
            $resourceOpts['kind'], $modelId, $ownerId, $resourceOpts['url'], 
            $resourceOpts['status']);

        $resource->writeToDb($dbConnector);   
        
        return $resource;
    }

    /**
     * Guarda la imagen principal de una entidad con el proveedor de almacenamiento configurado. Si ya tenia una la reemplaza.
     */
    public function saveEntityImage(UploadedFile $file, string $directory, int $modelId, int $ownerId, string $name, string $slug): Resource{
        $dbConnector = $this->getDbConnecto();
        $resourceKey = $this->uploadFile($file, $directory);

        $resource = Resource::queryByOwnerAndModelId($dbConnector, $modelId, $ownerId);
        if($resource){
            $this->deleteFile($resource->getUrl());
            $resource->setUrl($resourceKey);
        } else {
            $resource = Resource::allocNew($name, $slug.'-image', Resource::kindImg, $modelId, $ownerId, $resourceKey, EntityStatus::statusIdActive);
        }
        $resource->writeToDb($dbConnector);

        return $resource;
    }

    public function uploadFile(UploadedFile $file, string $directory): string{
        $resourceKey = trim($directory, '/').'/'.Str::uuid().'.'.$file->getClientOriginalExtension();
        return $this->allocStoreClient()->uploadResource($file->getRealPath(), $resourceKey)['key'];
    }

    public function deleteFile(?string $resourceKey): void{
        if(!$resourceKey || self::isExternalUrl($resourceKey)){
            return;
        }
        $this->allocStoreClient()->deleteResource($resourceKey);
    }

    public function obtainPublicUrl(?Resource $resource): ?string{
        return $resource ? $this->obtainFileUrl($resource->getUrl()) : null;
    }

    public function obtainFileUrl(?string $resourceKey): ?string{
        if(!$resourceKey || self::isExternalUrl($resourceKey)){
            return $resourceKey;
        }
        $client = $this->allocFetchClient();
        if(!$client){
            return Resource::buildLegacyPublicUrl($resourceKey);
        }
        try {
            return $client->obtainResource($resourceKey)['url'];
        } catch (PublicException $exception) {
            return $resourceKey;
        }
    }

    public function AllocResourceOpts(ResourceRequest $request):array{
        $request->validated();
        return $request->toArray();
    }

    private function allocStoreClient(): ResourceApi{
        return $this->storeClient ??= ProviderApiClient::allocForAction($this->getDbConnecto(), CoreConfig::actionStoreResources);
    }

    private function allocFetchClient(): ?ResourceApi{
        if($this->fetchClient === null){
            try {
                $this->fetchClient = ProviderApiClient::allocForAction($this->getDbConnecto(), CoreConfig::actionFetchResources);
            } catch (PublicException $exception) {
                Log::warning('resource.fetch_client_unavailable', ['code' => $exception->getInfoCode(), 'error' => $exception->getText()]);
                $this->fetchClient = false;
            }
        }
        return $this->fetchClient ?: null;
    }

    private static function isExternalUrl(string $resourceKey): bool{
        return (bool)preg_match('#^https?://#i', $resourceKey);
    }
}
