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
            'recap' => $recap ? self::buildSeason($recap, 'Tu temporada '.$recap['number']) : null,
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
        $awardList = array_map(fn(array $award) => self::buildAward($award), array_values(array_filter(
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
                ? 'Nadie gano fuerza todavia. ¡El primero que entrene se queda con la punta!'
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
            'position' => $position,
            'name' => self::fullName($standing['user']),
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

    private static function buildAward(array $award): array
    {
        [$title, $icon, $tone, $valueKind] = self::awardMap[$award['slug']];
        $value = (int)$award['value'];
        return [
            'slug' => $award['slug'],
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
