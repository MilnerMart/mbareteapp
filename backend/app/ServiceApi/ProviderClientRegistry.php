<?php

namespace App\ServiceApi;

use App\Db\DbConnector;
use App\Models\Provider;
use App\Models\ProviderConfig;
use App\PublicException;

class ProviderClientRegistry {

    const capabilityMap = [
        MessagingApi::class => 'messaging',
        ResourceApi::class => 'resources',
    ];

    static function clientClassMap(): array{
        return config('provider_clients', []);
    }

    static function clientClass(string $type): ?string{
        $clientClass = self::clientClassMap()[$type] ?? null;
        return $clientClass && is_subclass_of($clientClass, ProviderApiClient::class) ? $clientClass : null;
    }

    static function clientClassOrFail(string $type): string{
        $clientClass = self::clientClass($type);
        if(!$clientClass){
            throw PublicException::validationError('Tipo de proveedor invalido: '.$type, 'provider.invalid_type');
        }
        return $clientClass;
    }

    static function isClientCompatible(string $type, string $interface, ?string $channel = null): bool{
        $clientClass = self::clientClass($type);
        if(!$clientClass || !is_subclass_of($clientClass, $interface)){
            return false;
        }
        return !$channel || (is_subclass_of($clientClass, MessagingApi::class) && in_array($channel, $clientClass::supportedChannels(), true));
    }

    static function allocClient(DbConnector $dbConnect, ProviderConfig $providerConfig): ProviderApiClient{
        $provider = Provider::queryByDbIdOrFail($dbConnect, $providerConfig->getProviderId());
        $clientClass = self::clientClassOrFail($provider->getType());
        return new $clientClass($dbConnect, $providerConfig);
    }

    static function buildTypeModel(string $type): array{
        $clientClass = self::clientClass($type);
        if(!$clientClass){
            return ['type' => $type, 'label' => null, 'capabilities' => [], 'channels' => [], 'credentialKeys' => [], 'settingKeys' => []];
        }
        $capabilityList = [];
        foreach (self::capabilityMap as $interface => $capability) {
            if(is_subclass_of($clientClass, $interface)){
                $capabilityList[] = $capability;
            }
        }
        return [
            'type' => $type,
            'label' => $clientClass::clientLabel(),
            'capabilities' => $capabilityList,
            'channels' => is_subclass_of($clientClass, MessagingApi::class) ? $clientClass::supportedChannels() : [],
            'credentialKeys' => $clientClass::credentialKeyList(),
            'settingKeys' => $clientClass::settingKeyList(),
        ];
    }

    static function buildTypeModelList(): array{
        $model = [];
        foreach (array_keys(self::clientClassMap()) as $type) {
            if(self::clientClass($type)){
                $model[] = self::buildTypeModel($type);
            }
        }
        return $model;
    }
}
