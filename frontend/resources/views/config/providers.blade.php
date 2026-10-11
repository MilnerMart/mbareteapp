@php
    $capabilities = $data['capabilities'];
@endphp
@extends('layouts.layout')

@section('title', 'Proveedores | Mbarete App')

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
            <h1>Proveedores</h1>
            <p>Servicios externos a los que se conecta la app. Entra a uno para editarlo o agregarle credenciales.</p>
        </div>

        @if (session('config_saved'))
            <div class="alert alert-success">{{ session('config_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="config-panel">
            <h2>Nuevo proveedor</h2>
            @if (empty($data['types']))
                <p class="config-empty">No hay tipos de proveedor disponibles. Se agregan en el backend, en config/provider_clients.php.</p>
            @else
                <form method="POST" action="{{ route('config.providers.store') }}" class="config-form config-provider-form">
                    @csrf
                    <div>
                        <label for="provider_name" class="form-label">Nombre</label>
                        <input type="text" id="provider_name" name="name" class="form-control" value="{{ old('name') }}" placeholder="ej. Cloudinary" required minlength="2" maxlength="100">
                    </div>
                    <div>
                        <label for="provider_type" class="form-label">Tipo</label>
                        <select id="provider_type" name="type" class="form-select" required>
                            @foreach ($data['types'] as $providerType)
                                <option value="{{ $providerType['type'] }}" @selected(old('type') === $providerType['type'])>
                                    {{ $providerType['label'] }}
                                    @if (!empty($providerType['capabilities']))
                                        ({{ collect($providerType['capabilities'])->map(fn($capability) => $capabilities[$capability] ?? $capability)->implode(', ') }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="config-provider-form-url">
                        <label for="provider_base_url" class="form-label">Url de la api <span class="config-optional">(opcional)</span></label>
                        <input type="url" id="provider_base_url" name="base_url" class="form-control" value="{{ old('base_url') }}" placeholder="https://api.proveedor.com/v1" maxlength="255">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        <span>Crear</span>
                    </button>
                </form>
            @endif
        </div>

        <h2 class="config-subtitle">Proveedores cargados</h2>
        @if (empty($data['providers']))
            <p class="config-empty">Todavía no cargaste ningún proveedor.</p>
        @else
            <div class="config-grid">
                @foreach ($data['providers'] as $provider)
                    <a href="{{ route('config.providers.show', $provider['id']) }}" class="config-card">
                        <strong>{{ $provider['name'] }}</strong>
                        <span class="config-card-desc">{{ $provider['client']['label'] ?? 'Tipo desconocido: ' . $provider['type'] }}</span>
                        <span class="config-chip-list">
                            @foreach ($provider['client']['capabilities'] as $capability)
                                <span class="config-chip">{{ $capabilities[$capability] ?? $capability }}</span>
                            @endforeach
                            <span class="config-chip"><i class="fa-solid fa-key" aria-hidden="true"></i> {{ $provider['configCount'] }} {{ $provider['configCount'] === 1 ? 'credencial' : 'credenciales' }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
