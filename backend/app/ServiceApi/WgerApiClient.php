<?php

namespace App\ServiceApi;

use App\PublicException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WgerApiClient extends ProviderApiClient implements PublicResourceApi {

    use SearchesTextIndex;

    protected int $timeoutSeconds = 30;

    private const defaultBaseUrl = 'https://wger.de/api/v2';

    private const exerciseEndpoint = '/exerciseinfo/', licenseEndpoint = '/license/';

    private const exercisePagePath = '/exercise/%d/view-base';

    private const pageSize = 500, maxPageCount = 10;

    private const indexCacheSeconds = 86400, maxSearchLimit = 50;

    private const languageIdEs = 4, languageIdEn = 2;

    private const itemKeyPattern = '#^\d{1,10}$#';

    private const mediaKeyPattern = '#^(image|video)/\d{1,10}$#';

    private const licenseUrlPattern = '#^https?://creativecommons\.org/(licenses|publicdomain)/([a-z-]+)/#i';

    private const playableVideoCodecList = ['h264'];

    private const defaultAuthor = 'Comunidad de wger';

    private ?array $exerciseIndex = null;

    static function clientLabel(): string{
        return 'wger';
    }

    protected function buildAuthHeaders(): array{
        return [];
    }

    protected function getBaseUrl(): string{
        $baseUrl = $this->getProviderConfig()->getSetting('baseUrl') ?: $this->getProvider()->getBaseUrl();
        return rtrim($baseUrl ?: self::defaultBaseUrl, '/');
    }

    function searchPublicResources(string $query, int $limit = 10): array{
        $wordList = self::buildSearchWordListOrFail($query);
        $matchList = [];
        foreach ($this->queryExerciseIndex()['exercises'] as $itemKey => $exercise) {
            $score = self::scoreSearchMatch($wordList, $exercise['searchText'], $exercise['nameText'], [$exercise['nameEs'], $exercise['nameEn']]);
            if($score !== null){
                $matchList[] = ['key' => (string)$itemKey, 'exercise' => $exercise, 'score' => $score];
            }
        }

        usort($matchList, fn(array $a, array $b) => [$b['score'], mb_strlen($a['exercise']['name']), $a['exercise']['name']]
            <=> [$a['score'], mb_strlen($b['exercise']['name']), $b['exercise']['name']]);
        return array_map(fn(array $match) => [
            'key' => $match['key'],
            'name' => self::buildSearchName($match['exercise']),
        ], array_slice($matchList, 0, max(1, min($limit, self::maxSearchLimit))));
    }

    function listPublicResourceMedia(string $itemKey): array{
        $exercise = $this->queryExerciseOrFail($itemKey);
        $mediaList = [];
        foreach (array_keys($exercise['media']) as $mediaKey) {
            $mediaList[] = $this->buildMediaModel((int)$itemKey, $exercise, $mediaKey);
        }
        return $mediaList;
    }

    function obtainPublicResource(string $resourceKey): array{
        if(!preg_match(self::mediaKeyPattern, $resourceKey)){
            throw PublicException::validationError('Clave de recurso invalida: '.$resourceKey);
        }
        $index = $this->queryExerciseIndex();
        $exerciseId = $index['media'][$resourceKey] ?? null;
        if(!$exerciseId){
            throw PublicException::notFoundError($this->getProvider()->getName().' ya no tiene el recurso: '.$resourceKey);
        }
        return $this->buildMediaModel($exerciseId, $index['exercises'][$exerciseId], $resourceKey);
    }

    private function queryExerciseOrFail(string $itemKey): array{
        if(!preg_match(self::itemKeyPattern, $itemKey)){
            throw PublicException::validationError('Ejercicio invalido: '.$itemKey);
        }
        $exercise = $this->queryExerciseIndex()['exercises'][(int)$itemKey] ?? null;
        if(!$exercise){
            throw PublicException::notFoundError($this->getProvider()->getName().' no tiene el ejercicio: '.$itemKey);
        }
        return $exercise;
    }

    private function queryExerciseIndex(): array{
        return $this->exerciseIndex ??= Cache::remember($this->buildCacheKey('index'), self::indexCacheSeconds, function(){
            $licenseMap = $this->queryCommercialLicenseMap();
            $index = ['exercises' => [], 'media' => []];
            foreach ($this->queryExerciseList() as $exercise) {
                $mediaMap = self::buildExerciseMediaMap($exercise, $licenseMap);
                if(!$mediaMap || !is_int($exercise['id'] ?? null)){
                    continue;
                }
                $index['exercises'][$exercise['id']] = self::buildExerciseEntry($exercise, $mediaMap);
                foreach (array_keys($mediaMap) as $mediaKey) {
                    $index['media'][$mediaKey] = $exercise['id'];
                }
            }
            if(!$index['exercises']){
                throw PublicException::internalError($this->getProvider()->getName().' no devolvio ejercicios con recursos de uso comercial', 'provider.empty_dataset');
            }
            return $index;
        });
    }

    private function queryExerciseList(): array{
        $exerciseList = [];
        for ($page = 0; $page < self::maxPageCount; $page++) {
            $response = $this->getJson(self::exerciseEndpoint, ['format' => 'json', 'limit' => self::pageSize, 'offset' => $page * self::pageSize]);
            $exerciseList = [...$exerciseList, ...array_filter((array)($response['results'] ?? []), 'is_array')];
            if(empty($response['next'])){
                break;
            }
        }
        return $exerciseList;
    }

    private function queryCommercialLicenseMap(): array{
        $licenseMap = [];
        foreach ((array)($this->getJson(self::licenseEndpoint, ['format' => 'json', 'limit' => 100])['results'] ?? []) as $license) {
            $licenseInfo = is_array($license) ? self::buildCommercialLicenseInfo($license) : null;
            if($licenseInfo){
                $licenseMap[(int)$license['id']] = $licenseInfo;
            }
        }
        return $licenseMap;
    }

    private static function buildCommercialLicenseInfo(array $license): ?array{
        $url = (string)($license['url'] ?? '');
        if(!isset($license['id']) || !preg_match(self::licenseUrlPattern, $url, $match)){
            return null;
        }
        if(in_array('nc', explode('-', Str::lower($match[2])), true)){
            return null;
        }
        return [
            'name' => trim((string)($license['short_name'] ?? '')) ?: trim((string)($license['full_name'] ?? '')),
            'url' => $url,
            'requiresAttribution' => Str::lower($match[1]) === 'licenses',
        ];
    }

    private static function buildExerciseMediaMap(array $exercise, array $licenseMap): array{
        $mediaMap = [];
        foreach ([self::kindImage => 'images', self::kindVideo => 'videos'] as $kind => $field) {
            foreach ((array)($exercise[$field] ?? []) as $media) {
                $url = $kind === self::kindImage ? ($media['image'] ?? null) : ($media['video'] ?? null);
                $license = $licenseMap[(int)($media['license'] ?? 0)] ?? null;
                if(!is_array($media) || !is_int($media['id'] ?? null) || !is_string($url) || !$license){
                    continue;
                }
                if($kind === self::kindVideo && !in_array(Str::lower((string)($media['codec'] ?? '')), self::playableVideoCodecList, true)){
                    continue;
                }
                $mediaMap[$kind.'/'.$media['id']] = [
                    'kind' => $kind,
                    'url' => $url,
                    'license' => $license,
                    'title' => trim((string)($media['license_title'] ?? '')),
                    'author' => trim((string)($media['license_author'] ?? '')) ?: implode(', ', array_filter((array)($media['author_history'] ?? []), 'is_string')),
                    'authorUrl' => trim((string)($media['license_author_url'] ?? '')),
                    'sourceUrl' => trim((string)($media['license_object_url'] ?? '')),
                ];
            }
        }
        return $mediaMap;
    }

    private static function buildExerciseEntry(array $exercise, array $mediaMap): array{
        $nameMap = [];
        $aliasList = [];
        foreach ((array)($exercise['translations'] ?? []) as $translation) {
            $languageId = (int)($translation['language'] ?? 0);
            if(!in_array($languageId, [self::languageIdEs, self::languageIdEn], true) || !is_string($translation['name'] ?? null)){
                continue;
            }
            $nameMap[$languageId] = trim($translation['name']);
            foreach ((array)($translation['aliases'] ?? []) as $alias) {
                $aliasList[] = is_array($alias) ? (string)($alias['alias'] ?? '') : '';
            }
        }
        $nameEs = $nameMap[self::languageIdEs] ?? '';
        $nameEn = $nameMap[self::languageIdEn] ?? '';
        $nameText = self::normalizeText($nameEs.' '.$nameEn);
        return [
            'name' => $nameEs ?: $nameEn ?: 'Ejercicio '.$exercise['id'],
            'nameEs' => $nameEs,
            'nameEn' => $nameEn,
            'media' => $mediaMap,
            'nameText' => $nameText,
            'searchText' => $nameText.' '.self::normalizeText(implode(' ', [
                ...$aliasList,
                (string)($exercise['category']['name'] ?? ''),
                ...array_map(fn($muscle) => is_array($muscle) ? ($muscle['name'] ?? '').' '.($muscle['name_en'] ?? '') : '', (array)($exercise['muscles'] ?? [])),
                ...array_map(fn($equipment) => is_array($equipment) ? (string)($equipment['name'] ?? '') : '', (array)($exercise['equipment'] ?? [])),
            ])),
        ];
    }

    private function buildMediaModel(int $exerciseId, array $exercise, string $mediaKey): array{
        $media = $exercise['media'][$mediaKey];
        $position = array_search($mediaKey, array_keys(array_filter($exercise['media'], fn(array $item) => $item['kind'] === $media['kind'])), true) + 1;
        return [
            'key' => $mediaKey,
            'kind' => $media['kind'],
            'name' => $exercise['name'].' · '.($media['kind'] === self::kindVideo ? 'Video ' : 'Imagen ').$position,
            'url' => $media['url'],
            'attribution' => $media['license']['requiresAttribution'] ? [
                'title' => $media['title'] ?: $exercise['name'],
                'author' => $media['author'] ?: self::defaultAuthor,
                'authorUrl' => $media['authorUrl'] ?: null,
                'license' => $media['license']['name'],
                'licenseUrl' => $media['license']['url'],
                'sourceUrl' => $media['sourceUrl'] ?: $this->buildExercisePageUrl($exerciseId),
                'provider' => $this->getProvider()->getName(),
            ] : null,
        ];
    }

    private function buildExercisePageUrl(int $exerciseId): string{
        $urlInfo = parse_url($this->getBaseUrl());
        return ($urlInfo['scheme'] ?? 'https').'://'.($urlInfo['host'] ?? 'wger.de').sprintf(self::exercisePagePath, $exerciseId);
    }

    private static function buildSearchName(array $exercise): string{
        if($exercise['nameEs'] && $exercise['nameEn'] && self::normalizeText($exercise['nameEs']) !== self::normalizeText($exercise['nameEn'])){
            return $exercise['nameEs'].' ('.$exercise['nameEn'].')';
        }
        return $exercise['name'];
    }

    private function buildCacheKey(string $suffix): string{
        return 'wger:'.$this->getProviderConfig()->getEntityId().':'.$suffix;
    }
}
