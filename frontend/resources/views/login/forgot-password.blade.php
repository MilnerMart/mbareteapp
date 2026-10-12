@extends('layouts.layout')

@section('title', 'Recuperar contraseña | Leoncio Gym')

@section('css')
    @vite(['resources/css/login.css'])
@endsection

@section('content')
    <section class="login-page">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-xl-5 login-form-column">
                <div class="login-panel">
                    <div class="login-brand">
                        <img src="{{ asset('images/LeoncioLogo.png') }}" alt="Leoncio Gym">
                        <div>
                            <span>¿Olvidaste tu contraseña?</span>
                            <strong>Recuperar acceso</strong>
                        </div>
                    </div>

                    <p class="login-help">Escribe el correo de tu cuenta y te enviamos un enlace para elegir una contraseña nueva.</p>

                    <form method="POST" action="{{ route('user.password.email') }}" class="login-form">
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
                                    placeholder="tu@correo.com"
                                    autocomplete="email"
                                    required>
                            </div>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-paper-plane me-2"></i>
                            Enviar enlace
                        </button>

                        <p class="login-register-link">
                            ¿Ya la recordaste?
                            <a href="{{ route('user.login') }}">Ingresar</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
