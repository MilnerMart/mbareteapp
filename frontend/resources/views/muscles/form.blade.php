@extends('layouts.layout')

@section('title', 'Nuevo musculo | Mbarete App')

@section('css')
    @vite('resources/css/catalog.css')
@endsection

@section('content')
    <section class="py-3">
        <div class="catalog-form-panel">
            <a href="{{ route('muscle.index') }}" class="catalog-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Inicio</span>
            </a>
            <h1>Nuevo musculo</h1>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('muscle.store') }}" enctype="multipart/form-data" class="catalog-form">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Nombre</label>
                    <input
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="Pecho"
                        minlength="3"
                        maxlength="50"
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
                    <label for="recommended_rest_days" class="form-label">Dias de descanso recomendados</label>
                    <input
                        type="number"
                        class="form-control @error('recommended_rest_days') is-invalid @enderror"
                        id="recommended_rest_days"
                        name="recommended_rest_days"
                        value="{{ old('recommended_rest_days', 2) }}"
                        min="1"
                        max="14"
                        required>
                </div>

                <div class="mb-4">
                    <label for="image" class="form-label">Imagen</label>
                    <input
                        type="file"
                        class="form-control @error('image') is-invalid @enderror"
                        id="image"
                        name="image"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        required>
                    <small class="catalog-hint">Se muestra en la tarjeta del musculo en Inicio. Maximo 4 MB.</small>
                </div>

                <button type="submit" class="btn btn-primary w-100">Crear musculo</button>
            </form>
        </div>
    </section>
@endsection
