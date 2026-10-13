<?php

namespace Tests\Feature;

use App\Models\CoreConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class PublicResourceProvidersTest extends TestCase
{
    use InteractsWithMuscleApi {
        setUp as setUpMuscleApi;
    }

    private array $uploadedImageList = [];

    protected function setUp(): void
    {
        $this->setUpMuscleApi();
        Http::preventStrayRequests();
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response([
                ['id' => 'Barbell_Curl', 'name' => 'Barbell Curl', 'primaryMuscles' => ['biceps'], 'equipment' => 'barbell', 'images' => ['Barbell_Curl/0.jpg']],
            ]),
            'wger.de/api/v2/license/*' => Http::response(['next' => null, 'results' => [
                ['id' => 2, 'short_name' => 'CC-BY-SA 4', 'full_name' => 'Creative Commons Attribution Share Alike 4', 'url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.en'],
                ['id' => 3, 'short_name' => 'CC0', 'full_name' => 'Creative Commons Public Domain 1.0', 'url' => 'http://creativecommons.org/publicdomain/zero/1.0/'],
                ['id' => 5, 'short_name' => 'ODbL', 'full_name' => 'Open Data Commons Open Database License', 'url' => 'https://opendatacommons.org/licenses/odbl/'],
                ['id' => 9, 'short_name' => 'CC-BY-NC 4', 'full_name' => 'Creative Commons Attribution NonCommercial 4', 'url' => 'https://creativecommons.org/licenses/by-nc/4.0/'],
            ]]),
            'wger.de/api/v2/exerciseinfo/*' => Http::response(['next' => null, 'results' => [
                $this->wgerExercise(12, 'Curl de bíceps con barra', 'Barbell Biceps Curl', [
                    $this->wgerMedia(1, 2, 'Everkinetic', ['image' => 'https://wger.de/media/exercise-images/12/curl-1.png']),
                    $this->wgerMedia(2, 9, 'Alguien', ['image' => 'https://wger.de/media/exercise-images/12/curl-nc.png']),
                    $this->wgerMedia(3, 3, '', ['image' => 'https://wger.de/media/exercise-images/12/curl-cc0.png']),
                ], [
                    $this->wgerMedia(5, 2, 'Goulart', ['video' => 'https://wger.de/media/exercise-video/12/curl.mp4', 'codec' => 'h264']),
                    $this->wgerMedia(6, 2, 'Goulart', ['video' => 'https://wger.de/media/exercise-video/12/curl.MOV', 'codec' => 'hevc']),
                ]),
                $this->wgerExercise(13, 'Curl martillo', 'Hammer Curl', [
                    $this->wgerMedia(7, 9, 'Alguien', ['image' => 'https://wger.de/media/exercise-images/13/hammer.png']),
                ]),
            ]]),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->uploadedImageList as $imageUrl) {
            File::delete(public_path(parse_url($imageUrl, PHP_URL_PATH)));
        }
        parent::tearDown();
    }

    public function test_wger_is_seeded_in_the_second_public_resource_slot(): void
    {
        $providerConfigId = DB::table('provider_configs')
            ->where('provider_id', DB::table('providers')->where('slug', 'wger')->value('id'))->value('id');

        $this->assertSame($providerConfigId, DB::table('core_configs')->where('action', CoreConfig::actionFetchPublicResources2)->value('provider_config_id'));
    }

    public function test_search_merges_providers_and_skips_media_without_commercial_license(): void
    {
        $token = $this->login(static::adminEmail);

        $this->actingWithToken($token)->getJson('/api/v1/public-resources/provider')->assertOk()
            ->assertJsonCount(2, 'data.providers')
            ->assertJsonPath('data.providers.1.type', 'wger');

        $resultList = $this->actingWithToken($token)->getJson('/api/v1/public-resources?q=curl')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['free-exercise-db:Barbell_Curl', 'wger:12'], array_column($resultList, 'key'));
        $this->assertContains('Curl de bíceps con barra (Barbell Biceps Curl)', array_column($resultList, 'name'));

        $mediaList = $this->actingWithToken($token)->getJson('/api/v1/public-resources/'.rawurlencode('wger:12').'/media')->assertOk()->json('data');
        $this->assertSame(['wger:image/1', 'wger:image/3', 'wger:video/5'], array_column($mediaList, 'key'));
        $this->assertSame('Everkinetic', $mediaList[0]['attribution']['author']);
        $this->assertSame('CC-BY-SA 4', $mediaList[0]['attribution']['license']);
        $this->assertSame('https://wger.de/exercise/12/view-base', $mediaList[0]['attribution']['sourceUrl']);
        $this->assertNull($mediaList[1]['attribution']);
    }

    public function test_exercise_with_wger_media_returns_attribution(): void
    {
        $token = $this->login(static::adminEmail);
        $muscleId = $this->createMuscle($token)->assertCreated()->json('data.id');

        $exerciseId = $this->actingWithToken($token)->postJson('/api/v1/exercise', [
            'name' => 'Curl con barra',
            'muscle_id' => $muscleId,
            'description' => 'Descripcion del ejercicio',
            'recommended_rest_time' => 60,
            'is_public' => 1,
            'public_resource_key' => 'wger:image/1',
            'public_resource_media_keys' => ['wger:image/1', 'wger:video/5'],
        ])->assertCreated()->assertJsonPath('data.image_url', 'https://wger.de/media/exercise-images/12/curl-1.png')->json('data.id');

        $this->actingWithToken($token)->getJson('/api/v1/exercise/group/'.$muscleId)->assertOk()
            ->assertJsonPath('data.0.imageAttribution.author', 'Everkinetic')
            ->assertJsonPath('data.0.imageAttribution.provider', 'wger');

        $resourceList = $this->actingWithToken($token)->getJson('/api/v1/exercise/'.$exerciseId.'/resource')->assertOk()->json('data');
        $this->assertSame(['image', 'video'], array_column($resourceList, 'kind'));
        $this->assertSame(['Everkinetic', 'Goulart'], array_column(array_column($resourceList, 'attribution'), 'author'));
    }

    public function test_same_provider_cannot_fill_two_public_resource_slots(): void
    {
        $token = $this->login(static::adminEmail);
        $providerConfigId = DB::table('core_configs')->where('action', CoreConfig::actionFetchPublicResources2)->value('provider_config_id');

        $this->actingWithToken($token)->putJson('/api/v1/config/core', ['actions' => [
            CoreConfig::actionFetchPublicResources2 => $providerConfigId,
            CoreConfig::actionFetchPublicResources3 => $providerConfigId,
        ]])->assertStatus(400)->assertJsonPath("success", false);
    }

    private function createMuscle(string $token)
    {
        $response = $this->actingWithToken($token)->post('/api/v1/muscle', [
            'name' => 'Biceps',
            'description' => 'Descripcion del musculo',
            'recommended_rest_days' => 2,
            'is_public' => 1,
            'image' => UploadedFile::fake()->image('muscle.jpg'),
        ], ['Accept' => 'application/json']);
        $this->uploadedImageList[] = $response->json('data.image_url');
        return $response;
    }

    private function wgerExercise(int $id, string $nameEs, string $nameEn, array $imageList, array $videoList = []): array
    {
        return [
            'id' => $id,
            'category' => ['id' => 8, 'name' => 'Arms'],
            'muscles' => [['id' => 1, 'name' => 'Biceps brachii', 'name_en' => 'Biceps']],
            'equipment' => [['id' => 1, 'name' => 'Barbell']],
            'translations' => [
                ['language' => 4, 'name' => $nameEs, 'aliases' => []],
                ['language' => 2, 'name' => $nameEn, 'aliases' => [['alias' => 'Curl']]],
            ],
            'images' => $imageList,
            'videos' => $videoList,
        ];
    }

    private function wgerMedia(int $id, int $licenseId, string $author, array $extra): array
    {
        return $extra + [
            'id' => $id,
            'license' => $licenseId,
            'license_title' => '',
            'license_object_url' => '',
            'license_author' => $author,
            'license_author_url' => '',
            'author_history' => $author ? [$author] : [],
        ];
    }
}
