@extends('layouts.layout')

@section('title', 'Reglas del sistema | Mbarete App')

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
            <h1>Reglas del sistema</h1>
            <p>Tareas que la app corre sola a la hora que elijas. Si el servidor estuvo apagado a esa hora, la regla corre apenas vuelve y se pone al día.</p>
        </div>

        @if (session('config_saved'))
            <div class="alert alert-success">{{ session('config_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if ($data['loadFailed'])
            <p class="config-empty">No pudimos cargar las reglas.</p>
        @elseif (!$data['rules'])
            <p class="config-empty">Todavía no hay reglas.</p>
        @else
            <form method="POST" action="{{ route('config.rules.update') }}" class="config-form">
                @csrf
                @method('PUT')
                @foreach ($data['rules'] as $rule)
                    <div class="config-panel config-rule">
                        <div class="config-rule-header">
                            <span class="config-card-icon"><i class="{{ $rule['icon'] }}" aria-hidden="true"></i></span>
                            <div>
                                <h2>{{ $rule['title'] }}</h2>
                                <p class="config-optional">{{ $rule['description'] }}</p>
                            </div>
                        </div>

                        <div class="config-rule-settings">
                            <div class="form-check form-switch">
                                <input type="hidden" name="rules[{{ $rule['ruleModelId'] }}][is_enabled]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" value="1" id="rule_enabled_{{ $rule['ruleModelId'] }}"
                                    name="rules[{{ $rule['ruleModelId'] }}][is_enabled]" @checked($rule['isEnabled'])>
                                <label class="form-check-label" for="rule_enabled_{{ $rule['ruleModelId'] }}">Activa</label>
                            </div>
                            <div>
                                <label for="rule_frequency_{{ $rule['ruleModelId'] }}" class="form-label">Frecuencia</label>
                                <select id="rule_frequency_{{ $rule['ruleModelId'] }}" name="rules[{{ $rule['ruleModelId'] }}][frequency]" class="form-select">
                                    @foreach ($rule['frequencyOptions'] as $value => $label)
                                        <option value="{{ $value }}" @selected($rule['frequency'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="rule_run_at_{{ $rule['ruleModelId'] }}" class="form-label">Hora</label>
                                <input type="time" id="rule_run_at_{{ $rule['ruleModelId'] }}" name="rules[{{ $rule['ruleModelId'] }}][run_at]"
                                    class="form-control" value="{{ $rule['runAt'] }}" required>
                            </div>
                            <div>
                                <label for="rule_retry_{{ $rule['ruleModelId'] }}" class="form-label">Reintentar si falla (minutos)</label>
                                <input type="number" id="rule_retry_{{ $rule['ruleModelId'] }}" name="rules[{{ $rule['ruleModelId'] }}][retry_minutes]"
                                    class="form-control" value="{{ $rule['retryMinutes'] }}" min="0" max="1440" step="1" required>
                            </div>
                        </div>
                        <p class="config-rule-schedule">
                            <span>{{ $rule['scheduleText'] }} {{ $rule['retryText'] }}</span>
                            <strong>{{ $rule['nextRunLabel'] }}</strong>
                        </p>

                        <div class="config-rule-runs-header">
                            <h3>Últimas ejecuciones</h3>
                            <button type="submit" form="{{ $rule['runFormId'] }}" class="btn btn-sm config-rule-run-btn">
                                <i class="fa-solid fa-play" aria-hidden="true"></i> Ejecutar ahora
                            </button>
                        </div>
                        @if ($rule['runList'])
                            <ul class="config-rule-runs">
                                @foreach ($rule['runList'] as $run)
                                    <li>
                                        <span class="config-run-status {{ $run['statusClass'] }}">{{ $run['statusLabel'] }}</span>
                                        <span class="config-run-date">{{ $run['dateLabel'] }} · {{ $run['originLabel'] }}@if ($run['durationLabel']) · {{ $run['durationLabel'] }}@endif</span>
                                        @if ($run['detailText'])
                                            <span class="config-run-detail">{{ $run['detailText'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="config-optional">Todavía no corrió.</p>
                        @endif
                    </div>
                @endforeach
                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>

            {{-- fuera del formulario principal: los botones "Ejecutar ahora" los envian con el atributo form --}}
            @foreach ($data['rules'] as $rule)
                <form method="POST" action="{{ $rule['runUrl'] }}" id="{{ $rule['runFormId'] }}" hidden>
                    @csrf
                </form>
            @endforeach
        @endif
    </section>
@endsection
