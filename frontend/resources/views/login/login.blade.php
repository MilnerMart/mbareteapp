@extends('layouts.layout')

@section('title', 'Ingresar | Leoncio Gym')

@section('css')
    @vite(['resources/css/login.css'])
@endsection

@section('content')
    <section class="login-page">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <div class="login-copy">
                    <span class="login-eyebrow">Mbarete App</span>
                    <h1>Entrena con foco, vuelve con mas fuerza.</h1>
                    <p>
                        Accede a tus rutinas, ejercicios y progreso desde un panel pensado para mantener tu entrenamiento en movimiento.
                    </p>

                    <div class="login-stats">
                        <div>
                            <strong>12+</strong>
                            <span>grupos musculares</span>
                        </div>
                        <div>
                            <strong>24/7</strong>
                            <span>seguimiento</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 login-form-column">
                <div class="login-panel">
                    <div class="login-brand">
                        <img src="{{ asset('images/LeoncioLogo.png') }}" alt="Leoncio Gym">
                        <div>
                            <span>Bienvenido</span>
                            <strong>Iniciar sesion</strong>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="#" class="login-form">
                        @csrf

                        <div class="mb-3">
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
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
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
                                    placeholder="Tu contrasena"
                                    autocomplete="current-password"
                                    required>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="login-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Recordarme
                                </label>
                            </div>

                            <a href="#">Olvide mi contrasena</a>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>
                            Ingresar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
