<?php
namespace App\Helpers;

use DateTimeZone;
use DateTimeImmutable;

abstract class BaseHelper {

    private static ?DateTimeZone $utcTz = null;

    static public function UtcNow() {
        self::$utcTz ??= new DateTimeZone('UTC');
        return new DateTimeImmutable('now', self::$utcTz);
    }


}
