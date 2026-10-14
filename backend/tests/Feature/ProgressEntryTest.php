<?php

namespace Tests\Feature;

use App\Models\RoutineExerciseLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class ProgressEntryTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_body_weight_keeps_profile_weight_in_sync_and_shows_change(): void
    {
        $student = $this->register('student@example.com', 'trainee-role');
        [$userId, $token] = [$student->json('data.user.id'), $student->json('data.token')];

        $this->actingWithToken($token)->postJson('/api/v1/me/progress', ['entry_on' => $this->day(-3), 'metric' => 'body-weight', 'value' => 80])
            ->assertCreated()->assertJsonPath('data.previousValue', null);
        $todayId = $this->actingWithToken($token)->postJson('/api/v1/me/progress', ['entry_on' => $this->day(0), 'metric' => 'body-weight', 'value' => 78.5, 'note' => 'En ayunas'])
            ->assertCreated()
            ->assertJsonPath('data.previousValue', 80)
            ->assertJsonPath('data.note', 'En ayunas')
            ->json('data.id');
        $this->assertSame(79, (int)DB::table('users')->where('id', $userId)->value('weight'));

        // uno viejo no pisa el peso actual
        $this->actingWithToken($token)->postJson('/api/v1/me/progress', ['entry_on' => $this->day(-10), 'metric' => 'body-weight', 'value' => 85])->assertCreated();
        $this->assertSame(79, (int)DB::table('users')->where('id', $userId)->value('weight'));

        $otherToken = $this->register('other@example.com', 'trainee-role')->json('data.token');
        $this->actingWithToken($otherToken)->deleteJson('/api/v1/me/progress/'.$todayId)->assertNotFound();
        $this->actingWithToken($otherToken)->getJson('/api/v1/me/progress')->assertOk()->assertJsonPath('data.entries', []);

        $this->actingWithToken($token)->deleteJson('/api/v1/me/progress/'.$todayId)->assertOk();
        $this->assertSame(80, (int)DB::table('users')->where('id', $userId)->value('weight'));
        $this->actingWithToken($token)->getJson('/api/v1/me/progress')->assertOk()
            ->assertJsonCount(2, 'data.entries')
            ->assertJsonPath('data.height', 175);
    }

    public function test_records_by_exercise_flag_personal_bests_and_show_in_calendar(): void
    {
        $token = $this->register('student@example.com', 'trainee-role')->json('data.token');
        $exercise = DB::table('exercises')->first(['id', 'name']);

        foreach ([[-5, 60, true], [-2, 70, true], [0, 65, false]] as [$daysAgo, $kg, $isBest]) {
            $this->actingWithToken($token)->postJson('/api/v1/me/progress', [
                'entry_on' => $this->day($daysAgo), 'metric' => 'max-kg', 'exercise_id' => $exercise->id, 'value' => $kg,
            ])->assertCreated()
                ->assertJsonPath('data.exerciseName', $exercise->name)
                ->assertJsonPath('data.isPersonalBest', $isBest);
        }
        $this->actingWithToken($token)->postJson('/api/v1/me/progress', [
            'entry_on' => $this->day(0), 'metric' => 'max-reps', 'exercise_id' => $exercise->id, 'value' => 12,
        ])->assertCreated()->assertJsonPath('data.isPersonalBest', true);
        $this->actingWithToken($token)->postJson('/api/v1/me/progress', ['entry_on' => $this->day(0), 'metric' => 'waist', 'value' => 84])
            ->assertCreated()->assertJsonPath('data.exerciseId', null);

        $month = substr($this->day(0), 0, 7);
        $entries = $this->actingWithToken($token)->getJson('/api/v1/me/calendar?month='.$month)->assertOk()->json('data.progressEntries');
        $this->assertContains('waist', array_column($entries, 'metric'));
        $this->assertContains('max-reps', array_column($entries, 'metric'));
    }

    public function test_invalid_entries_are_rejected(): void
    {
        $token = $this->register('student@example.com', 'trainee-role')->json('data.token');

        $this->actingWithToken($token)->postJson('/api/v1/me/progress', ['entry_on' => $this->day(1), 'metric' => 'body-weight', 'value' => 80])->assertStatus(400);
        $this->actingWithToken($token)->postJson('/api/v1/me/progress', ['entry_on' => $this->day(0), 'metric' => 'body-weight', 'value' => 900])->assertStatus(400);
        $this->actingWithToken($token)->postJson('/api/v1/me/progress', ['entry_on' => $this->day(0), 'metric' => 'max-kg', 'value' => 80])->assertStatus(400);
        $this->actingWithToken($token)->postJson('/api/v1/me/progress', ['entry_on' => $this->day(0), 'metric' => 'max-kg', 'exercise_id' => 999999, 'value' => 80])->assertStatus(400);
        $this->actingWithToken($token)->postJson('/api/v1/me/progress', ['entry_on' => $this->day(0), 'metric' => 'nope', 'value' => 80])->assertUnprocessable();
    }

    private function day(int $offset): string
    {
        return CarbonImmutable::parse(RoutineExerciseLog::today())->addDays($offset)->toDateString();
    }
}
