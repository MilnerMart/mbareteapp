@php
    $weightCard = $data['weightCard'];
    $bmi = $data['bmi'];
@endphp
@extends('layouts.layout')

@section('title', 'Mi progreso | Mbarete App')

@section('css')
    @vite(['resources/css/progress.css'])
@endsection

@section('content')
    <section class="progress-page">
        <header class="progress-header">
            <span class="progress-eyebrow">Peso, medidas y records</span>
            <h1>Mi progreso</h1>
        </header>

        @if (session('progress_saved'))
            <div class="alert alert-success">{{ session('progress_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        @if ($data['loadFailed'])
            <div class="alert alert-danger">No pudimos cargar tu progreso. Intenta de nuevo.</div>
        @endif

        <div class="progress-summary">
            <article class="progress-card">
                <i class="fa-solid fa-weight-scale progress-card-icon" aria-hidden="true"></i>
                <span class="progress-card-label">Peso actual</span>
                <strong class="progress-card-value">{{ $weightCard['value'] }}</strong>
                @if ($weightCard['changeText'])
                    <span class="progress-change">{{ $weightCard['changeText'] }} desde el registro anterior</span>
                @endif
                <span class="progress-card-hint">{{ $weightCard['dateLabel'] }}</span>
            </article>
            <article class="progress-card">
                <i class="fa-solid fa-heart-pulse progress-card-icon" aria-hidden="true"></i>
                <span class="progress-card-label">IMC</span>
                @if ($bmi)
                    <strong class="progress-card-value">{{ $bmi['value'] }}</strong>
                    <span class="progress-bmi progress-bmi-{{ $bmi['level'] }}">{{ $bmi['label'] }}</span>
                @else
                    <strong class="progress-card-value">-</strong>
                    <span class="progress-card-hint">Completa tu altura y peso en el perfil.</span>
                @endif
            </article>
            <article class="progress-card">
                <i class="fa-solid fa-trophy progress-card-icon" aria-hidden="true"></i>
                <span class="progress-card-label">Records</span>
                <strong class="progress-card-value">{{ count($data['recordList']) }}</strong>
                <span class="progress-card-hint">ejercicios con marca personal</span>
            </article>
        </div>

        <div class="progress-layout">
            <div class="progress-main">
                <div class="progress-panel">
                    <h2>Registrar</h2>
                    <div class="progress-forms">
                        <form method="POST" action="{{ route('progress.store') }}" class="progress-form">
                            @csrf
                            <input type="hidden" name="metric" value="body-weight">
                            <h3><i class="fa-solid fa-weight-scale" aria-hidden="true"></i> Peso</h3>
                            <label class="form-label" for="weight_value">Kilos</label>
                            <input type="number" id="weight_value" name="value" class="form-control" min="25" max="300" step="0.1" required>
                            <label class="form-label" for="weight_date">Dia</label>
                            <input type="date" id="weight_date" name="entry_on" class="form-control" value="{{ $data['today'] }}" max="{{ $data['today'] }}" required>
                            <button type="submit" class="btn btn-primary">Guardar peso</button>
                        </form>

                        <form method="POST" action="{{ route('progress.store') }}" class="progress-form">
                            @csrf
                            <h3><i class="fa-solid fa-ruler-horizontal" aria-hidden="true"></i> Medida</h3>
                            <label class="form-label" for="measurement_metric">Que mediste</label>
                            <select id="measurement_metric" name="metric" class="form-select" required>
                                @foreach ($data['measurementOptions'] as $metric => $label)
                                    <option value="{{ $metric }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <label class="form-label" for="measurement_value">Valor</label>
                            <input type="number" id="measurement_value" name="value" class="form-control" min="1" max="300" step="0.1" required>
                            <label class="form-label" for="measurement_date">Dia</label>
                            <input type="date" id="measurement_date" name="entry_on" class="form-control" value="{{ $data['today'] }}" max="{{ $data['today'] }}" required>
                            <button type="submit" class="btn btn-primary">Guardar medida</button>
                        </form>

                        <form method="POST" action="{{ route('progress.store') }}" class="progress-form">
                            @csrf
                            <h3><i class="fa-solid fa-dumbbell" aria-hidden="true"></i> Record</h3>
                            <label class="form-label" for="record_exercise">Ejercicio</label>
                            <select id="record_exercise" name="exercise_id" class="form-select" required
                                data-searchable data-placeholder="Busca un ejercicio o un musculo">
                                <option value="">Busca un ejercicio o un musculo</option>
                                @foreach ($data['exerciseGroups'] as $muscleName => $exerciseList)
                                    <optgroup label="{{ $muscleName }}">
                                        @foreach ($exerciseList as $exerciseId => $exerciseName)
                                            <option value="{{ $exerciseId }}">{{ $exerciseName }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <div class="progress-form-row">
                                <div>
                                    <label class="form-label" for="record_metric">Marca</label>
                                    <select id="record_metric" name="metric" class="form-select">
                                        <option value="max-kg">Kilos maximos</option>
                                        <option value="max-reps">Repeticiones maximas</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" for="record_value">Valor</label>
                                    <input type="number" id="record_value" name="value" class="form-control" min="0.5" max="1000" step="0.5" required>
                                </div>
                            </div>
                            <label class="form-label" for="record_date">Dia</label>
                            <input type="date" id="record_date" name="entry_on" class="form-control" value="{{ $data['today'] }}" max="{{ $data['today'] }}" required>
                            <button type="submit" class="btn btn-primary">Guardar record</button>
                        </form>
                    </div>
                </div>

                @if (!empty($data['recordList']))
                    <div class="progress-panel">
                        <h2>Records por ejercicio</h2>
                        <div class="progress-records">
                            <div class="progress-record progress-record-head" aria-hidden="true">
                                <span>Ejercicio</span><span>Kilos maximos</span><span>Repeticiones maximas</span>
                            </div>
                            @foreach ($data['recordList'] as $record)
                                <div class="progress-record">
                                    <strong>{{ $record['exerciseName'] }}</strong>
                                    @foreach ($record['best'] as $best)
                                        <span>
                                            @if ($best)
                                                {{ $best['value'] }} <small>{{ $best['dateLabel'] }}</small>
                                            @else
                                                <small>-</small>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!empty($data['measurementList']))
                    <div class="progress-panel">
                        <h2>Medidas</h2>
                        <dl class="progress-measurements">
                            @foreach ($data['measurementList'] as $measurement)
                                <div>
                                    <dt>{{ $measurement['label'] }}</dt>
                                    <dd>{{ $measurement['value'] }}</dd>
                                    @if ($measurement['changeText'])
                                        <span class="progress-change">{{ $measurement['changeText'] }}</span>
                                    @endif
                                    <small>{{ $measurement['dateLabel'] }}</small>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                <div class="progress-panel">
                    <h2>Historial</h2>
                    @if (empty($data['historyList']))
                        <p class="progress-empty">Todavia no registraste nada. Empieza por tu peso de hoy.</p>
                    @else
                        <ul class="progress-history">
                            @foreach ($data['historyList'] as $entry)
                                <li @class(['progress-entry', 'is-best' => $entry['isPersonalBest']])>
                                    <i class="{{ $entry['icon'] }} progress-entry-icon" aria-hidden="true"></i>
                                    <div class="progress-entry-body">
                                        <span>{{ $entry['text'] }}</span>
                                        <small>
                                            {{ $entry['dateLabel'] }}
                                            @if ($entry['changeText']) · {{ $entry['changeText'] }} @endif
                                        </small>
                                    </div>
                                    <form method="POST" action="{{ route('progress.destroy', $entry['id']) }}" data-confirm-remove
                                        data-confirm-title="Eliminar registro"
                                        data-confirm-text="Se elimina «{{ $entry['text'] }}» del {{ $entry['dateLabel'] }}.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="progress-icon-btn" title="Eliminar" aria-label="Eliminar registro del {{ $entry['dateLabel'] }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <aside class="progress-tools">
                <div class="progress-panel">
                    <h2>Calculadora de IMC</h2>
                    <div class="progress-form-row">
                        <div>
                            <label class="form-label" for="bmi_weight">Peso (kg)</label>
                            <input type="number" id="bmi_weight" class="form-control" min="1" step="0.1" value="{{ $data['tools']['weight'] }}" data-bmi-input>
                        </div>
                        <div>
                            <label class="form-label" for="bmi_height">Altura (cm)</label>
                            <input type="number" id="bmi_height" class="form-control" min="1" step="1" value="{{ $data['tools']['height'] }}" data-bmi-input>
                        </div>
                    </div>
                    <p class="progress-tool-result" data-bmi-result aria-live="polite"></p>
                    <p class="progress-tool-hint">Referencia OMS: menos de 18,5 bajo peso · 18,5 a 24,9 saludable · 25 a 29,9 sobrepeso · 30 o más obesidad. Es orientativo: no distingue músculo de grasa.</p>
                </div>

                <div class="progress-panel">
                    <h2>Calculadora de 1RM</h2>
                    <p class="progress-tool-hint">Estima el peso máximo que podrías levantar una vez a partir de una serie.</p>
                    <div class="progress-form-row">
                        <div>
                            <label class="form-label" for="rm_weight">Peso (kg)</label>
                            <input type="number" id="rm_weight" class="form-control" min="1" step="0.5" data-rm-input>
                        </div>
                        <div>
                            <label class="form-label" for="rm_reps">Repeticiones</label>
                            <input type="number" id="rm_reps" class="form-control" min="1" max="30" step="1" data-rm-input>
                        </div>
                    </div>
                    <p class="progress-tool-result" data-rm-result aria-live="polite"></p>
                    <p class="progress-tool-hint">Fórmula de Epley. Es más precisa con series de 10 repeticiones o menos.</p>
                </div>
            </aside>
        </div>
    </section>
@endsection

@section('js')
    <script type="module">
        const formatNumber = (value) => value.toLocaleString('es-PY', { maximumFractionDigits: 1 });
        const numberOf = (id) => parseFloat(document.getElementById(id).value);

        const bmiCategory = (bmi) => bmi < 18.5 ? 'Bajo peso' : bmi < 25 ? 'Peso saludable' : bmi < 30 ? 'Sobrepeso' : 'Obesidad';
        const updateBmi = () => {
            const weight = numberOf('bmi_weight');
            const height = numberOf('bmi_height') / 100;
            const result = document.querySelector('[data-bmi-result]');
            if (!(weight > 0 && height > 0)) {
                result.textContent = 'Completa peso y altura.';
                return;
            }
            const bmi = weight / (height * height);
            const minWeight = 18.5 * height * height;
            const maxWeight = 24.9 * height * height;
            result.textContent = `IMC ${formatNumber(bmi)}: ${bmiCategory(bmi)}. Peso saludable para tu altura: ${formatNumber(minWeight)} a ${formatNumber(maxWeight)} kg.`;
        };

        const updateRm = () => {
            const weight = numberOf('rm_weight');
            const reps = numberOf('rm_reps');
            const result = document.querySelector('[data-rm-result]');
            if (!(weight > 0 && reps > 0)) {
                result.textContent = 'Completa peso y repeticiones.';
                return;
            }
            const oneRm = reps === 1 ? weight : weight * (1 + reps / 30);
            result.textContent = `1RM estimado: ${formatNumber(oneRm)} kg · 90%: ${formatNumber(oneRm * 0.9)} kg · 80%: ${formatNumber(oneRm * 0.8)} kg · 70%: ${formatNumber(oneRm * 0.7)} kg`;
        };

        document.querySelectorAll('[data-bmi-input]').forEach((input) => input.addEventListener('input', updateBmi));
        document.querySelectorAll('[data-rm-input]').forEach((input) => input.addEventListener('input', updateRm));
        updateBmi();
        updateRm();
    </script>
@endsection
