@php
    $gyms = $data['gyms'];
    $memberGym = $data['memberGym'];
    $baseGym = $data['baseGym'];
@endphp
@extends('layouts.layout')

@section('title', 'Mis gimnasios | Mbarete App')

@section('css')
    @vite(['resources/css/gym.css'])
@endsection

@section('content')
    <section class="gym-page">
        @if ($memberGym)
            <a href="{{ route('gym.member') }}" class="gym-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Mis gimnasios</span>
            </a>
        @endif

        <div class="gym-header">
            <div>
                <span class="gym-eyebrow">{{ $memberGym ? 'Gimnasio al que perteneces' : 'Gimnasios donde entrenas' }}</span>
                <h1>Mis gimnasios</h1>
            </div>
            <div class="gym-header-actions">
                @unless ($memberGym)
                    @include('gyms._base-switch', ['baseGym' => $baseGym, 'showBaseGym' => $data['showBaseGym'], 'route' => $data['memberRoute']])
                @endunless
                @if ($data['canJoinGym'])
                    <button type="button" class="btn gym-edit-btn" data-bs-toggle="collapse" data-bs-target="#gymJoinPanel"
                        aria-controls="gymJoinPanel" aria-expanded="{{ $data['isJoinFormOpen'] ? 'true' : 'false' }}">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>Unirme a un gimnasio</span>
                    </button>
                @endif
            </div>
        </div>

        @if ($data['canJoinGym'])
            <div @class(['collapse', 'show' => $data['isJoinFormOpen']]) id="gymJoinPanel">
                <form method="POST" action="{{ route('gym.member.join') }}" class="gym-join-form">
                    @csrf
                    <label for="gym_code" class="form-label">Codigo del gimnasio</label>
                    <p class="gym-join-hint">Pideselo a tu entrenador. Le enviaremos tu solicitud y entraras cuando la acepte.</p>
                    <div class="gym-join-row">
                        <input type="text" id="gym_code" name="gym_code" value="{{ old('gym_code') }}" maxlength="50" required
                            autocomplete="off" placeholder="ej: coach-gym" @class(['form-control', 'is-invalid' => $errors->has('gym_code')])>
                        <button type="submit" class="btn btn-primary">Enviar solicitud</button>
                    </div>
                    @error('gym_code')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </form>
            </div>
        @endif

        @foreach ($data['pendingJoinList'] as $pendingJoin)
            <div class="alert gym-join-pending">
                <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                Tu solicitud {{ $pendingJoin['number'] }} para unirte a <strong>{{ $pendingJoin['gymName'] }}</strong> espera que el entrenador la acepte.
            </div>
        @endforeach

        @if (session('gym_saved'))
            <div class="alert alert-success">{{ session('gym_saved') }}</div>
        @endif
        @if ($errors->any() && !$errors->has('gym_code'))
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if ($memberGym)
            @include('gyms._member-card', ['gym' => $memberGym])
        @elseif (empty($gyms))
            <div class="gym-empty">
                <i class="fa-solid fa-dumbbell"></i>
                @if ($baseGym && !$data['showBaseGym'])
                    <p>Activa "Ver gimnasio {{ $baseGym['name'] }}" para verlo en tu lista.</p>
                @else
                    <p>Todavia no perteneces a ningun gimnasio.</p>
                @endif
            </div>
        @else
            <div class="gym-grid">
                @foreach ($gyms as $gym)
                    @include('gyms._card', ['gym' => $gym, 'isAdmin' => false, 'memberRoute' => $data['memberRoute']])
                @endforeach
            </div>
        @endif
    </section>
@endsection
