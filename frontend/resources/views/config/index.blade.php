@extends('layouts.layout')

@section('title', 'Configuración | Mbarete App')

@section('css')
    @vite(['resources/css/config.css'])
@endsection

@section('content')
    <section class="config-page">
        <div class="config-header">
            <h1>Configuración</h1>
            <p>Ajustes generales de la app.</p>
        </div>

        @if (session('config_saved'))
            <div class="alert alert-success">{{ session('config_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="config-grid">
            @foreach ($data['sections'] as $section)
                {{-- sin route el apartado todavia no tiene pantalla: se muestra pero no se puede abrir --}}
                @if ($section['route'])
                    <a href="{{ route($section['route']) }}" class="config-card">
                        @include('config._section-card', ['section' => $section])
                    </a>
                @else
                    <div class="config-card is-disabled" aria-disabled="true">
                        @include('config._section-card', ['section' => $section])
                        <span class="config-card-badge">Próximamente</span>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
@endsection
