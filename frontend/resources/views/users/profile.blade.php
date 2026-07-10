@php
    $userProfile = $data['profileInfo'] ?? null;
    $fullName = trim(($userProfile['name'] ?? '') . ' ' . ($userProfile['last_name'] ?? ''));
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
                <div class="row justify-content-center mb-4">
                    <div class="col-12 d-flex justify-content-center">
                        <label class="profile-picture" for="profileImageInput">
                            <img src="{{ asset('images/leoncioBiceps.png') }}" alt="Foto de perfil">

                            <div class="profile-picture-overlay">
                                <i class="fa-solid fa-pen"></i>
                            </div>
                        </label>

                        <input type="file" id="profileImageInput" name="profile_image" class="d-none">
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
