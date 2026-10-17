<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use App\PublicException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use stdClass;

class GymSeason {

    private const myTable = DbSchema::tableGymSeasons;

    const maxSeasonDays = 365, podiumSize = 3, minSessionsForDiscipline = 4;

    const statusScheduled = 'scheduled', statusActive = 'active', statusEnded = 'ended';

    const awardConsistency = 'consistency', awardStreak = 'streak', awardStrength = 'strength', awardEndurance = 'endurance',
        awardDiscipline = 'discipline', awardComeback = 'comeback';

    const awardRuleMap = [
        self::awardConsistency => ['trainingDays', 1],
        self::awardStreak => ['bestStreak', 3],
        self::awardStrength => ['kilos', 1],
        self::awardEndurance => ['minutes', 1],
        self::awardDiscipline => ['completionPercent', 1],
        self::awardComeback => ['improvement', 1],
    ];

    static function queryLatest(DbConnector $dbConnect, int $gymId): ?array{
        $row = self::allocDbTable($dbConnect)->where('gym_id', $gymId)->orderByDesc('number')->first();
        return $row ? self::row2Season($row) : null;
    }

    static function status(array $season): string{
        $today = RoutineExerciseLog::today();
        return match (true) {
            $today < $season['startsOn'] => self::statusScheduled,
            $today > $season['endsOn'] => self::statusEnded,
            default => self::statusActive,
        };
    }

