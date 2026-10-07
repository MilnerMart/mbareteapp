@php
    $gym = $data['gym'];
    $isAdmin = $data['isAdmin'];
    $isEdit = $gym !== null;
@endphp
@extends('layouts.layout')

@section('title', ($isEdit ? 'Editar gimnasio' : 'Nuevo gimnasio') . ' | Mbarete App')

@section('css')
    @vite(['resources/css/gym.css'])
@endsection

@section('content')
    <section class="gym-page">
        <div class="gym-form-panel">
            <a href="{{ $isEdit ? route('gym.show', $gym['id']) : route('gym.index') }}" class="gym-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Volver</span>
            </a>
            <h1>{{ $isEdit ? 'Editar gimnasio' : 'Nuevo gimnasio' }}</h1>

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ $isEdit ? route('gym.update', $gym['id']) : route('gym.store') }}" class="gym-form">
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
                        value="{{ old('name', $gym['name'] ?? '') }}"
                        minlength="5"
                        maxlength="200"
                        required>
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Identificador</label>
                    <input
                        type="text"
                        class="form-control @error('slug') is-invalid @enderror"
                        id="slug"
                        name="slug"
                        value="{{ old('slug', $gym['slug'] ?? '') }}"
                        placeholder="mi-gimnasio"
                        minlength="5"
                        maxlength="50"
                        pattern="[A-Za-z0-9_\-]+"
                        required>
                    <small class="text-muted d-block mt-1">
                        Es el codigo de gimnasio que usaran tus alumnos al registrarse para sumarse directamente a este gimnasio.
                    </small>
                    @error('slug')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                @if ($isAdmin)
                    <div class="mb-4">
                        <label for="owner_id" class="form-label">ID del dueño</label>
                        <input
                            type="number"
                            class="form-control @error('owner_id') is-invalid @enderror"
                            id="owner_id"
                            name="owner_id"
                            value="{{ old('owner_id', $gym['ownerId'] ?? '') }}"
                            min="1">
                        <small class="gym-hint">Dejalo vacio para asignarte el gimnasio.</small>
                        @error('owner_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <button type="submit" class="btn btn-primary w-100">
                    {{ $isEdit ? 'Guardar cambios' : 'Crear gimnasio' }}
                </button>
            </form>
        </div>
    </section>
@endsection
