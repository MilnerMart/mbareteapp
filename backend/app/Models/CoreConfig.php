<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\HasDbData;
use App\Helpers\BaseHelper;
use App\Models\BaseEntity;
use App\PublicException;
use App\ServiceApi\MessagingApi;
use App\ServiceApi\ProviderClientRegistry;
use App\ServiceApi\PublicResourceApi;
use App\ServiceApi\ResourceApi;
use Illuminate\Database\Query\Builder;
use stdClass;

class CoreConfig extends BaseEntity {

    use HasDbData;

    private const myTable = DbSchema::tableCoreConfigs;

    const actionSendEmail = 'send-email', actionSendSms = 'send-sms', actionSendPush = 'send-push', actionSendWhatsapp = 'send-whatsapp',
        actionStoreResources = 'store-resources', actionFetchResources = 'fetch-resources', actionFetchPublicResources = 'fetch-public-resources',
        actionFetchPublicResources2 = 'fetch-public-resources-2', actionFetchPublicResources3 = 'fetch-public-resources-3';

    const publicResourceActionList = [self::actionFetchPublicResources, self::actionFetchPublicResources2, self::actionFetchPublicResources3];

    const actionMap = [
        self::actionStoreResources => ['label' => 'Almacenamiento de recursos', 'interface' => ResourceApi::class, 'channel' => null],
        self::actionFetchResources => ['label' => 'Obtener recursos', 'interface' => ResourceApi::class, 'channel' => null],
        self::actionFetchPublicResources => ['label' => 'Recursos publicos', 'interface' => PublicResourceApi::class, 'channel' => null],
        self::actionFetchPublicResources2 => ['label' => 'Recursos publicos (2)', 'interface' => PublicResourceApi::class, 'channel' => null],
        self::actionFetchPublicResources3 => ['label' => 'Recursos publicos (3)', 'interface' => PublicResourceApi::class, 'channel' => null],
        self::actionSendEmail => ['label' => 'Envio de correos', 'interface' => MessagingApi::class, 'channel' => MessagingApi::channelEmail],
        self::actionSendSms => ['label' => 'Envio de SMS', 'interface' => MessagingApi::class, 'channel' => MessagingApi::channelSms],
        self::actionSendPush => ['label' => 'Notificaciones push', 'interface' => MessagingApi::class, 'channel' => MessagingApi::channelPush],
        self::actionSendWhatsapp => ['label' => 'Envio de WhatsApp', 'interface' => MessagingApi::class, 'channel' => MessagingApi::channelWhatsapp],
    ];

    private string $action;

    private ?int $providerConfigId = null;

    public static function allocNew(string $action):self{
        self::actionInfoOrFail($action);
        $self = new self();
        $self->action = $action;
        return $self;
    }

    static function actionInfoOrFail(string $action): array{
        if(!isset(self::actionMap[$action])){
            throw PublicException::validationError('Accion de configuracion invalida: '.$action);
        }
        return self::actionMap[$action];
    }

    static function isProviderCompatible(string $action, Provider $provider): bool{
        $actionInfo = self::actionInfoOrFail($action);
        return ProviderClientRegistry::isClientCompatible($provider->getType(), $actionInfo['interface'], $actionInfo['channel']);
    }

    function getModelId(): int{
        return CoreModel::coreConfigModelId;
    }

    function getAction(): string{
        return $this->action;
    }

    function getProviderConfigId(): ?int{
        return $this->providerConfigId;
    }

    function setProviderConfigId(?int $providerConfigId): void{
        $this->providerConfigId = $providerConfigId;
    }

    private function coreConfig2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->action = $this->action;
        $row->provider_config_id = $this->providerConfigId;
        $row->data = $this->_encodeDataForDb();
        return $row;
    }

    static function row2CoreConfig(DbAdapter $dbAdapter, stdClass $row):self{
        $coreConfig = new CoreConfig();
        $coreConfig->initFromDbRow($dbAdapter, $row);
        $coreConfig->action = $row->action;
        $coreConfig->providerConfigId = $row->provider_config_id !== null ? (int)$row->provider_config_id : null;
        $coreConfig->_loadDataFromDb($row->data);
        return $coreConfig;
    }

    public function buildApiModel(): array{
        return [
            'action' => $this->action,
            'label' => self::actionMap[$this->action]['label'] ?? $this->action,
            'providerConfigId' => $this->providerConfigId,
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private static function allocSelectQuery(DbConnector $dbConnect): Builder{
        return self::allocDbTable($dbConnect)->where('status', '!=', EntityStatus::statusIdDeleted)->select();
    }

    static function queryActionMap(DbConnector $dbConnect):array{
        $map = [];
        foreach ($dbConnect->fetchAll(self::allocSelectQuery($dbConnect), [self::class, 'row2CoreConfig']) as $coreConfig) {
            $map[$coreConfig->action] = $coreConfig;
        }
        return $map;
    }

    static function queryByAction(DbConnector $dbConnect, string $action):?self{
        $query = self::allocSelectQuery($dbConnect)->where('action', $action);
        return $dbConnect->fetchSingle($query, [self::class, 'row2CoreConfig']);
    }

    static function queryProviderConfigForActionOrFail(DbConnector $dbConnect, string $action): ProviderConfig{
        $actionInfo = self::actionInfoOrFail($action);
        $providerConfigId = self::queryByAction($dbConnect, $action)?->getProviderConfigId();
        $providerConfig = $providerConfigId ? ProviderConfig::queryByDbId($dbConnect, $providerConfigId) : null;
        if(!$providerConfig){
            throw PublicException::validationError('No hay credencial configurada para: '.$actionInfo['label'], 'core_config.missing_credential');
        }
        return $providerConfig;
    }

    static function clearProviderConfigIdList(DbConnector $dbConnect, array $providerConfigIdList): void{
        if(!$providerConfigIdList){
            return;
        }
        self::allocDbTable($dbConnect)->whereIn('provider_config_id', $providerConfigIdList)
            ->update(['provider_config_id' => null, 'updated_at' => $dbConnect->toDbDate(BaseHelper::utcNow())]);
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime( $now );
        if( !$this->getCreatedTime() ){
            $this->setCreatedTime( $now );
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->coreConfig2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }
    }

}
