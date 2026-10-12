@extends('layouts.layout')

@section('title', 'Nueva contraseña | Leoncio Gym')

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
                            <span>{{ $email }}</span>
                            <strong>Nueva contraseña</strong>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('user.password.update') }}" class="login-form">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <input type="hidden" name="email" value="{{ $email }}">

                        @foreach ([['password', 'Contraseña nueva', 'Minimo 8 caracteres'], ['password_confirmation', 'Repite la contraseña', 'Igual a la anterior']] as [$field, $label, $placeholder])
                            <div class="mb-3">
                                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fa-solid fa-lock"></i>
                                    </span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="{{ $field }}"
                                        name="{{ $field }}"
                                        placeholder="{{ $placeholder }}"
                                        autocomplete="new-password"
                                        minlength="8"
                                        required>
                                    <button
                                        type="button"
                                        class="btn btn-password-toggle"
                                        data-password-toggle="#{{ $field }}"
                                        aria-label="Mostrar contraseña"
                                        aria-pressed="false">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-key me-2"></i>
                            Cambiar contraseña
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
