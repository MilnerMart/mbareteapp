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
    @if ($errors->has('exercise'))
        <div class="alert alert-danger">{{ $errors->first('exercise') }}</div>
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
                        @if ($isAdmin)
                            <div class="card-body d-flex gap-2">
                                <a href="{{ route('exercise.edit', $exercise['id']) }}" class="btn btn-outline-secondary btn-sm flex-fill">
                                    <i class="fa-solid fa-pen"></i>
                                    <span>Editar</span>
                                </a>
                                <form method="POST" action="{{ route('exercise.destroy', $exercise['id']) }}" class="flex-fill"
                                    data-confirm-delete
                                    data-confirm-title="Eliminar ejercicio"
                                    data-confirm-text="El ejercicio dejara de mostrarse.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                        <i class="fa-solid fa-trash"></i>
                                        <span>Eliminar</span>
                                    </button>
                                </form>
                            </div>
                        @endif
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