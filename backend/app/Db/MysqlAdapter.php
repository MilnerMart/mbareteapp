<?php

namespace App\Db;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class MysqlAdapter implements DbAdapter {

    private const dbDateFormat = 'Y-m-d H:i:s';

    private DateTimeZone $utcTz;

    public function __construct() {
        $this->utcTz = new DateTimeZone('UTC');
    }

    function getDbDateFormat():string {
        return self::dbDateFormat;
    }

    function toDbDate(?DateTimeInterface $date):?string {
        if( !$date ){
            return null;
        }
        return DateTimeImmutable::createFromInterface($date)->setTimezone($this->utcTz)->format(self::dbDateFormat);
    }

    function fromDbDate(?string $value):?DateTimeImmutable {
        return $this->fromDbDateWithTZ($value, $this->utcTz);
    }

    function fromDbDateWithTZ(?string $value, DateTimeZone $tz):?DateTimeImmutable {
        if( $value === null || $value === '' ){
            return null;
        }
        $date = DateTimeImmutable::createFromFormat(self::dbDateFormat, $value, $this->utcTz);
        return $date ? $date->setTimezone($tz) : null;
    }
}
