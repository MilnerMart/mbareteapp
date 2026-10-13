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

    private const slugMaxLength = 80;

    private ?ResourceApi $storeClient = null;

    private ResourceApi|false|null $fetchClient = null;

    private ?array $publicClientMap = null;

    private array $publicMediaMap = [];

    private array $failedPublicTypeMap = [];

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
            $resource = Resource::allocNew($name, self::buildSlug($slug, 'image'), Resource::kindImg, $modelId, $ownerId, $resourceKey, EntityStatus::statusIdActive);
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
        $resultListMap = [];
        $lastException = null;
        foreach ($this->allocPublicClientMapOrFail() as $type => $client) {
            try {
                $resultListMap[$type] = array_map(fn(array $result) => [
                    'key' => self::composePublicKey($type, $result['key']),
                    'name' => $result['name'],
                    'provider' => $client->getProvider()->getName(),
                ], $client->searchPublicResources($query, $limit));
            } catch (PublicException $exception) {
                Log::warning('resource.public_search_failed', ['provider' => $type, 'code' => $exception->getInfoCode()]);
                $lastException = $exception;
            }
        }
        if(!$resultListMap && $lastException){
            throw $lastException;
        }

        $model = [];
        for ($position = 0; count($model) < $limit && $resultListMap; $position++) {
            foreach ($resultListMap as $type => $resultList) {
                if(!isset($resultList[$position])){
                    unset($resultListMap[$type]);
                } elseif(count($model) < $limit){
                    $model[] = $resultList[$position];
                }
            }
        }
        return $model;
    }

    public function listPublicResourceMedia(string $itemKey): array{
        [$type, $providerKey] = self::splitPublicKey($itemKey);
        return array_map(fn(array $media) => ['key' => self::composePublicKey($type, $media['key'])] + $media,
            $this->allocPublicClientOrFail($type)->listPublicResourceMedia($providerKey));
    }

    public function attachPublicResource(int $modelId, int $ownerId, string $mediaKey): Resource{
        $media = $this->obtainPublicMediaOrFail($mediaKey);
        $resource = Resource::allocNew(
            Str::limit($media['name'], 250, ''),
            self::buildSlug(Str::slug($media['name']), Str::lower(Str::random(6))),
            Resource::kindIdFromSlug($media['kind']) ?? Resource::kindImg,
            $modelId,
            $ownerId,
            Resource::publicKeyPrefix.$mediaKey,
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
        $media = $this->obtainPublicMediaOrFail($mediaKey);
        if($media['kind'] !== PublicResourceApi::kindImage){
            throw PublicException::validationError('La imagen principal tiene que ser una imagen; los videos se agregan como recursos extra');
        }
        $publicKey = Resource::publicKeyPrefix.$mediaKey;

        $resource = Resource::queryByOwnerAndModelId($dbConnector, $modelId, $ownerId);
        if($resource){
            $this->deleteFile($resource->getUrl());
            $resource->setUrl($publicKey);
            $resource->setKind(Resource::kindImg);
        } else {
            $resource = Resource::allocNew($name, self::buildSlug($slug, 'image'), Resource::kindImg, $modelId, $ownerId, $publicKey, EntityStatus::statusIdActive);
        }
        $resource->writeToDb($dbConnector);

        return $resource;
    }

    public function obtainAttribution(?Resource $resource): ?array{
        if(!$resource?->isPublicResource()){
            return null;
        }
        return $this->obtainPublicMedia(substr($resource->getUrl(), strlen(Resource::publicKeyPrefix)))['attribution'] ?? null;
    }

    public function publicProviderModelList(): array{
        return array_values(array_map(fn(PublicResourceApi $client) => [
            'name' => $client->getProvider()->getName(),
            'type' => $client->getProvider()->getType(),
        ], $this->allocPublicClientMap()));
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
        return $this->obtainPublicMedia(substr($resourceKey, strlen(Resource::publicKeyPrefix)))['url'] ?? null;
    }

    private function obtainPublicMedia(string $mediaKey): ?array{
        $type = strstr($mediaKey, ':', true) ?: $mediaKey;
        if(isset($this->failedPublicTypeMap[$type])){
            return null;
        }
        if(!array_key_exists($mediaKey, $this->publicMediaMap)){
            try {
                $this->publicMediaMap[$mediaKey] = $this->obtainPublicMediaOrFail($mediaKey);
            } catch (PublicException $exception) {
                Log::warning('resource.public_unavailable', ['key' => $mediaKey, 'code' => $exception->getInfoCode()]);
                $this->publicMediaMap[$mediaKey] = null;
                if(str_starts_with((string)$exception->getInfoCode(), 'provider.')){
                    $this->failedPublicTypeMap[$type] = true;
                }
            }
        }
        return $this->publicMediaMap[$mediaKey];
    }

    private function obtainPublicMediaOrFail(string $mediaKey): array{
        [$type, $providerKey] = self::splitPublicKey($mediaKey);
        return $this->allocPublicClientOrFail($type)->obtainPublicResource($providerKey);
    }

    private function allocPublicClientMap(): array{
        if($this->publicClientMap === null){
            $this->publicClientMap = [];
            foreach (CoreConfig::publicResourceActionList as $action) {
                try {
                    $client = ProviderApiClient::allocForAction($this->getDbConnecto(), $action);
                } catch (PublicException $exception) {
                    continue;
                }
                $this->publicClientMap[$client->getProvider()->getType()] ??= $client;
            }
        }
        return $this->publicClientMap;
    }

    private function allocPublicClientMapOrFail(): array{
        return $this->allocPublicClientMap()
            ?: throw PublicException::validationError('No hay un proveedor de recursos publicos configurado', 'core_config.missing_credential');
    }

    private function allocPublicClientOrFail(string $type): PublicResourceApi{
        return $this->allocPublicClientMapOrFail()[$type]
            ?? throw PublicException::validationError('El proveedor de recursos publicos "'.$type.'" no esta configurado', 'core_config.missing_credential');
    }

    private static function composePublicKey(string $type, string $providerKey): string{
        return $type.':'.$providerKey;
    }

    private static function splitPublicKey(string $publicKey): array{
        [$type, $providerKey] = array_pad(explode(':', $publicKey, 2), 2, '');
        if($type === '' || $providerKey === ''){
            throw PublicException::validationError('Clave de recurso publico invalida: '.$publicKey);
        }
        return [$type, $providerKey];
    }

    private static function buildSlug(string $slug, string $suffix): string{
        return rtrim(Str::limit($slug, self::slugMaxLength - strlen($suffix) - 1, ''), '-').'-'.$suffix;
    }

    private static function isPublicKey(string $resourceKey): bool{
        return str_starts_with($resourceKey, Resource::publicKeyPrefix);
    }

    private static function isExternalUrl(string $resourceKey): bool{
        return (bool)preg_match('#^https?://#i', $resourceKey);
    }
}
