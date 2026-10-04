<?php
namespace App\Models;

use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\PublicException;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Query\Builder;
use DateTimeImmutable;

abstract class BaseEntity extends Model{


    private int $id, $modelI, $status;

    private DateTimeImmutable $createdAt, $updatedAt;

    abstract static function allocDbTable(DbConnector $dbConnect, ?string $alias = null):Builder;

    abstract function getModelId():int;

    public function __construct(?int $id = 0, ?int $status = EntityStatus::statusIdActive){
        $this->id = $id;
        $this->status = $status;
    }

    public function getEntityId(): int{
        return $this->id;
    }

    public function setEntityId(int $id): void{
        $this->id = $id;
    }

    public function getStatusId(): int{
        return $this->status;
    }

    public function setStatusId(int $id): void{
        $this->status = $id;
    }

    public function setCreatedTime(DateTimeImmutable $createdAt):void{
        $this->createdAt = $createdAt;
    }

    public function getCreatedTime(): ?DateTimeImmutable{
        return $this->createdAt ?? null;
    }

    public function setUpdatedTime(DateTimeImmutable $updatedAt):void{
        $this->updatedAt = $updatedAt;
    }
    public function getUpdatedTime(): DateTimeImmutable{
        return $this->updatedAt;
    }

    protected function initFromDbRow( DbAdapter $dbAdapter, \stdClass $row ): void {
        $this->id = (int)$row->id;
        $this->status = (int)$row->status ;
        $this->createdAt = $dbAdapter->fromDbDate( $row->created_at );
        $this->updatedAt = $dbAdapter->fromDbDate( $row->updated_at );
    }

    protected function allocDbRow( DbAdapter $dbAdapter ):\stdClass {
        $row = new \stdClass();
        $row->status = $this->status;
        $row->updated_at = $dbAdapter->toDbDate($this->updatedAt);
        if( !$this->id ){
            $row->created_at = $dbAdapter->toDbDate($this->createdAt);
        }
        return $row;
    }

    protected function isReadyForDbOrFail():void {

        if( !$this->status ){
            throw PublicException::validationError('Estado de registro inválido');
        }
        if( !(isset($this->updatedAt) && isset($this->createdAt)) ){
            throw PublicException::validationError('Fecha de registro inválido');
        }
    }
}