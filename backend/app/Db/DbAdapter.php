<?php

namespace App\Db;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

interface DbAdapter {
    function getDbDateFormat():string;
    function toDbDate(?DateTimeInterface $date):?string;
    function fromDbDate(?string $value):?DateTimeImmutable;
    function fromDbDateWithTZ(?string $value, DateTimeZone $tz):?DateTimeImmutable;

}
