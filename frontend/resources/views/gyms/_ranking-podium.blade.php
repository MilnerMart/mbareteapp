{{-- Podio de una temporada (el primero al medio). Recibe $podiumList. --}}
<ol class="ranking-podium" aria-label="Podio">
    @foreach ($podiumList as $place)
        <li @class(['ranking-podium-place', $place['medalClass'], 'is-viewer' => $place['isViewer']])>
            <span class="ranking-podium-avatar">
                <img src="{{ $place['imageUrl'] }}" alt="">
                <span class="ranking-podium-medal" aria-hidden="true">{{ $place['position'] }}</span>
            </span>
            <strong class="ranking-podium-name">{{ $place['name'] }}</strong>
            <span class="ranking-podium-label">{{ $place['podiumLabel'] }}</span>
            <span class="ranking-podium-points"><i class="fa-solid fa-dumbbell" aria-hidden="true"></i> {{ $place['scoreLabel'] }}</span>
            @if ($place['gainLabel'])
                <span class="ranking-gain">{{ $place['gainLabel'] }}</span>
            @endif
            <span class="ranking-podium-step" aria-hidden="true"></span>
        </li>
    @endforeach
</ol>
