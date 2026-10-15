<?php

namespace App\Models;

use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use App\PublicException;
use App\SystemRules\SystemRule;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use stdClass;

class SystemRuleConfig {

    private const myTable = DbSchema::tableSystemRules;

    private int $ruleModelId;

    private bool $isEnabled;

    private string $frequency, $runAt;

    private int $retryMinutes = 0;

    private ?string $nextRunAtValue = null;

    private ?DateTimeImmutable $nextRunAt = null;

    static function ruleClassMap(): array{
        $classMap = [];
        foreach (config('system_rules.rules', []) as $ruleClass) {
            $classMap[$ruleClass::ruleModelId] = $ruleClass;
        }
        return $classMap;
    }

    static function syncCatalog(DbConnector $dbConnect): void{
        $now = BaseHelper::utcNow();
        $rowList = [];
        foreach (self::ruleClassMap() as $ruleModelId => $ruleClass) {
            $rowList[] = [
                'rule_model_id' => $ruleModelId,
                'is_enabled' => $ruleClass::defaultEnabled,
                'frequency' => $ruleClass::defaultFrequency,
                'run_at' => $ruleClass::defaultRunAt,
                'retry_minutes' => $ruleClass::defaultRetryMinutes,
                'next_run_at' => $dbConnect->toDbDate(SystemRule::buildNextRunAt($ruleClass::defaultFrequency, $ruleClass::defaultRunAt, CarbonImmutable::instance($now))),
                'created_at' => $dbConnect->toDbDate($now),
                'updated_at' => $dbConnect->toDbDate($now),
            ];
        }
        if($rowList){
            self::allocDbTable($dbConnect)->insertOrIgnore($rowList);
        }
    }

    static function queryList(DbConnector $dbConnect): array{
        $query = self::allocCatalogQuery($dbConnect)->orderBy('rule_model_id')->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Config']);
    }

    static function queryDueList(DbConnector $dbConnect, DateTimeImmutable $now): array{
        $query = self::allocCatalogQuery($dbConnect)->where('is_enabled', true)->where('next_run_at', '<=', $dbConnect->toDbDate($now))
            ->orderBy('next_run_at')->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Config']);
    }

    static function queryByRuleModelIdOrFail(DbConnector $dbConnect, int $ruleModelId): self{
        $query = self::allocCatalogQuery($dbConnect)->where('rule_model_id', $ruleModelId)->select();
        return $dbConnect->fetchSingle($query, [self::class, 'row2Config'])
            ?? throw PublicException::notFoundError('No existe la regla '.$ruleModelId);
    }

    private static function allocCatalogQuery(DbConnector $dbConnect): Builder{
        return self::allocDbTable($dbConnect)->whereIn('rule_model_id', array_keys(self::ruleClassMap()));
    }

    function claim(DbConnector $dbConnect, DateTimeImmutable $now): bool{
        $nextRunAt = SystemRule::buildNextRunAt($this->frequency, $this->runAt, CarbonImmutable::instance($now));
        $isClaimed = self::allocDbTable($dbConnect)->where('rule_model_id', $this->ruleModelId)->where('next_run_at', $this->nextRunAtValue)
            ->update(['next_run_at' => $dbConnect->toDbDate($nextRunAt), 'updated_at' => $dbConnect->toDbDate($now)]) > 0;
        if($isClaimed){
            $this->setNextRunAt($dbConnect, $nextRunAt);
        }
        return $isClaimed;
    }

    function scheduleRetry(DbConnector $dbConnect, DateTimeImmutable $now): ?DateTimeImmutable{
        if($this->retryMinutes <= 0){
            return null;
        }
        $retryAt = $now->modify('+'.$this->retryMinutes.' minutes');
        if($this->nextRunAt && $retryAt >= $this->nextRunAt){
            return null;
        }
        $this->setNextRunAt($dbConnect, $retryAt);
        self::allocDbTable($dbConnect)->where('rule_model_id', $this->ruleModelId)
            ->update(['next_run_at' => $this->nextRunAtValue, 'updated_at' => $dbConnect->toDbDate($now)]);
        return $this->nextRunAt;
    }

    function updateSettings(DbConnector $dbConnect, bool $isEnabled, string $frequency, string $runAt, int $retryMinutes): void{
        $now = BaseHelper::utcNow();
        $this->isEnabled = $isEnabled;
        $this->frequency = $frequency;
        $this->runAt = $runAt;
        $this->retryMinutes = $retryMinutes;
        $this->setNextRunAt($dbConnect, SystemRule::buildNextRunAt($frequency, $runAt, CarbonImmutable::instance($now)));
        self::allocDbTable($dbConnect)->where('rule_model_id', $this->ruleModelId)->update([
            'is_enabled' => $isEnabled,
            'frequency' => $frequency,
            'run_at' => $runAt,
            'retry_minutes' => $retryMinutes,
            'next_run_at' => $this->nextRunAtValue,
            'updated_at' => $dbConnect->toDbDate($now),
        ]);
    }

    private function setNextRunAt(DbConnector $dbConnect, DateTimeImmutable $nextRunAt): void{
        $this->nextRunAtValue = $dbConnect->toDbDate($nextRunAt);
        $this->nextRunAt = $dbConnect->fromDbDate($this->nextRunAtValue);
    }

    function allocRule(): SystemRule{
        $ruleClass = self::ruleClassMap()[$this->ruleModelId];
        return new $ruleClass();
    }

    function getRuleModelId(): int{
        return $this->ruleModelId;
    }

    function getSlug(): string{
        return self::ruleClassMap()[$this->ruleModelId]::slug;
    }

    static function row2Config(DbAdapter $dbAdapter, stdClass $row): self{
        $self = new self();
        $self->ruleModelId = (int)$row->rule_model_id;
        $self->isEnabled = (bool)$row->is_enabled;
        $self->frequency = $row->frequency;
        $self->runAt = $row->run_at;
        $self->retryMinutes = (int)$row->retry_minutes;
        $self->nextRunAtValue = $row->next_run_at;
        $self->nextRunAt = $dbAdapter->fromDbDate($row->next_run_at);
        return $self;
    }

    function buildApiModel(): array{
        return [
            'ruleModelId' => $this->ruleModelId,
            'slug' => $this->getSlug(),
            'isEnabled' => $this->isEnabled,
            'frequency' => $this->frequency,
            'runAt' => $this->runAt,
            'retryMinutes' => $this->retryMinutes,
            'timezone' => SystemRule::timezone,
            'nextRunAt' => BaseHelper::toDisplayDate($this->nextRunAt),
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }
}
