<?php

namespace App\Models;

use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use Illuminate\Database\Query\Builder;

class AppSetting {

    private const myTable = DbSchema::tableAppSettings;

    static function queryValue(DbConnector $dbConnect, string $key): ?array{
        return BaseHelper::fromDbJson(self::allocDbTable($dbConnect)->where('key', $key)->value('value'));
    }

    static function saveValue(DbConnector $dbConnect, string $key, array $value): void{
        $now = $dbConnect->toDbDate(BaseHelper::utcNow());
        self::allocDbTable($dbConnect)->upsert(
            [['key' => $key, 'value' => BaseHelper::jsonEncode($value), 'created_at' => $now, 'updated_at' => $now]],
            ['key'], ['value', 'updated_at']
        );
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }
}
