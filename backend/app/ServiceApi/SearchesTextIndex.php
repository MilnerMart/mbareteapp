<?php

namespace App\ServiceApi;

use App\PublicException;
use Illuminate\Support\Str;

trait SearchesTextIndex {

    private static array $stopWordList = ['de', 'del', 'con', 'en', 'la', 'el', 'los', 'las', 'y', 'a', 'al', 'para', 'por', 'un', 'una',
        'the', 'with', 'on', 'of', 'and', 'to', 'for'];

    private static function buildSearchWordListOrFail(string $query): array{
        $wordList = array_values(array_diff(explode(' ', self::normalizeText($query)), self::$stopWordList, ['']));
        if(mb_strlen(implode('', $wordList)) < 2){
            throw PublicException::validationError('La busqueda debe tener al menos 2 caracteres');
        }
        return $wordList;
    }

    private static function scoreSearchMatch(array $wordList, string $searchText, string $nameText, array $nameList): ?int{
        $score = 0;
        foreach ($wordList as $word) {
            if(!self::containsWord($searchText, $word)){
                return null;
            }
            $score += self::containsWord($nameText, $word) ? 10 : 0;
        }
        $queryPhrase = implode(' ', $wordList);
        foreach ($nameList as $name) {
            $namePhrase = implode(' ', array_diff(explode(' ', self::normalizeText($name)), self::$stopWordList));
            $score += match(true) {
                $namePhrase === $queryPhrase => 100,
                $namePhrase !== '' && str_starts_with($namePhrase, $queryPhrase) => 20,
                default => 0,
            };
        }
        return $score;
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
}
