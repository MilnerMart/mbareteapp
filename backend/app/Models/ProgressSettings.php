<?php

namespace App\Models;

use App\Db\DbConnector;
use App\PublicException;

class ProgressSettings {

    private const settingKey = 'progress';

    const rankSlugList = ['novice', 'beginner', 'steady', 'intermediate', 'advanced', 'elite', 'beast'];

    const defaultScoring = ['pointsPerDay' => 10, 'pointsPerStreakDay' => 5, 'pointsPerMinute' => 1, 'kgPerPoint' => 100, 'maxPointsPerDay' => 60];

    const defaultRanks = [
        'novice' => ['minPoints' => 0],
        'beginner' => ['minPoints' => 150],
        'steady' => ['minPoints' => 400],
        'intermediate' => ['minPoints' => 900],
        'advanced' => ['minPoints' => 1800],
        'elite' => ['minPoints' => 3500],
        'beast' => ['minPoints' => 6000],
    ];

    private function __construct(private array $scoring, private array $ranks){
    }

    static function load(DbConnector $dbConnect): self{
        $stored = AppSetting::queryValue($dbConnect, self::settingKey) ?? [];
        $ranks = [];
        foreach (self::defaultRanks as $slug => $default) {
            $ranks[$slug] = array_map('intval', array_intersect_key(($stored['ranks'][$slug] ?? []) + $default, $default));
        }
        $scoring = array_map('intval', array_intersect_key(($stored['scoring'] ?? []) + self::defaultScoring, self::defaultScoring));
        return new self($scoring, $ranks);
    }

    static function saveOrFail(DbConnector $dbConnect, array $scoring, array $ranks): self{
        $self = new self(
            array_map('intval', array_intersect_key($scoring, self::defaultScoring)),
            array_map(fn(array $rank) => array_map('intval', array_intersect_key($rank, ['minPoints' => 0])),
                array_intersect_key($ranks, self::defaultRanks)) + self::defaultRanks
        );
        $self->ranks['novice'] = self::defaultRanks['novice'];
        $self->validateOrFail();
        AppSetting::saveValue($dbConnect, self::settingKey, $self->buildApiModel());
        return $self;
    }

    private function validateOrFail(): void{
        if($this->scoring['kgPerPoint'] < 1){
            throw PublicException::validationError('Los kilos por punto tienen que ser al menos 1');
        }
        if($this->scoring['maxPointsPerDay'] < 1){
            throw PublicException::validationError('El tope de puntos por dia tiene que ser al menos 1');
        }
        $previous = null;
        foreach (self::rankSlugList as $slug) {
            $rank = $this->ranks[$slug];
            if($previous && $rank['minPoints'] <= $previous['minPoints']){
                throw PublicException::validationError('Cada rango tiene que pedir mas puntos que el anterior');
            }
            $previous = $rank;
        }
    }

    function scoring(string $key): int{
        return $this->scoring[$key];
    }

    function buildRankModel(int $points): array{
        $rank = self::rankSlugList[0];
        $nextRank = null;
        foreach (self::rankSlugList as $slug) {
            if($points >= $this->ranks[$slug]['minPoints']){
                $rank = $slug;
            } else {
                $nextRank = $slug;
                break;
            }
        }
        $rankPoints = $this->ranks[$rank]['minPoints'];
        $nextPoints = $nextRank ? $this->ranks[$nextRank]['minPoints'] : null;
        return [
            'rank' => $rank,
            'nextRank' => $nextRank,
            'nextRankPoints' => $nextPoints,
            'progressPercent' => $nextPoints ? (int)floor(100 * ($points - $rankPoints) / ($nextPoints - $rankPoints)) : 100,
        ];
    }

    static function isHigherRank(string $rank, string $thanRank): bool{
        return array_search($rank, self::rankSlugList, true) > array_search($thanRank, self::rankSlugList, true);
    }

    function buildApiModel(): array{
        return ['scoring' => $this->scoring, 'ranks' => $this->ranks];
    }
}
