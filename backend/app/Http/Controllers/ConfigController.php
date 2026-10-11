<?php

namespace App\Http\Controllers;

use App\Core\EntityStatus;
use App\Http\Requests\CoreConfigRequest;
use App\Http\Requests\ProviderConfigRequest;
use App\Http\Requests\ProviderRequest;
use App\Models\CoreConfig;
use App\Models\Provider;
use App\Models\ProviderConfig;
use App\PublicException;
use App\ServiceApi\ProviderClientRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConfigController extends Controller
{
    private const keyPattern = '/^[A-Za-z0-9_.-]{1,50}$/';

    public function providerTypeIndex(Request $request): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        return $this->successApiResponse(ProviderClientRegistry::buildTypeModelList());
    }

    public function providerIndex(Request $request): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $providerList = Provider::queryList($dbConnector);

        $configCountMap = [];
        $providerIdList = array_map(fn(Provider $provider) => $provider->getEntityId(), $providerList);
        foreach (ProviderConfig::queryListByProviderIdList($dbConnector, $providerIdList) as $providerConfig) {
            $configCountMap[$providerConfig->getProviderId()] = ($configCountMap[$providerConfig->getProviderId()] ?? 0) + 1;
        }

        $model = [];
        foreach ($providerList as $provider) {
            $model[] = $this->buildProviderModel($provider) + ['configCount' => $configCountMap[$provider->getEntityId()] ?? 0];
        }
        return $this->successApiResponse($model);
    }

    public function providerShow(Request $request, string $id): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $provider = Provider::queryByDbIdOrFail($dbConnector, (int)$id);
        $configList = array_map(
            fn(ProviderConfig $providerConfig) => $providerConfig->buildApiModel(),
            ProviderConfig::queryListByProviderId($dbConnector, $provider->getEntityId())
        );

        return $this->successApiResponse($this->buildProviderModel($provider) + ['configs' => $configList]);
    }

    public function providerStore(ProviderRequest $request): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $validated = $request->validated();
        $provider = Provider::allocNew(
            $validated['name'],
            Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
            $validated['type'],
            $validated['base_url'] ?? null,
        );
        $provider->writeToDb($this->getDbConnector());

        return $this->successApiResponse($this->buildProviderModel($provider), 201);
    }

    public function providerUpdate(ProviderRequest $request, string $id): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $provider = Provider::queryByDbIdOrFail($dbConnector, (int)$id);
        $validated = $request->validated();
        $provider->setName($validated['name']);
        $provider->setBaseUrl($validated['base_url'] ?? null);
        $provider->writeToDb($dbConnector);

        return $this->successApiResponse($this->buildProviderModel($provider));
    }

    public function providerDestroy(Request $request, string $id): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $provider = Provider::queryByDbIdOrFail($dbConnector, (int)$id);

        $providerConfigIdList = [];
        foreach (ProviderConfig::queryListByProviderId($dbConnector, $provider->getEntityId()) as $providerConfig) {
            $providerConfig->setStatusId(EntityStatus::statusIdDeleted);
            $providerConfig->writeToDb($dbConnector);
            $providerConfigIdList[] = $providerConfig->getEntityId();
        }
        CoreConfig::clearProviderConfigIdList($dbConnector, $providerConfigIdList);

        $provider->setStatusId(EntityStatus::statusIdDeleted);
        $provider->writeToDb($dbConnector);

        return $this->successApiResponse();
    }

    public function providerConfigStore(ProviderConfigRequest $request, string $id): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $provider = Provider::queryByDbIdOrFail($dbConnector, (int)$id);
        $validated = $request->validated();

        $providerConfig = ProviderConfig::allocNew(
            $provider->getEntityId(),
            $validated['name'],
            $this->filterKeyValueOrFail($validated['credentials'] ?? [], 'credencial'),
        );
        foreach ($this->filterKeyValueOrFail($validated['settings'] ?? [], 'ajuste') as $key => $value) {
            $providerConfig->setSetting($key, $value);
        }
        $providerConfig->writeToDb($dbConnector);

        return $this->successApiResponse($providerConfig->buildApiModel(), 201);
    }

    public function providerConfigDestroy(Request $request, string $id): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $providerConfig = ProviderConfig::queryByDbIdOrFail($dbConnector, (int)$id);
        $providerConfig->setStatusId(EntityStatus::statusIdDeleted);
        $providerConfig->writeToDb($dbConnector);
        CoreConfig::clearProviderConfigIdList($dbConnector, [$providerConfig->getEntityId()]);

        return $this->successApiResponse();
    }

    public function coreIndex(Request $request): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        return $this->successApiResponse($this->buildCoreModel());
    }

    public function coreUpdate(CoreConfigRequest $request): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $coreConfigMap = CoreConfig::queryActionMap($dbConnector);

        foreach ($request->validated()['actions'] as $action => $providerConfigId) {
            $action = (string)$action;
            $actionInfo = CoreConfig::actionInfoOrFail($action);
            $providerConfigId = $providerConfigId ? (int)$providerConfigId : null;
            if($providerConfigId){
                $providerConfig = ProviderConfig::queryByDbIdOrFail($dbConnector, $providerConfigId);
                $provider = Provider::queryByDbIdOrFail($dbConnector, $providerConfig->getProviderId());
                if(!$providerConfig->isActive() || !CoreConfig::isProviderCompatible($action, $provider)){
                    throw PublicException::validationError('La credencial "'.$providerConfig->getName().'" no sirve para: '.$actionInfo['label']);
                }
            }

            $coreConfig = $coreConfigMap[$action] ?? CoreConfig::allocNew($action);
            $coreConfig->setProviderConfigId($providerConfigId);
            $coreConfig->writeToDb($dbConnector);
        }

        return $this->successApiResponse($this->buildCoreModel());
    }

    private function buildProviderModel(Provider $provider): array {
        return $provider->buildApiModel() + ['client' => ProviderClientRegistry::buildTypeModel($provider->getType())];
    }

    private function buildCoreModel(): array {
        $dbConnector = $this->getDbConnector();
        $coreConfigMap = CoreConfig::queryActionMap($dbConnector);
        $providerMap = [];
        foreach (Provider::queryList($dbConnector) as $provider) {
            $providerMap[$provider->getEntityId()] = $provider;
        }
        $providerConfigList = ProviderConfig::queryListByProviderIdList($dbConnector, array_keys($providerMap));

        $model = [];
        foreach (CoreConfig::actionMap as $action => $actionInfo) {
            $optionList = [];
            foreach ($providerConfigList as $providerConfig) {
                $provider = $providerMap[$providerConfig->getProviderId()];
                if($providerConfig->isActive() && CoreConfig::isProviderCompatible($action, $provider)){
                    $optionList[] = [
                        'id' => $providerConfig->getEntityId(),
                        'name' => $providerConfig->getName(),
                        'providerName' => $provider->getName(),
                    ];
                }
            }
            $model[] = [
                'action' => $action,
                'label' => $actionInfo['label'],
                'capability' => ProviderClientRegistry::capabilityMap[$actionInfo['interface']],
                'channel' => $actionInfo['channel'],
                'providerConfigId' => isset($coreConfigMap[$action]) ? $coreConfigMap[$action]->getProviderConfigId() : null,
                'options' => $optionList,
            ];
        }
        return $model;
    }

    private function filterKeyValueOrFail(array $keyValueMap, string $label): array {
        $filtered = [];
        foreach ($keyValueMap as $key => $value) {
            if(!preg_match(self::keyPattern, (string)$key)){
                throw PublicException::validationError('Nombre de '.$label.' invalido: '.$key.'. Usa letras, numeros, punto, guion o guion bajo');
            }
            if($value !== null && $value !== ''){
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}
