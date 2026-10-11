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
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use stdClass;

class ProviderConfig extends BaseEntity {

    use HasDbData;

    private const myTable = DbSchema::tableProviderConfigs;

    private int $providerId;

    private string $name;

    private ?array $credentials = null;

    public static function allocNew(int $providerId, string $name, array $credentials = []):self{
        $self = new self();
        $self->providerId = $providerId;
        $self->name = $name;
        $self->credentials = $credentials ?: null;
        return $self;
    }

    function getModelId(): int{
        return CoreModel::providerConfigModelId;
    }

    function getProviderId(): int{
        return $this->providerId;
    }

    function getName(): string{
        return $this->name;
    }

    function setName(string $name): void{
        $this->name = $name;
    }

    function getCredentials(): array{
        return $this->credentials ?? [];
    }

    function getCredential(string $key): mixed{
        return $this->credentials[$key] ?? null;
    }

    function setCredentials(array $credentials): void{
        $this->credentials = $credentials ?: null;
    }

    function setCredential(string $key, mixed $value): void{
        if($value === null){
            unset($this->credentials[$key]);
        } else {
            $this->credentials[$key] = BaseHelper::storableOrFail($value);
        }
    }

    function getSetting(string $key): mixed{
        return $this->_getDataItem($key);
    }

    function setSetting(string $key, mixed $value): void{
        $this->_setDataItem($key, $value);
    }

    function isActive(): bool{
        return $this->getStatusId() === EntityStatus::statusIdActive;
    }

    private static function encryptCredentials(?array $credentials): ?string{
        return $credentials ? Crypt::encryptString(BaseHelper::jsonEncode($credentials)) : null;
    }

    private static function decryptCredentials(?string $value): ?array{
        if(!$value){
            return null;
        }
        try {
            return BaseHelper::arrayOrNull(BaseHelper::jsonDecode(Crypt::decryptString($value)));
        } catch (DecryptException $exception) {
            throw PublicException::internalError('No se pudieron leer las credenciales del proveedor', 'provider.credentials_unreadable');
        }
    }

    private function providerConfig2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->provider_id = $this->providerId;
        $row->name = $this->name;
        $row->credentials = self::encryptCredentials($this->credentials);
        $row->data = $this->_encodeDataForDb();
        return $row;
    }

    static function row2ProviderConfig(DbAdapter $dbAdapter, stdClass $row):self{
        $providerConfig = new ProviderConfig();
        $providerConfig->initFromDbRow($dbAdapter, $row);
        $providerConfig->providerId = (int)$row->provider_id;
        $providerConfig->name = $row->name;
        $providerConfig->credentials = self::decryptCredentials($row->credentials);
        $providerConfig->_loadDataFromDb($row->data);
        return $providerConfig;
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'providerId' => $this->providerId,
            'name' => $this->name,
            'credentialKeys' => array_keys($this->getCredentials()),
            'settings' => $this->_getAllData() ?? [],
            'status' => $this->getStatusId(),
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private static function allocSelectQuery(DbConnector $dbConnect): Builder{
        return self::allocDbTable($dbConnect)->where('status', '!=', EntityStatus::statusIdDeleted)->select()->orderBy('id');
    }

    static function queryListByProviderId(DbConnector $dbConnect, int $providerId):array{
        $query = self::allocSelectQuery($dbConnect)->where('provider_id', $providerId);
        return $dbConnect->fetchAll($query, [self::class, 'row2ProviderConfig']);
    }

    static function queryListByProviderIdList(DbConnector $dbConnect, array $providerIdList):array{
        if(!$providerIdList){
            return [];
        }
        $query = self::allocSelectQuery($dbConnect)->whereIn('provider_id', $providerIdList);
        return $dbConnect->fetchAll($query, [self::class, 'row2ProviderConfig']);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = self::allocSelectQuery($dbConnect)->where('id', $dbId);
        return $dbConnect->fetchSingle($query, [self::class, 'row2ProviderConfig']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::notFoundError('No se encuentra la configuracion de proveedor con id: '.$dbId);
        }
        return $self;
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime( $now );
        if( !$this->getCreatedTime() ){
            $this->setCreatedTime( $now );
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->providerConfig2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }
    }

}
