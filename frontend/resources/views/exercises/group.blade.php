@php
    $exerciseGroup = $data['exerciseGroup'];
    $muscle = $data['muscle'];
    $isAdmin = $data['isAdmin'];
@endphp
@extends('layouts.layout')
@section('css')
    @vite(['resources/css/group.css', 'resources/css/catalog.css'])
@endsection
@section('content')
    <a href="{{ route('muscle.index') }}" class="catalog-back-link">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Inicio</span>
    </a>
    @if (session('catalog_saved'))
        <div class="alert alert-success">{{ session('catalog_saved') }}</div>
    @endif
    <div class="catalog-header">
        <div>
            <h1>{{ $muscle['name'] }}</h1>
            @if (!empty($muscle['description']))
                <p>{{ $muscle['description'] }}</p>
            @endif
        </div>
        @if ($isAdmin)
            <a href="{{ route('exercise.create', $muscle['id']) }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                <span>Agregar ejercicio</span>
            </a>
        @endif
    </div>
    @if (!empty($exerciseGroup))
        <div class="row justify-content-center">
            @foreach ($exerciseGroup as $exercise)
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex justify-content-center mb-4">
                    <div class="card" style="width: 25rem; border-radius: 10%;">
                        <a href="{{ route('exercise.resource', $exercise['id']) }}"><img src="{{ asset($exercise['image'] ?? 'images/leoncioRest.png') }}" alt="{{ $exercise['name'] }}" class="card-img-top" style="border-radius: 10%;"></a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-exercises-wrapper">
            <img src="{{ asset('images/leoncioRest.png') }}" alt="Leoncio descansando" class="leoncio-rest-img">
        </div>
    @endif    
@endsection