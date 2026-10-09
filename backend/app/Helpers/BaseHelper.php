<?php
namespace App\Helpers;

use App\PublicException;
use DateTimeZone;
use DateTimeImmutable;
use DateTimeInterface;

abstract class BaseHelper {

    // zona horaria en la que se muestran las fechas al usuario (en la base todo queda en UTC)
    const displayTimezone = 'America/Asuncion';

    private static ?DateTimeZone $utcTz = null, $displayTz = null;

    static public function UtcNow() {
        self::$utcTz ??= new DateTimeZone('UTC');
        return new DateTimeImmutable('now', self::$utcTz);
    }

    /**
     * Fecha para la API en hora de Paraguay, ej: 2026-10-08T07:00:00-03:00
     */
    static function toDisplayDate(DateTimeInterface|string|null $date): ?string {
        if( !$date ){
            return null;
        }
        self::$displayTz ??= new DateTimeZone(self::displayTimezone);
        $date = is_string($date) ? new DateTimeImmutable($date) : DateTimeImmutable::createFromInterface($date);
        return $date->setTimezone(self::$displayTz)->format(DATE_ATOM);
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
