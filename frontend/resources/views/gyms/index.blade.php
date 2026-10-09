@php
    $gyms = $data['gyms'];
    $isAdmin = $data['isAdmin'];
    $memberGym = $data['memberGym'];
@endphp
@extends('layouts.layout')

@section('title', 'Gimnasios | Mbarete App')

@section('css')
    @vite(['resources/css/gym.css'])
@endsection

@section('content')
    <section class="gym-page">
        @if ($memberGym)
            <a href="{{ route('gym.index') }}" class="gym-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Gimnasios</span>
            </a>
        @endif

        <div class="gym-header">
            <div>
                <span class="gym-eyebrow">
                    @if ($memberGym)
                        Gimnasio al que perteneces
                    @else
                        {{ $isAdmin ? 'Todos los gimnasios' : 'Mis gimnasios' }}
                    @endif
                </span>
                <h1>Gimnasios</h1>
            </div>
            <div class="gym-header-actions">
                @if (!$memberGym)
                    @include('gyms._base-switch', ['baseGym' => $data['baseGym'], 'showBaseGym' => $data['showBaseGym'], 'route' => $data['memberRoute']])
                @endif
                @unless ($memberGym)
                    <a href="{{ route('gym.create') }}" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        <span>Nuevo gimnasio</span>
                    </a>
                @endunless
            </div>
        </div>

        @if (session('gym_saved'))
            <div class="alert alert-success">{{ session('gym_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if ($memberGym)
            @include('gyms._member-card', ['gym' => $memberGym])
        @elseif (empty($gyms))
            <div class="gym-empty">
                <i class="fa-solid fa-dumbbell"></i>
                <p>Todavia no hay gimnasios cargados.</p>
            </div>
        @else
            <div class="gym-grid">
                @foreach ($gyms as $gym)
                    @include('gyms._card', ['gym' => $gym, 'isAdmin' => $isAdmin, 'memberRoute' => $data['memberRoute']])
                @endforeach
            </div>
        @endif
    </section>
@endsection
