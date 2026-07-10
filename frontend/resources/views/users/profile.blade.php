@php
    $userProfile = $data['profileInfo'] ?? null;
    $fullName = trim(($userProfile['name'] ?? '') . ' ' . ($userProfile['last_name'] ?? ''));
    $profileImage = $userProfile['profile_image_url'] ?? asset('images/leoncioBiceps.png');
@endphp
@extends('layouts.layout')
@section('css')
    @vite('resources/css/profile.css')
@endsection
@section('content')
    <div class="container mt-4">
        <div class="card profile-card">
            <div class="card-body">
                <div class="card-header profile-card-header">
                    <button type="button" class="btn-edit-profile">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>
                @if (session('profile_image_updated'))
                    <div class="alert alert-success profile-alert">
                        {{ session('profile_image_updated') }}
                    </div>
                @endif
                @error('profile_image')
                    <div class="alert alert-danger profile-alert">
                        {{ $message }}
                    </div>
                @enderror
                <div class="row justify-content-center mb-4">
                    <div class="col-12 d-flex justify-content-center">
                        <form method="POST" action="{{ route('user.profile.image', $userProfile['id'] ?? session('auth_user.id')) }}" enctype="multipart/form-data" class="profile-picture-form">
                            @csrf
                            <label class="profile-picture" for="profileImageInput">
                                <img src="{{ $profileImage }}" alt="Foto de perfil">

                                <span class="profile-picture-overlay">
                                    <i class="fa-solid fa-camera"></i>
                                </span>
                            </label>

                            <input
                                type="file"
                                id="profileImageInput"
                                name="profile_image"
                                class="d-none"
                                accept="image/jpeg,image/png,image/webp"
                                onchange="this.form.submit()">
                        </form>
                    </div>
                </div>

                <div class="row g-3 profile-info-row">
                    <div class="col-12 col-md-6">
                        <p><strong>Nombre:</strong> {{ $fullName ?: 'Usuario' }}</p>
                        <p><strong>Edad:</strong> {{ $userProfile['age'] ?? '-' }} años</p>
                        <p><strong>Altura:</strong> {{ $userProfile['height'] ?? '-' }} cm</p>
                        <p><strong>Peso:</strong> {{ $userProfile['weight'] ?? '-' }} kg</p>
                    </div>

                    <div class="col-12 col-md-6">
                        <p><strong>Nivel:</strong> Principiante</p>
                        <p><strong>Rutina:</strong> Bíceps</p>
                        <p><strong>Objetivo:</strong> Hipertrofia</p>
                        <p><strong>rango:</strong> Bestia</p>
                    </div>
                </div>

            </div>
        </div>
        <div class="card profile-card">
            <div class="card-header">
                <div class="row text-center">
                    <p><strong>Progresos</strong></p>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-md-6">
                        <p><strong>Nivel:</strong> Principiante</p>
                        <p><strong>Rutina:</strong> Bíceps</p>
                        <p><strong>Objetivo:</strong> Hipertrofia</p>
                        <p><strong>rango:</strong> Bestia</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
