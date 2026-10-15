<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Textos del ranking por temporada del gimnasio; el backend manda los numeros y los slugs de los premios.
 */
class RankingLabels
{
    // slug => [titulo, icono, color, como se cuenta el valor]
    const awardMap = [
        'consistency' => ['Maquina de constancia', 'fa-solid fa-calendar-check', 'gold', 'days'],
        'streak' => ['Racha imparable', 'fa-solid fa-fire', 'red', 'streak'],
        'strength' => ['Fuerza sobrehumana', 'fa-solid fa-dumbbell', 'purple', 'kilos'],
        'endurance' => ['Motor incansable', 'fa-solid fa-stopwatch', 'blue', 'minutes'],
        'discipline' => ['Disciplina de acero', 'fa-solid fa-clipboard-check', 'green', 'percent'],
        'comeback' => ['Gran remontada', 'fa-solid fa-arrow-trend-up', 'teal', 'comeback'],
    ];

    const podiumLabelMap = [1 => 'Campeon', 2 => 'Subcampeon', 3 => 'Tercer puesto'];

    const femalePodiumLabelMap = [1 => 'Campeona', 2 => 'Subcampeona'];

    const medalClassMap = [1 => 'is-gold', 2 => 'is-silver', 3 => 'is-bronze'];

    const celebrationTitleMap = [1 => '¡Eres el Campeon!', 2 => '¡Eres el Subcampeon!', 3 => '¡Tercer puesto!'];

    const femaleCelebrationTitleMap = [1 => '¡Eres la Campeona!', 2 => '¡Eres la Subcampeona!'];

    const finishOrdinalMap = [1 => 'primero', 2 => 'segundo', 3 => 'tercero'];

    const femaleFinishOrdinalMap = [1 => 'primera', 2 => 'segunda', 3 => 'tercera'];

    // carrera por la punta: el grafico mide raceWidth x raceHeight y deja a la derecha lugar para la foto y el nombre
    const raceWidth = 334, raceHeight = 206, racePlotWidth = 240, raceTop = 14, raceBottom = 182, raceMaxPoints = 60, raceAvatarRadius = 11, raceAvatarGap = 25;

    // filas de la tabla final que se ven antes de "Ver los N alumnos"
    const recapVisibleRows = 6;

    const confirmSeasonWord = 'confirmar';

    const defaultSeasonDays = 30;

    // atajos debajo de las fechas: calculan el fin a partir del inicio
    const seasonPresetList = [
        ['label' => '1 semana', 'days' => 7, 'months' => 0],
        ['label' => '2 semanas', 'days' => 14, 'months' => 0],
        ['label' => '1 mes', 'days' => 0, 'months' => 1],
    ];

    const reasonTextMap = [
        'owner' => 'Eres el entrenador: tus alumnos compiten y tu sigues el ranking.',
        'private' => 'Activa "Mostrarme en la lista de alumnos" para entrar al ranking.',
        'absent' => 'No participaste en esta temporada.',
    ];

    static function buildPanel(array $ranking, callable $seasonUrl): array
    {
        $current = $ranking['current'] ?? null;
        $recap = $ranking['recap'] ?? null;
        $closedNumberList = $ranking['closedSeasonNumbers'] ?? [];
        $canManage = (bool)($ranking['canManage'] ?? false);
        return [
            'gymId' => $ranking['gym']['id'],
            'isRankingPublic' => (bool)($ranking['gym']['isRankingPublic'] ?? false),
            'canManage' => $canManage,
            'current' => $current ? self::buildSeason($current, 'Tu temporada') : null,
            'noSeasonText' => $canManage
                ? 'No hay una temporada en curso. Iniciala para que tus alumnos empiecen a sumar puntos.'
                : 'El entrenador todavia no inicio una nueva temporada.',
            'seasonForm' => $canManage ? self::buildSeasonForm($ranking['gym']['id'], $current) : null,
            'recap' => $recap ? self::buildRecap($recap, $current, $canManage) : null,
            'recapButtonLabel' => $recap ? 'Premios de la temporada '.$recap['number'] : null,
            'openRecap' => (bool)($ranking['openRecap'] ?? false),
            // con una sola temporada terminada no hay a donde ir
            'seasonLinkList' => count($closedNumberList) > 1 ? array_map(fn(int $number) => [
                'label' => 'Temporada '.$number,
                'url' => $seasonUrl($number),
                'isActive' => $recap && $recap['number'] === $number,
            ], $closedNumberList) : [],
        ];
    }

