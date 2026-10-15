<?php

namespace App\Models;

use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use stdClass;

class SystemRuleRun {

    private const myTable = DbSchema::tableSystemRuleRuns;

    const statusIdPending = 10, statusIdRunning = 20, statusIdCompleted = 30, statusIdFailed = 40, statusIdSkipped = 50;

    const statusSlugMap = [
        self::statusIdPending => 'pending',
        self::statusIdRunning => 'running',
        self::statusIdCompleted => 'completed',
        self::statusIdFailed => 'failed',
        self::statusIdSkipped => 'skipped',
    ];

    const staleMinutes = 60, recentLimit = 10;

    private int $id, $ruleModelId, $status;

    private bool $isManual = false;

    private ?DateTimeImmutable $createdAt = null, $startedAt = null, $finishedAt = null;

    private ?array $summary = null;

    private ?string $error = null;

    static function addPending(DbConnector $dbConnect, int $ruleModelId, bool $isManual): self{
        return self::addNew($dbConnect, $ruleModelId, self::statusIdPending, $isManual);
    }

    static function addSkipped(DbConnector $dbConnect, int $ruleModelId, bool $isManual): self{
        $self = self::addNew($dbConnect, $ruleModelId, self::statusIdSkipped, $isManual);
        $self->update($dbConnect, ['finished_at' => BaseHelper::utcNow(), 'error' => 'Ya habia una ejecucion en curso']);
        return $self;
    }

    private static function addNew(DbConnector $dbConnect, int $ruleModelId, int $status, bool $isManual): self{
        $now = BaseHelper::utcNow();
        $self = new self();
        $self->ruleModelId = $ruleModelId;
        $self->status = $status;
        $self->isManual = $isManual;
        $self->createdAt = $now;
        $self->id = self::allocDbTable($dbConnect)->insertGetId([
            'rule_model_id' => $ruleModelId,
            'status' => $status,
            'is_manual' => $isManual,
            'created_at' => $dbConnect->toDbDate($now),
            'updated_at' => $dbConnect->toDbDate($now),
        ]);
        return $self;
    }

    function markRunning(DbConnector $dbConnect): void{
        $this->update($dbConnect, ['status' => self::statusIdRunning, 'started_at' => BaseHelper::utcNow()]);
    }

    function markCompleted(DbConnector $dbConnect, array $summary): void{
        $this->update($dbConnect, ['status' => self::statusIdCompleted, 'finished_at' => BaseHelper::utcNow(), 'summary' => $summary]);
    }

    function markFailed(DbConnector $dbConnect, string $error): void{
        $this->update($dbConnect, ['status' => self::statusIdFailed, 'finished_at' => BaseHelper::utcNow(), 'error' => mb_substr($error, 0, 1000)]);
    }

    private function update(DbConnector $dbConnect, array $values): void{
        $this->status = $values['status'] ?? $this->status;
        $this->startedAt = $values['started_at'] ?? $this->startedAt;
        $this->finishedAt = $values['finished_at'] ?? $this->finishedAt;
        $this->summary = $values['summary'] ?? $this->summary;
        $this->error = $values['error'] ?? $this->error;
        $row = array_map(fn($value) => $value instanceof DateTimeImmutable ? $dbConnect->toDbDate($value) : $value, $values);
        if(isset($row['summary'])){
            $row['summary'] = BaseHelper::jsonEncode($row['summary']);
        }
        self::allocDbTable($dbConnect)->where('id', $this->id)->update($row + ['updated_at' => $dbConnect->toDbDate(BaseHelper::utcNow())]);
    }

    static function isRunning(DbConnector $dbConnect, int $ruleModelId): bool{
        return self::allocDbTable($dbConnect)->where('rule_model_id', $ruleModelId)->where('status', self::statusIdRunning)->exists();
    }

    static function failStale(DbConnector $dbConnect): int{
        $now = BaseHelper::utcNow();
        return self::allocDbTable($dbConnect)->whereIn('status', [self::statusIdPending, self::statusIdRunning])
            ->where('created_at', '<', $dbConnect->toDbDate($now->modify('-'.self::staleMinutes.' minutes')))
            ->update([
                'status' => self::statusIdFailed,
                'finished_at' => $dbConnect->toDbDate($now),
                'error' => 'Se interrumpio antes de terminar',
                'updated_at' => $dbConnect->toDbDate($now),
            ]);
    }

    static function queryRecentList(DbConnector $dbConnect, int $ruleModelId, int $limit = self::recentLimit): array{
        $query = self::allocDbTable($dbConnect)->where('rule_model_id', $ruleModelId)->orderByDesc('id')->limit($limit)->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Run']);
    }

    static function row2Run(DbAdapter $dbAdapter, stdClass $row): self{
        $self = new self();
        $self->id = (int)$row->id;
        $self->ruleModelId = (int)$row->rule_model_id;
        $self->status = (int)$row->status;
        $self->isManual = (bool)$row->is_manual;
        $self->createdAt = $dbAdapter->fromDbDate($row->created_at);
        $self->startedAt = $dbAdapter->fromDbDate($row->started_at);
        $self->finishedAt = $dbAdapter->fromDbDate($row->finished_at);
        $self->summary = BaseHelper::fromDbJson($row->summary);
        $self->error = $row->error;
        return $self;
    }

    function getStatus(): int{
        return $this->status;
    }

    function buildApiModel(): array{
        return [
            'id' => $this->id,
            'status' => self::statusSlugMap[$this->status] ?? null,
            'isManual' => $this->isManual,
            'createdAt' => BaseHelper::toDisplayDate($this->createdAt),
            'startedAt' => BaseHelper::toDisplayDate($this->startedAt),
            'finishedAt' => BaseHelper::toDisplayDate($this->finishedAt),
            'summary' => $this->summary ?? [],
            'error' => $this->error,
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }
}
