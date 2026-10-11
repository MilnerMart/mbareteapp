<?php

namespace App\Services;

use App\Http\Requests\ResourceRequest;
use App\Core\EntityStatus;
use App\Models\CoreConfig;
use App\Models\Resource;
use App\PublicException;
use App\ServiceApi\ProviderApiClient;
use App\ServiceApi\PublicResourceApi;
use App\ServiceApi\ResourceApi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResourceService extends BaseService {

    private const slugMaxLength = 40;

    private ?ResourceApi $storeClient = null;

    private ResourceApi|false|null $fetchClient = null;

    private PublicResourceApi|false|null $publicClient = null;

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
            $resource->setKind(Resource::kindImg);
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
        if(!$resourceKey || self::isExternalUrl($resourceKey) || self::isPublicKey($resourceKey)){
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
        if(self::isPublicKey($resourceKey)){
            return $this->obtainPublicFileUrl($resourceKey);
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

    public function searchPublicResources(string $query, int $limit = 10): array{
        return $this->allocPublicClientOrFail()->searchPublicResources($query, $limit);
    }

    public function listPublicResourceMedia(string $itemKey): array{
        return $this->allocPublicClientOrFail()->listPublicResourceMedia($itemKey);
    }

    public function attachPublicResource(int $modelId, int $ownerId, string $mediaKey): Resource{
        $client = $this->allocPublicClientOrFail();
        $media = $client->obtainPublicResource($mediaKey);
        $resource = Resource::allocNew(
            Str::limit($media['name'], 250, ''),
            rtrim(Str::limit(Str::slug($media['name']), self::slugMaxLength - 7, ''), '-').'-'.Str::lower(Str::random(6)),
            Resource::kindIdFromSlug($media['kind']) ?? Resource::kindImg,
            $modelId,
            $ownerId,
            $this->buildPublicKey($client, $media),
            EntityStatus::statusIdActive,
        );
        $resource->writeToDb($this->getDbConnecto());
        return $resource;
    }

    /**
     * Usa una imagen del proveedor de recursos publicos como imagen principal de la entidad. Si ya tenia una la reemplaza.
     */
    public function saveEntityPublicImage(int $modelId, int $ownerId, string $mediaKey, string $name, string $slug): Resource{
        $dbConnector = $this->getDbConnecto();
        $client = $this->allocPublicClientOrFail();
        $media = $client->obtainPublicResource($mediaKey);
        if($media['kind'] !== PublicResourceApi::kindImage){
            throw PublicException::validationError('La imagen principal tiene que ser una imagen; los videos se agregan como recursos extra');
        }
        $publicKey = $this->buildPublicKey($client, $media);

        $resource = Resource::queryByOwnerAndModelId($dbConnector, $modelId, $ownerId);
        if($resource){
            $this->deleteFile($resource->getUrl());
            $resource->setUrl($publicKey);
            $resource->setKind(Resource::kindImg);
        } else {
            $resource = Resource::allocNew($name, $slug.'-image', Resource::kindImg, $modelId, $ownerId, $publicKey, EntityStatus::statusIdActive);
        }
        $resource->writeToDb($dbConnector);

        return $resource;
    }

    public function publicProviderModel(): ?array{
        $client = $this->allocPublicClient();
        return $client ? ['name' => $client->getProvider()->getName(), 'type' => $client->getProvider()->getType()] : null;
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

    private function obtainPublicFileUrl(string $resourceKey): ?string{
        [$providerType, $mediaKey] = array_pad(explode(':', substr($resourceKey, strlen(Resource::publicKeyPrefix)), 2), 2, '');
        $client = $this->allocPublicClient();
        if(!$client || $client->getProvider()->getType() !== $providerType){
            return null;
        }
        try {
            return $client->obtainPublicResource($mediaKey)['url'];
        } catch (PublicException $exception) {
            Log::warning('resource.public_unavailable', ['key' => $resourceKey, 'code' => $exception->getInfoCode()]);
            return null;
        }
    }

    private function buildPublicKey(PublicResourceApi $client, array $media): string{
        return Resource::publicKeyPrefix.$client->getProvider()->getType().':'.$media['key'];
    }

    private function allocPublicClient(): ?PublicResourceApi{
        if($this->publicClient === null){
            try {
                $this->publicClient = ProviderApiClient::allocForAction($this->getDbConnecto(), CoreConfig::actionFetchPublicResources);
            } catch (PublicException $exception) {
                $this->publicClient = false;
            }
        }
        return $this->publicClient ?: null;
    }

    private function allocPublicClientOrFail(): PublicResourceApi{
        return $this->allocPublicClient()
            ?? throw PublicException::validationError('No hay un proveedor de recursos publicos configurado', 'core_config.missing_credential');
    }

    private static function isPublicKey(string $resourceKey): bool{
        return str_starts_with($resourceKey, Resource::publicKeyPrefix);
    }

    private static function isExternalUrl(string $resourceKey): bool{
        return (bool)preg_match('#^https?://#i', $resourceKey);
    }
}