    private static function buildSeason(array $season, string $viewerTitle): array
    {
        $standingList = $season['standings'] ?? [];
        $isActive = ($season['status'] ?? null) === 'active';
        $rowList = array_map(fn(array $standing) => self::buildRow($standing, $season['viewer']['standing']['userId'] ?? null, $isActive), $standingList);
        $podiumList = array_values(array_filter($rowList, fn(array $row) => $row['isPodium']));
        $viewerId = $season['viewer']['standing']['userId'] ?? null;
        $awardList = array_map(fn(array $award) => self::buildAward($award, $viewerId), array_values(array_filter(
            $season['awards'] ?? [], fn(array $award) => isset(self::awardMap[$award['slug']]))));

        $status = $season['status'] ?? 'ended';
        return [
            'title' => 'Temporada '.$season['number'],
            'subtitle' => 'Lo mejor de los '.$season['days'].' dias de la temporada',
            'dateLabel' => 'Del '.Carbon::parse($season['startsOn'])->format('d/m').' al '.Carbon::parse($season['endsOn'])->format('d/m/Y'),
            'statusLabel' => match ($status) {
                'active' => $season['daysLeft'] === 1 ? 'Ultimo dia' : 'Quedan '.$season['daysLeft'].' dias',
                'scheduled' => $season['daysToStart'] === 1 ? 'Empieza mañana' : 'Empieza en '.$season['daysToStart'].' dias',
                default => 'Terminada',
            },
            'isScheduled' => $status === 'scheduled',
            'scheduledText' => 'La temporada empieza el '.Carbon::parse($season['startsOn'])->format('d/m/Y').'. Desde ese dia cada entrenamiento suma.',
            'rowList' => $rowList,
            // el primero va al medio del podio
            'podiumList' => array_values(array_filter([$podiumList[1] ?? null, $podiumList[0] ?? null, $podiumList[2] ?? null])),
            'awardList' => $awardList,
            'viewer' => self::buildViewer($season['viewer'] ?? [], count($standingList), $awardList, $isActive) + ['title' => $viewerTitle],
            'emptyText' => $standingList
                ? 'Nadie gano fuerza todavia. ¡El primero que entrene se queda con la cima!'
                : 'Todavia no hay alumnos en el ranking: participan los que eligen mostrarse en la lista de alumnos.',
        ];
    }

    // sin temporada se inicia con las dos fechas; programada se cambian las dos; en curso solo el fin y con confirmacion escrita
    private static function buildSeasonForm(int $gymId, ?array $current): array
    {
        $today = CarbonImmutable::now(CalendarMonth::timezone)->toDateString();
        if(!$current){
            return [
                'title' => 'Nueva temporada',
                'hint' => 'Elige cuando empieza y cuando termina. Tus alumnos ganan fuerza con las rutinas que les asignas.',
                'action' => route('gym.season.start', $gymId),
                'isUpdate' => false,
                'startsOn' => old('starts_on', $today),
                'endsOn' => old('ends_on', CarbonImmutable::parse($today)->addDays(self::defaultSeasonDays - 1)->toDateString()),
                'minDate' => $today,
                'isStartLocked' => false,
                'buttonLabel' => 'Iniciar temporada',
                'confirmWord' => null,
                'confirmTitle' => 'Iniciar temporada',
                'confirmText' => 'La temporada queda registrada y el ranking arranca en la fecha de inicio.',
                'presetList' => self::seasonPresetList,
            ];
        }
        $isActive = ($current['status'] ?? null) === 'active';
        return [
            'title' => 'Fechas de la temporada '.$current['number'],
            'hint' => $isActive
                ? 'La temporada ya empezo: solo puedes cambiar la fecha de fin.'
                : 'Todavia no empezo: puedes cambiar las dos fechas.',
            'action' => route('gym.season.update', $gymId),
            'isUpdate' => true,
            'startsOn' => $isActive ? $current['startsOn'] : old('starts_on', $current['startsOn']),
            'endsOn' => old('ends_on', $current['endsOn']),
            'minDate' => $today,
            'isStartLocked' => $isActive,
            'buttonLabel' => 'Guardar fechas',
            'confirmWord' => $isActive ? self::confirmSeasonWord : null,
            'confirmTitle' => 'Cambiar la temporada '.$current['number'],
            'confirmText' => $isActive
                ? 'Vas a cambiar la fecha de fin de la temporada en curso: el ranking cambiara para tus alumnos.'
                : 'La temporada empezara y terminara en las nuevas fechas.',
            'presetList' => self::seasonPresetList,
        ];
    }

