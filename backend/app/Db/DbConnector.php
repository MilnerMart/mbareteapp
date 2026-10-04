<?php

namespace App\Db;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use \Illuminate\Database\Query\Builder;

class DbConnector implements DbAdapter{

    private DbAdapter $adapter;
    
    private const EnvDb= 'muscleDb';

    public function __construct(){
        $this->adapter = new MysqlAdapter();
    }

    function getEnvConecction(){
        return $this->_getConecction(self::EnvDb);
    }

    private function _getConecction(string $name): ConnectionInterface{
        return DB::connection($name);
    }

    function fetchSingle(Builder $query, callable $rowConverter):?object {
        return ($row=$query->first()) ? call_user_func( $rowConverter, $this, $row) : null;
    }

    function fetchAll(Builder $query, callable $rowConverter):array {
        $list = [];
        foreach( $query->get() as $row){
            $list[] = call_user_func( $rowConverter, $this, $row);
        }
        return $list;
    }

    function getDbDateFormat():string {
        return $this->adapter->getDbDateFormat();
    }
    function toDbDate(?DateTimeInterface $date):?string {
        return $this->adapter->toDbDate( $date );
    }
    function fromDbDate(?string $value):?DateTimeImmutable {
        return $this->adapter->fromDbDate( $value );
    }
    function fromDbDateWithTZ(?string $value, DateTimeZone $tz): ?DateTimeImmutable {
        return $this->adapter->fromDbDateWithTZ( $value, $tz );
    }
}