{{-- Como le va en la temporada a quien esta mirando. Recibe $viewer. --}}
@if ($viewer['statList'])
    <div class="ranking-viewer">
        <div class="ranking-viewer-header">
            <h3>{{ $viewer['title'] }}</h3>
            <div class="ranking-viewer-score">
                <strong>{{ $viewer['positionLabel'] }}</strong>
                <span><i class="fa-solid fa-dumbbell" aria-hidden="true"></i> {{ $viewer['scoreLabel'] }}</span>
                @if ($viewer['gainLabel'])
                    <span class="ranking-gain">{{ $viewer['gainLabel'] }}</span>
                @endif
            </div>
        </div>
        <dl class="ranking-viewer-stats">
            @foreach ($viewer['statList'] as $stat)
                <div>
                    <dt><i class="{{ $stat['icon'] }}" aria-hidden="true"></i> {{ $stat['label'] }}</dt>
                    <dd>{{ $stat['value'] }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($viewer['awardTitleList'])
            <ul class="ranking-viewer-awards" aria-label="Tus premios">
                @foreach ($viewer['awardTitleList'] as $awardTitle)
                    <li><i class="fa-solid fa-award" aria-hidden="true"></i> {{ $awardTitle }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@elseif ($viewer['reasonText'])
    <p class="ranking-viewer-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ $viewer['reasonText'] }}</p>
@endif
