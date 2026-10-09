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
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Solicitud que genera un usuario y que resuelve el admin (alta como entrenador)
 * o el dueño del gimnasio (ingreso con el codigo del gimnasio). El admin ve y resuelve todas.
 */
class Ticket extends BaseEntity {

    use HasDbData;

    private const myTable = DbSchema::tableTickets;

    const typeIdTrainerRequest = 10, typeIdGymJoin = 20, typeIdMuscleCreate = 30, typeIdExerciseCreate = 40;

    // solicitudes que resuelve solo el admin
    const adminTypeIdList = [self::typeIdTrainerRequest, self::typeIdMuscleCreate, self::typeIdExerciseCreate];

    const stateIdPending = 10, stateIdApproved = 20, stateIdRejected = 30;

    // veces que el solicitante puede volver a enviar una solicitud rechazada
    const maxResubmitCount = 1;

    private const typeSlugMap = [
        self::typeIdTrainerRequest => 'trainer-request',
        self::typeIdGymJoin => 'gym-join',
        self::typeIdMuscleCreate => 'muscle-create',
        self::typeIdExerciseCreate => 'exercise-create',
    ];

    private const stateSlugMap = [
        self::stateIdPending => 'pending',
        self::stateIdApproved => 'approved',
        self::stateIdRejected => 'rejected',
    ];

    private int $type, $requesterId, $state;

    private ?int $gymId = null, $resolvedBy = null;

    private ?DateTimeImmutable $resolvedAt = null;

    public static function allocNew(int $type, int $requesterId, ?int $gymId = null):self{
        $self = new self();
        $self->type = $type;
        $self->requesterId = $requesterId;
        $self->gymId = $gymId;
        $self->state = self::stateIdPending;
        return $self;
    }

    static function addNewTicket(DbConnector $dbConnect, int $type, int $requesterId, ?int $gymId = null): self{
        $self = self::allocNew($type, $requesterId, $gymId);
        $self->writeToDb($dbConnect);
        return $self;
    }

    /**
     * Solicitud de alta de un musculo o ejercicio propuesto; el id queda en data.
     */
    static function addNewCatalogTicket(DbConnector $dbConnect, int $type, int $requesterId, int $entityId): self{
        $self = self::allocNew($type, $requesterId);
        $self->_setDataItem('entityId', $entityId);
        $self->writeToDb($dbConnect);
        return $self;
    }

    function getModelId(): int{
        return CoreModel::ticketModelId;
    }

    function getType(): int{
        return $this->type;
    }

    function getRequesterId(): int{
        return $this->requesterId;
    }

    function getGymId(): ?int{
        return $this->gymId;
    }

    function getResolvedBy(): ?int{
        return $this->resolvedBy;
    }

    function getState(): int{
        return $this->state;
    }

    function isPending(): bool{
        return $this->state === self::stateIdPending;
    }

    function isTrainerRequest(): bool{
        return $this->type === self::typeIdTrainerRequest;
    }

    function isGymJoin(): bool{
        return $this->type === self::typeIdGymJoin;
    }

    function isCatalogRequest(): bool{
        return $this->type === self::typeIdMuscleCreate || $this->type === self::typeIdExerciseCreate;
    }

    function isAdminRequest(): bool{
        return in_array($this->type, self::adminTypeIdList, true);
    }

    /**
     * Musculo o ejercicio al que se refiere la solicitud.
     */
    function getEntityRefId(): ?int{
        $entityId = $this->_getDataItem('entityId');
        return $entityId !== null ? (int)$entityId : null;
    }

    /**
     * Numero visible para el usuario, ej: TK-000042
     */
    function getTicketNumber(): string{
        return self::formatTicketNumber($this->getEntityId());
    }

    static function formatTicketNumber(int $id): string{
        return 'TK-'.str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    }

    function setResolutionNote(?string $note): void{
        $this->_setDataItem('resolutionNote', $note ?: null);
    }

    function getResolutionNote(): ?string{
        return $this->_getDataItem('resolutionNote');
    }

    function getRequesterNote(): ?string{
        return $this->_getDataItem('requesterNote');
    }

    function getResubmitCount(): int{
        return (int)($this->_getDataItem('resubmitCount') ?? 0);
    }

    function canResubmit(): bool{
        return $this->state === self::stateIdRejected && $this->getResubmitCount() < self::maxResubmitCount;
    }

    /**
     * Movimientos de la solicitud: [['action'=>'rejected|approved|resubmitted', 'userId'=>, 'at'=>, 'note'=>], ...]
     */
    function getHistory(): array{
        return $this->_getArrayDataItem('history');
    }

