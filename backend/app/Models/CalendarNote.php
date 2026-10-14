<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use App\PublicException;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use stdClass;

class CalendarNote extends BaseEntity {

    private const myTable = DbSchema::tableCalendarNotes;

    // dias de anticipacion con que se puede avisar: 2 dias antes, el dia anterior y el mismo dia
    const remindDayList = [2, 1, 0];

    private int $userId;

    private string $noteOn, $title;

    private ?string $body = null;

    private array $remindDays = [], $notifiedDays = [];

    public static function allocNew(int $userId, string $noteOn, string $title, ?string $body, array $remindDays):self{
        $self = new self();
        $self->userId = $userId;
        $self->edit($noteOn, $title, $body, $remindDays);
        return $self;
    }

    function getModelId(): int{
        return CoreModel::calendarNoteModelId;
    }

    function getUserId(): int{
        return $this->userId;
    }

    function getNoteOn(): string{
        return $this->noteOn;
    }

    function getTitle(): string{
        return $this->title;
    }

    function getRemindDays(): array{
        return $this->remindDays;
    }

    // si cambia el dia o los avisos se vuelve a avisar (devuelve true); los que ya quedaron en el pasado no se mandan
    function edit(string $noteOn, string $title, ?string $body, array $remindDays): bool{
        $remindDays = array_values(array_intersect(self::remindDayList, array_map('intval', $remindDays)));
        $isRescheduled = !isset($this->noteOn) || $this->noteOn !== $noteOn || $this->remindDays !== $remindDays;
        if($isRescheduled){
            $this->notifiedDays = array_values(array_filter($remindDays, fn(int $daysBefore) => self::remindDateFor($noteOn, $daysBefore) < self::today()));
        }
        $this->noteOn = $noteOn;
        $this->title = $title;
        $this->body = $body;
        $this->remindDays = $remindDays;
        return $isRescheduled;
    }

    function isInPast(): bool{
        return $this->noteOn < self::today();
    }

    function daysLeft(): int{
        return (int)(new DateTimeImmutable(self::today()))->diff(new DateTimeImmutable($this->noteOn))->format('%r%a');
    }

    private static function remindDateFor(string $noteOn, int $daysBefore): string{
        return (new DateTimeImmutable($noteOn))->modify('-'.$daysBefore.' days')->format('Y-m-d');
    }

    private function queryDueDays(): array{
        return array_values(array_filter(array_diff($this->remindDays, $this->notifiedDays),
            fn(int $daysBefore) => self::remindDateFor($this->noteOn, $daysBefore) <= self::today()));
    }

    static function today(): string{
        return (new DateTimeImmutable('now', new DateTimeZone(BaseHelper::displayTimezone)))->format('Y-m-d');
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'noteOn' => $this->noteOn,
            'title' => $this->title,
            'body' => $this->body,
            'remindDays' => $this->remindDays,
            'notifiedDays' => $this->notifiedDays,
        ];
    }

    // sin tareas programadas: se llama al consultar la campanita. Si se juntaron varios avisos (no entro en dias) va uno solo
    static function notifyDueReminders(DbConnector $dbConnect, int $userId): void{
        $lastDay = (new DateTimeImmutable(self::today()))->modify('+'.max(self::remindDayList).' days')->format('Y-m-d');
        $query = self::allocSelectQuery($dbConnect, $userId)
        ->whereNotNull('remind_days')
        ->whereBetween('note_on', [self::today(), $lastDay]);
        $dueList = array_filter($dbConnect->fetchAll($query, [self::class, 'row2CalendarNote']),
            fn(CalendarNote $note) => (bool)$note->queryDueDays());
        if(!$dueList){
            return;
        }
        $dbConnect->getEnvConecction()->transaction(function() use ($dbConnect, $dueList){
            foreach ($dueList as $note) {
                /**  @var CalendarNote $note */
                UserNotification::addReminder($dbConnect, $note);
                $note->notifiedDays = array_values(array_unique(array_merge($note->notifiedDays, $note->queryDueDays())));
                $note->writeToDb($dbConnect);
            }
        });
    }

    private function calendarNote2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->user_id = $this->userId;
        $row->note_on = $this->noteOn;
        $row->title = $this->title;
        $row->body = $this->body;
        $row->remind_days = BaseHelper::toDbJson($this->remindDays);
        $row->notified_days = BaseHelper::toDbJson($this->notifiedDays);
        return $row;
    }

    static function row2CalendarNote(DbAdapter $dbAdapter, stdClass $row):self{
        $note = new CalendarNote();
        $note->initFromDbRow($dbAdapter, $row);
        $note->userId = (int)$row->user_id;
        $note->noteOn = substr((string)$row->note_on, 0, 10);
        $note->title = $row->title;
        $note->body = $row->body;
        $note->remindDays = array_map('intval', BaseHelper::fromDbJson($row->remind_days) ?? []);
        $note->notifiedDays = array_map('intval', BaseHelper::fromDbJson($row->notified_days) ?? []);
        return $note;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private static function allocSelectQuery(DbConnector $dbConnect, int $userId): Builder{
        return self::allocDbTable($dbConnect)
        ->where('user_id', $userId)
        ->where('status', '!=', EntityStatus::statusIdDeleted)
        ->select();
    }

    static function queryListByUserIdAndRange(DbConnector $dbConnect, int $userId, string $fromDay, string $toDay): array{
        $query = self::allocSelectQuery($dbConnect, $userId)
        ->whereBetween('note_on', [$fromDay, $toDay])
        ->orderBy('note_on')->orderBy('id');
        return $dbConnect->fetchAll($query, [self::class, 'row2CalendarNote']);
    }

    static function queryOwnedOrFail(DbConnector $dbConnect, int $userId, int $dbId): self{
        $note = $dbConnect->fetchSingle(self::allocSelectQuery($dbConnect, $userId)->where('id', $dbId), [self::class, 'row2CalendarNote']);
        if(!$note){
            throw PublicException::notFoundError('No se encuentra la anotacion con id: '.$dbId);
        }
        return $note;
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime($now);
        if(!$this->getCreatedTime()){
            $this->setCreatedTime($now);
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->calendarNote2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if($this->getEntityId()){
            $writable->where(['id' => $this->getEntityId()])->update($writeArray);
        } else {
            $this->setEntityId($writable->insertGetId($writeArray));
        }
    }
}
