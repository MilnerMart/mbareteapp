<?php

namespace App\Models;

class Achievement {

    const metricBestStreak = 'bestStreak', metricTrainingDays = 'trainingDays', metricKilos = 'kilos', metricMinutes = 'minutes';

    const catalog = [
        'streak-3' => [self::metricBestStreak, 3],
        'streak-7' => [self::metricBestStreak, 7],
        'streak-14' => [self::metricBestStreak, 14],
        'streak-30' => [self::metricBestStreak, 30],
        'streak-60' => [self::metricBestStreak, 60],
        'streak-100' => [self::metricBestStreak, 100],
        'days-1' => [self::metricTrainingDays, 1],
        'days-10' => [self::metricTrainingDays, 10],
        'days-25' => [self::metricTrainingDays, 25],
        'days-50' => [self::metricTrainingDays, 50],
        'days-100' => [self::metricTrainingDays, 100],
        'days-200' => [self::metricTrainingDays, 200],
        'kilos-500' => [self::metricKilos, 500],
        'kilos-1500' => [self::metricKilos, 1500],
        'kilos-3000' => [self::metricKilos, 3000],
        'kilos-15000' => [self::metricKilos, 15000],
        'kilos-40000' => [self::metricKilos, 40000],
        'kilos-150000' => [self::metricKilos, 150000],
        'minutes-60' => [self::metricMinutes, 60],
        'minutes-600' => [self::metricMinutes, 600],
        'minutes-3000' => [self::metricMinutes, 3000],
        'minutes-6000' => [self::metricMinutes, 6000],
    ];

    static function queryReachedSlugList(array $progress): array{
        return array_keys(array_filter(self::catalog, fn(array $rule) => (int)($progress[$rule[0]] ?? 0) >= $rule[1]));
    }
}
