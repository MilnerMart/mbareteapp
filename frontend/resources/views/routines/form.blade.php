@php
    $exerciseId = $data['exerciseId'] ?? null;
    $backUrl = $exerciseId !== null ? route('exercise.resource', $exerciseId) : route('routine.index');
@endphp
@extends('layouts.layout')

@section('title', 'Nueva rutina | Mbarete App')

@section('css')
    @vite(['resources/css/routine.css'])
@endsection

@section('content')
    <section class="routine-page">
        <div class="routine-form-panel">
            <a href="{{ $backUrl }}" class="routine-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Volver</span>
            </a>
            <h1>Nueva rutina</h1>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('routine.store') }}" class="routine-form">
                @csrf
                @if ($exerciseId)
                    <input type="hidden" name="exercise_id" value="{{ $exerciseId }}">
                @endif

                <div class="mb-3">
                    <label for="name" class="form-label">Nombre</label>
                    <input
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="Pecho y triceps"
                        minlength="3"
                        maxlength="100"
                        required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Descripcion</label>
                    <textarea
                        class="form-control @error('description') is-invalid @enderror"
                        id="description"
                        name="description"
                        rows="2"
                        maxlength="255"
                        placeholder="Opcional">{{ old('description') }}</textarea>
                </div>

                <div class="routine-form-row mb-4">
                    <div>
                        <label for="frequency" class="form-label">Dias por semana</label>
                        <input
                            type="number"
                            class="form-control @error('frequency') is-invalid @enderror"
                            id="frequency"
                            name="frequency"
                            value="{{ old('frequency', 3) }}"
                            min="1"
                            max="7"
                            required>
                    </div>
                    <div>
                        <label for="rest_minutes" class="form-label">Descanso entre ejercicios (min)</label>
                        <input
                            type="number"
                            class="form-control @error('rest_minutes') is-invalid @enderror"
                            id="rest_minutes"
                            name="rest_minutes"
                            value="{{ old('rest_minutes', 1) }}"
                            min="0"
                            max="30"
                            step="0.5"
                            required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Crear rutina</button>
            </form>
        </div>
    </section>
@endsection
