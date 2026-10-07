@php
    $routine = $data['routine'];
    $exercises = $routine['exercises'] ?? [];
    $canEdit = $routine['canEdit'];
    $owner = $routine['refs']['owner'] ?? null;
    $ownerName = $owner ? trim(($owner['name'] ?? '') . ' ' . ($owner['last_name'] ?? '')) : null;
    $isMine = $routine['ownerId'] === (int) session('auth_user.id');
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
                    <span><i class="fa-regular fa-clock"></i> {{ $routine['restTime'] }}s descanso</span>
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
                    <form method="POST" action="{{ route('routine.destroy', $routine['id']) }}" onsubmit="return confirm('¿Eliminar esta rutina?')">
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
                        Entra a un musculo desde <a href="{{ route('muscle.index') }}">Inicio</a>, elige un ejercicio y agregalo a esta rutina.
                    @endif
                </p>
            </div>
        @else
            <ol class="routine-exercise-list">
                @foreach ($exercises as $exercise)
                    <li class="routine-exercise">
                        <a href="{{ route('exercise.resource', $exercise['id']) }}" class="routine-exercise-name">{{ $exercise['name'] }}</a>
                        <span class="routine-exercise-volume">
                            {{ $exercise['sets'] ?? '-' }} series × {{ $exercise['reps'] ?? '-' }} reps
                        </span>
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
            @if ($canEdit)
                <p class="routine-hint">Para agregar mas ejercicios entra a un musculo desde <a href="{{ route('muscle.index') }}">acá</a>.</p>
            @endif
        @endif
    </section>
@endsection
