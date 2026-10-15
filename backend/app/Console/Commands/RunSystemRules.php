<?php

namespace App\Console\Commands;

use App\Db\DbConnector;
use App\Helpers\BaseHelper;
use App\Models\SystemRuleConfig;
use App\Models\SystemRuleRun;
use Illuminate\Console\Command;

class RunSystemRules extends Command
{
    protected $signature = 'rules:run {rule? : Slug de una regla para correrla ya, aunque no le toque}';

    protected $description = 'Corre las reglas del sistema a las que les toca segun su horario (el scheduler lo llama cada minuto)';

    public function handle(DbConnector $dbConnector): int
    {
        SystemRuleConfig::syncCatalog($dbConnector);
        SystemRuleRun::failStale($dbConnector);

        if($this->argument('rule') !== null){
            $config = collect(SystemRuleConfig::queryList($dbConnector))
                ->first(fn(SystemRuleConfig $config) => $config->getSlug() === $this->argument('rule'));
            if(!$config){
                $this->error('No existe la regla '.$this->argument('rule'));
                return self::FAILURE;
            }
            return $this->runRule($dbConnector, $config, true);
        }

        $exitCode = self::SUCCESS;
        foreach (SystemRuleConfig::queryDueList($dbConnector, BaseHelper::utcNow()) as $config) {
            if($config->claim($dbConnector, BaseHelper::utcNow())){
                $exitCode = max($exitCode, $this->runRule($dbConnector, $config, false));
            }
        }
        return $exitCode;
    }

    private function runRule(DbConnector $dbConnector, SystemRuleConfig $config, bool $isManual): int
    {
        $run = $config->allocRule()->runAndRecord($dbConnector, $isManual);
        $model = $run->buildApiModel();
        $this->line($config->getSlug().': '.$model['status'].' '.json_encode($model['summary']).($model['error'] ? ' '.$model['error'] : ''));
        if($run->getStatus() !== SystemRuleRun::statusIdFailed){
            return self::SUCCESS;
        }
        $retryAt = $isManual ? null : $config->scheduleRetry($dbConnector, BaseHelper::utcNow());
        if($retryAt){
            $this->line($config->getSlug().': reintenta '.BaseHelper::toDisplayDate($retryAt));
        }
        return self::FAILURE;
    }
}
