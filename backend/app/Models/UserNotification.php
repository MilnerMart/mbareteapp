<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\HasDbData;
use App\Helpers\BaseHelper;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use stdClass;

class UserNotification extends BaseEntity {

    use HasDbData;

    private const myTable = DbSchema::tableUserNotifications;

    const typeIdTicketReceived = 10, typeIdTicketResubmitted = 20, typeIdTicketResolved = 30, typeIdRoutineAssigned = 40, typeIdReminder = 50;

    const listLimit = 20;

    private const typeSlugMap = [
        self::typeIdTicketReceived => 'ticket-received',
        self::typeIdTicketResubmitted => 'ticket-resubmitted',
        self::typeIdTicketResolved => 'ticket-resolved',
        self::typeIdRoutineAssigned => 'routine-assigned',
        self::typeIdReminder => 'reminder',
    ];

    private int $userId, $type;

    private ?int $refId = null;

    private ?DateTimeImmutable $readAt = null;

    public static function allocNew(int $userId, int $type, ?int $refId, array $data):self{
        $self = new self();
        $self->userId = $userId;
        $self->type = $type;
        $self->refId = $refId;
        $self->_setAllData($data ?: null);
        return $self;
    }

    function getModelId(): int{
        return CoreModel::notificationModelId;
    }

    function getUserId(): int{
        return $this->userId;
    }

    function getType(): int{
        return $this->type;
    }

    function getRefId(): ?int{
        return $this->refId;
    }

    function isRead(): bool{
        return $this->readAt !== null;
    }

    static function addTicketReceivedList(DbConnector $dbConnect, Ticket $ticket, bool $isResubmit = false): void{
        $gym = $ticket->getGymId() ? Gym::queryByDbId($dbConnect, $ticket->getGymId()) : null;
        $recipientIdList = $ticket->isAdminRequest()
            ? User::queryIdListWithPermit($dbConnect, Permit::seeAllPermitSlug)
            : ($gym ? [$gym->getOwnerId()] : []);
        $recipientIdList = array_diff($recipientIdList, [$ticket->getRequesterId()]);

        self::addNewList($dbConnect, $recipientIdList, $isResubmit ? self::typeIdTicketResubmitted : self::typeIdTicketReceived, $ticket->getEntityId(), [
            'ticketNumber' => $ticket->getTicketNumber(),
            'ticketType' => $ticket->getTypeSlug(),
            'requesterName' => User::queryFullNameById($dbConnect, $ticket->getRequesterId()),
            'gymName' => $gym?->getName(),
        ]);
    }

    static function addTicketResolved(DbConnector $dbConnect, Ticket $ticket, User $resolver): void{
        if($ticket->getRequesterId() === $resolver->id){
            return;
        }
        $gym = $ticket->getGymId() ? Gym::queryByDbId($dbConnect, $ticket->getGymId()) : null;
        self::addNewList($dbConnect, [$ticket->getRequesterId()], self::typeIdTicketResolved, $ticket->getEntityId(), [
            'ticketNumber' => $ticket->getTicketNumber(),
            'ticketType' => $ticket->getTypeSlug(),
            'ticketState' => Ticket::stateSlugFromId($ticket->getState()),
            'resolverName' => $resolver->getFullName(),
            'gymName' => $gym?->getName(),
        ]);
    }

    static function addRoutineAssigned(DbConnector $dbConnect, int $userId, Routine $routine, User $assigner): void{
        if($userId === $assigner->id){
            return;
        }
        self::addNewList($dbConnect, [$userId], self::typeIdRoutineAssigned, $routine->getEntityId(), [
            'routineName' => $routine->getName(),
            'assignerName' => $assigner->getFullName(),
        ]);
    }

    static function addReminder(DbConnector $dbConnect, CalendarNote $note): void{
        self::addNewList($dbConnect, [$note->getUserId()], self::typeIdReminder, $note->getEntityId(), [
            'title' => $note->getTitle(),
            'noteOn' => $note->getNoteOn(),
            'daysLeft' => $note->daysLeft(),
        ]);
    }

    private static function addNewList(DbConnector $dbConnect, array $userIdList, int $type, ?int $refId, array $data): void{
        $rowList = [];
        foreach (array_unique($userIdList) as $userId) {
            $self = self::allocNew((int)$userId, $type, $refId, array_filter($data, fn($value) => $value !== null));
            $now = BaseHelper::utcNow();
            $self->setCreatedTime($now);
            $self->setUpdatedTime($now);
            $self->isReadyForDbOrFail();
            $rowList[] = (array)$self->notification2Row($dbConnect);
        }
        if($rowList){
            self::allocDbTable($dbConnect)->insert($rowList);
        }
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'type' => self::typeSlugMap[$this->type] ?? null,
            'refId' => $this->refId,
            'isRead' => $this->isRead(),
            'data' => $this->_getAllData() ?? [],
            'createdAt' => BaseHelper::toDisplayDate($this->getCreatedTime()),
        ];
    }

    private function notification2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->user_id = $this->userId;
        $row->type = $this->type;
        $row->ref_id = $this->refId;
        $row->read_at = $dbAdapter->toDbDate($this->readAt);
        $row->data = $this->_encodeDataForDb();
        return $row;
    }

    static function row2Notification(DbAdapter $dbAdapter, stdClass $row):self{
        $notification = new UserNotification();
        $notification->initFromDbRow($dbAdapter, $row);
        $notification->userId = (int)$row->user_id;
        $notification->type = (int)$row->type;
        $notification->refId = $row->ref_id !== null ? (int)$row->ref_id : null;
        $notification->readAt = $dbAdapter->fromDbDate($row->read_at);
        $notification->_loadDataFromDb($row->data);
        return $notification;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private static function allocUserQuery(DbConnector $dbConnect, int $userId): Builder{
        return self::allocDbTable($dbConnect)
        ->where('user_id', $userId)
        ->where('status', '!=', EntityStatus::statusIdDeleted);
    }

    static function queryListByUserId(DbConnector $dbConnect, int $userId, int $limit = self::listLimit): array{
        $query = self::allocUserQuery($dbConnect, $userId)
        ->orderByDesc('created_at')->orderByDesc('id')
        ->limit($limit)
        ->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Notification']);
    }

    static function countUnreadByUserId(DbConnector $dbConnect, int $userId): int{
        return self::allocUserQuery($dbConnect, $userId)->whereNull('read_at')->count();
    }

    static function markReadByUserId(DbConnector $dbConnect, int $userId, ?array $idList = null): int{
        $query = self::allocUserQuery($dbConnect, $userId)->whereNull('read_at');
        if($idList !== null){
            $query->whereIn('id', $idList);
        }
        $now = $dbConnect->toDbDate(BaseHelper::utcNow());
        return $query->update(['read_at' => $now, 'updated_at' => $now]);
    }
}
