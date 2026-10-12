<?php

namespace App\ServiceApi;

use App\PublicException;
use Aws\S3\S3Client;
use Closure;
use Illuminate\Support\Facades\File;

class AwsApiClient extends ProviderApiClient implements ResourceApi {

    use UsesAwsClient;

    private const defaultUrlExpirationMinutes = 60, maxUrlExpirationMinutes = 10080;

    private const cacheControl = 'public, max-age=31536000, immutable';

    private ?S3Client $s3Client = null;

    static function clientLabel(): string{
        return 'Amazon S3';
    }

    static function credentialKeyList(): array{
        return ['access_key_id', 'secret_access_key'];
    }

    static function settingKeyList(): array{
        return ['bucket', 'region', 'prefix', 'publicBaseUrl', 'urlExpirationMinutes', 'endpoint'];
    }

    protected function buildAuthHeaders(): array{
        return [];
    }

    function uploadResource(string $localPath, string $resourceKey, array $options = []): array{
        if(!File::isFile($localPath)){
            throw PublicException::validationError('No se encuentra el archivo a subir');
        }
        $objectKey = $this->buildObjectKey($resourceKey);
        $this->runS3OrFail('subir', $resourceKey, fn() => $this->getS3Client()->putObject([
            'Bucket' => $this->getBucketOrFail(),
            'Key' => $objectKey,
            'SourceFile' => $localPath,
            'ContentType' => File::mimeType($localPath) ?: 'application/octet-stream',
            'CacheControl' => self::cacheControl,
        ]));
        return $this->buildResourceModel($resourceKey, $objectKey);
    }

    function obtainResource(string $resourceKey, array $options = []): array{
        return $this->buildResourceModel($resourceKey, $this->buildObjectKey($resourceKey));
    }

    function deleteResource(string $resourceKey, array $options = []): bool{
        $objectKey = $this->buildObjectKey($resourceKey);
        $this->runS3OrFail('eliminar', $resourceKey, fn() => $this->getS3Client()->deleteObject([
            'Bucket' => $this->getBucketOrFail(),
            'Key' => $objectKey,
        ]));
        return true;
    }

    private function buildResourceModel(string $resourceKey, string $objectKey): array{
        return [
            'key' => $resourceKey,
            'path' => $objectKey,
            'url' => $this->buildObjectUrl($objectKey),
        ];
    }

    private function buildObjectUrl(string $objectKey): string{
        $publicBaseUrl = $this->getProviderConfig()->getSetting('publicBaseUrl');
        if($publicBaseUrl){
            return rtrim($publicBaseUrl, '/').'/'.implode('/', array_map('rawurlencode', explode('/', $objectKey)));
        }
        $command = $this->getS3Client()->getCommand('GetObject', ['Bucket' => $this->getBucketOrFail(), 'Key' => $objectKey]);
        return (string)$this->runS3OrFail('firmar', $objectKey,
            fn() => $this->getS3Client()->createPresignedRequest($command, '+'.$this->getUrlExpirationMinutes().' minutes')->getUri());
    }

    private function buildObjectKey(string $resourceKey): string{
        $resourceKey = ltrim($resourceKey, '/');
        if($resourceKey === '' || str_contains($resourceKey, '..')){
            throw PublicException::validationError('Clave de recurso invalida: '.$resourceKey);
        }
        $prefix = trim((string)$this->getProviderConfig()->getSetting('prefix'), '/');
        return $prefix === '' ? $resourceKey : $prefix.'/'.$resourceKey;
    }

    private function getUrlExpirationMinutes(): int{
        $minutes = (int)($this->getProviderConfig()->getSetting('urlExpirationMinutes') ?: self::defaultUrlExpirationMinutes);
        return max(1, min($minutes, self::maxUrlExpirationMinutes));
    }

    private function getBucketOrFail(): string{
        $bucket = $this->getProviderConfig()->getSetting('bucket');
        if(!$bucket){
            throw PublicException::validationError('Falta el ajuste bucket de '.$this->getProvider()->getName(), 'provider.missing_setting');
        }
        return $bucket;
    }

    private function getS3Client(): S3Client{
        if($this->s3Client){
            return $this->s3Client;
        }
        $s3Options = $this->buildAwsClientOptions();
        if(isset($s3Options['endpoint'])){
            $s3Options['use_path_style_endpoint'] = true;
        }
        return $this->s3Client = new S3Client($s3Options);
    }

    private function runS3OrFail(string $actionLabel, string $resourceKey, Closure $operation): mixed{
        return $this->runAwsOrFail($actionLabel, $resourceKey, $operation,
            'No se pudo '.$actionLabel.' el recurso en '.$this->getProvider()->getName(), 'provider.s3_error');
    }
}