    private static function buildRow(array $standing, ?int $viewerId, bool $isActive): array
    {
        $position = (int)$standing['position'];
        $isPodium = (bool)($standing['isPodium'] ?? false);
        return [
            'userId' => (int)$standing['userId'],
            'position' => $position,
            'name' => self::fullName($standing['user']),
            'shortName' => trim($standing['user']['name'] ?? '') ?: 'Alumno',
            'points' => (int)$standing['points'],
            'imageUrl' => self::imageUrl($standing['user']),
            'scoreValue' => self::formatNumber((int)$standing['points']),
            'scoreLabel' => self::formatScore((int)$standing['points']),
            'gainLabel' => self::gainLabel($standing, $isActive),
            'streakLabel' => self::streakLabel($standing, $isActive),
            'kilosLabel' => self::formatKilos((int)$standing['kilos']),
            'isPodium' => $isPodium,
            'podiumLabel' => $isPodium ? self::podiumLabel($position, $standing['user']['gender'] ?? null) : null,
            'medalClass' => $isPodium ? self::medalClassMap[$position] : null,
            'isViewer' => $viewerId !== null && (int)$standing['userId'] === $viewerId,
        ];
    }

    private static function buildAward(array $award, ?int $viewerId): array
    {
        [$title, $icon, $tone, $valueKind] = self::awardMap[$award['slug']];
        $value = (int)$award['value'];
        return [
            'slug' => $award['slug'],
            'isViewer' => $viewerId !== null && (int)$award['userId'] === $viewerId,
            'title' => $title,
            'icon' => $icon,
            'toneClass' => 'is-'.$tone,
            'name' => self::fullName($award['user']),
            'imageUrl' => self::imageUrl($award['user']),
            'valueText' => match ($valueKind) {
                'days' => $value.' dias entrenados',
                'streak' => $value.' dias seguidos sin aflojar',
                'kilos' => self::formatKilos($value).' levantados',
                'minutes' => ProfileLabels::formatMinutes($value).' entrenando',
                'percent' => $value.'% de las rutinas completas',
                default => '+'.self::formatScore($value).' en la segunda mitad',
            },
        ];
    }

    private static function buildViewer(array $viewer, int $participantCount, array $awardList, bool $isActive): array
    {
        $standing = $viewer['standing'] ?? null;
        if(!$standing){
            return ['statList' => [], 'awardTitleList' => [], 'reasonText' => self::reasonTextMap[$viewer['reason'] ?? ''] ?? null];
        }
        $awardSlugList = $viewer['awardSlugs'] ?? [];
        return [
            'positionLabel' => '#'.$standing['position'].' de '.$participantCount,
            'scoreLabel' => self::formatScore((int)$standing['points']),
            'gainLabel' => self::gainLabel($standing, $isActive),
            'statList' => array_merge($isActive ? [
                ['icon' => 'fa-solid fa-fire', 'label' => 'Racha actual', 'value' => self::formatDays((int)($standing['currentStreak'] ?? 0))],
            ] : [], [
                ['icon' => 'fa-solid fa-fire-flame-curved', 'label' => 'Mejor racha', 'value' => self::formatDays((int)$standing['bestStreak'])],
                ['icon' => 'fa-solid fa-dumbbell', 'label' => 'Kilos levantados', 'value' => self::formatKilos((int)$standing['kilos'])],
                ['icon' => 'fa-solid fa-stopwatch', 'label' => 'Tiempo entrenado', 'value' => ProfileLabels::formatMinutes((int)$standing['minutes'])],
                ['icon' => 'fa-solid fa-clipboard-check', 'label' => 'Rutinas completas',
                    'value' => $standing['completedSessions'].' de '.$standing['sessions'].($standing['sessions'] ? ' ('.$standing['completionPercent'].'%)' : '')],
            ]),
            'awardTitleList' => array_column(array_filter($awardList, fn(array $award) => in_array($award['slug'], $awardSlugList, true)), 'title'),
            'reasonText' => null,
        ];
    }

