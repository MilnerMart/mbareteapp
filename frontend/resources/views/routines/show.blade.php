@php
    $routine = $data['routine'];
    $exercises = $routine['exercises'] ?? [];
    $canEdit = $routine['canEdit'];
    $owner = $routine['refs']['owner'] ?? null;
    $ownerName = $owner ? trim(($owner['name'] ?? '') . ' ' . ($owner['last_name'] ?? '')) : null;
    $isMine = $routine['ownerId'] === (int) session('auth_user.id');
    // solo llega cuando quien mira es admin
    $assignedUsers = $routine['assignedUsers'] ?? null;
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
                        @if ($canEdit)
                            <details class="routine-exercise-edit">
                                <summary>
                                    <span class="routine-exercise-name">{{ $exercise['name'] }}</span>
                                    <span class="routine-exercise-volume">
                                        {{ $exercise['sets'] ?? '-' }} series × {{ $exercise['reps'] ?? '-' }} reps
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </span>
                                </summary>
                                <form method="POST" action="{{ route('routine.exercises.update', [$routine['id'], $exercise['id']]) }}" class="routine-form routine-exercise-form">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label for="sets_{{ $exercise['id'] }}" class="form-label">Series</label>
                                        <input type="number" id="sets_{{ $exercise['id'] }}" name="sets" class="form-control" value="{{ $exercise['sets'] }}" min="1" max="20">
                                    </div>
                                    <div>
                                        <label for="reps_{{ $exercise['id'] }}" class="form-label">Repeticiones</label>
                                        <input type="number" id="reps_{{ $exercise['id'] }}" name="reps" class="form-control" value="{{ $exercise['reps'] }}" min="1" max="100">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Guardar</button>
                                </form>
                            </details>
                        @else
                            <button type="button" class="routine-exercise-name routine-exercise-toggle" aria-pressed="false">
                                {{ $exercise['name'] }}
                            </button>
                            <span class="routine-exercise-volume">
                                {{ $exercise['sets'] ?? '-' }} series × {{ $exercise['reps'] ?? '-' }} reps
                            </span>
                        @endif
                        @if ($canEdit)
                            <form method="POST" action="{{ route('routine.exercises.remove', [$routine['id'], $exercise['id']]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn routine-icon-btn" title="Quitar de la rutina" aria-label="Quitar {{ $exercise['name'] }} de la rutina">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if (!$canEdit)
                <p class="routine-hint">Toca un ejercicio para marcarlo mientras entrenas.</p>
            @endif
            @if ($canEdit)
                <p class="routine-hint">Para agregar mas ejercicios entra a un musculo desde <a href="{{ route('muscle.index') }}">acá</a>.</p>
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
    <script>
        // en rutinas asignadas el ejercicio solo se resalta, no se puede modificar
        document.querySelectorAll('.routine-exercise-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const isOn = button.getAttribute('aria-pressed') !== 'true';
                button.setAttribute('aria-pressed', String(isOn));
                button.closest('.routine-exercise').classList.toggle('is-highlighted', isOn);
            });
        });
    </script>
@endsection
