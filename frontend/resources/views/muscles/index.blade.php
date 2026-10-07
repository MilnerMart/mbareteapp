@php
    $muscles = $data['muscles'];
    $isAdmin = $data['isAdmin'];
@endphp
@extends('layouts.layout')
@section('css')
    @vite('resources/css/catalog.css')
@endsection
@section('content')
    @if (session('catalog_saved'))
        <div class="alert alert-success">{{ session('catalog_saved') }}</div>
    @endif
    @if ($errors->has('muscle'))
        <div class="alert alert-danger">{{ $errors->first('muscle') }}</div>
    @endif
    @if ($isAdmin)
        <div class="catalog-header">
            <h1>Musculos</h1>
            <a href="{{ route('muscle.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                <span>Agregar musculo</span>
            </a>
        </div>
    @endif
    @if (!empty($muscles))
        <div class="row justify-content-center">
            @foreach ($muscles as $muscle)
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex justify-content-center mb-4">
                    <div class="card" style="width: 18rem;">
                        <a href="{{ route('exercise.group', $muscle['id']) }}"><img src="{{ asset($muscle['image_url']??null) }}" class="card-img-top"></a>
                        <div class="card-body">
                            <h5 class="card-title text-center">{{ $muscle['name'] }}</h5>
                            <p class="card-text justify-content-center">{{ $muscle['description'] }}</p>
                            @if ($isAdmin)
                                <div class="d-flex gap-2">
                                    <a href="{{ route('muscle.edit', $muscle['id']) }}" class="btn btn-outline-secondary btn-sm flex-fill">
                                        <i class="fa-solid fa-pen"></i>
                                        <span>Editar</span>
                                    </a>
                                    <form method="POST" action="{{ route('muscle.destroy', $muscle['id']) }}" class="flex-fill"
                                        data-confirm-name="{{ $muscle['name'] }}"
                                        data-confirm-title="Eliminar musculo"
                                        data-confirm-text="El musculo dejara de mostrarse. Solo se puede eliminar si no tiene ejercicios.">
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
                </div>
            @endforeach
        </div>
    @endif
@endsection