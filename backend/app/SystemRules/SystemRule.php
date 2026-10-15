<?php

namespace App\SystemRules;

use App\Db\DbConnector;
use App\Models\RoutineExerciseLog;
use App\Models\SystemRuleRun;
use Carbon\CarbonImmutable;
use Throwable;

abstract class SystemRule {

    const ruleModelId = 0;

    const slug = '';

    const frequencyDaily = 'daily', frequencyHourly = 'hourly';

    const frequencyList = [self::frequencyDaily, self::frequencyHourly];

    const timezone = RoutineExerciseLog::dayTimezone;

    const defaultEnabled = true, defaultFrequency = self::frequencyDaily, defaultRunAt = '00:05', defaultRetryMinutes = 15;

    const maxRetryMinutes = 1440;

    abstract protected function hydrate(DbConnector $dbConnect): void;

    abstract protected function execute(DbConnector $dbConnect): array;

    final function runAndRecord(DbConnector $dbConnect, bool $isManual = false): SystemRuleRun{
        if(SystemRuleRun::isRunning($dbConnect, static::ruleModelId)){
            return SystemRuleRun::addSkipped($dbConnect, static::ruleModelId, $isManual);
        }
        $run = SystemRuleRun::addPending($dbConnect, static::ruleModelId, $isManual);
        $run->markRunning($dbConnect);
        try {
            $this->hydrate($dbConnect);
            $run->markCompleted($dbConnect, $this->execute($dbConnect));
        } catch (Throwable $exception) {
            report($exception);
            $run->markFailed($dbConnect, $exception->getMessage());
        }
        return $run;
    }

    static function buildNextRunAt(string $frequency, string $runAt, CarbonImmutable $after): CarbonImmutable{
        $local = $after->setTimezone(self::timezone);
        [$hour, $minute] = array_map('intval', explode(':', $runAt));
        if($frequency === self::frequencyHourly){
            $next = $local->setTime($local->hour, $minute);
            return ($next->lessThanOrEqualTo($local) ? $next->addHour() : $next)->utc();
        }
        $next = $local->setTime($hour, $minute);
        return ($next->lessThanOrEqualTo($local) ? $next->addDay() : $next)->utc();
    }
}
