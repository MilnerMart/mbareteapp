@php
    $profile = $data['profile'];
    $form = $data['form'];
    $tickets = $data['tickets'];
    $isOwnProfile = $data['isOwnProfile'];
    $progress = $data['progress'];
@endphp
@extends('layouts.layout')
@section('title', $profile['fullName'].' | Mbarete App')
@section('css')
    @vite(['resources/css/profile.css', 'resources/css/ticket.css'])
@endsection
@section('content')
    <div class="container profile-page">
        @foreach (['register_tickets' => 'info', 'profile_image_updated' => 'success', 'profile_saved' => 'success'] as $flashKey => $flashType)
            @if (session($flashKey))
                <div class="alert alert-{{ $flashType }} profile-alert">{{ session($flashKey) }}</div>
            @endif
        @endforeach
        @error('profile_image')
            <div class="alert alert-danger profile-alert">{{ $message }}</div>
        @enderror

        <section class="card profile-card">
            <div class="card-body profile-hero">
                @if ($isOwnProfile)
                    <button type="button" class="btn-edit-profile" data-profile-edit-toggle aria-controls="profile-edit"
                        aria-expanded="{{ $form['isOpen'] ? 'true' : 'false' }}" title="Editar perfil" aria-label="Editar perfil">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" action="{{ route('user.profile.image', $profile['id']) }}" enctype="multipart/form-data" class="profile-picture-form">
                        @csrf
                        <label class="profile-picture" for="profileImageInput" title="Cambiar foto">
                            <img src="{{ $profile['imageUrl'] }}" alt="Foto de perfil">
                            <span class="profile-picture-overlay"><i class="fa-solid fa-camera"></i></span>
                        </label>
                        <input type="file" id="profileImageInput" name="profile_image" class="d-none"
                            accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()">
                    </form>
                @else
                    <div class="profile-picture profile-picture-static">
                        <img src="{{ $profile['imageUrl'] }}" alt="Foto de {{ $profile['fullName'] }}">
                    </div>
                @endif

                <h1 class="profile-name">{{ $profile['fullName'] }}</h1>
                <div class="profile-chips">
                    <span class="profile-chip @unless ($profile['goalLabel']) is-empty @endunless">
                        <i class="fa-solid fa-bullseye"></i>
                        <span>{{ $profile['goalLabel'] ?? ($isOwnProfile ? 'Elige tu objetivo' : 'Sin objetivo') }}</span>
                    </span>
                    <span class="profile-chip">
                        <i class="fa-solid fa-medal"></i>
                        <span>{{ $profile['rankLabel'] }}</span>
                    </span>
                </div>

                <dl class="profile-stats">
                    @foreach ($profile['statList'] as $stat)
                        <div class="profile-stat">
                            <dt>{{ $stat['label'] }}</dt>
                            <dd>{{ $stat['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        @if ($isOwnProfile)
            <section class="card profile-card" id="profile-edit" @unless ($form['isOpen']) hidden @endunless>
                <div class="card-body">
                    <h2 class="profile-section-title">Editar perfil</h2>
                    @if ($errors->hasAny(['profile', 'name', 'last_name', 'age', 'height', 'weight', 'goal', 'gender']))
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif
                    <form method="POST" action="{{ route('user.profile.update', $profile['id']) }}" class="profile-form"
                        data-confirm-submit
                        data-confirm-title="Guardar perfil"
                        data-confirm-text="Se actualizan tus datos, tu objetivo y tu genero.">
                        @csrf
                        @method('PUT')
                        <div class="profile-form-row">
                            <div>
                                <label for="name" class="form-label">Nombre</label>
                                <input type="text" id="name" name="name" class="form-control" value="{{ $form['values']['name'] }}" autocomplete="given-name"
                                    minlength="2" maxlength="60" required
                                    pattern="[\p{L}\p{M}]+(?:(?:\. *| +|['’\-])[\p{L}\p{M}]+)*\.?"
                                    title="Solo letras, espacios, apóstrofes o guiones. Sin números, correos ni símbolos.">
                            </div>
                            <div>
                                <label for="last_name" class="form-label">Apellido</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" value="{{ $form['values']['last_name'] }}" autocomplete="family-name"
                                    minlength="2" maxlength="60" required
                                    pattern="[\p{L}\p{M}]+(?:(?:\. *| +|['’\-])[\p{L}\p{M}]+)*\.?"
                                    title="Solo letras, espacios, apóstrofes o guiones. Sin números, correos ni símbolos.">
                            </div>
                        </div>
                        <div class="profile-form-row profile-form-row-3">
                            <div>
                                <label for="age" class="form-label">Edad</label>
                                <input type="number" id="age" name="age" class="form-control" value="{{ $form['values']['age'] }}" min="12" max="100" required>
                            </div>
                            <div>
                                <label for="height" class="form-label">Altura (cm)</label>
                                <input type="number" id="height" name="height" class="form-control" value="{{ $form['values']['height'] }}" min="100" max="230" required>
                            </div>
                            <div>
                                <label for="weight" class="form-label">Peso (kg)</label>
                                <input type="number" id="weight" name="weight" class="form-control" value="{{ $form['values']['weight'] }}" min="25" max="250" required>
                            </div>
                        </div>
                        <div class="profile-form-row">
                            <div>
                                <label for="goal" class="form-label">Objetivo</label>
                                <select id="goal" name="goal" class="form-select">
                                    <option value="">Sin definir</option>
                                    @foreach ($form['goalOptions'] as $value => $label)
                                        <option value="{{ $value }}" @selected($form['values']['goal'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="gender" class="form-label">Genero</label>
                                <select id="gender" name="gender" class="form-select">
                                    <option value="">Sin definir</option>
                                    @foreach ($form['genderOptions'] as $value => $label)
                                        <option value="{{ $value }}" @selected($form['values']['gender'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="profile-form-hint">Define como se nombra tu rango (Novato o Novata, por ejemplo).</small>
                            </div>
                        </div>
                        <div class="profile-form-actions">
                            <button type="button" class="btn profile-btn-cancel" data-profile-edit-toggle aria-controls="profile-edit">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </section>
        @endif

        <section class="card profile-card">
            <div class="card-body">
                <h2 class="profile-section-title">
                    Nivel
                    <small>se gana entrenando</small>
                </h2>
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
                <p class="profile-rank-hint">
                    Sumas puntos por cada dia entrenado, por mantener la racha, por los minutos entrenados y por los kilos que levantas.
                    Se cuentan los ejercicios que guardas como hechos en tus rutinas.
                </p>
            </div>
        </section>

        <section class="card profile-card">
            <div class="card-body">
                <h2 class="profile-section-title">
                    Favoritos
                    <small>según lo que más entrena{{ $isOwnProfile ? 's' : '' }}</small>
                </h2>
                <div class="profile-favorites">
                    @foreach ($data['favorites'] as $favorite)
                        <article class="profile-favorite">
                            <div class="profile-favorite-media media-attribution-host">
                                @if ($favorite['imageUrl'])
                                    <a href="{{ $favorite['url'] }}"><img src="{{ $favorite['imageUrl'] }}" alt="{{ $favorite['name'] }}"></a>
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
        </section>

        @if (!empty($tickets))
            <section class="card profile-card">
                <div class="card-body">
                    <h2 class="profile-section-title">Mis solicitudes</h2>
                    <ul class="profile-ticket-list">
                        @foreach ($tickets as $ticket)
                            <li class="profile-ticket">
                                <a href="{{ route('ticket.show', $ticket['id']) }}" class="ticket-number stretched-link">{{ $ticket['number'] }}</a>
                                <span class="profile-ticket-summary">{{ $ticket['summary'] }}</span>
                                <span class="ticket-state ticket-state-{{ $ticket['state'] }}">{{ $ticket['stateLabel'] }}</span>
                                @if (!empty($ticket['resolutionNote']))
                                    <span class="profile-ticket-note">“{{ $ticket['resolutionNote'] }}”</span>
                                @endif
                                @if ($ticket['canResubmit'] ?? false)
                                    <span class="profile-ticket-note profile-ticket-cta">Podes volver a enviarla una vez</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </div>
@endsection

@if ($isOwnProfile)
    @section('js')
        {{-- module: corre despues de app.js, que es quien expone jQuery --}}
        <script type="module">
            // el lapiz y cancelar abren o cierran el editor; nada se guarda hasta confirmar
            $('[data-profile-edit-toggle]').on('click', () => {
                const $panel = $('#profile-edit');
                const isOpening = $panel.prop('hidden');
                $panel.prop('hidden', !isOpening);
                $('.btn-edit-profile').attr('aria-expanded', String(isOpening));
                if (isOpening) {
                    $panel.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
                    $panel.find('#name').trigger('focus');
                }
            });
        </script>
    @endsection
@endif
