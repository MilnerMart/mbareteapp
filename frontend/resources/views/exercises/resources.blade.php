@php
    $exerciseResources = $data['exerciseResources'];
    $editableRoutines = $data['editableRoutines'];
    $exercise = $data['exercise'];
@endphp
@extends('layouts.layout')
@section('css')
    @vite(['resources/css/resource.css', 'resources/css/routine.css', 'resources/css/catalog.css'])
@endsection
@section('content')
    <a href="{{ route('exercise.group', $exercise['muscle_id']) }}" class="catalog-back-link">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Ejercicios</span>
    </a>
    <div class="catalog-header">
        <div>
            <h1>{{ $exercise['name'] }}</h1>
            @if (!empty($exercise['description']))
                <p>{{ $exercise['description'] }}</p>
            @endif
        </div>
    </div>
    @if (session('routine_saved'))
        <div class="alert alert-success">{{ session('routine_saved') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (!empty($exerciseResources))
        <div class="row justify-content-center">
            @foreach ($exerciseResources as $resource)
                <div class="col-12 col-md-10 col-lg-8 d-flex justify-content-center mb-4">
                    <div class="card exercise-card">
                        <div class="exercise-image-wrapper">
                            <img src="{{ asset($resource['url']) }}" class="exercise-image">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if (session('auth_user'))
        <div class="routine-form-panel routine-add-panel">
            <h2 class="routine-subtitle">Agregar a una rutina</h2>
            @if (empty($editableRoutines))
                <p class="routine-hint mb-0">
                    Todavia no tienes rutinas. <a href="{{ route('routine.create', ['exercise_id' => $data['exerciseId']]) }}">Crea una</a> con este ejercicio.
                </p>
            @else
                <form method="POST" action="{{ route('routine.exercises.add') }}" class="routine-form">
                    @csrf
                    <input type="hidden" name="exercise_id" value="{{ $data['exerciseId'] }}">
                    <div class="mb-3">
                        <label for="routine_id" class="form-label">Rutina</label>
                        <select id="routine_id" name="routine_id" class="form-select" required>
                            @foreach ($editableRoutines as $routine)
                                <option value="{{ $routine['id'] }}" @selected(old('routine_id') == $routine['id'])>{{ $routine['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="routine-form-row mb-3">
                        <div>
                            <label for="sets" class="form-label">Series</label>
                            <input type="number" id="sets" name="sets" class="form-control" value="{{ old('sets', 3) }}" min="1" max="20">
                        </div>
                        <div>
                            <label for="reps" class="form-label">Repeticiones</label>
                            <input type="number" id="reps" name="reps" class="form-control" value="{{ old('reps', 12) }}" min="1" max="100">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-2"></i>
                        Agregar a la rutina
                    </button>
                </form>
                <p class="routine-hint mt-3 mb-0">
                    O <a href="{{ route('routine.create', ['exercise_id' => $data['exerciseId']]) }}">crea una rutina nueva</a> con este ejercicio.
                </p>
            @endif
        </div>
    @endif
@endsection