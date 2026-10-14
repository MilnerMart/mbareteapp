<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use App\PublicException;
use Illuminate\Database\Query\Builder;
use stdClass;

class ProgressEntry extends BaseEntity {

    private const myTable = DbSchema::tableProgressEntries;

    const metricBodyWeight = 'body-weight', metricMaxKg = 'max-kg', metricMaxReps = 'max-reps';

    const metricMap = [
        self::metricBodyWeight => ['min' => 25, 'max' => 300, 'isRecord' => false],
        'waist' => ['min' => 30, 'max' => 250, 'isRecord' => false],
        'hip' => ['min' => 30, 'max' => 250, 'isRecord' => false],
        'chest' => ['min' => 30, 'max' => 250, 'isRecord' => false],
        'arm' => ['min' => 10, 'max' => 100, 'isRecord' => false],
        'thigh' => ['min' => 20, 'max' => 150, 'isRecord' => false],
        'calf' => ['min' => 15, 'max' => 100, 'isRecord' => false],
        'neck' => ['min' => 15, 'max' => 100, 'isRecord' => false],
        'body-fat' => ['min' => 2, 'max' => 70, 'isRecord' => false],
        self::metricMaxKg => ['min' => 0.5, 'max' => 1000, 'isRecord' => true],
        self::metricMaxReps => ['min' => 1, 'max' => 1000, 'isRecord' => true],
    ];

    private int $userId;

    private string $entryOn, $metric;

    private ?int $exerciseId = null;

    private float $value;

    private ?string $note = null;

    public static function allocNew(int $userId, string $entryOn, string $metric, ?int $exerciseId, float $value, ?string $note):self{
        $metricInfo = self::metricMap[$metric] ?? throw PublicException::validationError('Tipo de registro invalido: '.$metric);
        if($metricInfo['isRecord'] && !$exerciseId){
            throw PublicException::validationError('Elige el ejercicio del record');
        }
        if($value < $metricInfo['min'] || $value > $metricInfo['max']){
            throw PublicException::validationError('El valor tiene que estar entre '.$metricInfo['min'].' y '.$metricInfo['max']);
        }
        if($entryOn > CalendarNote::today()){
            throw PublicException::validationError('No se puede registrar un dia que todavia no llego');
        }
        $self = new self();
        $self->userId = $userId;
        $self->entryOn = $entryOn;
        $self->metric = $metric;
        $self->exerciseId = $metricInfo['isRecord'] ? $exerciseId : null;
        $self->value = round($value, 2);
        $self->note = $note ?: null;
        return $self;
    }

    function getModelId(): int{
        return CoreModel::progressEntryModelId;
    }

    function getEntryOn(): string{
        return $this->entryOn;
    }

    function getMetric(): string{
        return $this->metric;
    }

    function getExerciseId(): ?int{
        return $this->exerciseId;
    }

    function getValue(): float{
        return $this->value;
    }

    function isRecordMetric(): bool{
        return self::metricMap[$this->metric]['isRecord'];
    }