    // la temporada terminada se recorre por pasos: festejo (si subio al podio), carrera, podio, premios y como le fue
    private static function buildRecap(array $season, ?array $current, bool $canManage): array
    {
        $recap = self::buildSeason($season, 'Tu temporada '.$season['number']);
        $standingList = $season['standings'] ?? [];
        $standing = $season['viewer']['standing'] ?? null;
        $participantCount = count($standingList);
        $sessionCount = array_sum(array_column($standingList, 'sessions'));
        $tableList = array_values(array_filter($recap['rowList'], fn(array $row) => !$row['isPodium']));
        $tableList = array_map(fn(array $row, int $index) => $row + ['isCollapsed' => $index >= self::recapVisibleRows && !$row['isViewer']],
            $tableList, array_keys($tableList));
        $hasCollapsed = in_array(true, array_column($tableList, 'isCollapsed'), true);

        return array_merge($recap, [
            'endedTitle' => '¡Termino la temporada '.$season['number'].'!',
            'endedSubtitle' => $recap['dateLabel'].' · '.self::formatDays((int)$season['days']),
            'summaryList' => [
                ['value' => self::formatNumber($participantCount), 'label' => $participantCount === 1 ? 'alumno compitio' : 'alumnos compitieron'],
                ['value' => self::formatNumber($sessionCount), 'label' => $sessionCount === 1 ? 'entrenamiento' : 'entrenamientos'],
            ],
            'race' => self::buildRace($season['race'] ?? [], $recap['rowList'], $season['startsOn']),
            'tableList' => $tableList,
            'tableCountLabel' => $participantCount === 1 ? '1 alumno' : $participantCount.' alumnos',
            'moreRowsLabel' => $hasCollapsed ? 'Ver los '.$participantCount.' alumnos' : null,
            'celebration' => $standing ? self::buildCelebration($standing, $participantCount, $recap['viewer']['awardTitleList']) : null,
            'viewer' => $recap['viewer'] + self::buildRecapViewer($season['viewer'] ?? [], $recap['rowList'], $recap['awardList']),
            'nextSeason' => self::buildNextSeason($current, $canManage),
            'summaryButtonLabel' => $standing ? 'Ver como me fue' : 'Seguir',
        ]);
    }

    private static function buildCelebration(array $standing, int $participantCount, array $awardTitleList): ?array
    {
        $position = (int)$standing['position'];
        if(!($standing['isPodium'] ?? false)){
            return null;
        }
        $isFemale = ($standing['user']['gender'] ?? null) === 'female';
        return [
            'position' => $position,
            'medalClass' => self::medalClassMap[$position],
            'isChampion' => $position === 1,
            'name' => self::fullName($standing['user']),
            'imageUrl' => self::imageUrl($standing['user']),
            'title' => $isFemale ? (self::femaleCelebrationTitleMap[$position] ?? self::celebrationTitleMap[$position]) : self::celebrationTitleMap[$position],
            'text' => 'Terminaste '.($isFemale ? self::femaleFinishOrdinalMap[$position] : self::finishOrdinalMap[$position]).' entre '
                .$participantCount.' alumnos con '.self::formatScore((int)$standing['points']).'.',
            'statList' => [
                ['value' => self::formatNumber((int)$standing['trainingDays']), 'label' => (int)$standing['trainingDays'] === 1 ? 'dia entrenado' : 'dias entrenados'],
                ['value' => self::formatDays((int)$standing['bestStreak']), 'label' => 'mejor racha'],
                ['value' => self::formatNumber((int)$standing['kilos']), 'label' => 'kg levantados'],
            ],
            'awardTitleList' => $awardTitleList,
        ];
    }

