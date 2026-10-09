@php
    $muscle = $data['muscle'];
    $exercise = $data['exercise'] ?? null;
    $muscles = $data['muscles'] ?? [];
    $isEdit = (bool) $exercise;
@endphp
@extends('layouts.layout')

@section('title', ($isEdit ? 'Editar ejercicio' : 'Nuevo ejercicio').' | Mbarete App')

@section('css')
    @vite('resources/css/catalog.css')
@endsection

@section('content')
    <section class="py-3">
        <div class="catalog-form-panel">
            <a href="{{ route('exercise.group', $muscle['id']) }}" class="catalog-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ $muscle['name'] }}</span>
            </a>
            <h1>{{ $isEdit ? 'Editar '.$exercise['name'] : 'Nuevo ejercicio de '.$muscle['name'] }}</h1>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ $isEdit ? route('exercise.update', $exercise['id']) : route('exercise.store') }}" enctype="multipart/form-data" class="catalog-form">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                    <div class="mb-3">
                        <label for="muscle_id" class="form-label">Musculo</label>
                        <select class="form-select @error('muscle_id') is-invalid @enderror" id="muscle_id" name="muscle_id" required>
                            @foreach ($muscles as $muscleOption)
                                <option value="{{ $muscleOption['id'] }}" @selected(old('muscle_id', $exercise['muscle_id']) == $muscleOption['id'])>
                                    {{ $muscleOption['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="muscle_id" value="{{ $muscle['id'] }}">
                @endif

                <div class="mb-3">
                    <label for="name" class="form-label">Nombre</label>
                    <input
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        value="{{ old('name', $exercise['name'] ?? '') }}"
                        placeholder="Press de banca"
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
                        rows="3"
                        minlength="5"
                        maxlength="255"
                        required>{{ old('description', $exercise['description'] ?? '') }}</textarea>
                </div>

                <div class="mb-3">
                    <label for="recommended_rest_time" class="form-label">Descanso recomendado entre series (seg)</label>
                    <input
                        type="number"
                        class="form-control @error('recommended_rest_time') is-invalid @enderror"
                        id="recommended_rest_time"
                        name="recommended_rest_time"
                        value="{{ old('recommended_rest_time', $exercise['recommended_rest_time'] ?? 60) }}"
                        min="1"
                        max="600"
                        required>
                </div>

                <div class="mb-4">
                    <label for="image" class="form-label">Imagen o GIF</label>
                    @if ($isEdit && !empty($exercise['image_url']))
                        <img src="{{ asset($exercise['image_url']) }}" alt="{{ $exercise['name'] }}" class="d-block mb-2 rounded" style="max-height: 120px;">
                    @endif
                    <input
                        type="file"
                        class="form-control @error('image') is-invalid @enderror"
                        id="image"
                        name="image"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        @required(!$isEdit)>
                    <small class="catalog-hint">
                        {{ $isEdit ? 'Deja vacio para mantener la imagen actual.' : 'Muestra como se hace el ejercicio.' }} Maximo 4 MB.
                    </small>
                </div>

                @include('layouts._partials.visibility-field', ['entity' => $exercise, 'isAdmin' => $data['isAdmin']])

                <button type="submit" class="btn btn-primary w-100">
                    {{ $isEdit ? 'Guardar cambios' : ($data['isAdmin'] ? 'Crear ejercicio' : 'Enviar a revision') }}
                </button>
            </form>
        </div>
    </section>
@endsection
