<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\HasDbData;
use App\Helpers\BaseHelper;
use Illuminate\Database\Query\Builder;
use stdClass;

class EntityLog extends BaseEntity {

    use HasDbData;

    private const myTable = DbSchema::tableEntityLogs;

    const actionIdCreated = 10, actionIdUpdated = 20, actionIdDeleted = 30, actionIdAssigned = 40, actionIdUnassigned = 50,
        actionIdExerciseAdded = 60, actionIdExerciseRemoved = 70, actionIdResourceAdded = 80, actionIdResourceRemoved = 90,
        actionIdImageUpdated = 100, actionIdApproved = 110, actionIdRejected = 120, actionIdResubmitted = 130,
        actionIdMemberRemoved = 140, actionIdVisibilityChanged = 150, actionIdRankUp = 160;

    const pageSize = 30;

    private const actionSlugMap = [
        self::actionIdCreated => 'created',
        self::actionIdUpdated => 'updated',
        self::actionIdDeleted => 'deleted',
        self::actionIdAssigned => 'assigned',
        self::actionIdUnassigned => 'unassigned',
        self::actionIdExerciseAdded => 'exercise-added',
        self::actionIdExerciseRemoved => 'exercise-removed',
        self::actionIdResourceAdded => 'resource-added',
        self::actionIdResourceRemoved => 'resource-removed',
        self::actionIdImageUpdated => 'image-updated',
        self::actionIdApproved => 'approved',
        self::actionIdRejected => 'rejected',
        self::actionIdResubmitted => 'resubmitted',
        self::actionIdMemberRemoved => 'member-removed',
        self::actionIdVisibilityChanged => 'visibility-changed',
        self::actionIdRankUp => 'rank-up',
    ];

    private const modelSlugMap = [
        CoreModel::routineModelId => 'routine',
        CoreModel::exerciseModelId => 'exercise',
        CoreModel::muscleModelId => 'muscle',
        CoreModel::gymEntityModelId => 'gym',
        CoreModel::ticketModelId => 'ticket',
        CoreModel::userModelId => 'user',
    ];

    private int $userId, $refModelId, $refEntityId, $action;

    public static function allocNew(int $userId, int $refModelId, int $refEntityId, int $action, array $data):self{
        $self = new self();
        $self->userId = $userId;
        $self->refModelId = $refModelId;
        $self->refEntityId = $refEntityId;
        $self->action = $action;
        $self->_setAllData(array_filter($data, fn($value) => $value !== null) ?: null);
        return $self;
    }

    static function addNew(DbConnector $dbConnect, int $userId, BaseEntity $entity, int $action, string $entityName, array $data = []): self{
        return self::addNewForRef($dbConnect, $userId, $entity->getModelId(), $entity->getEntityId(), $action, $entityName, $data);
    }

    static function addNewForRef(DbConnector $dbConnect, int $userId, int $refModelId, int $refEntityId, int $action, string $entityName, array $data = []): self{
        $self = self::allocNew($userId, $refModelId, $refEntityId, $action, ['name' => $entityName] + $data);
        $self->writeToDb($dbConnect);
        return $self;
    }

    static function existsWithData(DbConnector $dbConnect, int $userId, int $refModelId, int $action, string $dataKey, string $value): bool{
        return self::allocDbTable($dbConnect)
        ->where('user_id', $userId)
        ->where('model_id', $refModelId)
        ->where('action', $action)
        ->where('data->'.$dataKey, $value)
        ->exists();
    }

    function getModelId(): int{
        return CoreModel::entityLogModelId;
    }

    function getUserId(): int{
        return $this->userId;
    }

    function getRefModelId(): int{
        return $this->refModelId;
    }

    function getRefEntityId(): int{
        return $this->refEntityId;
    }

    function getAction(): int{
        return $this->action;
    }

    static function modelIdFromSlug(?string $slug): ?int{
        $modelId = array_search($slug, self::modelSlugMap, true);
        return $modelId === false ? null : $modelId;
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'action' => self::actionSlugMap[$this->action] ?? null,
            'model' => self::modelSlugMap[$this->refModelId] ?? null,
            'entityId' => $this->refEntityId,
            'data' => $this->_getAllData() ?? [],
            'createdAt' => BaseHelper::toDisplayDate($this->getCreatedTime()),
        ];
    }

    private function entityLog2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->user_id = $this->userId;
        $row->model_id = $this->refModelId;
        $row->entity_id = $this->refEntityId;
        $row->action = $this->action;
        $row->data = $this->_encodeDataForDb();
        return $row;
    }

    static function row2EntityLog(DbAdapter $dbAdapter, stdClass $row):self{
        $entityLog = new EntityLog();
        $entityLog->initFromDbRow($dbAdapter, $row);
        $entityLog->userId = (int)$row->user_id;
        $entityLog->refModelId = (int)$row->model_id;
        $entityLog->refEntityId = (int)$row->entity_id;
        $entityLog->action = (int)$row->action;
        $entityLog->_loadDataFromDb($row->data);
        return $entityLog;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    static function queryPageByUserId(DbConnector $dbConnect, int $userId, int $page, ?int $refModelId = null, int $pageSize = self::pageSize): array{
        $query = self::allocDbTable($dbConnect)
        ->where('user_id', $userId)
        ->where('status', '!=', EntityStatus::statusIdDeleted)
        ->orderByDesc('created_at')->orderByDesc('id')
        ->offset((max(1, $page) - 1) * $pageSize)
        ->limit($pageSize + 1)
        ->select();
        if($refModelId){
            $query->where('model_id', $refModelId);
        }
        $entityLogList = $dbConnect->fetchAll($query, [self::class, 'row2EntityLog']);
        return [array_slice($entityLogList, 0, $pageSize), count($entityLogList) > $pageSize];
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime($now);
        if(!$this->getCreatedTime()){
            $this->setCreatedTime($now);
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->entityLog2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if($this->getEntityId()){
            $writable->where(['id' => $this->getEntityId()])->update($writeArray);
        } else {
            $this->setEntityId($writable->insertGetId($writeArray));
        }
    }
}
