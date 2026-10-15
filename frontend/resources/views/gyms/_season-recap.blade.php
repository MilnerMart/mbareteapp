{{-- Desglose de una temporada terminada: podio, premios y como le fue a quien mira. Recibe $recap, $seasonLinkList y $openRecap. --}}
<div class="modal fade" id="seasonRecapModal" tabindex="-1" aria-labelledby="seasonRecapTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content season-recap">
            <div class="modal-header">
                <div class="season-recap-heading">
                    <span class="season-recap-eyebrow">{{ $recap['title'] }} · {{ $recap['dateLabel'] }}</span>
                    <h2 class="modal-title" id="seasonRecapTitle">Premios</h2>
                    <p>{{ $recap['subtitle'] }}</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                @if ($recap['podiumList'])
                    @include('gyms._ranking-podium', ['podiumList' => $recap['podiumList']])
                @else
                    <p class="ranking-empty">Nadie sumo puntos en esta temporada.</p>
                @endif

                @if ($recap['awardList'])
                    <ul class="season-awards">
                        @foreach ($recap['awardList'] as $award)
                            <li class="season-award {{ $award['toneClass'] }}">
                                <span class="season-award-icon" aria-hidden="true"><i class="{{ $award['icon'] }}"></i></span>
                                <div class="season-award-body">
                                    <span class="season-award-title">{{ $award['title'] }}</span>
                                    <strong>{{ $award['name'] }}</strong>
                                    <span class="season-award-value">{{ $award['valueText'] }}</span>
                                </div>
                                <img src="{{ $award['imageUrl'] }}" alt="" class="season-award-avatar">
                            </li>
                        @endforeach
                    </ul>
                @endif

                @include('gyms._ranking-viewer', ['viewer' => $recap['viewer']])

                @if ($seasonLinkList)
                    <nav class="season-recap-links" aria-label="Otras temporadas">
                        @foreach ($seasonLinkList as $seasonLink)
                            <a href="{{ $seasonLink['url'] }}" @class(['is-active' => $seasonLink['isActive']])>{{ $seasonLink['label'] }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($openRecap)
    <script>
        // app.js (quien expone bootstrap) es un modulo: ya corrio cuando se dispara DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => window.bootstrap.Modal.getOrCreateInstance(document.getElementById('seasonRecapModal')).show());
    </script>
@endif
