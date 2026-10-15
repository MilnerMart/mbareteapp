<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use Illuminate\Database\Query\Builder;

class UserAchievement {

    private const myTable = DbSchema::tableUserAchievements;

    static function unlockReached(DbConnector $dbConnect, User $user, array $progress): array{
        $newSlugList = array_values(array_diff(Achievement::queryReachedSlugList($progress), array_keys(self::queryUnlockedMap($dbConnect, $user->id))));
        if(!$newSlugList){
            return [];
        }
        $dbConnect->getEnvConecction()->transaction(function() use ($dbConnect, $user, $newSlugList){
            $now = $dbConnect->toDbDate(BaseHelper::utcNow());
            self::allocDbTable($dbConnect)->insertOrIgnore(array_map(fn(string $slug) => [
                'user_id' => $user->id, 'slug' => $slug, 'unlocked_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ], $newSlugList));
            foreach ($newSlugList as $slug) {
                UserNotification::addAchievement($dbConnect, $user->id, $slug, $user->gender);
                EntityLog::addNewForRef($dbConnect, $user->id, CoreModel::userModelId, $user->id, EntityLog::actionIdAchievement, $user->getFullName(), [
                    'achievement' => $slug,
                    'gender' => $user->gender,
                ]);
            }
        });
        return $newSlugList;
    }

    static function buildApiList(DbConnector $dbConnect, int $userId, array $progress): array{
        $unlockedMap = self::queryUnlockedMap($dbConnect, $userId);
        $model = [];
        foreach (Achievement::catalog as $slug => [$metric, $goal]) {
            $model[] = [
                'slug' => $slug,
                'metric' => $metric,
                'goal' => $goal,
                'current' => min((int)($progress[$metric] ?? 0), $goal),
                'isUnlocked' => isset($unlockedMap[$slug]),
                'unlockedAt' => isset($unlockedMap[$slug]) ? BaseHelper::toDisplayDate($dbConnect->fromDbDate($unlockedMap[$slug])) : null,
            ];
        }
        return $model;
    }

    private static function queryUnlockedMap(DbConnector $dbConnect, int $userId): array{
        return self::allocDbTable($dbConnect)->where('user_id', $userId)->pluck('unlocked_at', 'slug')->all();
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }
}
