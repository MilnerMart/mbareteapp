<?php

namespace App\ServiceApi;

use App\PublicException;
use Illuminate\Support\Facades\File;

class LocalStorageApiClient extends ProviderApiClient implements ResourceApi {

    static function clientLabel(): string{
        return 'Almacenamiento local';
    }

    static function settingKeyList(): array{
        return ['directory'];
    }

    protected function buildAuthHeaders(): array{
        return [];
    }

    function uploadResource(string $localPath, string $resourceKey, array $options = []): array{
        if(!File::isFile($localPath)){
            throw PublicException::validationError('No se encuentra el archivo a subir');
        }
        $relativePath = $this->buildRelativePath($resourceKey);
        File::ensureDirectoryExists(dirname(public_path($relativePath)));
        File::copy($localPath, public_path($relativePath));
        return $this->buildResourceModel($resourceKey, $relativePath);
    }

    function obtainResource(string $resourceKey, array $options = []): array{
        $relativePath = $this->buildRelativePath($resourceKey);
        if(!File::isFile(public_path($relativePath))){
            throw PublicException::notFoundError('No se encuentra el recurso: '.$resourceKey);
        }
        return $this->buildResourceModel($resourceKey, $relativePath) + ['size' => File::size(public_path($relativePath))];
    }

    function deleteResource(string $resourceKey, array $options = []): bool{
        $fullPath = public_path($this->buildRelativePath($resourceKey));
        return File::isFile($fullPath) && File::delete($fullPath);
    }

    private function buildRelativePath(string $resourceKey): string{
        $resourceKey = ltrim($resourceKey, '/');
        if($resourceKey === '' || str_contains($resourceKey, '..')){
            throw PublicException::validationError('Clave de recurso invalida: '.$resourceKey);
        }
        $directory = trim($this->getProviderConfig()->getSetting('directory') ?? '', '/');
        return $directory === '' ? $resourceKey : $directory.'/'.$resourceKey;
    }

    private function buildResourceModel(string $resourceKey, string $relativePath): array{
        return [
            'key' => $resourceKey,
            'path' => $relativePath,
            'url' => asset($relativePath),
        ];
    }
}
