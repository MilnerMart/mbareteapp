<?php

namespace App\ServiceApi;

use App\Db\DbConnector;
use App\Models\CoreConfig;
use App\Models\Provider;
use App\Models\ProviderConfig;
use App\PublicException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class ProviderApiClient {


    protected int $timeoutSeconds = 15;

    private const logBodyLength = 500;

    private DbConnector $dbConnect;

    private ProviderConfig $providerConfig;

    private Provider $provider;

    function __construct(DbConnector $dbConnect, ProviderConfig $providerConfig){
        if(!$providerConfig->isActive()){
            throw PublicException::validationError('La configuracion "'.$providerConfig->getName().'" no esta activa', 'provider.config_inactive');
        }
        $this->dbConnect = $dbConnect;
        $this->providerConfig = $providerConfig;
        $this->provider = Provider::queryByDbIdOrFail($dbConnect, $providerConfig->getProviderId());
    }

    abstract static function clientLabel(): string;

    static function credentialKeyList(): array{
        return [];
    }

    static function settingKeyList(): array{
        return [];
    }

    static function allocForConfigId(DbConnector $dbConnect, int $providerConfigId): self{
        return ProviderClientRegistry::allocClient($dbConnect, ProviderConfig::queryByDbIdOrFail($dbConnect, $providerConfigId));
    }

    static function allocForAction(DbConnector $dbConnect, string $action): self{
        $actionInfo = CoreConfig::actionInfoOrFail($action);
        $client = ProviderClientRegistry::allocClient($dbConnect, CoreConfig::queryProviderConfigForActionOrFail($dbConnect, $action));
        if(!$client instanceof $actionInfo['interface']){
            throw PublicException::validationError('La credencial configurada no sirve para: '.$actionInfo['label'], 'core_config.incompatible_credential');
        }
        return $client;
    }

    function getDbConnector(): DbConnector{
        return $this->dbConnect;
    }

    function getProvider(): Provider{
        return $this->provider;
    }

    function getProviderConfig(): ProviderConfig{
        return $this->providerConfig;
    }


    abstract protected function buildAuthHeaders(): array;

    protected function getBaseUrl(): string{
        $baseUrl = $this->providerConfig->getSetting('baseUrl') ?: $this->provider->getBaseUrl();
        if(!$baseUrl){
            throw PublicException::validationError('El proveedor '.$this->provider->getName().' no tiene url configurada', 'provider.missing_base_url');
        }
        return rtrim($baseUrl, '/');
    }

    function getJson(string $endpoint, array $query = []): array{
        return $this->requestJson('GET', $endpoint, $query ? ['query' => $query] : []);
    }

    function postJson(string $endpoint, array $body = []): array{
        return $this->requestJson('POST', $endpoint, ['json' => $body]);
    }

    function putJson(string $endpoint, array $body = []): array{
        return $this->requestJson('PUT', $endpoint, ['json' => $body]);
    }

    function patchJson(string $endpoint, array $body = []): array{
        return $this->requestJson('PATCH', $endpoint, ['json' => $body]);
    }

    function deleteJson(string $endpoint, array $body = []): array{
        return $this->requestJson('DELETE', $endpoint, $body ? ['json' => $body] : []);
    }

    protected function allocRequest(): PendingRequest{
        return Http::baseUrl($this->getBaseUrl())
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->withHeaders($this->buildAuthHeaders());
    }


    protected function requestJson(string $method, string $endpoint, array $options = []): array{
        $url = '/'.ltrim($endpoint, '/');
        try {
            $response = $this->allocRequest()->send($method, $url, $options);
        } catch (ConnectionException $exception) {
            Log::warning('provider.connection_failed', $this->buildLogContext($method, $url) + ['error' => $exception->getMessage()]);
            throw PublicException::internalError('No se pudo conectar con '.$this->provider->getName(), 'provider.connection_failed');
        }

        if($response->failed()){
            Log::warning('provider.request_failed', $this->buildLogContext($method, $url) + [
                'httpStatus' => $response->status(),
                'body' => mb_substr($response->body(), 0, self::logBodyLength),
            ]);
            throw PublicException::internalError($this->provider->getName().' respondio con un error', 'provider.request_failed', [
                'httpStatus' => $response->status(),
            ]);
        }

        return $this->decodeJsonOrFail($response, $method, $url);
    }

    private function decodeJsonOrFail(Response $response, string $method, string $url): array{
        if(trim($response->body()) === ''){
            return [];
        }
        $json = $response->json();
        if(!is_array($json)){
            Log::warning('provider.invalid_json', $this->buildLogContext($method, $url) + [
                'body' => mb_substr($response->body(), 0, self::logBodyLength),
            ]);
            throw PublicException::internalError($this->provider->getName().' devolvio una respuesta invalida', 'provider.invalid_json');
        }
        return $json;
    }

    private function buildLogContext(string $method, string $url): array{
        return [
            'provider' => $this->provider->getSlug(),
            'providerConfigId' => $this->providerConfig->getEntityId(),
            'method' => $method,
            'url' => $url,
        ];
    }
}
