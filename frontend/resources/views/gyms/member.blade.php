@php
    $gyms = $data['gyms'];
@endphp
@extends('layouts.layout')

@section('title', 'Mi gimnasio | Mbarete App')

@section('css')
    @vite(['resources/css/gym.css'])
@endsection

@section('content')
    <section class="gym-page">
        <div class="gym-header">
            <h1>{{ count($gyms) > 1 ? 'Mis gimnasios' : 'Mi gimnasio' }}</h1>
        </div>

        @if (session('gym_saved'))
            <div class="alert alert-success">{{ session('gym_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if (empty($gyms))
            <div class="gym-empty">
                <i class="fa-solid fa-dumbbell"></i>
                <p>Todavia no perteneces a ningun gimnasio.</p>
            </div>
        @endif

        @foreach ($gyms as $gym)
            @include('gyms._member-card', ['gym' => $gym])
        @endforeach
    </section>
@endsection
