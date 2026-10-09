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