    private function addHistoryItem(string $action, int $userId, ?string $note): void{
        $history = $this->getHistory();
        $history[] = [
            'action' => $action,
            'userId' => $userId,
            'at' => BaseHelper::utcNow()->format(DATE_ATOM),
            'note' => $note ?: null,
        ];
        $this->_setDataItem('history', $history);
    }

    function resolve(int $state, int $resolvedBy, ?string $note): void{
        if(!$this->isPending()){
            throw PublicException::validationError('La solicitud '.$this->getTicketNumber().' ya fue resuelta');
        }
        $this->state = $state;
        $this->resolvedBy = $resolvedBy;
        $this->resolvedAt = BaseHelper::utcNow();
        $this->setResolutionNote($note);
        $this->addHistoryItem(self::stateSlugMap[$state], $resolvedBy, $note);
    }

    /**
     * El solicitante reabre la solicitud rechazada con su nota; queda pendiente con el mismo numero.
     */
    function resubmit(string $note): void{
        if($this->state !== self::stateIdRejected){
            throw PublicException::validationError('Solo se pueden reenviar solicitudes rechazadas');
        }
        if(!$this->canResubmit()){
            throw PublicException::validationError('La solicitud '.$this->getTicketNumber().' ya fue reenviada '.self::maxResubmitCount.' vez');
        }
        $this->state = self::stateIdPending;
        $this->resolvedBy = null;
        $this->resolvedAt = null;
        $this->setResolutionNote(null);
        $this->_setDataItem('requesterNote', $note);
        $this->_setDataItem('resubmitCount', $this->getResubmitCount() + 1);
        $this->addHistoryItem('resubmitted', $this->requesterId, $note);
    }

