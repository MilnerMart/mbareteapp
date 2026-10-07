@php
    $muscle = $data['muscle'];
@endphp
@extends('layouts.layout')

@section('title', 'Nuevo ejercicio | Mbarete App')

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
            <h1>Nuevo ejercicio de {{ $muscle['name'] }}</h1>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('exercise.store') }}" enctype="multipart/form-data" class="catalog-form">
                @csrf
                <input type="hidden" name="muscle_id" value="{{ $muscle['id'] }}">

                <div class="mb-3">
                    <label for="name" class="form-label">Nombre</label>
                    <input
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
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
                        required>{{ old('description') }}</textarea>
                </div>

                <div class="mb-3">
                    <label for="recommended_rest_time" class="form-label">Descanso recomendado entre series (seg)</label>
                    <input
                        type="number"
                        class="form-control @error('recommended_rest_time') is-invalid @enderror"
                        id="recommended_rest_time"
                        name="recommended_rest_time"
                        value="{{ old('recommended_rest_time', 60) }}"
                        min="1"
                        max="600"
                        required>
                </div>

                <div class="mb-4">
                    <label for="image" class="form-label">Imagen o GIF</label>
                    <input
                        type="file"
                        class="form-control @error('image') is-invalid @enderror"
                        id="image"
                        name="image"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        required>
                    <small class="catalog-hint">Muestra como se hace el ejercicio. Maximo 4 MB.</small>
                </div>

                <button type="submit" class="btn btn-primary w-100">Crear ejercicio</button>
            </form>
        </div>
    </section>
@endsection
