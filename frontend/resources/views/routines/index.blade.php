@php
    $sections = [
        ['title' => 'Mis rutinas', 'routines' => $data['ownRoutines'], 'empty' => 'Todavia no creaste ninguna rutina.'],
        ['title' => 'Asignadas', 'routines' => $data['assignedRoutines'], 'empty' => 'Tu entrenador todavia no te asigno rutinas.'],
    ];
    if (!empty($data['otherRoutines'])) {
        $sections[] = ['title' => 'Otras rutinas', 'routines' => $data['otherRoutines'], 'empty' => ''];
    }
@endphp
@extends('layouts.layout')

@section('title', 'Rutinas | Mbarete App')

@section('css')
    @vite(['resources/css/routine.css'])
@endsection

@section('content')
    <section class="routine-page">
        <div class="routine-header">
            <h1>Rutinas</h1>
            <a href="{{ route('routine.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                <span>Nueva rutina</span>
            </a>
        </div>

        @if (session('routine_saved'))
            <div class="alert alert-success">{{ session('routine_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @foreach ($sections as $section)
            <div class="routine-section">
                <h2>{{ $section['title'] }}</h2>
                @if (empty($section['routines']))
                    <p class="routine-empty">{{ $section['empty'] }}</p>
                @else
                    <div class="routine-grid">
                        @foreach ($section['routines'] as $routine)
                            <a href="{{ route('routine.show', $routine['id']) }}" class="routine-card">
                                <strong>{{ $routine['name'] }}</strong>
                                @if (!empty($routine['description']))
                                    <span class="routine-card-desc">{{ $routine['description'] }}</span>
                                @endif
                                <span class="routine-meta">
                                    <span><i class="fa-regular fa-calendar"></i> {{ $routine['frequency'] }} dias/semana</span>
                                    <span><i class="fa-regular fa-clock"></i> {{ $routine['restMinutes'] }} min descanso</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </section>
@endsection
