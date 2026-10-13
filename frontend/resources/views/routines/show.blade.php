@php
    $routine = $data['routine'];
    $exercises = $routine['exercises'] ?? [];
    $canEdit = $routine['canEdit'];
    $owner = $routine['refs']['owner'] ?? null;
    $ownerName = $owner ? trim(($owner['name'] ?? '') . ' ' . ($owner['last_name'] ?? '')) : null;
    $isMine = $routine['ownerId'] === (int) session('auth_user.id');
    // solo llega cuando quien mira es admin
    $assignedUsers = $routine['assignedUsers'] ?? null;
    // imagenes y descripcion de cada ejercicio para abrirlo desde la rutina
    $exerciseDetailMap = collect($exercises)->keyBy('id')->map(fn($exercise) => [
        'name' => $exercise['name'],
        'description' => $exercise['description'] ?? null,
        'resources' => $exercise['resources'] ?? [],
        'volume' => $exercise['detailVolume'],
        'notes' => $exercise['notes'] ?? null,
    ]);
@endphp
@extends('layouts.layout')

@section('title', $routine['name'] . ' | Mbarete App')

@section('css')
    @vite(['resources/css/routine.css'])
@endsection

@section('content')
    <section class="routine-page">
        <a href="{{ route('routine.index') }}" class="routine-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Rutinas</span>
        </a>

        @if (session('routine_saved'))
            <div class="alert alert-success">{{ session('routine_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="routine-detail">
            <div>
                <h1>{{ $routine['name'] }}</h1>
                @if (!empty($routine['description']))
                    <p class="routine-card-desc">{{ $routine['description'] }}</p>
                @endif
                <span class="routine-meta">
                    <span><i class="fa-regular fa-calendar"></i> {{ $routine['frequency'] }} dias/semana</span>
                    <span><i class="fa-regular fa-clock"></i> {{ $routine['restMinutes'] }} min descanso</span>
                    @if (!$isMine && $ownerName)
                        <span><i class="fa-solid fa-user"></i> Creada por {{ $ownerName }}</span>
                    @endif
                </span>
            </div>
            @if ($canEdit)
                <div class="routine-detail-actions">
                    <a href="{{ route('routine.edit', $routine['id']) }}" class="btn routine-btn">
                        <i class="fa-solid fa-pen"></i>
                        <span>Editar</span>
                    </a>
                    <form method="POST" action="{{ route('routine.destroy', $routine['id']) }}"
                          data-confirm-delete
                          data-confirm-title="Eliminar rutina"
                          data-confirm-text="La rutina dejara de mostrarse.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn routine-remove-btn">
                            <i class="fa-solid fa-trash"></i>
                            <span>Eliminar</span>
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <h2 class="routine-subtitle">Ejercicios</h2>
        @if (empty($exercises))
            <div class="routine-empty-box">
                <i class="fa-solid fa-dumbbell"></i>
                <p>
                    Esta rutina todavia no tiene ejercicios.
                    @if ($canEdit)
                        Entra a un musculo desde <a href="{{ route('muscle.index') }}">acá</a>, elige un ejercicio y agregalo a esta rutina.
                    @endif
                </p>
            </div>
        @else
            <ol class="routine-exercise-list">
                @foreach ($exercises as $exercise)
                    <li class="routine-exercise">
                        <div class="routine-exercise-info">
                            <button type="button" class="routine-exercise-name routine-exercise-open" data-exercise-detail="{{ $exercise['id'] }}">
                                {{ $exercise['name'] }}
                            </button>
                            <span class="routine-exercise-volume">{{ $exercise['volume'] }}</span>
                            @if (!empty($exercise['notes']))
                                <p class="routine-exercise-notes">{{ $exercise['notes'] }}</p>
                            @endif
                            @if ($exercise['tracksWeight'])
                                <label class="routine-weight">
                                    <input type="number" name="weights[{{ $exercise['id'] }}]" form="routine-done-form" class="form-control"
                                        value="{{ $exercise['doneWeightKg'] ?? '' }}" min="0" max="1000" step="0.5" inputmode="decimal"
                                        placeholder="Peso" aria-label="Kg usados en {{ $exercise['name'] }}" data-done-weight>
                                    <span>kg</span>
                                </label>
                            @endif
                        </div>
                        <div class="routine-exercise-actions">
                            <label class="btn routine-icon-btn routine-exercise-toggle" title="Marcar como hecho">
                                <input type="checkbox" name="exercise_ids[]" value="{{ $exercise['id'] }}" form="routine-done-form"
                                    class="visually-hidden" aria-label="Marcar {{ $exercise['name'] }} como hecho" data-done-check
                                    @checked($exercise['doneToday'] ?? false)>
                                <i class="fa-solid fa-check"></i>
                            </label>
                        </div>
                    </li>
                @endforeach
            </ol>
            {{-- lo marcado se guarda todo junto en una sola llamada --}}
            <form method="POST" action="{{ route('routine.done', $routine['id']) }}" id="routine-done-form" class="routine-done-form">
                @csrf
                @method('PUT')
                {{-- siempre viaja la lista, aunque no quede nada marcado (asi se borra lo de hoy) --}}
                <input type="hidden" name="exercise_ids[]" value="">
                <button type="submit" class="btn btn-primary routine-btn-save" data-done-save>
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Guardar entrenamiento de hoy</span>
                </button>
            </form>
            <p class="routine-hint">Toca un ejercicio para ver como se hace y marcalo con <i class="fa-solid fa-check"></i> mientras entrenas. Si usas peso, carga los kg. Al terminar guarda el entrenamiento: suma puntos a tu nivel y arma tus favoritos del perfil.</p>
            @if ($canEdit)
                <p class="routine-hint">
                    Para cambiar tiempos, series, ordenar o quitar ejercicios usa <a href="{{ route('routine.edit', $routine['id']) }}">Editar</a>.
                    Para agregar mas entra a un musculo o actividad desde <a href="{{ route('muscle.index') }}">acá</a>.
                </p>
            @endif
        @endif

        @if ($assignedUsers !== null)
            <h2 class="routine-subtitle mt-4">Asignada a</h2>
            @if (empty($assignedUsers))
                <p class="routine-hint">Esta rutina no esta asignada a ningun usuario.</p>
            @else
                <ul class="routine-user-list">
                    @foreach ($assignedUsers as $assignedUser)
                        @php
                            $assignedName = trim(($assignedUser['name'] ?? '') . ' ' . ($assignedUser['last_name'] ?? ''));
                        @endphp
                        <li class="routine-user">
                            <img src="{{ $assignedUser['profile_image_url'] ?? asset('images/leoncioBiceps.png') }}" alt="" class="routine-user-avatar">
                            <div class="routine-user-info">
                                <strong>{{ $assignedName ?: 'Usuario' }}</strong>
                                <span>{{ $assignedUser['email'] }}</span>
                                <span class="routine-user-gyms">
                                    <i class="fa-solid fa-dumbbell"></i>
                                    {{ empty($assignedUser['gyms']) ? 'Sin gimnasio' : implode(', ', $assignedUser['gyms']) }}
                                </span>
                            </div>
                            <form
                                method="POST"
                                action="{{ route('routine.unassign', [$routine['id'], $assignedUser['id']]) }}"
                                onsubmit="return confirm('¿Quitar esta rutina a {{ addslashes($assignedName ?: 'este usuario') }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn routine-remove-btn">
                                    <i class="fa-solid fa-user-minus"></i>
                                    <span>Quitar</span>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>
@endsection

@section('js')
    <script type="application/json" id="routineExerciseData">@json($exerciseDetailMap)</script>
    {{-- module: corre despues de app.js, que es quien expone jQuery y Swal --}}
    <script type="module">
        const routineExerciseData = JSON.parse($('#routineExerciseData').text());

        const link = (text, href, rel = 'noopener') => $('<a>', { href, target: '_blank', rel }).text(text);

        // mismo bloque que layouts._partials.media-attribution
        const buildAttribution = (attribution) => $('<details>', { class: 'media-attribution' }).append(
            $('<summary>', { title: 'Créditos y licencia', 'aria-label': 'Créditos y licencia' })
                .append($('<i>', { class: 'fa-brands fa-creative-commons', 'aria-hidden': 'true' })),
            $('<p>').append(
                link(attribution.title, attribution.sourceUrl), ' por ',
                attribution.authorUrl ? link(attribution.author, attribution.authorUrl) : document.createTextNode(attribution.author), ', ',
                link(attribution.license, attribution.licenseUrl, 'noopener license'),
                document.createTextNode(` · vía ${attribution.provider}`),
            ),
        );

        // .text() y .attr() escapan: la descripcion la escribe un usuario
        const buildExerciseDetail = (exercise) => {
            const $container = $('<div>', { class: 'routine-exercise-detail' });
            $.each(exercise.resources, (index, resource) => {
                const $media = $('<div>', { class: 'media-attribution-host' }).appendTo($container);
                if (resource.kind === 'video') {
                    $('<video>', { src: resource.url, controls: true, muted: true, loop: true, playsinline: true, preload: 'metadata' }).appendTo($media);
                } else {
                    $('<img>', { src: resource.url, alt: exercise.name }).appendTo($media);
                }
                if (resource.attribution) {
                    buildAttribution(resource.attribution).appendTo($media);
                }
            });
            $('<p>', { class: 'routine-exercise-detail-meta' })
                .text(exercise.volume)
                .appendTo($container);
            if (exercise.notes) {
                $('<p>', { class: 'routine-exercise-notes' }).text(exercise.notes).appendTo($container);
            }
            $('<p>').text(exercise.description || 'Este ejercicio no tiene descripcion.').appendTo($container);
            return $container.get(0);
        };

        $('[data-exercise-detail]').on('click', function () {
            const exercise = routineExerciseData[$(this).data('exerciseDetail')];
            if (!exercise) {
                return;
            }
            Swal.fire({
                title: exercise.name,
                html: buildExerciseDetail(exercise),
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#780000',
                customClass: { popup: 'routine-exercise-popup' },
            });
        });

        // las casillas solo cambian en pantalla; el boton de guardar se habilita cuando hay cambios
        const $doneCheckList = $('[data-done-check]');
        const $doneWeightList = $('[data-done-weight]');
        const readDoneState = () => [
            ...$doneCheckList.map((index, check) => check.checked).get(),
            ...$doneWeightList.map((index, input) => input.value).get(),
        ].join();
        const savedDoneState = readDoneState();
        const refreshDoneSave = () => {
            const currentState = readDoneState();
            $('[data-done-save]').prop('disabled', currentState === savedDoneState);
        };
        $doneCheckList.on('change', refreshDoneSave);
        // cargar kg marca el ejercicio como hecho
        $doneWeightList.on('input', function () {
            if (this.value !== '') {
                $(this).closest('.routine-exercise').find('[data-done-check]').prop('checked', true);
            }
            refreshDoneSave();
        });
        refreshDoneSave();
    </script>
@endsection
