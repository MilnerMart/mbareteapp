<?php

namespace Tests\Feature;

use App\Models\RoutineExerciseLog;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_month_shows_own_actions_and_completed_routines(): void
    {
        $token = $this->register('student@example.com', 'trainee-role')->json('data.token');
        $exerciseIdList = DB::table('exercises')->limit(2)->pluck('id')->all();
        $routineId = $this->actingWithToken($token)->postJson('/api/v1/routines', ['name' => 'Fuerza', 'frequency' => 2, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        foreach ($exerciseIdList as $exerciseId) {
            $this->actingWithToken($token)->postJson('/api/v1/routines/'.$routineId.'/exercises', ['exercise_id' => $exerciseId])->assertSuccessful();
        }
        $this->actingWithToken($token)->putJson('/api/v1/routines/'.$routineId.'/done', [
            'exercises' => array_map(fn($id) => ['exercise_id' => $id], $exerciseIdList),
        ])->assertOk();

        $month = substr(RoutineExerciseLog::today(), 0, 7);
        $calendar = $this->actingWithToken($token)->getJson('/api/v1/me/calendar?month='.$month)->assertOk()
            ->assertJsonPath('data.month', $month)
            ->assertJsonPath('data.actions.0.action', 'created')
            ->assertJsonPath('data.actions.0.data.name', 'Fuerza')
            ->assertJsonPath('data.trainings.0.day', RoutineExerciseLog::today())
            ->assertJsonPath('data.trainings.0.routineName', 'Fuerza')
            ->assertJsonPath('data.trainings.0.doneCount', 2)
            ->assertJsonPath('data.trainings.0.isCompleted', true);
        $this->assertNotContains('done', array_column($calendar->json('data.actions'), 'action'));

        $this->actingWithToken($token)->getJson('/api/v1/me/calendar?month=2020-01')->assertOk()
            ->assertJsonPath('data.actions', [])->assertJsonPath('data.trainings', []);
        $this->actingWithToken($token)->getJson('/api/v1/me/calendar?month=2026-13')->assertStatus(400);
    }

    public function test_notes_belong_to_their_owner_and_reminders_skip_days_already_gone(): void
    {
        $token = $this->register('student@example.com', 'trainee-role')->json('data.token');
        $otherToken = $this->register('other@example.com', 'trainee-role')->json('data.token');
        $tomorrow = $this->dayFromToday(1);
        $inFiveDays = $this->dayFromToday(5);

        $this->actingWithToken($token)->postJson('/api/v1/me/calendar/notes', ['note_on' => '2020-01-01', 'title' => 'Pasado', 'remind_days' => [0]])
            ->assertStatus(400);
        $this->actingWithToken($token)->postJson('/api/v1/me/calendar/notes', ['note_on' => '2020-01-01', 'title' => 'Me acuerdo de esto'])
            ->assertCreated();
        $this->actingWithToken($token)->postJson('/api/v1/me/calendar/notes', ['note_on' => $tomorrow, 'title' => 'Mal', 'remind_days' => [3]])
            ->assertUnprocessable();

        $noteId = $this->actingWithToken($token)->postJson('/api/v1/me/calendar/notes', [
            'note_on' => $inFiveDays, 'title' => 'Medir cintura', 'body' => 'En ayunas', 'remind_days' => [0, 2, 1],
        ])->assertCreated()
            ->assertJsonPath('data.remindDays', [2, 1, 0])
            ->assertJsonPath('data.notifiedDays', [])
            ->json('data.id');
        // para mañana, "2 dias antes" ya paso: no se va a mandar
        $this->actingWithToken($token)->postJson('/api/v1/me/calendar/notes', ['note_on' => $tomorrow, 'title' => 'Turno', 'remind_days' => [2, 1, 0]])
            ->assertCreated()->assertJsonPath('data.notifiedDays', [2]);

        $this->actingWithToken($otherToken)->putJson('/api/v1/me/calendar/notes/'.$noteId, ['note_on' => $inFiveDays, 'title' => 'Hackeo'])->assertNotFound();
        $this->actingWithToken($otherToken)->deleteJson('/api/v1/me/calendar/notes/'.$noteId)->assertNotFound();
        $this->actingWithToken($otherToken)->getJson('/api/v1/me/calendar?month='.substr($inFiveDays, 0, 7))->assertJsonPath('data.notes', []);

        // el dia no se cambia al editar: se queda el del calendario aunque lo manden
        $this->actingWithToken($token)->putJson('/api/v1/me/calendar/notes/'.$noteId, [
            'note_on' => $tomorrow, 'title' => 'Medir cintura y cadera', 'remind_days' => [1],
        ])->assertOk()
            ->assertJsonPath('data.noteOn', $inFiveDays)
            ->assertJsonPath('data.title', 'Medir cintura y cadera')
            ->assertJsonPath('data.remindDays', [1])
            ->assertJsonPath('data.body', null);

        $this->actingWithToken($token)->deleteJson('/api/v1/me/calendar/notes/'.$noteId)->assertOk();
        $this->actingWithToken($token)->getJson('/api/v1/me/calendar?month='.substr($inFiveDays, 0, 7))->assertOk()
            ->assertJsonMissing(['title' => 'Medir cintura y cadera']);
    }

    public function test_reminders_arrive_two_days_before_the_day_before_and_the_same_day(): void
    {
        $token = $this->register('student@example.com', 'trainee-role')->json('data.token');
        $noteId = $this->actingWithToken($token)->postJson('/api/v1/me/calendar/notes', [
            'note_on' => $this->dayFromToday(5), 'title' => 'Turno con el nutricionista', 'remind_days' => [2, 1, 0],
        ])->assertCreated()->json('data.id');
        $this->actingWithToken($token)->getJson('/api/v1/me/notifications')->assertJsonPath('data.unreadCount', 0);

        foreach ([2, 1, 0] as $index => $daysLeft) {
            DB::table('calendar_notes')->where('id', $noteId)->update(['note_on' => $this->dayFromToday($daysLeft)]);
            $this->actingWithToken($token)->getJson('/api/v1/me/notifications')->assertOk()
                ->assertJsonPath('data.unreadCount', $index + 1)
                ->assertJsonPath('data.items.0.type', 'reminder')
                ->assertJsonPath('data.items.0.refId', $noteId)
                ->assertJsonPath('data.items.0.data.title', 'Turno con el nutricionista')
                ->assertJsonPath('data.items.0.data.daysLeft', $daysLeft);
            $this->actingWithToken($token)->getJson('/api/v1/me/notifications')->assertJsonCount($index + 1, 'data.items');
        }
    }

    public function test_reminders_missed_while_away_arrive_as_a_single_notice(): void
    {
        $token = $this->register('student@example.com', 'trainee-role')->json('data.token');
        $noteId = $this->actingWithToken($token)->postJson('/api/v1/me/calendar/notes', [
            'note_on' => $this->dayFromToday(5), 'title' => 'Competencia', 'remind_days' => [2, 1, 0],
        ])->assertCreated()->json('data.id');

        DB::table('calendar_notes')->where('id', $noteId)->update(['note_on' => $this->dayFromToday(0)]);
        $this->actingWithToken($token)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.data.daysLeft', 0);
        $this->assertSame([2, 1, 0], json_decode(DB::table('calendar_notes')->where('id', $noteId)->value('notified_days'), true));
    }

    private function dayFromToday(int $days): string
    {
        return now('America/Asuncion')->addDays($days)->toDateString();
    }
}
