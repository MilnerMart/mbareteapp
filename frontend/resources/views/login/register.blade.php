@extends('layouts.layout')

@section('title', 'Registrarme | Mbarete App')

@section('css')
    @vite(['resources/css/register.css'])
@endsection

@section('content')
    <section class="register-page">
        <div class="register-shell">
            <div class="register-copy">
                <span class="register-eyebrow">Mbarete App</span>
                <h1>Tu punto de partida.</h1>
                <p>
                    Crea tu perfil con lo esencial para preparar rutinas, seguimiento y progresos mas precisos.
                </p>
            </div>

            <div class="register-panel">
                <div class="register-brand">
                    <img src="{{ asset('images/LeoncioLogo.png') }}" alt="Mbarete App">
                    <div>
                        <span>Nuevo perfil</span>
                        <strong>Registrarme</strong>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="#" class="register-form">
                    @csrf

                    <div class="register-grid">
                        <div>
                            <label for="name" class="form-label">Nombre</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-user"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control @error('name') is-invalid @enderror"
                                    id="name"
                                    name="name"
                                    value="{{ old('name') }}"
                                    placeholder="Tu nombre"
                                    autocomplete="given-name"
                                    required>
                            </div>
                            @error('name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="last_name" class="form-label">Apellido</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-id-card"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control @error('last_name') is-invalid @enderror"
                                    id="last_name"
                                    name="last_name"
                                    value="{{ old('last_name') }}"
                                    placeholder="Tu apellido"
                                    autocomplete="family-name"
                                    required>
                            </div>
                            @error('last_name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="register-grid-full">
                            <label for="email" class="form-label">Correo electronico</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-envelope"></i>
                                </span>
                                <input
                                    type="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    id="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    placeholder="usuario@email.com"
                                    autocomplete="email"
                                    required>
                            </div>
                            <small class="register-hint">Lo usaremos para verificar tu cuenta mas adelante.</small>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="form-label">Contrasena</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-lock"></i>
                                </span>
                                <input
                                    type="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    id="password"
                                    name="password"
                                    placeholder="Crea una contrasena"
                                    autocomplete="new-password"
                                    required>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="form-label">Confirmar</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </span>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    placeholder="Repite la contrasena"
                                    autocomplete="new-password"
                                    required>
                            </div>
                        </div>

                        <div>
                            <label for="age" class="form-label">Edad</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-calendar-day"></i>
                                </span>
                                <input
                                    type="number"
                                    class="form-control @error('age') is-invalid @enderror"
                                    id="age"
                                    name="age"
                                    value="{{ old('age') }}"
                                    placeholder="25"
                                    min="12"
                                    max="100"
                                    required>
                            </div>
                            @error('age')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="weight" class="form-label">Peso</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-weight-scale"></i>
                                </span>
                                <input
                                    type="number"
                                    class="form-control @error('weight') is-invalid @enderror"
                                    id="weight"
                                    name="weight"
                                    value="{{ old('weight') }}"
                                    placeholder="75"
                                    min="25"
                                    max="250"
                                    step="0.1"
                                    required>
                                <span class="input-group-text">kg</span>
                            </div>
                            @error('weight')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="height" class="form-label">Altura</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-ruler-vertical"></i>
                                </span>
                                <input
                                    type="number"
                                    class="form-control @error('height') is-invalid @enderror"
                                    id="height"
                                    name="height"
                                    value="{{ old('height') }}"
                                    placeholder="175"
                                    min="100"
                                    max="230"
                                    step="0.1"
                                    required>
                                <span class="input-group-text">cm</span>
                            </div>
                            @error('height')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-user-plus me-2"></i>
                        Crear cuenta
                    </button>

                    <p class="register-login-link">
                        Ya tienes cuenta?
                        <a href="{{ route('user.login') }}">Ingresar</a>
                    </p>
                </form>
            </div>
        </div>
    </section>
@endsection
