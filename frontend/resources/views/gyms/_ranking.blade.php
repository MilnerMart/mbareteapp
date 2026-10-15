{{-- Ranking de la temporada en curso y acceso a los premios de la anterior. Recibe $ranking (RankingLabels::buildPanel). --}}
@php
    $current = $ranking['current'];
    $recap = $ranking['recap'];
    $seasonForm = $ranking['seasonForm'];
@endphp
<section class="ranking" aria-labelledby="rankingTitle">
    <div class="ranking-header">
        <div>
            <span class="gym-eyebrow"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i>&nbsp;Ranking del gimnasio</span>
            @if ($current)
                <h2 id="rankingTitle" class="ranking-title">{{ $current['title'] }}</h2>
                <span class="ranking-dates">{{ $current['dateLabel'] }} · <strong>{{ $current['statusLabel'] }}</strong></span>
            @else
                <h2 id="rankingTitle" class="ranking-title">Sin temporada en curso</h2>
            @endif
        </div>
        @if ($recap)
            <button type="button" class="btn ranking-recap-btn" data-bs-toggle="modal" data-bs-target="#seasonRecapModal">
                <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                <span>{{ $ranking['recapButtonLabel'] }}</span>
            </button>
        @endif
    </div>

    @if ($ranking['canManage'])
        <form method="POST" action="{{ route('gym.ranking.visibility', $ranking['gymId']) }}" class="ranking-visibility-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="is_public" value="{{ $ranking['isRankingPublic'] ? 0 : 1 }}">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="rankingVisibility"
                    @checked($ranking['isRankingPublic']) onchange="this.form.submit()">
                <label class="form-check-label" for="rankingVisibility">Mostrar el ranking a mis alumnos</label>
            </div>
            <small>Participan los alumnos que se muestran en la lista. Suman puntos con las rutinas que les asignas.</small>
        </form>

        <form method="POST" action="{{ $seasonForm['action'] }}" class="ranking-season-form"
            @if ($seasonForm['confirmWord'])
                data-confirm-word="{{ $seasonForm['confirmWord'] }}"
                data-confirm-button="Guardar"
            @else
                data-confirm-submit
            @endif
            data-confirm-title="{{ $seasonForm['confirmTitle'] }}"
            data-confirm-text="{{ $seasonForm['confirmText'] }}">
            @csrf
            @if ($seasonForm['isUpdate'])
                @method('PUT')
            @endif
            <h3>{{ $seasonForm['title'] }}</h3>
            <p>{{ $seasonForm['hint'] }}</p>
            <div class="ranking-season-dates">
                <div>
                    <label for="seasonStartsOn" class="form-label">Empieza</label>
                    <input type="date" id="seasonStartsOn" name="starts_on" class="form-control" value="{{ $seasonForm['startsOn'] }}"
                        min="{{ $seasonForm['minDate'] }}" required data-preset-start @disabled($seasonForm['isStartLocked'])>
                </div>
                <div>
                    <label for="seasonEndsOn" class="form-label">Termina</label>
                    <input type="date" id="seasonEndsOn" name="ends_on" class="form-control" value="{{ $seasonForm['endsOn'] }}"
                        min="{{ $seasonForm['minDate'] }}" required data-preset-end>
                </div>
                <button type="submit" class="btn btn-primary">{{ $seasonForm['buttonLabel'] }}</button>
            </div>
            <div class="ranking-season-presets" role="group" aria-label="Duracion de la temporada">
                @foreach ($seasonForm['presetList'] as $preset)
                    <button type="button" class="btn ranking-preset-btn" data-date-preset
                        data-preset-days="{{ $preset['days'] }}" data-preset-months="{{ $preset['months'] }}">{{ $preset['label'] }}</button>
                @endforeach
            </div>
        </form>
    @endif

    @if (!$current)
        @unless ($ranking['canManage'])
            <p class="ranking-empty">{{ $ranking['noSeasonText'] }}</p>
        @endunless
    @elseif ($current['isScheduled'])
        <p class="ranking-empty">{{ $current['scheduledText'] }}</p>
    @else
        @if ($current['podiumList'])
            @include('gyms._ranking-podium', ['podiumList' => $current['podiumList']])
        @else
            <p class="ranking-empty">{{ $current['emptyText'] }}</p>
        @endif

        @include('gyms._ranking-viewer', ['viewer' => $current['viewer']])

        @if ($current['rowList'])
            <ol class="ranking-list" aria-label="Tabla de posiciones">
                @foreach ($current['rowList'] as $row)
                    <li @class(['ranking-row', 'is-viewer' => $row['isViewer'], $row['medalClass']])>
                        <span class="ranking-row-position">{{ $row['position'] }}</span>
                        <img src="{{ $row['imageUrl'] }}" alt="" class="gym-student-avatar">
                        <div class="ranking-row-info">
                            <strong>{{ $row['name'] }}@if ($row['isViewer']) <small>(tu)</small>@endif</strong>
                            <span><i class="fa-solid fa-fire" aria-hidden="true"></i> {{ $row['streakLabel'] }} · {{ $row['kilosLabel'] }}</span>
                        </div>
                        <div class="ranking-row-score" aria-label="{{ $row['scoreLabel'] }}">
                            <strong><i class="fa-solid fa-dumbbell" aria-hidden="true"></i> {{ $row['scoreValue'] }}</strong>
                            @if ($row['gainLabel'])
                                <span class="ranking-gain">{{ $row['gainLabel'] }}</span>
                            @else
                                <small>fuerza</small>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    @endif
</section>

@if ($recap)
    @include('gyms._season-recap', ['recap' => $recap, 'seasonLinkList' => $ranking['seasonLinkList'], 'openRecap' => $ranking['openRecap']])
@endif