    static function startOrFail(DbConnector $dbConnect, Gym $gym, string $startsOn, string $endsOn): array{
        $latest = self::queryLatest($dbConnect, $gym->getEntityId());
        if($latest && self::status($latest) !== self::statusEnded){
            throw PublicException::validationError('Ya hay una temporada en curso o programada');
        }
        if($startsOn < RoutineExerciseLog::today()){
            throw PublicException::validationError('La temporada no puede empezar antes de hoy');
        }
        self::validateRangeOrFail($startsOn, $endsOn);

        $now = $dbConnect->toDbDate(BaseHelper::utcNow());
        self::allocDbTable($dbConnect)->insert([
            'gym_id' => $gym->getEntityId(),
            'number' => ($latest['number'] ?? 0) + 1,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return self::queryLatest($dbConnect, $gym->getEntityId());
    }

    static function updateDatesOrFail(DbConnector $dbConnect, Gym $gym, ?string $startsOn, string $endsOn): array{
        $latest = self::queryLatest($dbConnect, $gym->getEntityId());
        $status = $latest ? self::status($latest) : self::statusEnded;
        if($status === self::statusEnded){
            throw PublicException::validationError('No hay una temporada en curso para modificar');
        }
        if($status === self::statusActive){
            if($startsOn !== null && $startsOn !== $latest['startsOn']){
                throw PublicException::validationError('La temporada ya empezo: solo puedes cambiar la fecha de fin');
            }
            if($endsOn < RoutineExerciseLog::today()){
                throw PublicException::validationError('La fecha de fin no puede ser anterior a hoy');
            }
        } elseif($startsOn !== null && $startsOn < RoutineExerciseLog::today()){
            throw PublicException::validationError('La temporada no puede empezar antes de hoy');
        }
        $startsOn = $status === self::statusActive ? $latest['startsOn'] : ($startsOn ?? $latest['startsOn']);
        self::validateRangeOrFail($startsOn, $endsOn);

        self::allocDbTable($dbConnect)->where('gym_id', $gym->getEntityId())->where('number', $latest['number'])->update([
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'updated_at' => $dbConnect->toDbDate(BaseHelper::utcNow()),
        ]);
        return self::queryLatest($dbConnect, $gym->getEntityId());
    }

    private static function validateRangeOrFail(string $startsOn, string $endsOn): void{
        if($endsOn < $startsOn){
            throw PublicException::validationError('La fecha de fin tiene que ser igual o posterior a la de inicio');
        }
        if(CarbonImmutable::parse($startsOn)->diffInDays(CarbonImmutable::parse($endsOn)) + 1 > self::maxSeasonDays){
            throw PublicException::validationError('Una temporada puede durar hasta '.self::maxSeasonDays.' dias');
        }
    }

    static function queryParticipantIdList(DbConnector $dbConnect, Gym $gym): array{
        return array_values(array_filter(array_map('intval', GymUser::queryPublicUserIdListByGymId($dbConnect, $gym->getEntityId())),
            fn(int $userId) => !$gym->isOwnedBy($userId)));
    }

    static function buildStandingList(DbConnector $dbConnect, Gym $gym, string $startsOn, string $endsOn): array{
        return self::buildStandingData($dbConnect, $gym, $startsOn, $endsOn)[0];
    }

    private static function buildStandingData(DbConnector $dbConnect, Gym $gym, string $startsOn, string $endsOn): array{
        $participantIdList = self::queryParticipantIdList($dbConnect, $gym);
        if(!$participantIdList){
            return [[], []];
        }
        $ownerId = $gym->getOwnerId();
        $rowList = RoutineExerciseLog::allocDbTable($dbConnect, 'l')
            ->join(DbSchema::tableRoutines.' as r', 'r.id', '=', 'l.routine_id')
            ->join(DbSchema::tableUserRoutines.' as ur', fn(JoinClause $join) => $join->on('ur.routine_id', '=', 'l.routine_id')->on('ur.user_id', '=', 'l.user_id'))
            ->join(DbSchema::tableExercise.' as e', 'e.id', '=', 'l.exercise_id')
            ->whereIn('l.user_id', $participantIdList)
            ->whereBetween('l.done_on', [$startsOn, $endsOn])
            ->where(fn(Builder $where) => $where->where('r.owner_id', $ownerId)->orWhere('ur.assigned_by', $ownerId))
            ->get(['l.user_id', 'l.routine_id', 'l.exercise_id', 'l.done_on', 'l.sets', 'l.reps', 'l.duration_minutes', 'l.weight_kg', 'e.recommended_rest_time']);

        $exerciseCountMap = $rowList->isEmpty() ? [] : RoutineExercise::allocDbTable($dbConnect)
            ->whereIn('routine_id', $rowList->pluck('routine_id')->unique()->all())
            ->groupBy('routine_id')->selectRaw('routine_id, count(*) as total')->pluck('total', 'routine_id')->all();

        $dayMapByUser = [];
        $sessionMapByUser = [];
        foreach ($rowList as $row) {
            $userId = (int)$row->user_id;
            $day = substr((string)$row->done_on, 0, 10);
            $dayMapByUser[$userId][$day] ??= ['minutes' => 0, 'kilos' => 0.0];
            $dayMapByUser[$userId][$day]['minutes'] += UserProgress::estimateMinutes($row);
            if($row->weight_kg !== null && $row->sets && $row->reps){
                $dayMapByUser[$userId][$day]['kilos'] += (float)$row->weight_kg * (int)$row->sets * (int)$row->reps;
            }
            $sessionMapByUser[$userId][$day.'|'.$row->routine_id][(int)$row->exercise_id] = true;
        }

        $settings = ProgressSettings::load($dbConnect);
        $restDayMap = User::queryRestDayMapByIds($dbConnect, $participantIdList);
        $seasonDays = (int)CarbonImmutable::parse($startsOn)->diffInDays(CarbonImmutable::parse($endsOn)) + 1;
        $secondHalfOn = CarbonImmutable::parse($startsOn)->addDays(intdiv($seasonDays, 2))->toDateString();
        $standingList = [];
        $pointsMapByUser = [];
        foreach ($participantIdList as $userId) {
            $dayMap = $dayMapByUser[$userId] ?? [];
            ksort($dayMap);
            $restDayList = $restDayMap[$userId] ?? [];
            $pointsMap = UserProgress::buildDayPointsMap($settings, $dayMap, $restDayList);
            $pointsMapByUser[$userId] = $pointsMap;
            $firstHalfPoints = array_sum(array_filter($pointsMap, fn(string $day) => $day < $secondHalfOn, ARRAY_FILTER_USE_KEY));
            $sessionMap = $sessionMapByUser[$userId] ?? [];
            $completedSessions = count(array_filter($sessionMap, function(array $exerciseSet, string $sessionKey) use ($exerciseCountMap){
                $total = (int)($exerciseCountMap[(int)explode('|', $sessionKey)[1]] ?? 0);
                return $total > 0 && count($exerciseSet) >= $total;
            }, ARRAY_FILTER_USE_BOTH));
            $points = array_sum($pointsMap);
            $streakList = $dayMap ? UserProgress::countStreaks(array_keys($dayMap), $restDayList) : [0, 0, 0];
            $standingList[] = [
                'userId' => $userId,
                'points' => $points,
                'trainingDays' => count($dayMap),
                'bestStreak' => $streakList[1],
                'currentStreak' => $streakList[2],
                'todayPoints' => $pointsMap[RoutineExerciseLog::today()] ?? 0,
                'kilos' => (int)round(array_sum(array_column($dayMap, 'kilos'))),
                'minutes' => array_sum(array_column($dayMap, 'minutes')),
                'sessions' => count($sessionMap),
                'completedSessions' => $completedSessions,
                'completionPercent' => $sessionMap ? (int)floor(100 * $completedSessions / count($sessionMap)) : 0,
                'improvement' => $points - 2 * $firstHalfPoints,
            ];
        }

        usort($standingList, fn(array $a, array $b) => [$b['points'], $b['trainingDays'], $b['kilos'], $a['userId']]
            <=> [$a['points'], $a['trainingDays'], $a['kilos'], $b['userId']]);
        return [array_map(fn(array $standing, int $index) => $standing + ['position' => $index + 1], $standingList, array_keys($standingList)), $pointsMapByUser];
    }

    static function buildRace(array $standingList, array $pointsMapByUser, string $startsOn, string $endsOn): array{
        $dayList = [];
        for ($day = CarbonImmutable::parse($startsOn); $day->toDateString() <= $endsOn; $day = $day->addDay()) {
            $dayList[] = $day->toDateString();
        }
        $race = [];
        foreach (array_filter($standingList, fn(array $standing) => self::isOnPodium($standing)) as $standing) {
            $pointsMap = $pointsMapByUser[$standing['userId']] ?? [];
            $total = 0;
            $pointList = [];
            foreach ($dayList as $day) {
                $total += $pointsMap[$day] ?? 0;
                $pointList[] = $total;
            }
            $race[] = ['userId' => $standing['userId'], 'points' => $pointList];
        }
        return $race;
    }

    static function buildAwardList(array $standingList): array{
        $awardList = [];
        foreach (self::awardRuleMap as $slug => [$metric, $minValue]) {
            $winner = null;
            foreach ($standingList as $standing) {
                if($standing[$metric] < $minValue || ($slug === self::awardDiscipline && $standing['sessions'] < self::minSessionsForDiscipline)){
                    continue;
                }
                if(!$winner || $standing[$metric] > $winner[$metric]){
                    $winner = $standing;
                }
            }
            if($winner){
                $awardList[] = ['slug' => $slug, 'userId' => $winner['userId'], 'value' => $winner[$metric]];
            }
        }
        return $awardList;
    }

    static function isOnPodium(array $standing): bool{
        return $standing['position'] <= self::podiumSize && $standing['points'] > 0;
    }

    static function queryGymIdListToAttend(DbConnector $dbConnect): array{
        $today = RoutineExerciseLog::today();
        return self::allocDbTable($dbConnect)
            ->where(fn(Builder $where) => $where->where(fn(Builder $ended) => $ended->whereNull('results')->where('ends_on', '<', $today))
                ->orWhere(fn(Builder $started) => $started->whereNull('started_notified_at')->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)))
            ->distinct()->orderBy('gym_id')->pluck('gym_id')->map(fn($gymId) => (int)$gymId)->all();
    }

    static function notifyStartIfDue(DbConnector $dbConnect, Gym $gym): int{
        $today = RoutineExerciseLog::today();
        $row = self::allocDbTable($dbConnect)->where('gym_id', $gym->getEntityId())->whereNull('started_notified_at')
            ->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->orderByDesc('number')->first();
        if(!$row){
            return 0;
        }
        return $dbConnect->getEnvConecction()->transaction(function() use ($dbConnect, $gym, $row){
            $isMarked = self::allocDbTable($dbConnect)->where('id', $row->id)->whereNull('started_notified_at')
                ->update(['started_notified_at' => $dbConnect->toDbDate(BaseHelper::utcNow())]) > 0;
            if(!$isMarked || !$gym->isRankingPublic()){
                return 0;
            }
            $participantIdList = self::queryParticipantIdList($dbConnect, $gym);
            UserNotification::addSeasonStartedList($dbConnect, $gym, self::row2Season($row), $participantIdList);
            return count($participantIdList);
        });
    }

    static function closeEnded(DbConnector $dbConnect, Gym $gym): int{
        $rowList = self::allocDbTable($dbConnect)->where('gym_id', $gym->getEntityId())->whereNull('results')
            ->where('ends_on', '<', RoutineExerciseLog::today())->orderBy('number')->get();
        $closedCount = 0;
        foreach ($rowList as $row) {
            $season = self::row2Season($row);
            [$standingList, $pointsMapByUser] = self::buildStandingData($dbConnect, $gym, $season['startsOn'], $season['endsOn']);
            $awardList = self::buildAwardList($standingList);
            $race = self::buildRace($standingList, $pointsMapByUser, $season['startsOn'], $season['endsOn']);
            $closedCount += $dbConnect->getEnvConecction()->transaction(function() use ($dbConnect, $gym, $row, $season, $standingList, $awardList, $race){
                $isClosed = self::allocDbTable($dbConnect)->where('id', $row->id)->whereNull('results')->update([
                    'results' => BaseHelper::jsonEncode(['standings' => $standingList, 'awards' => $awardList, 'race' => $race]),
                    'updated_at' => $dbConnect->toDbDate(BaseHelper::utcNow()),
                ]) > 0;
                if(!$isClosed){
                    return 0;
                }
                self::addResultLogList($dbConnect, $gym, $season['number'], $standingList, $awardList);
                if($gym->isRankingPublic()){
                    UserNotification::addSeasonClosedList($dbConnect, $gym, $season['number'], $standingList);
                }
                return 1;
            });
        }
        return $closedCount;
    }

    private static function addResultLogList(DbConnector $dbConnect, Gym $gym, int $number, array $standingList, array $awardList): void{
        $ownerId = $gym->getOwnerId();
        EntityLog::addNewForRef($dbConnect, $ownerId, CoreModel::gymEntityModelId, $gym->getEntityId(), EntityLog::actionIdSeasonResult, $gym->getName(), [
            'season' => $number,
            'isOwner' => true,
            'participantCount' => count($standingList),
        ]);
        if(!$gym->isRankingPublic()){
            return;
        }
        foreach ($standingList as $standing) {
            if($standing['points'] <= 0){
                continue;
            }
            EntityLog::addNewForRef($dbConnect, $standing['userId'], CoreModel::gymEntityModelId, $gym->getEntityId(), EntityLog::actionIdSeasonResult, $gym->getName(), [
                'season' => $number,
                'position' => $standing['position'],
                'points' => $standing['points'],
                'awards' => array_column(array_filter($awardList, fn(array $award) => $award['userId'] === $standing['userId']), 'slug') ?: null,
            ]);
        }
    }

    static function queryClosedNumberList(DbConnector $dbConnect, int $gymId): array{
        return self::allocDbTable($dbConnect)->where('gym_id', $gymId)->whereNotNull('results')
            ->orderByDesc('number')->pluck('number')->map(fn($number) => (int)$number)->all();
    }

    static function queryClosed(DbConnector $dbConnect, int $gymId, int $number): ?array{
        $row = self::allocDbTable($dbConnect)->where('gym_id', $gymId)->where('number', $number)->whereNotNull('results')->first();
        return $row ? self::row2Season($row) : null;
    }

    private static function row2Season(stdClass $row): array{
        $results = BaseHelper::fromDbJson($row->results);
        return [
            'number' => (int)$row->number,
            'startsOn' => substr((string)$row->starts_on, 0, 10),
            'endsOn' => substr((string)$row->ends_on, 0, 10),
            'isClosed' => $results !== null,
            'standings' => $results['standings'] ?? [],
            'awards' => $results['awards'] ?? [],
            'race' => $results['race'] ?? [],
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }
}
