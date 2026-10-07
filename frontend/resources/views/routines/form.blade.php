@php
    $routine = $data['routine'];
    $isEdit = $routine !== null;
@endphp
@extends('layouts.layout')

@section('title', ($isEdit ? 'Editar rutina' : 'Nueva rutina') . ' | Mbarete App')

@section('css')
    @vite(['resources/css/routine.css'])
@endsection

@section('content')
    <section class="routine-page">
        <div class="routine-form-panel">
            <a href="{{ $isEdit ? route('routine.show', $routine['id']) : route('routine.index') }}" class="routine-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Volver</span>
            </a>
            <h1>{{ $isEdit ? 'Editar rutina' : 'Nueva rutina' }}</h1>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ $isEdit ? route('routine.update', $routine['id']) : route('routine.store') }}" class="routine-form">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label for="name" class="form-label">Nombre</label>
                    <input
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        value="{{ old('name', $routine['name'] ?? '') }}"
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
                        placeholder="Opcional">{{ old('description', $routine['description'] ?? '') }}</textarea>
                </div>

                <div class="routine-form-row mb-4">
                    <div>
                        <label for="frequency" class="form-label">Dias por semana</label>
                        <input
                            type="number"
                            class="form-control @error('frequency') is-invalid @enderror"
                            id="frequency"
                            name="frequency"
                            value="{{ old('frequency', $routine['frequency'] ?? 3) }}"
                            min="1"
                            max="7"
                            required>
                    </div>
                    <div>
                        <label for="rest_time" class="form-label">Descanso entre ejercicios (seg)</label>
                        <input
                            type="number"
                            class="form-control @error('rest_time') is-invalid @enderror"
                            id="rest_time"
                            name="rest_time"
                            value="{{ old('rest_time', $routine['restTime'] ?? 60) }}"
                            min="0"
                            max="600"
                            required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    {{ $isEdit ? 'Guardar cambios' : 'Crear rutina' }}
                </button>
            </form>
        </div>
    </section>
@endsection