    function isSameSeries(ProgressEntry $other): bool{
        return $this->metric === $other->metric && $this->exerciseId === $other->exerciseId;
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'entryOn' => $this->entryOn,
            'metric' => $this->metric,
            'exerciseId' => $this->exerciseId,
            'value' => $this->value,
            'note' => $this->note,
        ];
    }

    static function buildApiModelList(DbConnector $dbConnect, array $entryList): array{
        $exerciseIdList = array_values(array_unique(array_filter(array_map(fn(ProgressEntry $entry) => $entry->exerciseId, $entryList))));
        $exerciseNameMap = $exerciseIdList ? Exercise::allocDbTable($dbConnect)->whereIn('id', $exerciseIdList)->pluck('name', 'id')->all() : [];
        $orderedList = $entryList;
        usort($orderedList, fn(ProgressEntry $a, ProgressEntry $b) => [$a->entryOn, $a->getEntityId()] <=> [$b->entryOn, $b->getEntityId()]);

        $previousMap = [];
        $changeMap = [];
        foreach ($orderedList as $entry) {
            $seriesKey = $entry->metric.'-'.$entry->exerciseId;
            $previous = $previousMap[$seriesKey] ?? null;
            $changeMap[$entry->getEntityId()] = [
                'previousValue' => $previous['value'] ?? null,
                'isPersonalBest' => $entry->isRecordMetric() && ($previous === null || $entry->value > $previous['best']),
            ];
            $previousMap[$seriesKey] = ['value' => $entry->value, 'best' => max($entry->value, $previous['best'] ?? 0)];
        }

        return array_map(fn(ProgressEntry $entry) => $entry->buildApiModel() + [
            'exerciseName' => $entry->exerciseId ? ($exerciseNameMap[$entry->exerciseId] ?? null) : null,
            'previousValue' => $changeMap[$entry->getEntityId()]['previousValue'],
            'isPersonalBest' => $changeMap[$entry->getEntityId()]['isPersonalBest'],
        ], $entryList);
    }

    private function progressEntry2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->user_id = $this->userId;
        $row->entry_on = $this->entryOn;
        $row->metric = $this->metric;
        $row->exercise_id = $this->exerciseId;
        $row->value = $this->value;
        $row->note = $this->note;
        return $row;
    }

    static function row2ProgressEntry(DbAdapter $dbAdapter, stdClass $row):self{
        $entry = new ProgressEntry();
        $entry->initFromDbRow($dbAdapter, $row);
        $entry->userId = (int)$row->user_id;
        $entry->entryOn = substr((string)$row->entry_on, 0, 10);
        $entry->metric = $row->metric;
        $entry->exerciseId = $row->exercise_id !== null ? (int)$row->exercise_id : null;
        $entry->value = (float)$row->value;
        $entry->note = $row->note;
        return $entry;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private static function allocSelectQuery(DbConnector $dbConnect, int $userId): Builder{
        return self::allocDbTable($dbConnect)
        ->where('user_id', $userId)
        ->where('status', '!=', EntityStatus::statusIdDeleted)
        ->select();
    }

    static function buildApiModelListByUserId(DbConnector $dbConnect, int $userId, ?string $fromDay = null, ?string $toDay = null): array{
        $modelList = self::buildApiModelList($dbConnect, self::queryListByUserId($dbConnect, $userId));
        if($fromDay === null){
            return $modelList;
        }
        return array_values(array_filter($modelList, fn(array $entry) => $entry['entryOn'] >= $fromDay && $entry['entryOn'] <= $toDay));
    }

    static function queryListByUserId(DbConnector $dbConnect, int $userId): array{
        $query = self::allocSelectQuery($dbConnect, $userId)->orderByDesc('entry_on')->orderByDesc('id');
        return $dbConnect->fetchAll($query, [self::class, 'row2ProgressEntry']);
    }

    static function queryLatestBodyWeight(DbConnector $dbConnect, int $userId): ?self{
        $query = self::allocSelectQuery($dbConnect, $userId)->where('metric', self::metricBodyWeight)
        ->orderByDesc('entry_on')->orderByDesc('id');
        return $dbConnect->fetchSingle($query, [self::class, 'row2ProgressEntry']);
    }

    static function queryOwnedOrFail(DbConnector $dbConnect, int $userId, int $dbId): self{
        $entry = $dbConnect->fetchSingle(self::allocSelectQuery($dbConnect, $userId)->where('id', $dbId), [self::class, 'row2ProgressEntry']);
        if(!$entry){
            throw PublicException::notFoundError('No se encuentra el registro con id: '.$dbId);
        }
        return $entry;
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime($now);
        if(!$this->getCreatedTime()){
            $this->setCreatedTime($now);
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->progressEntry2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if($this->getEntityId()){
            $writable->where(['id' => $this->getEntityId()])->update($writeArray);
        } else {
            $this->setEntityId($writable->insertGetId($writeArray));
        }
    }
}
