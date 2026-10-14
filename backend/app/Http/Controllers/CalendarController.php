<?php

namespace App\Http\Controllers;

use App\Core\EntityStatus;
use App\Helpers\BaseHelper;
use App\Http\Requests\CalendarNoteRequest;
use App\Models\CalendarNote;
use App\Models\EntityLog;
use App\Models\ProgressEntry;
use App\Models\RoutineExerciseLog;
use App\PublicException;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller {

    public function index(Request $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $userId = $request->user()->id;
        $displayTz = new DateTimeZone(BaseHelper::displayTimezone);
        $month = $request->query('month', (new DateTimeImmutable('now', $displayTz))->format('Y-m'));
        $monthStart = DateTimeImmutable::createFromFormat('!Y-m', (string)$month, $displayTz);
        if(!$monthStart || $monthStart->format('Y-m') !== $month){
            throw PublicException::validationError('Mes invalido: '.$month);
        }
        $nextMonthStart = $monthStart->modify('first day of next month');
        $fromDay = $monthStart->format('Y-m-d');
        $toDay = $nextMonthStart->modify('-1 day')->format('Y-m-d');

        return $this->successApiResponse([
            'month' => $month,
            'actions' => array_map(fn(EntityLog $entityLog) => $entityLog->buildApiModel(),
                EntityLog::queryListByUserIdAndRange($dbConnector, $userId, $monthStart, $nextMonthStart)),
            'trainings' => RoutineExerciseLog::queryTrainingDayList($dbConnector, $userId, $fromDay, $toDay),
            'notes' => array_map(fn(CalendarNote $note) => $note->buildApiModel(),
                CalendarNote::queryListByUserIdAndRange($dbConnector, $userId, $fromDay, $toDay)),
            'progressEntries' => ProgressEntry::buildApiModelListByUserId($dbConnector, $userId, $fromDay, $toDay),
        ]);
    }

    public function storeNote(CalendarNoteRequest $request): JsonResponse {
        $validated = $request->validated();
        $note = CalendarNote::allocNew($request->user()->id, $validated['note_on'], $validated['title'],
            $validated['body'] ?? null, $validated['remind_days'] ?? []);
        $this->futureReminderOrFail($note);
        $note->writeToDb($this->getDbConnector());
        return $this->successApiResponse($note->buildApiModel(), 201);
    }

    public function updateNote(CalendarNoteRequest $request, string $id): JsonResponse {
        $note = CalendarNote::queryOwnedOrFail($this->getDbConnector(), $request->user()->id, (int)$id);
        $validated = $request->validated();
        if($note->edit($note->getNoteOn(), $validated['title'], $validated['body'] ?? null, $validated['remind_days'] ?? [])){
            $this->futureReminderOrFail($note);
        }
        $note->writeToDb($this->getDbConnector());
        return $this->successApiResponse($note->buildApiModel());
    }

    public function destroyNote(Request $request, string $id): JsonResponse {
        $note = CalendarNote::queryOwnedOrFail($this->getDbConnector(), $request->user()->id, (int)$id);
        $note->setStatusId(EntityStatus::statusIdDeleted);
        $note->writeToDb($this->getDbConnector());
        return $this->successApiResponse(code: 200);
    }

    private function futureReminderOrFail(CalendarNote $note): void {
        if($note->getRemindDays() && $note->isInPast()){
            throw PublicException::validationError('No se puede poner un recordatorio en un dia que ya paso');
        }
    }
}