    private static function buildRecapViewer(array $viewer, array $rowList, array $awardList): array
    {
        $standing = $viewer['standing'] ?? null;
        $extra = ['name' => null, 'imageUrl' => null, 'gap' => null, 'previous' => null, 'wonAwardTitle' => null,
            'wonAwardList' => array_values(array_filter($awardList, fn(array $award) => $award['isViewer']))];
        if(!$standing){
            return $extra;
        }
        $extra['name'] = self::fullName($standing['user']);
        $extra['imageUrl'] = self::imageUrl($standing['user']);
        $extra['wonAwardTitle'] = count($extra['wonAwardList']) === 1 ? 'Ganaste un premio' : 'Ganaste '.count($extra['wonAwardList']).' premios';

        $pointsByPosition = array_column($rowList, 'points', 'position');
        $third = collect($rowList)->first(fn(array $row) => $row['position'] === 3 && $row['isPodium']);
        $points = (int)$standing['points'];
        if($third && !($standing['isPodium'] ?? false)){
            $thirdPoints = $pointsByPosition[3];
            $maxPoints = max($pointsByPosition[1], 1);
            $missing = $thirdPoints - $points;
            $extra['gap'] = [
                'fillPercent' => round(100 * $points / $maxPoints, 1),
                'markerPercent' => round(100 * $thirdPoints / $maxPoints, 1),
                'markerLabel' => 'Podio: '.self::formatNumber($thirdPoints),
                'text' => $missing > 0
                    ? 'Te faltaron '.self::formatScore($missing).' para subir al podio.'
                    : 'Empataste en fuerza con el tercer puesto: el desempate fue por dias entrenados.',
            ];
        }

        $previous = $viewer['previous'] ?? null;
        if($previous){
            $diff = (int)$previous['position'] - (int)$standing['position'];
            $places = abs($diff).' puesto'.(abs($diff) === 1 ? '' : 's');
            $was = 'en la temporada '.$previous['number'].' fuiste #'.$previous['position'];
            $extra['previous'] = match (true) {
                $diff > 0 => ['text' => 'Subiste '.$places.': '.$was, 'icon' => 'fa-solid fa-arrow-trend-up', 'toneClass' => 'is-up'],
                $diff < 0 => ['text' => 'Bajaste '.$places.': '.$was, 'icon' => 'fa-solid fa-arrow-trend-down', 'toneClass' => 'is-down'],
                default => ['text' => 'Repetiste el puesto #'.$previous['position'].' de la temporada '.$previous['number'], 'icon' => 'fa-solid fa-equals', 'toneClass' => 'is-same'],
            };
        }
        return $extra;
    }

    private static function buildNextSeason(?array $current, bool $canManage): array
    {
        if(!$current){
            return [
                'title' => 'Proxima temporada',
                'text' => $canManage ? 'Cuando quieras, inicia la proxima temporada desde el ranking.' : 'El entrenador todavia no inicio una nueva temporada.',
            ];
        }
        $title = 'Temporada '.$current['number'];
        if(($current['status'] ?? null) === 'scheduled'){
            $startsOn = Carbon::parse($current['startsOn'])->format('d/m/Y');
            return [
                'title' => $title.' · '.($current['daysToStart'] === 1 ? 'Empieza mañana' : 'Empieza en '.$current['daysToStart'].' dias'),
                'text' => 'La temporada empieza el '.$startsOn.'. Desde ese dia cada entrenamiento suma.',
            ];
        }
        return [
            'title' => $title.' · En curso',
            'text' => $canManage ? 'Tus alumnos ya estan sumando fuerza.' : 'Ya estas sumando fuerza: cada entrenamiento cuenta.',
        ];
    }

