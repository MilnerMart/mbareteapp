<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProgressPageTest extends TestCase
{
    private const authSession = ['auth_user' => ['id' => 1, 'permits' => []], 'auth_token' => 'token'];

    private const entries = [
        ['id' => 5, 'entryOn' => '2026-10-13', 'metric' => 'max-kg', 'exerciseId' => 7, 'exerciseName' => 'Sentadilla', 'value' => 100, 'note' => null, 'previousValue' => 90, 'isPersonalBest' => true],
        ['id' => 4, 'entryOn' => '2026-10-13', 'metric' => 'body-weight', 'exerciseId' => null, 'exerciseName' => null, 'value' => 78.5, 'note' => null, 'previousValue' => 80, 'isPersonalBest' => false],
        ['id' => 3, 'entryOn' => '2026-10-12', 'metric' => 'waist', 'exerciseId' => null, 'exerciseName' => null, 'value' => 84, 'note' => null, 'previousValue' => 86, 'isPersonalBest' => false],
        ['id' => 2, 'entryOn' => '2026-10-10', 'metric' => 'max-reps', 'exerciseId' => 7, 'exerciseName' => 'Sentadilla', 'value' => 12, 'note' => null, 'previousValue' => null, 'isPersonalBest' => true],
        ['id' => 1, 'entryOn' => '2026-10-01', 'metric' => 'body-weight', 'exerciseId' => null, 'exerciseName' => null, 'value' => 80, 'note' => null, 'previousValue' => null, 'isPersonalBest' => false],
    ];

    private array $requestList = [];

    private array $storeResponse = ['success' => true, 'data' => self::entries[0]];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->requestList[] = [$request->method(), $path, $request->data()];
            return match (true) {
                $request->method() === 'POST' && str_ends_with($path, '/me/progress') => Http::response($this->storeResponse, 201),
                str_ends_with($path, '/me/progress') => Http::response(['success' => true, 'data' => ['height' => 175, 'weight' => 79, 'entries' => self::entries]]),
                str_ends_with($path, '/me/calendar') => Http::response(['success' => true, 'data' => [
                    'month' => '2026-10', 'actions' => [], 'trainings' => [], 'notes' => [], 'progressEntries' => array_slice(self::entries, 0, 2),
                ]]),
                str_ends_with($path, '/exercise') => Http::response(['success' => true, 'data' => [
                    ['id' => 7, 'name' => 'Sentadilla', 'muscle_id' => 2], ['id' => 8, 'name' => 'Curl de biceps', 'muscle_id' => 1],
                ]]),
                str_ends_with($path, '/muscle') => Http::response(['success' => true, 'data' => [['id' => 1, 'name' => 'Biceps'], ['id' => 2, 'name' => 'Cuadriceps']]]),
                default => Http::response(['success' => true, 'data' => []]),
            };
        });
    }

    public function test_page_summarizes_weight_bmi_records_and_measurements(): void
    {
        $this->withSession(self::authSession)->get('/progress')
            ->assertOk()
            ->assertSeeInOrder(['Peso actual', '78,5 kg', '−1,5 kg desde el registro anterior', 'IMC', '25,6', 'Sobrepeso', 'Records', '1'])
            ->assertSeeInOrder(['Records por ejercicio', 'Sentadilla', '100 kg', '13/10/2026', '12 reps', '10/10/2026'])
            ->assertSeeInOrder(['Medidas', 'Cintura', '84 cm', '−2 cm'])
            ->assertSeeInOrder(['Historial', 'Nuevo record en «Sentadilla»: 100 kg', 'Registraste tu peso: 78,5 kg'])
            ->assertSee('data-searchable', false)
            ->assertSeeInOrder(['<optgroup label="Biceps">', 'Curl de biceps', '<optgroup label="Cuadriceps">', '<option value="7">Sentadilla</option>'], false)
            ->assertSee('id="bmi_height" class="form-control" min="1" step="1" value="175"', false)
            ->assertSee(route('progress.destroy', 5), false);
    }

    public function test_saving_a_personal_best_celebrates_it(): void
    {
        $this->withSession(self::authSession)->withoutMiddleware(VerifyCsrfToken::class)
            ->post('/progress', ['entry_on' => '2026-10-13', 'metric' => 'max-kg', 'exercise_id' => '7', 'value' => '100'])
            ->assertRedirect(route('progress.index'))
            ->assertSessionHas('progress_saved', '¡Nuevo record en «Sentadilla»: 100 kg! Es tu nuevo record personal.');
        $this->assertContains(['POST', '/api/v1/me/progress', ['entry_on' => '2026-10-13', 'metric' => 'max-kg', 'exercise_id' => '7', 'value' => '100']], $this->requestList);

        $this->storeResponse = ['success' => true, 'data' => self::entries[1]];
        $this->withSession(self::authSession)->withoutMiddleware(VerifyCsrfToken::class)
            ->post('/progress', ['entry_on' => '2026-10-13', 'metric' => 'body-weight', 'value' => '78.5'])
            ->assertSessionHas('progress_saved', 'Registro guardado.');
    }

    public function test_calendar_day_shows_the_progress_entries(): void
    {
        $this->withSession(self::authSession)->get('/calendar?month=2026-10&day=2026-10-13')
            ->assertOk()
            ->assertSee('mark-progress', false)
            ->assertSeeInOrder(['Progreso', 'Nuevo record en «Sentadilla»: 100 kg', 'Registraste tu peso: 78,5 kg', '−1,5 kg desde el registro anterior']);
    }
}
