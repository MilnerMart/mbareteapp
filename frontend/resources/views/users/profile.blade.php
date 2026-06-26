@php
    $userProfile = $data['profileInfo'] ?? null;
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
                        <p><strong>Nombre:</strong> Leoncio Paraguay</p>
                        <p><strong>Edad:</strong> 25 años</p>
                        <p><strong>Altura:</strong> 1.75 cm</p>
                        <p><strong>Peso:</strong> 90 kg</p>
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