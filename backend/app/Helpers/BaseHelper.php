<?php
namespace App\Helpers;

use App\PublicException;
use DateTimeZone;
use DateTimeImmutable;

abstract class BaseHelper {

    private static ?DateTimeZone $utcTz = null;

    static public function UtcNow() {
        self::$utcTz ??= new DateTimeZone('UTC');
        return new DateTimeImmutable('now', self::$utcTz);
    }

    static function fromDbJson(?string $json):?array {
        return $json ? self::arrayOrNull(self::jsonDecode($json)) : null;
    }

    static function toDbJson($data): ?string{
        return $data ? self::jsonEncode($data) : null;
    }

    static function arrayOrNull($a): ?array {
        return \is_array($a) ? $a : null;
    }

    static function forceArray($a): array{
        return \is_array($a) ? $a : [];
    }

    static function jsonEncode($value)
    {
        return json_encode($value, 0);
    }

    static function jsonDecode(string $json){
        return json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
    }

    static function storableOrFail(mixed $value) {
        if( $value !== null && !is_scalar($value) && !is_array($value) ) {
            throw PublicException::internalError('El valor almacenado debe ser un array o scalar', 'internal.value_not_scalar');
        }
        return $value;
    }
}
