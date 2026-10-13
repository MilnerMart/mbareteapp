@php
    $isAdmin = $data['isAdmin'];
    $canCreate = $canCreateCatalog ?? false;
    // grupos musculares y actividades (cardio, boxeo...) se muestran en secciones separadas
    $sectionList = [
        ['title' => 'Musculos', 'kind' => 'muscle', 'addLabel' => 'Agregar musculo', 'groups' => $data['muscles']],
        ['title' => 'Actividades', 'kind' => 'activity', 'addLabel' => 'Agregar actividad', 'groups' => $data['activities']],
    ];
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
    @foreach ($sectionList as $section)
        @if ($canCreate || !empty($section['groups']))
            <section class="mb-4">
                <div class="catalog-header">
                    <h1>{{ $section['title'] }}</h1>
                    @if ($canCreate)
                        <a href="{{ route('muscle.create', ['kind' => $section['kind']]) }}" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            <span>{{ $section['addLabel'] }}</span>
                        </a>
                    @endif
                </div>
                @if (!empty($section['groups']))
                    <div class="row justify-content-center">
                        @foreach ($section['groups'] as $muscle)
                            @include('muscles._card', ['muscle' => $muscle, 'isAdmin' => $isAdmin])
                        @endforeach
                    </div>
                @else
                    <p class="catalog-hint">Todavia no hay {{ mb_strtolower($section['title']) }}.</p>
                @endif
            </section>
        @endif
    @endforeach
@endsection
