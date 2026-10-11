<?php

namespace App\ServiceApi;

use App\PublicException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FreeExerciseDbApiClient extends ProviderApiClient implements PublicResourceApi {

    protected int $timeoutSeconds = 30;

    private const defaultBaseUrl = 'https://raw.githubusercontent.com/yuhonas/free-exercise-db/main';

    private const datasetEndpoint = '/dist/exercises.json', imagesEndpoint = '/exercises';

    private const indexCacheSeconds = 86400, maxSearchLimit = 50;

    private const itemKeyPattern = '#^[A-Za-z0-9_-]{1,120}$#';

    private const mediaKeyPattern = '#^([A-Za-z0-9_-]{1,120})/(\d{1,2})\.(jpg|jpeg|png|gif|webp)$#';

    private const imageLabelMap = ['Posicion inicial', 'Posicion final'];

    private const translationPath = 'data/free-exercise-db/es.json';

    private const stopWordList = ['de', 'del', 'con', 'en', 'la', 'el', 'los', 'las', 'y', 'a', 'al', 'para', 'por', 'un', 'una',
        'the', 'with', 'on', 'of', 'and', 'to', 'for'];

    private ?array $exerciseIndex = null;

    static function clientLabel(): string{
        return 'Free Exercise DB';
    }

    static function settingKeyList(): array{
        return ['imageBaseUrl'];
    }

    protected function buildAuthHeaders(): array{
        return [];
    }

    protected function getBaseUrl(): string{
        $baseUrl = $this->getProviderConfig()->getSetting('baseUrl') ?: $this->getProvider()->getBaseUrl();
        return rtrim($baseUrl ?: self::defaultBaseUrl, '/');
    }

    function searchPublicResources(string $query, int $limit = 10): array{
        $wordList = array_values(array_diff(explode(' ', self::normalizeText($query)), self::stopWordList, ['']));
        if(mb_strlen(implode('', $wordList)) < 2){
            throw PublicException::validationError('La busqueda debe tener al menos 2 caracteres');
        }

        $queryPhrase = implode(' ', $wordList);
        $matchList = [];
        foreach ($this->queryExerciseIndex() as $itemKey => $exercise) {
            if(!$exercise['images']){
                continue;
            }
            $score = 0;
            foreach ($wordList as $word) {
                if(!self::containsWord($exercise['searchText'], $word)){
                    continue 2;
                }
                $score += self::containsWord($exercise['nameText'], $word) ? 10 : 0;
            }
            foreach ([$exercise['name'], $exercise['nameEs']] as $name) {
                $namePhrase = implode(' ', array_diff(explode(' ', self::normalizeText($name)), self::stopWordList));
                $score += match(true) {
                    $namePhrase === $queryPhrase => 100,
                    $namePhrase !== '' && str_starts_with($namePhrase, $queryPhrase) => 20,
                    default => 0,
                };
            }
            $matchList[] = ['key' => (string)$itemKey, 'exercise' => $exercise, 'score' => $score];
        }

        usort($matchList, fn(array $a, array $b) => [$b['score'], mb_strlen(self::displayName($a['exercise'])), self::displayName($a['exercise'])]
            <=> [$a['score'], mb_strlen(self::displayName($b['exercise'])), self::displayName($b['exercise'])]);
        return array_map(fn(array $match) => [
            'key' => $match['key'],
            'name' => $match['exercise']['nameEs'] ? $match['exercise']['nameEs'].' ('.$match['exercise']['name'].')' : $match['exercise']['name'],
        ], array_slice($matchList, 0, max(1, min($limit, self::maxSearchLimit))));
    }

    function listPublicResourceMedia(string $itemKey): array{
        $exercise = $this->queryExerciseOrFail($itemKey);
        $mediaList = [];
        foreach ($exercise['images'] as $imagePath) {
            if(preg_match(self::mediaKeyPattern, $imagePath, $match) && $match[1] === $itemKey){
                $mediaList[] = $this->buildMediaModel($exercise, $imagePath, (int)$match[2]);
            }
        }
        return $mediaList;
    }

    function obtainPublicResource(string $resourceKey): array{
        if(!preg_match(self::mediaKeyPattern, $resourceKey, $match)){
            throw PublicException::validationError('Clave de recurso invalida: '.$resourceKey);
        }
        $exercise = $this->queryExerciseOrFail($match[1]);
        if(!in_array($resourceKey, $exercise['images'], true)){
            throw PublicException::notFoundError($this->getProvider()->getName().' ya no tiene el recurso: '.$resourceKey);
        }
        return $this->buildMediaModel($exercise, $resourceKey, (int)$match[2]);
    }

    private function queryExerciseOrFail(string $itemKey): array{
        if(!preg_match(self::itemKeyPattern, $itemKey)){
            throw PublicException::validationError('Ejercicio invalido: '.$itemKey);
        }
        $exercise = $this->queryExerciseIndex()[$itemKey] ?? null;
        if(!$exercise){
            throw PublicException::notFoundError($this->getProvider()->getName().' no tiene el ejercicio: '.$itemKey);
        }
        return $exercise;
    }

    private function queryExerciseIndex(): array{
        $translationFile = resource_path(self::translationPath);
        $translationVersion = File::exists($translationFile) ? File::lastModified($translationFile) : 0;
        return $this->exerciseIndex ??= Cache::remember($this->buildCacheKey('index:'.$translationVersion), self::indexCacheSeconds, function() use ($translationFile){
            $translation = File::exists($translationFile) ? (json_decode(File::get($translationFile), true) ?: []) : [];
            $index = [];
            foreach ($this->getJson(self::datasetEndpoint) as $exercise) {
                if(!is_array($exercise) || !isset($exercise['id'], $exercise['name']) || !preg_match(self::itemKeyPattern, (string)$exercise['id'])){
                    continue;
                }
                $muscleList = array_values(array_filter((array)($exercise['primaryMuscles'] ?? []), 'is_string'));
                $equipment = (string)($exercise['equipment'] ?? '');
                $nameEs = $translation['names'][$exercise['id']] ?? null;
                $nameText = self::normalizeText($exercise['name'].' '.$nameEs);
                $index[$exercise['id']] = [
                    'name' => (string)$exercise['name'],
                    'nameEs' => is_string($nameEs) && $nameEs !== '' ? $nameEs : null,
                    'images' => array_values(array_filter((array)($exercise['images'] ?? []), 'is_string')),
                    'nameText' => $nameText,
                    'searchText' => $nameText.' '.self::normalizeText(implode(' ', [
                        ...$muscleList,
                        ...array_map(fn(string $muscle) => $translation['muscles'][$muscle] ?? '', $muscleList),
                        $equipment,
                        $translation['equipment'][$equipment] ?? '',
                    ])),
                ];
            }
            if(!$index){
                throw PublicException::internalError($this->getProvider()->getName().' devolvio un listado vacio', 'provider.empty_dataset');
            }
            return $index;
        });
    }

    private function buildMediaModel(array $exercise, string $imagePath, int $position): array{
        $imageBaseUrl = rtrim($this->getProviderConfig()->getSetting('imageBaseUrl') ?: $this->getBaseUrl().self::imagesEndpoint, '/');
        return [
            'key' => $imagePath,
            'kind' => self::kindImage,
            'name' => self::displayName($exercise).' · '.(self::imageLabelMap[$position] ?? 'Imagen '.($position + 1)),
            'url' => $imageBaseUrl.'/'.implode('/', array_map('rawurlencode', explode('/', $imagePath))),
        ];
    }

    private static function displayName(array $exercise): string{
        return $exercise['nameEs'] ?? $exercise['name'];
    }

    private static function normalizeText(?string $text): string{
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string)$text))));
    }

    private static function containsWord(string $text, string $word): bool{
        if(str_contains(' '.$text, ' '.$word)){
            return true;
        }
        $singular = preg_replace('/(es|s)$/', '', $word);
        return strlen($singular) >= 3 && $singular !== $word && str_contains(' '.$text, ' '.$singular);
    }

    private function buildCacheKey(string $suffix): string{
        return 'free-exercise-db:'.$this->getProviderConfig()->getEntityId().':'.$suffix;
    }
}
