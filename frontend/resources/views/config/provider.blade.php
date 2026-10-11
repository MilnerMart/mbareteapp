@php
    $provider = $data['provider'];
    $client = $provider['client'];
    $capabilities = $data['capabilities'];
    $channels = $data['channels'];
    $kvLists = [
        ['list' => 'credentials', 'legend' => 'Credenciales', 'hint' => 'Se guardan cifradas y no se vuelven a mostrar.', 'keyPlaceholder' => 'ej. api_key', 'secret' => true, 'keys' => $client['credentialKeys']],
        ['list' => 'settings', 'legend' => 'Ajustes', 'hint' => 'Datos que no son secretos, ej. from, region o baseUrl para usar otra url.', 'keyPlaceholder' => 'ej. from', 'secret' => false, 'keys' => $client['settingKeys']],
    ];
@endphp
@extends('layouts.layout')

@section('title', $provider['name'] . ' | Mbarete App')

@section('css')
    @vite(['resources/css/config.css'])
@endsection

@section('content')
    <section class="config-page">
        <a href="{{ route('config.providers') }}" class="config-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Proveedores</span>
        </a>

        <div class="config-header">
            <h1>{{ $provider['name'] }}</h1>
            <div class="config-chip-list">
                <span class="config-chip">{{ $client['label'] ?? 'Tipo desconocido: ' . $provider['type'] }}</span>
                @foreach ($client['capabilities'] as $capability)
                    <span class="config-chip">{{ $capabilities[$capability] ?? $capability }}</span>
                @endforeach
                @foreach ($client['channels'] as $channel)
                    <span class="config-chip">{{ $channels[$channel] ?? $channel }}</span>
                @endforeach
            </div>
            @if (!$client['label'])
                <p class="config-optional">Este tipo no tiene un cliente registrado en el backend: sus credenciales no se pueden usar en la app.</p>
            @endif
        </div>

        @if (session('config_saved'))
            <div class="alert alert-success">{{ session('config_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="config-panel">
            <h2>Datos del proveedor</h2>
            <form method="POST" action="{{ route('config.providers.update', $provider['id']) }}" class="config-form config-provider-form">
                @csrf
                @method('PUT')
                <div>
                    <label for="provider_name" class="form-label">Nombre</label>
                    <input type="text" id="provider_name" name="name" class="form-control" value="{{ old('name', $provider['name']) }}" required minlength="2" maxlength="100">
                </div>
                <div class="config-provider-form-url">
                    <label for="provider_base_url" class="form-label">Url de la api <span class="config-optional">(opcional)</span></label>
                    <input type="url" id="provider_base_url" name="base_url" class="form-control" value="{{ old('base_url', $provider['baseUrl']) }}" placeholder="https://api.proveedor.com/v1" maxlength="255">
                </div>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
            <form method="POST" action="{{ route('config.providers.destroy', $provider['id']) }}" class="config-danger-zone"
                  data-confirm-delete
                  data-confirm-title="Eliminar proveedor"
                  data-confirm-text="Se eliminan tambien sus credenciales y dejan de usarse en la app.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn config-remove-btn">
                    <i class="fa-solid fa-trash"></i>
                    <span>Eliminar proveedor</span>
                </button>
            </form>
        </div>

        <div class="config-panel">
            <h2>Credenciales</h2>
            @if (empty($provider['configs']))
                <p class="config-empty">Sin credenciales todavía.</p>
            @else
                <ul class="config-credential-list">
                    @foreach ($provider['configs'] as $providerConfig)
                        <li class="config-credential">
                            <div class="config-credential-info">
                                <strong>{{ $providerConfig['name'] }}</strong>
                                <span class="config-chip-list">
                                    @forelse ($providerConfig['credentialKeys'] as $credentialKey)
                                        <span class="config-chip"><i class="fa-solid fa-key" aria-hidden="true"></i> {{ $credentialKey }}</span>
                                    @empty
                                        <span class="config-optional">Sin claves</span>
                                    @endforelse
                                </span>
                                @foreach ($providerConfig['settings'] as $settingKey => $settingValue)
                                    <span class="config-credential-setting">{{ $settingKey }}: {{ is_scalar($settingValue) ? $settingValue : json_encode($settingValue) }}</span>
                                @endforeach
                            </div>
                            <form method="POST" action="{{ route('config.provider-configs.destroy', $providerConfig['id']) }}"
                                  data-confirm-delete
                                  data-confirm-title="Eliminar credencial"
                                  data-confirm-text="Si la app la estaba usando, esa accion queda sin credencial.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn config-icon-btn" title="Eliminar credencial" aria-label="Eliminar credencial {{ $providerConfig['name'] }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <details class="config-credential-new" @if (empty($provider['configs']) || old('config_name')) open @endif>
                <summary>
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Agregar credencial</span>
                </summary>
                <form method="POST" action="{{ route('config.provider-configs.store', $provider['id']) }}" class="config-form" autocomplete="off">
                    @csrf
                    <div>
                        <label for="config_name" class="form-label">Nombre de la credencial</label>
                        <input type="text" id="config_name" name="config_name" class="form-control" value="{{ old('config_name') }}"
                               placeholder="ej. Produccion" required minlength="2" maxlength="100">
                    </div>
                    @foreach ($kvLists as $kvList)
                        <fieldset class="config-kv" data-kv-list data-kv-next="{{ max(count($kvList['keys']), 1) }}">
                            <legend>{{ $kvList['legend'] }}</legend>
                            <p class="config-optional">{{ $kvList['hint'] }}</p>
                            <div class="config-kv-rows">
                                @forelse ($kvList['keys'] as $keyIndex => $keyName)
                                    @include('config._kv-row', $kvList + ['index' => $keyIndex, 'key' => $keyName])
                                @empty
                                    @include('config._kv-row', $kvList + ['index' => 0, 'key' => ''])
                                @endforelse
                            </div>
                            <template>
                                @include('config._kv-row', $kvList + ['index' => '__INDEX__', 'key' => ''])
                            </template>
                            <button type="button" class="btn config-btn" data-kv-add>
                                <i class="fa-solid fa-plus"></i>
                                <span>Agregar fila</span>
                            </button>
                        </fieldset>
                    @endforeach
                    <div>
                        <button type="submit" class="btn btn-primary">Guardar credencial</button>
                    </div>
                </form>
            </details>
        </div>
    </section>
@endsection

@section('js')
    {{-- module: corre despues de app.js, que es quien expone jQuery --}}
    <script type="module">
        $('[data-kv-add]').on('click', function () {
            const $list = $(this).closest('[data-kv-list]');
            const index = Number($list.attr('data-kv-next'));
            $list.attr('data-kv-next', index + 1);
            $list.find('.config-kv-rows').append($list.find('template').html().replaceAll('__INDEX__', index));
        });

        // la ultima fila no se quita, solo se vacia
        $(document).on('click', '[data-kv-remove]', function () {
            const $row = $(this).closest('.config-kv-row');
            if ($row.siblings('.config-kv-row').length) {
                $row.remove();
            } else {
                $row.find('input').val('');
            }
        });
    </script>
@endsection
