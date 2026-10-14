{{-- Ficha de solo lectura de un usuario para el admin: se inserta en el modal de la tarjeta del alumno. Recibe $data. --}}
@php
    $profile = $data['profile'];
    $progress = $data['progress'];
@endphp
<div class="user-detail">
    <div class="user-detail-hero">
        <div class="profile-picture profile-picture-static">
            <img src="{{ $profile['imageUrl'] }}" alt="Foto de {{ $profile['fullName'] }}">
        </div>
        <div>
            <h2 class="user-detail-name" id="userDetailTitle">{{ $profile['fullName'] }}</h2>
            <div class="profile-chips">
                <span class="profile-chip"><i class="fa-solid fa-medal"></i><span>{{ $profile['rankLabel'] }}</span></span>
            </div>
            <a href="{{ $data['activityUrl'] }}" class="btn gym-edit-btn user-detail-activity">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Ver registro de actividad</span>
            </a>
        </div>
    </div>

    @if ($data['pendingText'])
        <p class="user-detail-pending"><i class="fa-solid fa-user-pen" aria-hidden="true"></i> {{ $data['pendingText'] }}</p>
    @endif

    <dl class="profile-stats">
        @foreach ($profile['statList'] as $stat)
            <div class="profile-stat">
                <dt>{{ $stat['label'] }}</dt>
                <dd>{{ $stat['value'] }}</dd>
            </div>
        @endforeach
    </dl>

    <dl class="user-detail-info">
        @foreach ($data['infoList'] as $info)
            <div>
                <dt>{{ $info['label'] }}</dt>
                <dd>{{ $info['value'] }}</dd>
            </div>
        @endforeach
    </dl>

    <h3 class="profile-section-title">Rutinas asignadas</h3>
    @if (empty($data['routines']))
        <p class="profile-favorite-empty">No tiene rutinas asignadas.</p>
    @else
        <ul class="user-detail-routines">
            @foreach ($data['routines'] as $routine)
                <li><a href="{{ $routine['url'] }}">{{ $routine['name'] }}</a></li>
            @endforeach
        </ul>
    @endif

    <h3 class="profile-section-title">Nivel</h3>
    <div class="profile-rank">
        <i class="fa-solid fa-medal profile-rank-icon" aria-hidden="true"></i>
        <div class="profile-rank-info">
            <span class="profile-rank-name">{{ $progress['rankLabel'] }}</span>
            <span class="profile-rank-points">{{ $progress['points'] }} puntos</span>
        </div>
    </div>
    <div class="profile-rank-bar" role="progressbar" aria-label="Avance al siguiente rango"
        aria-valuenow="{{ $progress['progressPercent'] }}" aria-valuemin="0" aria-valuemax="100">
        <span style="width: {{ $progress['progressPercent'] }}%"></span>
    </div>
    <p class="profile-rank-next">{{ $progress['nextText'] }}</p>
    <dl class="profile-metrics">
        @foreach ($progress['metricList'] as $metric)
            <div class="profile-metric">
                <i class="{{ $metric['icon'] }}" aria-hidden="true"></i>
                <dt>{{ $metric['label'] }}</dt>
                <dd>{{ $metric['value'] }}</dd>
            </div>
        @endforeach
    </dl>

    <h3 class="profile-section-title">Favoritos</h3>
    <div class="profile-favorites">
        @foreach ($data['favorites'] as $favorite)
            <article class="profile-favorite">
                <div class="profile-favorite-media media-attribution-host">
                    @if ($favorite['imageUrl'])
                        <img src="{{ $favorite['imageUrl'] }}" alt="{{ $favorite['name'] }}">
                        @include('layouts._partials.media-attribution', ['attribution' => $favorite['imageAttribution']])
                    @else
                        <i class="{{ $favorite['icon'] }}" aria-hidden="true"></i>
                    @endif
                </div>
                <span class="profile-favorite-title">{{ $favorite['title'] }}</span>
                @if ($favorite['name'])
                    <a href="{{ $favorite['url'] }}" class="profile-favorite-name">{{ $favorite['name'] }}</a>
                    @if ($favorite['detail'])
                        <span class="profile-favorite-detail">{{ $favorite['detail'] }}</span>
                    @endif
                @else
                    <p class="profile-favorite-empty">{{ $favorite['emptyText'] }}</p>
                @endif
            </article>
        @endforeach
    </div>
</div>
