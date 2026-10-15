{{-- Desglose de una temporada terminada, por pasos: festejo (si subio al podio), carrera por la punta, podio, premios y como le fue.
     Recibe $recap, $seasonLinkList y $openRecap. Los botones con data-season-step cambian de paso (app.js). --}}
@php
    $celebration = $recap['celebration'];
    $race = $recap['race'];
    $viewer = $recap['viewer'];
@endphp
<div class="modal fade" id="seasonRecapModal" tabindex="-1" aria-labelledby="seasonRecapTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content season-recap">
            <div class="modal-header">
                <span class="season-recap-eyebrow" id="seasonRecapTitle">{{ $recap['title'] }} · {{ $recap['dateLabel'] }}</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                @if ($celebration)
                    <section class="season-step season-celebration {{ $celebration['medalClass'] }}" data-season-panel="festejo">
                        <div class="season-celebration-avatar">
                            @if ($celebration['isChampion'])
                                <i class="fa-solid fa-crown season-celebration-crown" aria-hidden="true"></i>
                            @endif
                            <img src="{{ $celebration['imageUrl'] }}" alt="">
                            <span class="season-celebration-medal" aria-hidden="true">{{ $celebration['position'] }}</span>
                        </div>
                        <h2 tabindex="-1">{{ $celebration['title'] }}</h2>
                        <p>{{ $celebration['text'] }}</p>
                        <dl class="season-summary is-three">
                            @foreach ($celebration['statList'] as $stat)
                                <div>
                                    <dt>{{ $stat['label'] }}</dt>
                                    <dd>{{ $stat['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($celebration['awardTitleList'])
                            <div class="season-celebration-awards">
                                <span>Ademas ganaste</span>
                                <ul>
                                    @foreach ($celebration['awardTitleList'] as $awardTitle)
                                        <li><i class="fa-solid fa-award" aria-hidden="true"></i> {{ $awardTitle }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="season-step-actions">
                            <button type="button" class="btn season-btn-gold" data-season-step="inicio">Ver como fue la temporada</button>
                        </div>
                    </section>
                @endif

                <section class="season-step season-intro" data-season-panel="inicio" @if ($celebration) hidden @endif>
                    <div class="season-step-heading is-center">
                        <span class="season-step-eyebrow">{{ $recap['title'] }} · Terminada</span>
                        <h2 tabindex="-1">{{ $recap['endedTitle'] }}</h2>
                        <p>{{ $recap['endedSubtitle'] }}</p>
                    </div>

                    @if ($race)
                        <figure class="season-race" data-race="{{ $race['tipJson'] }}">
                            <figcaption>
                                <strong>Asi se peleo la cima</strong>
                                <span>{{ $race['subtitle'] }}</span>
                            </figcaption>
                            <ul class="season-race-legend">
                                @foreach ($race['legendList'] as $legend)
                                    <li><span class="season-race-swatch {{ $legend['medalClass'] }}" aria-hidden="true"></span>{{ $legend['name'] }}</li>
                                @endforeach
                            </ul>
                            <div class="season-race-plot">
                                <svg viewBox="0 0 {{ $race['width'] }} {{ $race['height'] }}" role="img" aria-label="{{ $race['ariaLabel'] }}">
                                    @foreach ($race['gridList'] as $grid)
                                        <line class="season-race-grid" x1="0" x2="{{ $race['plotWidth'] }}" y1="{{ $grid['y'] }}" y2="{{ $grid['y'] }}"></line>
                                        <text class="season-race-tick" x="0" y="{{ $grid['labelY'] }}">{{ $grid['label'] }}</text>
                                    @endforeach
                                    <line class="season-race-base" x1="0" x2="{{ $race['plotWidth'] }}" y1="{{ $race['bottom'] }}" y2="{{ $race['bottom'] }}"></line>
                                    @foreach ($race['axisList'] as $axis)
                                        <text class="season-race-tick" x="{{ $axis['x'] }}" y="{{ $race['axisY'] }}" text-anchor="{{ $axis['anchor'] }}">{{ $axis['label'] }}</text>
                                    @endforeach
                                    <line class="season-race-cross" x1="0" x2="0" y1="{{ $race['top'] }}" y2="{{ $race['bottom'] }}"></line>
                                    @foreach ($race['seriesList'] as $series)
                                        <path class="season-race-line {{ $series['medalClass'] }}" d="{{ $series['path'] }}"></path>
                                        <line class="season-race-line {{ $series['medalClass'] }}" x1="{{ $series['endX'] }}" y1="{{ $series['endY'] }}"
                                            x2="{{ $series['avatarX'] }}" y2="{{ $series['avatarY'] }}"></line>
                                        <clipPath id="{{ $series['clipId'] }}"><circle cx="{{ $series['avatarX'] }}" cy="{{ $series['avatarY'] }}" r="{{ $series['avatarRadius'] }}"></circle></clipPath>
                                        <circle class="season-race-avatar-gap" cx="{{ $series['avatarX'] }}" cy="{{ $series['avatarY'] }}" r="{{ $series['gapRadius'] }}"></circle>
                                        <image href="{{ $series['imageUrl'] }}" x="{{ $series['imageX'] }}" y="{{ $series['imageY'] }}" width="{{ $series['imageSize'] }}"
                                            height="{{ $series['imageSize'] }}" preserveAspectRatio="xMidYMid slice" clip-path="url(#{{ $series['clipId'] }})"></image>
                                        <circle class="season-race-ring {{ $series['medalClass'] }}" cx="{{ $series['avatarX'] }}" cy="{{ $series['avatarY'] }}" r="{{ $series['ringRadius'] }}"></circle>
                                        <text class="season-race-name" x="{{ $series['labelX'] }}" y="{{ $series['labelY'] }}">{{ $series['name'] }}</text>
                                    @endforeach
                                    @if ($race['pass'])
                                        <circle class="season-race-pass" cx="{{ $race['pass']['x'] }}" cy="{{ $race['pass']['y'] }}" r="7"></circle>
                                        <text class="season-race-pass-label" x="{{ $race['pass']['textX'] }}" y="{{ $race['pass']['textY'] }}" text-anchor="middle">{{ $race['pass']['label'] }}</text>
                                    @endif
                                    @foreach ($race['pointList'] as $point)
                                        <rect class="season-race-hit" data-race-point="{{ $loop->index }}" x="{{ $point['hitX'] }}" y="0"
                                            width="{{ $point['hitWidth'] }}" height="{{ $race['bottom'] + 2 }}"></rect>
                                    @endforeach
                                </svg>
                                <div class="season-race-tip" hidden></div>
                            </div>
                        </figure>
                    @endif

                    <dl class="season-summary">
                        @foreach ($recap['summaryList'] as $summary)
                            <div>
                                <dt>{{ $summary['label'] }}</dt>
                                <dd>{{ $summary['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="season-step-actions">
                        <button type="button" class="btn btn-primary" data-season-step="podio">Ver el podio</button>
                        @if ($viewer['statList'])
                            <button type="button" class="btn season-btn-link" data-season-step="resumen">Ir directo a mi resumen</button>
                        @endif
                    </div>
                </section>

                <section class="season-step" data-season-panel="podio" hidden>
                    <button type="button" class="season-step-back" data-season-step="inicio"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Volver</button>
                    <div class="season-step-heading">
                        <h2 tabindex="-1">Podio</h2>
                        <p>{{ $recap['subtitle'] }}</p>
                    </div>
                    @if ($recap['podiumList'])
                        @include('gyms._ranking-podium', ['podiumList' => $recap['podiumList']])
                    @else
                        <p class="ranking-empty">Nadie sumo puntos en esta temporada.</p>
                    @endif

                    @if ($recap['tableList'])
                        <div class="season-table-heading">
                            <h3>Tabla final</h3>
                            <span>{{ $recap['tableCountLabel'] }}</span>
                        </div>
                        <ol class="ranking-list" aria-label="Tabla final">
                            @foreach ($recap['tableList'] as $row)
                                <li @class(['ranking-row', 'is-viewer' => $row['isViewer']]) @if ($row['isCollapsed']) hidden @endif>
                                    <span class="ranking-row-position">{{ $row['position'] }}</span>
                                    <img src="{{ $row['imageUrl'] }}" alt="" class="gym-student-avatar">
                                    <div class="ranking-row-info">
                                        <strong>{{ $row['name'] }}@if ($row['isViewer']) <small>(tu)</small>@endif</strong>
                                        <span><i class="fa-solid fa-fire" aria-hidden="true"></i> {{ $row['streakLabel'] }}</span>
                                    </div>
                                    <div class="ranking-row-score" aria-label="{{ $row['scoreLabel'] }}">
                                        <strong><i class="fa-solid fa-dumbbell" aria-hidden="true"></i> {{ $row['scoreValue'] }}</strong>
                                        <small>fuerza</small>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        @if ($recap['moreRowsLabel'])
                            <button type="button" class="btn season-more-btn" data-season-more>{{ $recap['moreRowsLabel'] }}</button>
                        @endif
                    @endif

                    <div class="season-step-actions">
                        <button type="button" class="btn btn-primary" data-season-step="premios"><i class="fa-solid fa-award" aria-hidden="true"></i> Ver los premios</button>
                    </div>
                </section>

                <section class="season-step" data-season-panel="premios" hidden>
                    <button type="button" class="season-step-back" data-season-step="podio"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Podio</button>
                    <div class="season-step-heading">
                        <h2 tabindex="-1">Premios</h2>
                        <p>No solo gana el primero: cada premio reconoce algo distinto de la temporada.</p>
                    </div>
                    @if ($recap['awardList'])
                        <ul class="season-awards">
                            @foreach ($recap['awardList'] as $award)
                                <li @class(['season-award', $award['toneClass'], 'is-viewer' => $award['isViewer']])>
                                    @if ($award['isViewer'])
                                        <span class="season-award-badge">¡Es tuyo!</span>
                                    @endif
                                    <span class="season-award-icon" aria-hidden="true"><i class="{{ $award['icon'] }}"></i></span>
                                    <span class="season-award-title">{{ $award['title'] }}</span>
                                    <span class="season-award-winner">
                                        <img src="{{ $award['imageUrl'] }}" alt="" class="season-award-avatar">
                                        <strong>{{ $award['name'] }}</strong>
                                    </span>
                                    <span class="season-award-value">{{ $award['valueText'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="ranking-empty">Nadie gano premios en esta temporada.</p>
                    @endif
                    <div class="season-step-actions">
                        <button type="button" class="btn btn-primary" data-season-step="resumen">{{ $recap['summaryButtonLabel'] }}</button>
                    </div>
                </section>

                <section class="season-step" data-season-panel="resumen" hidden>
                    <button type="button" class="season-step-back" data-season-step="premios"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Premios</button>
                    <div class="season-step-heading">
                        <h2 tabindex="-1">{{ $viewer['title'] }}</h2>
                    </div>

                    @if ($viewer['statList'])
                        <div class="ranking-viewer season-viewer">
                            <div class="season-viewer-header">
                                <img src="{{ $viewer['imageUrl'] }}" alt="" class="gym-student-avatar">
                                <div>
                                    <strong>{{ $viewer['name'] }}</strong>
                                    <span><i class="fa-solid fa-dumbbell" aria-hidden="true"></i> {{ $viewer['scoreLabel'] }}</span>
                                </div>
                                <span class="season-viewer-position">{{ $viewer['positionLabel'] }}</span>
                            </div>
                            @if ($viewer['gap'])
                                <div class="season-gap">
                                    <div class="season-gap-bar" aria-hidden="true">
                                        <span class="season-gap-fill" style="width: {{ $viewer['gap']['fillPercent'] }}%"></span>
                                        <span class="season-gap-marker" style="left: {{ $viewer['gap']['markerPercent'] }}%"></span>
                                    </div>
                                    <span class="season-gap-marker-label">{{ $viewer['gap']['markerLabel'] }}</span>
                                    <p>{{ $viewer['gap']['text'] }}</p>
                                </div>
                            @endif
                            @if ($viewer['previous'])
                                <p class="season-previous {{ $viewer['previous']['toneClass'] }}">
                                    <i class="{{ $viewer['previous']['icon'] }}" aria-hidden="true"></i> {{ $viewer['previous']['text'] }}
                                </p>
                            @endif
                        </div>

                        @if ($viewer['wonAwardList'])
                            <div class="season-won">
                                <span class="season-won-title">{{ $viewer['wonAwardTitle'] }}</span>
                                @foreach ($viewer['wonAwardList'] as $award)
                                    <div class="season-won-award {{ $award['toneClass'] }}">
                                        <span class="season-award-icon" aria-hidden="true"><i class="{{ $award['icon'] }}"></i></span>
                                        <div>
                                            <strong>{{ $award['title'] }}</strong>
                                            <span>{{ $award['valueText'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <h3 class="season-subtitle">Tus numeros</h3>
                        <dl class="ranking-viewer-stats">
                            @foreach ($viewer['statList'] as $stat)
                                <div>
                                    <dt><i class="{{ $stat['icon'] }}" aria-hidden="true"></i> {{ $stat['label'] }}</dt>
                                    <dd>{{ $stat['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @elseif ($viewer['reasonText'])
                        <p class="ranking-viewer-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ $viewer['reasonText'] }}</p>
                    @endif

                    <div class="season-next">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <div>
                            <strong>{{ $recap['nextSeason']['title'] }}</strong>
                            <span>{{ $recap['nextSeason']['text'] }}</span>
                        </div>
                    </div>

                    @if ($seasonLinkList)
                        <nav class="season-recap-links" aria-label="Otras temporadas">
                            @foreach ($seasonLinkList as $seasonLink)
                                <a href="{{ $seasonLink['url'] }}" @class(['is-active' => $seasonLink['isActive']]) @if ($seasonLink['isActive']) aria-current="page" @endif>{{ $seasonLink['label'] }}</a>
                            @endforeach
                        </nav>
                    @endif

                    <div class="season-step-actions">
                        <button type="button" class="btn season-btn-outline" data-bs-dismiss="modal">Volver al ranking</button>
                    </div>
                </section>
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