    private function ticket2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->type = $this->type;
        $row->requester_id = $this->requesterId;
        $row->gym_id = $this->gymId;
        $row->state = $this->state;
        $row->resolved_by = $this->resolvedBy;
        $row->resolved_at = $dbAdapter->toDbDate($this->resolvedAt);
        $row->data = $this->_encodeDataForDb();
        return $row;
    }

    static function row2Ticket(DbAdapter $dbAdapter, stdClass $row):self{
        $ticket = new Ticket();
        $ticket->initFromDbRow($dbAdapter, $row);
        $ticket->type = (int)$row->type;
        $ticket->requesterId = (int)$row->requester_id;
        $ticket->gymId = $row->gym_id !== null ? (int)$row->gym_id : null;
        $ticket->state = (int)$row->state;
        $ticket->resolvedBy = $row->resolved_by !== null ? (int)$row->resolved_by : null;
        $ticket->resolvedAt = $dbAdapter->fromDbDate($row->resolved_at);
        $ticket->_loadDataFromDb($row->data);
        return $ticket;
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'number' => $this->getTicketNumber(),
            'type' => self::typeSlugMap[$this->type] ?? null,
            'state' => self::stateSlugMap[$this->state] ?? null,
            'requesterId' => $this->requesterId,
            'gymId' => $this->gymId,
            'entityId' => $this->getEntityRefId(),
            'resolvedBy' => $this->resolvedBy,
            'resolvedAt' => BaseHelper::toDisplayDate($this->resolvedAt),
            'resolutionNote' => $this->getResolutionNote(),
            'requesterNote' => $this->getRequesterNote(),
            'resubmitCount' => $this->getResubmitCount(),
            'canResubmit' => $this->canResubmit(),
            'createdAt' => BaseHelper::toDisplayDate($this->getCreatedTime()),
        ];
    }

    /**
     * Modelos con solicitante, gimnasio, quien resolvio e historial, sin hacer una consulta por ticket.
     */
    static function buildApiModelList(DbConnector $dbConnect, array $ticketList): array{
        $userIdList = [];
        $gymIdList = [];
        foreach ($ticketList as $ticket) {
            /**  @var Ticket $ticket */
            $userIdList[] = $ticket->requesterId;
            $userIdList[] = $ticket->resolvedBy;
            $userIdList = array_merge($userIdList, array_column($ticket->getHistory(), 'userId'));
            $gymIdList[] = $ticket->gymId;
        }
        $userMap = User::whereIn('id', array_filter(array_unique($userIdList)))->get()->keyBy('id');
        $gymNameMap = Gym::allocDbTable($dbConnect)
            ->whereIn('id', array_filter(array_unique($gymIdList)))->pluck('name', 'id')->all();
        $entityRefMap = self::buildEntityRefMap($dbConnect, $ticketList);

        $model = [];
        foreach ($ticketList as $ticket) {
            $ticketModel = $ticket->buildApiModel();
            $requester = $userMap[$ticket->requesterId] ?? null;
            $resolver = $ticket->resolvedBy ? ($userMap[$ticket->resolvedBy] ?? null) : null;
            $ticketModel['refs'] = [
                'requester' => $requester ? $requester->buildPublicApiModel() + ['email' => $requester->email] : null,
                'gym' => $ticket->gymId ? ['id' => $ticket->gymId, 'name' => $gymNameMap[$ticket->gymId] ?? null] : null,
                'resolver' => $resolver?->buildPublicApiModel(),
                'entity' => $entityRefMap[$ticket->type][$ticket->getEntityRefId()] ?? null,
            ];
            $ticketModel['history'] = array_map(fn(array $item) => ['at' => BaseHelper::toDisplayDate($item['at'])] + $item + [
                'userName' => isset($userMap[$item['userId']])
                    ? trim($userMap[$item['userId']]->name.' '.$userMap[$item['userId']]->last_name) : null,
            ], $ticket->getHistory());
            $model[] = $ticketModel;
        }
        return $model;
    }

    /**
     * Datos del musculo o ejercicio propuesto para que el admin lo verifique: [type => [entityId => ref]]
     */
    private static function buildEntityRefMap(DbConnector $dbConnect, array $ticketList): array{
        $idMap = [];
        foreach ($ticketList as $ticket) {
            /**  @var Ticket $ticket */
            if($ticket->isCatalogRequest() && $ticket->getEntityRefId()){
                $idMap[$ticket->type][] = $ticket->getEntityRefId();
            }
        }

        $refMap = [];
        foreach ($idMap[self::typeIdMuscleCreate] ?? [] as $muscleId) {
            $muscle = Muscle::queryByDbId($dbConnect, $muscleId);
            if($muscle){
                $resource = Resource::queryByOwnerAndModelId($dbConnect, CoreModel::muscleModelId, $muscleId);
                $refMap[self::typeIdMuscleCreate][$muscleId] = $muscle->buildApiModel() + ['image_url' => $resource?->getPublicUrl()];
            }
        }
        foreach ($idMap[self::typeIdExerciseCreate] ?? [] as $exerciseId) {
            $exercise = Exercise::queryByDbId($dbConnect, $exerciseId);
            if($exercise){
                $resource = Resource::queryByOwnerAndModelId($dbConnect, CoreModel::exerciseModelId, $exerciseId);
                $refMap[self::typeIdExerciseCreate][$exerciseId] = $exercise->buildApiModel() + [
                    'image_url' => $resource?->getPublicUrl(),
                    'muscleName' => Muscle::queryByDbId($dbConnect, $exercise->getMuscleId())?->getName(),
                ];
            }
        }
        return $refMap;
    }

    static function stateSlugFromId(int $stateId): ?string{
        return self::stateSlugMap[$stateId] ?? null;
    }

    static function stateIdFromSlug(?string $slug): ?int{
        $stateId = array_search($slug, self::stateSlugMap, true);
        return $stateId === false ? null : $stateId;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private static function allocSelectQuery(DbConnector $dbConnect, ?int $stateId): Builder{
        $query = self::allocDbTable($dbConnect, 't')
        ->where('t.status', '!=', EntityStatus::statusIdDeleted)
        ->select('t.*')
        ->orderByDesc('t.created_at')->orderByDesc('t.id');
        if($stateId){
            $query->where('t.state', $stateId);
        }
        return $query;
    }

    static function queryList(DbConnector $dbConnect, ?int $stateId = null):array{
        return $dbConnect->fetchAll(self::allocSelectQuery($dbConnect, $stateId), [self::class, 'row2Ticket']);
    }

    /**
     * Bandeja del usuario: los ingresos a sus gimnasios y, si es admin, las altas de entrenador.
     */
    static function queryInboxList(DbConnector $dbConnect, int $userId, bool $isAdmin, ?int $stateId = null):array{
        $query = self::allocSelectQuery($dbConnect, $stateId)
        ->leftJoin(DbSchema::tableGymEntity.' as g', 'g.id', '=', 't.gym_id')
        ->where(function(Builder $where) use ($userId, $isAdmin){
            $where->where(function(Builder $gymJoin) use ($userId){
                $gymJoin->where('t.type', self::typeIdGymJoin)->where('g.owner_id', $userId);
            });
            if($isAdmin){
                $where->orWhereIn('t.type', self::adminTypeIdList);
            }
        });
        return $dbConnect->fetchAll($query, [self::class, 'row2Ticket']);
    }

    static function queryListByRequesterId(DbConnector $dbConnect, int $requesterId):array{
        $query = self::allocSelectQuery($dbConnect, null)->where('t.requester_id', $requesterId);
        return $dbConnect->fetchAll($query, [self::class, 'row2Ticket']);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = self::allocSelectQuery($dbConnect, null)->where('t.id', $dbId);
        return $dbConnect->fetchSingle($query, [self::class ,'row2Ticket']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::notFoundError('No se encuentra la solicitud con id: '.$dbId);
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
        $writeArray = (array)$this->ticket2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }

    }

}
