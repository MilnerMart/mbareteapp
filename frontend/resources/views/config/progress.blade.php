@extends('layouts.layout')

@section('title', 'Rangos y puntos | Mbarete App')

@section('css')
    @vite(['resources/css/config.css'])
@endsection

@section('content')
    <section class="config-page">
        <a href="{{ route('config.index') }}" class="config-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Configuración</span>
        </a>

        <div class="config-header">
            <h1>Rangos y puntos</h1>
            <p>Los alumnos suman puntos entrenando. Cada día suma como mucho el tope, así una sola sesión larga no alcanza para subir de rango.</p>
        </div>

        @if (session('config_saved'))
            <div class="alert alert-success">{{ session('config_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if ($data['loadFailed'])
            <p class="config-empty">No pudimos cargar la configuración.</p>
        @else
            <form method="POST" action="{{ route('config.progress.update') }}" class="config-form">
                @csrf
                @method('PUT')
                <div class="config-panel">
                    <h2>Puntos</h2>
                    <div class="config-action-list">
                        @foreach ($data['scoringFields'] as $field)
                            <div class="config-action">
                                <label for="scoring_{{ $field['key'] }}" class="form-label">{{ $field['label'] }}</label>
                                <input type="number" id="scoring_{{ $field['key'] }}" name="scoring[{{ $field['key'] }}]" class="form-control"
                                    value="{{ $field['value'] }}" min="{{ $field['min'] }}" step="1" required>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="config-panel">
                    <h2>Rangos</h2>
                    <p class="config-optional">Cada rango tiene que pedir más puntos que el anterior.</p>
                    <div class="config-rank-table">
                        <div class="config-rank-row config-rank-head" aria-hidden="true">
                            <span>Rango</span>
                            <span>Puntos mínimos</span>
                        </div>
                        @foreach ($data['rankRows'] as $rank)
                            <div class="config-rank-row">
                                <strong>{{ $rank['label'] }}</strong>
                                @if ($rank['isFixed'])
                                    <input type="hidden" name="ranks[{{ $rank['slug'] }}][minPoints]" value="0">
                                    <span class="config-optional">Rango de entrada</span>
                                @else
                                    <input type="number" name="ranks[{{ $rank['slug'] }}][minPoints]" class="form-control" value="{{ $rank['minPoints'] }}"
                                        min="0" step="1" required aria-label="Puntos mínimos para {{ $rank['label'] }}">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
        @endif
    </section>
@endsection
