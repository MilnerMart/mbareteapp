<?php

namespace App\SystemRules;

use App\Core\CoreModel;
use App\Db\DbConnector;
use App\Models\CalendarNote;

class ReminderSystemRule extends SystemRule {

    const ruleModelId = CoreModel::calendarNoteModelId;

    const slug = 'reminders';

    const defaultFrequency = self::frequencyHourly, defaultRunAt = '00:00';

    private array $dueNoteList = [];

    protected function hydrate(DbConnector $dbConnect): void{
        $this->dueNoteList = CalendarNote::queryDueReminderList($dbConnect);
    }

    protected function execute(DbConnector $dbConnect): array{
        return ['notes' => CalendarNote::notifyReminderList($dbConnect, $this->dueNoteList)];
    }
}