    // fuerza acumulada dia a dia de los del podio; marca cuando el campeon paso al frente por ultima vez
    private static function buildRace(array $lineList, array $rowList, string $startsOn): ?array
    {
        $rowMap = array_column(array_filter($rowList, fn(array $row) => $row['isPodium']), null, 'userId');
        $lineList = array_values(array_filter($lineList, fn(array $line) => isset($rowMap[$line['userId']])));
        $dayCount = count($lineList[0]['points'] ?? []);
        $maxPoints = $lineList ? max(array_map(fn(array $line) => (int)end($line['points']), $lineList)) : 0;
        if(count($lineList) < 2 || $dayCount < 2 || $maxPoints <= 0){
            return null;
        }

        [$changeCount, $passIndex] = self::countLeadChanges($lineList, $dayCount);
        $stride = (int)ceil($dayCount / self::raceMaxPoints);
        $indexList = array_unique(array_merge(range(0, $dayCount - 1, $stride), [$dayCount - 1], $passIndex === null ? [] : [$passIndex]));
        sort($indexList);

        $step = self::niceStep($maxPoints / 3);
        $topValue = $step * (int)ceil($maxPoints / $step);
        $x = fn(int $index) => round($index / ($dayCount - 1) * self::racePlotWidth, 1);
        $y = fn(int $value) => round(self::raceBottom - $value / $topValue * (self::raceBottom - self::raceTop), 1);
        $champion = $rowMap[$lineList[0]['userId']];

        $seriesList = array_map(function(array $line) use ($rowMap, $indexList, $x, $y){
            $row = $rowMap[$line['userId']];
            $last = (int)end($line['points']);
            return [
                'name' => $row['shortName'],
                'medalClass' => $row['medalClass'],
                'imageUrl' => $row['imageUrl'],
                'path' => implode(' ', array_map(fn(int $index, int $order) => ($order ? 'L' : 'M').$x($index).' '.$y((int)$line['points'][$index]),
                    $indexList, array_keys($indexList))),
                'endY' => $y($last),
                'avatarY' => $y($last),
            ];
        }, $lineList);
        // la foto va en la punta de cada linea; si dos quedan encimadas se separan y un trazo corto las une a su linea
        usort($seriesList, fn(array $a, array $b) => $a['avatarY'] <=> $b['avatarY']);
        foreach ($seriesList as $order => &$series) {
            if($order){
                $series['avatarY'] = max($series['avatarY'], $seriesList[$order - 1]['avatarY'] + self::raceAvatarGap);
            }
        }
        unset($series);
        $overflow = max(end($seriesList)['avatarY'] - self::raceBottom, 0);
        $avatarX = self::racePlotWidth + self::raceAvatarRadius + 2;
        $seriesList = array_map(fn(array $series, int $order) => [
            'avatarY' => $series['avatarY'] - $overflow,
            'avatarX' => $avatarX,
            'avatarRadius' => self::raceAvatarRadius,
            'ringRadius' => self::raceAvatarRadius + 1,
            'gapRadius' => self::raceAvatarRadius + 3,
            'imageX' => $avatarX - self::raceAvatarRadius,
            'imageY' => $series['avatarY'] - $overflow - self::raceAvatarRadius,
            'imageSize' => 2 * self::raceAvatarRadius,
            'clipId' => 'seasonRaceAvatar'.$order,
            'endX' => self::racePlotWidth,
            'labelX' => $avatarX + self::raceAvatarRadius + 5,
            'labelY' => $series['avatarY'] - $overflow + 4,
        ] + $series, $seriesList, array_keys($seriesList));

        $start = Carbon::parse($startsOn);
        $pointList = array_map(function(int $index, int $order) use ($indexList, $lineList, $rowMap, $x, $start){
            $rowList = array_map(fn(array $line) => [
                'name' => $rowMap[$line['userId']]['shortName'],
                'medalClass' => $rowMap[$line['userId']]['medalClass'],
                'points' => (int)$line['points'][$index],
            ], $lineList);
            usort($rowList, fn(array $a, array $b) => $b['points'] <=> $a['points']);
            $left = $order ? ($x($indexList[$order - 1]) + $x($index)) / 2 : 0;
            $right = $order < count($indexList) - 1 ? ($x($index) + $x($indexList[$order + 1])) / 2 : self::racePlotWidth + 6;
            return [
                'x' => $x($index),
                'xPercent' => round(100 * $x($index) / self::raceWidth, 1),
                'hitX' => round($left, 1),
                'hitWidth' => round($right - $left, 1),
                'dayLabel' => 'Dia '.($index + 1).' · '.$start->copy()->addDays($index)->format('d/m'),
                'rowList' => array_map(fn(array $row) => ['name' => $row['name'], 'medalClass' => $row['medalClass'],
                    'value' => self::formatNumber($row['points'])], $rowList),
            ];
        }, $indexList, array_keys($indexList));

        $middleIndex = intdiv($dayCount - 1, 2);
        $pass = null;
        if($passIndex !== null){
            $passX = $x($passIndex);
            $passY = $y((int)$lineList[0]['points'][$passIndex]);
            $pass = [
                'x' => $passX,
                'y' => $passY,
                'textX' => min(max($passX, 66), self::racePlotWidth - 40),
                'textY' => $passY - 14 < self::raceTop + 8 ? $passY + 22 : $passY - 14,
                'label' => 'Dia '.($passIndex + 1).': pasa al frente',
            ];
        }
        $subtitle = match ($changeCount) {
            0 => $champion['shortName'].' lidero de principio a fin',
            1 => 'La cima cambio de manos 1 vez',
            default => 'La cima cambio de manos '.$changeCount.' veces',
        };
        $nameList = array_map(fn(array $line) => $rowMap[$line['userId']]['name'], $lineList);

        return [
            'width' => self::raceWidth,
            'height' => self::raceHeight,
            'plotWidth' => self::racePlotWidth,
            'top' => self::raceTop,
            'bottom' => self::raceBottom,
            'subtitle' => $subtitle,
            'ariaLabel' => 'Fuerza acumulada dia a dia del podio: '.implode(', ', array_slice($nameList, 0, -1)).' y '.end($nameList).'. '.$subtitle.'.',
            'legendList' => array_map(fn(array $line) => ['name' => $rowMap[$line['userId']]['shortName'], 'medalClass' => $rowMap[$line['userId']]['medalClass']], $lineList),
            'gridList' => array_map(fn(int $value) => ['y' => $y($value), 'labelY' => $y($value) - 4, 'label' => self::formatNumber($value)],
                range($step, $topValue, $step)),
            'axisList' => [
                ['x' => 0, 'anchor' => 'start', 'label' => 'Dia 1'],
                ['x' => $x($middleIndex), 'anchor' => 'middle', 'label' => 'Dia '.($middleIndex + 1)],
                ['x' => self::racePlotWidth, 'anchor' => 'end', 'label' => 'Dia '.$dayCount],
            ],
            'axisY' => self::raceBottom + 16,
            'seriesList' => $seriesList,
            'pointList' => $pointList,
            'tipJson' => json_encode(array_map(fn(array $point) => array_intersect_key($point, array_flip(['x', 'xPercent', 'dayLabel', 'rowList'])), $pointList),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'pass' => $pass,
        ];
    }

    // quien lleva la punta cada dia (en un empate la conserva el que ya la tenia)
    private static function countLeadChanges(array $lineList, int $dayCount): array
    {
        $championId = $lineList[0]['userId'];
        $leaderId = null;
        $changeCount = 0;
        $passIndex = null;
        for ($index = 0; $index < $dayCount; $index++) {
            $valueMap = array_column(array_map(fn(array $line) => ['userId' => $line['userId'], 'value' => (int)$line['points'][$index]], $lineList), 'value', 'userId');
            $best = max($valueMap);
            if($best <= 0 || ($leaderId !== null && $valueMap[$leaderId] === $best)){
                continue;
            }
            $newLeaderId = array_search($best, $valueMap, true);
            if($leaderId !== null){
                $changeCount++;
                if($newLeaderId === $championId){
                    $passIndex = $index;
                }
            }
            $leaderId = $newLeaderId;
        }
        return [$changeCount, $passIndex];
    }

    private static function niceStep(float $rough): int
    {
        $magnitude = 10 ** (int)floor(log10(max($rough, 1)));
        foreach ([1, 2, 5, 10] as $factor) {
            if($rough <= $factor * $magnitude){
                return (int)($factor * $magnitude);
            }
        }
        return (int)(10 * $magnitude);
    }

    private static function podiumLabel(int $position, ?string $gender): string
    {
        return $gender === 'female' ? (self::femalePodiumLabelMap[$position] ?? self::podiumLabelMap[$position]) : self::podiumLabelMap[$position];
    }

    // en la temporada en curso se ve la racha viva; en una terminada, la mejor que tuvo
    private static function streakLabel(array $standing, bool $isActive): string
    {
        $streak = (int)($isActive ? ($standing['currentStreak'] ?? 0) : $standing['bestStreak']);
        if(!$streak){
            return 'Sin racha';
        }
        return ($isActive ? 'Racha de ' : 'Mejor racha: ').self::formatDays($streak);
    }

    // lo que gano hoy, para que se note cuando sube en el ranking
    private static function gainLabel(array $standing, bool $isActive): ?string
    {
        $todayPoints = (int)($standing['todayPoints'] ?? 0);
        return $isActive && $todayPoints > 0 ? '+'.self::formatNumber($todayPoints).' hoy' : null;
    }

    static function awardTitle(string $slug): string
    {
        return self::awardMap[$slug][0] ?? 'Premio';
    }

    static function formatScore(int $points): string
    {
        return self::formatNumber($points).' de fuerza';
    }

    private static function formatNumber(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private static function formatDays(int $days): string
    {
        return $days.' dia'.($days === 1 ? '' : 's');
    }

    private static function formatKilos(int $kilos): string
    {
        return self::formatNumber($kilos).' kg';
    }

    private static function fullName(array $user): string
    {
        return trim(($user['name'] ?? '').' '.($user['last_name'] ?? '')) ?: 'Alumno';
    }

    private static function imageUrl(array $user): string
    {
        return $user['profile_image_url'] ?? asset('images/chavukuBiceps.png');
    }
}
