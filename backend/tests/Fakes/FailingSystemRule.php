<?php

namespace Tests\Fakes;

use App\Db\DbConnector;
use App\SystemRules\SystemRule;
use RuntimeException;

class FailingSystemRule extends SystemRule {

    const ruleModelId = 999;

    const slug = 'failing';

    const defaultRunAt = '03:00';

    protected function hydrate(DbConnector $dbConnect): void{
    }

    protected function execute(DbConnector $dbConnect): array{
        throw new RuntimeException('Se corto la conexion');
    }
}
