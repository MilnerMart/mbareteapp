@php
    $actionsByCapability = collect($data['actions'])->groupBy('capability');
@endphp
@extends('layouts.layout')

@section('title', 'Credenciales de la app | Mbarete App')

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
            <h1>Credenciales de la app</h1>
            <p>Elige que credencial usa la app para cada accion. Solo aparecen las de proveedores que pueden hacerla.</p>
        </div>

        @if (session('config_saved'))
            <div class="alert alert-success">{{ session('config_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if (empty($data['actions']))
            <p class="config-empty">No pudimos cargar la configuración.</p>
        @else
            <form method="POST" action="{{ route('config.core.update') }}" class="config-form">
                @csrf
                @method('PUT')
                @foreach ($actionsByCapability as $capability => $actionList)
                    <div class="config-panel">
                        <h2>{{ $data['capabilities'][$capability] ?? $capability }}</h2>
                        <div class="config-action-list">
                            @foreach ($actionList as $action)
                                <div class="config-action">
                                    <label for="action_{{ $action['action'] }}" class="form-label">
                                        {{ $action['label'] }}
                                        @if ($action['channel'])
                                            <span class="config-chip">{{ $data['channels'][$action['channel']] ?? $action['channel'] }}</span>
                                        @endif
                                    </label>
                                    <select id="action_{{ $action['action'] }}" name="actions[{{ $action['action'] }}]" class="form-select">
                                        <option value="">Sin asignar</option>
                                        @foreach ($action['options'] as $option)
                                            <option value="{{ $option['id'] }}" @selected($action['providerConfigId'] === $option['id'])>
                                                {{ $option['providerName'] }} · {{ $option['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if (empty($action['options']))
                                        <span class="config-optional">
                                            Ningún proveedor cargado puede hacer esto. Revisa los <a href="{{ route('config.providers') }}">proveedores</a>.
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
        @endif
    </section>
@endsection
