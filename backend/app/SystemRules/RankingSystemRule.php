<?php

namespace App\SystemRules;

use App\Core\CoreModel;
use App\Db\DbConnector;
use App\Models\Gym;
use App\Models\GymSeason;

class RankingSystemRule extends SystemRule {

    const ruleModelId = CoreModel::gymSeasonModelId;

    const slug = 'ranking';

    const defaultRunAt = '00:05';

    private array $gymList = [];

    protected function hydrate(DbConnector $dbConnect): void{
        $this->gymList = array_values(array_filter(array_map(
            fn(int $gymId) => Gym::queryByDbId($dbConnect, $gymId),
            GymSeason::queryGymIdListToAttend($dbConnect)
        )));
    }

    protected function execute(DbConnector $dbConnect): array{
        $summary = ['gyms' => count($this->gymList), 'closedSeasons' => 0, 'startNotices' => 0];
        foreach ($this->gymList as $gym) {
            $summary['closedSeasons'] += GymSeason::closeEnded($dbConnect, $gym);
            $summary['startNotices'] += GymSeason::notifyStartIfDue($dbConnect, $gym);
        }
        return $summary;
    }
}
