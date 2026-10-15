<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemRuleRequest;
use App\Models\SystemRuleConfig;
use App\Models\SystemRuleRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemRuleController extends Controller {

    public function index(Request $request): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        SystemRuleConfig::syncCatalog($dbConnector);
        SystemRuleRun::failStale($dbConnector);
        return $this->successApiResponse(array_map(fn(SystemRuleConfig $config) => $this->buildRuleModel($config),
            SystemRuleConfig::queryList($dbConnector)));
    }

    public function sync(SystemRuleRequest $request): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $ruleList = $request->validated()['rules'];
        $configList = array_map(fn($ruleModelId) => $this->queryConfigOrFail((string)$ruleModelId), array_keys($ruleList));
        $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $configList, $ruleList){
            foreach ($configList as $config) {
                $rule = $ruleList[$config->getRuleModelId()];
                $config->updateSettings($dbConnector, (bool)$rule['is_enabled'], $rule['frequency'], $rule['run_at'], (int)$rule['retry_minutes']);
            }
        });
        return $this->successApiResponse(array_map(fn(SystemRuleConfig $config) => $this->buildRuleModel($config),
            SystemRuleConfig::queryList($dbConnector)));
    }

    public function run(Request $request, string $ruleModelId): JsonResponse {
        $this->allowConfigPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $config = $this->queryConfigOrFail($ruleModelId);
        $run = $config->allocRule()->runAndRecord($dbConnector, true);
        return $this->successApiResponse($this->buildRuleModel($config) + ['lastRun' => $run->buildApiModel()]);
    }

    private function queryConfigOrFail(string $ruleModelId): SystemRuleConfig {
        SystemRuleConfig::syncCatalog($this->getDbConnector());
        return SystemRuleConfig::queryByRuleModelIdOrFail($this->getDbConnector(), (int)$ruleModelId);
    }

    private function buildRuleModel(SystemRuleConfig $config): array {
        return $config->buildApiModel() + [
            'runs' => array_map(fn(SystemRuleRun $run) => $run->buildApiModel(),
                SystemRuleRun::queryRecentList($this->getDbConnector(), $config->getRuleModelId())),
        ];
    }
}
