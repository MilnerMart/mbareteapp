@php
    $routine = $data['routine'];
    $fields = $data['fields'];
    $exercises = $data['exercises'];
@endphp
@extends('layouts.layout')

@section('title', 'Editar '.$routine['name'].' | Mbarete App')

@section('css')
    @vite(['resources/css/routine.css'])
@endsection

@section('content')
    {{-- misma vista de la rutina: cada dato y cada ejercicio se editan con su lapiz y se guarda todo junto al confirmar --}}
    <form method="POST" action="{{ route('routine.update', $routine['id']) }}" class="routine-page routine-form routine-edit-form"
        data-confirm-submit
        data-confirm-title="Guardar cambios"
        data-confirm-text="Se guardan los datos de la rutina, el orden de los ejercicios, sus tiempos y series, y se quitan los que eliminaste.">
        @csrf
        @method('PUT')
        <input type="hidden" name="sync_exercises" value="1">

        <a href="{{ route('routine.show', $routine['id']) }}" class="routine-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Volver sin guardar</span>
        </a>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="routine-detail">
            <div class="routine-edit-header">
                <div class="routine-inline" data-inline-edit>
                    <h1 data-inline-view data-inline-display>{{ $fields['name'] }}</h1>
                    <input type="text" name="name" class="form-control" value="{{ $fields['name'] }}" minlength="3" maxlength="100" required
                        aria-label="Nombre" data-inline-input hidden>
                    <button type="button" class="btn routine-icon-btn" data-inline-toggle title="Editar nombre" aria-label="Editar nombre">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>

                <div class="routine-inline" data-inline-edit>
                    <p class="routine-card-desc" data-inline-view data-inline-display data-empty-text="Sin descripcion">{{ $fields['description'] ?: 'Sin descripcion' }}</p>
                    <textarea name="description" class="form-control" rows="2" maxlength="255" placeholder="Opcional"
                        aria-label="Descripcion" data-inline-input hidden>{{ $fields['description'] }}</textarea>
                    <button type="button" class="btn routine-icon-btn routine-inline-btn-sm" data-inline-toggle title="Editar descripcion" aria-label="Editar descripcion">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>

                <span class="routine-meta">
                    <span class="routine-inline" data-inline-edit>
                        <span class="routine-inline-label"><i class="fa-regular fa-calendar"></i>
                            <span data-inline-view data-inline-display>{{ $fields['frequency'] }}</span>
                            <input type="number" name="frequency" class="form-control routine-inline-number" value="{{ $fields['frequency'] }}" min="1" max="7" required
                                aria-label="Dias por semana" data-inline-input hidden>
                            dias/semana</span>
                        <button type="button" class="btn routine-icon-btn routine-inline-btn-sm" data-inline-toggle title="Editar dias por semana" aria-label="Editar dias por semana">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </span>
                    <span class="routine-inline" data-inline-edit>
                        <span class="routine-inline-label"><i class="fa-regular fa-clock"></i>
                            <span data-inline-view data-inline-display>{{ $fields['rest_minutes'] }}</span>
                            <input type="number" name="rest_minutes" class="form-control routine-inline-number" value="{{ $fields['rest_minutes'] }}" min="0" max="30" step="0.5" required
                                aria-label="Descanso entre ejercicios en minutos" data-inline-input hidden>
                            min descanso</span>
                        <button type="button" class="btn routine-icon-btn routine-inline-btn-sm" data-inline-toggle title="Editar descanso" aria-label="Editar descanso entre ejercicios">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </span>
                </span>
            </div>
            <div class="routine-detail-actions">
                <a href="{{ route('routine.show', $routine['id']) }}" class="btn routine-btn">
                    <i class="fa-solid fa-xmark"></i>
                    <span>Cancelar</span>
                </a>
                <button type="submit" class="btn btn-primary routine-btn-save">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Guardar cambios</span>
                </button>
            </div>
        </div>

        <h2 class="routine-subtitle">Ejercicios</h2>
        <p class="routine-hint">Ordenalos con las flechas, edita tiempos y series con el lapiz o quitalos. Nada se guarda hasta que confirmes.</p>
        <div class="routine-empty-box" data-exercise-empty @if (!empty($exercises)) hidden @endif>
            <i class="fa-solid fa-dumbbell"></i>
            <p>La rutina no tiene ejercicios.</p>
        </div>
        <ol class="routine-exercise-list" data-exercise-list>
            @foreach ($exercises as $exercise)
                <li class="routine-exercise" data-exercise-item>
                    <div class="routine-exercise-order">
                        <button type="button" class="btn routine-order-btn" data-move="up" title="Subir" aria-label="Subir {{ $exercise['name'] }}">
                            <i class="fa-solid fa-chevron-up"></i>
                        </button>
                        <button type="button" class="btn routine-order-btn" data-move="down" title="Bajar" aria-label="Bajar {{ $exercise['name'] }}">
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="routine-exercise-info">
                        <span class="routine-exercise-name">{{ $exercise['name'] }}</span>
                        <span class="routine-exercise-volume" data-volume-summary>{{ $exercise['volume'] }}</span>
                        <p class="routine-exercise-notes" data-notes-summary @if (empty($exercise['notes'])) hidden @endif>{{ $exercise['notes'] }}</p>
                    </div>
                    <div class="routine-exercise-actions">
                        <button type="button" class="btn routine-icon-btn" data-exercise-edit-toggle aria-expanded="false"
                            aria-controls="exercise_edit_{{ $exercise['id'] }}"
                            title="Editar indicaciones" aria-label="Editar indicaciones de {{ $exercise['name'] }}">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" class="btn routine-icon-btn" data-remove-exercise title="Quitar de la rutina" aria-label="Quitar {{ $exercise['name'] }} de la rutina">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div id="exercise_edit_{{ $exercise['id'] }}" class="routine-exercise-edit" hidden>
                        @include('routines._volume-fields', ['fieldId' => $exercise['id'], 'fields' => $exercise['volumeFields']])
                    </div>
                </li>
            @endforeach
        </ol>

    </form>
@endsection

@section('js')
    {{-- module: corre despues de app.js, que es quien expone jQuery --}}
    <script type="module">
        const $form = $('.routine-edit-form');
        const $list = $('[data-exercise-list]');

        // lapiz de cada dato de la rutina: muestra el campo y lo que se escribe se ve en la vista
        $form.on('click', '[data-inline-toggle]', function () {
            const $box = $(this).closest('[data-inline-edit]');
            const $input = $box.find('[data-inline-input]');
            const isOpening = $input.prop('hidden');
            $input.prop('hidden', !isOpening);
            $box.find('[data-inline-view]').prop('hidden', isOpening);
            if (isOpening) {
                $input.trigger('focus');
            }
        });

        $form.on('input', '[data-inline-input]', function () {
            const $display = $(this).closest('[data-inline-edit]').find('[data-inline-display]');
            $display.text($(this).val().trim() || $display.data('emptyText') || '');
        });

        // mismo texto que arma el servidor: "10 min · 2 series × 10 reps"
        const formatVolume = ($item) => {
            const valueOf = (key) => {
                const $field = $item.find(`[name$="[${key}]"]`);
                return $field.prop('disabled') ? '' : String($field.val() || '').trim();
            };
            const minutes = valueOf('duration_minutes');
            const sets = valueOf('sets');
            const reps = valueOf('reps');
            const partList = [];
            if (minutes) {
                partList.push(`${minutes} min`);
            }
            if (sets && reps) {
                partList.push(`${sets} series × ${reps} reps`);
            } else if (sets) {
                partList.push(`${sets} series`);
            } else if (reps) {
                partList.push(`${reps} reps`);
            }
            return partList.length ? partList.join(' · ') : 'Sin indicaciones';
        };

        const refreshSummary = ($item) => {
            $item.find('[data-volume-summary]').text(formatVolume($item));
            const notes = String($item.find('[name$="[notes]"]').val() || '').trim();
            $item.find('[data-notes-summary]').text(notes).prop('hidden', !notes);
        };

        // las casillas habilitan sus campos en app.js: se espera a que termine para leerlos
        $list.on('input change', 'input', function () {
            const $item = $(this).closest('[data-exercise-item]');
            setTimeout(() => refreshSummary($item));
        });

        $list.on('click', '[data-exercise-edit-toggle]', function () {
            const isOpen = $(this).attr('aria-expanded') !== 'true';
            $(this).attr('aria-expanded', String(isOpen));
            $('#' + $(this).attr('aria-controls')).prop('hidden', !isOpen);
        });

        // el primero no sube y el ultimo no baja; el numero lo actualiza el contador de la lista
        const refreshOrderButtons = () => {
            const $itemList = $list.children('[data-exercise-item]');
            $itemList.each((index, item) => {
                $(item).find('[data-move="up"]').prop('disabled', index === 0);
                $(item).find('[data-move="down"]').prop('disabled', index === $itemList.length - 1);
            });
            $('[data-exercise-empty]').prop('hidden', $itemList.length > 0);
        };

        $list.on('click', '[data-move]', function () {
            const $item = $(this).closest('[data-exercise-item]');
            if ($(this).data('move') === 'up') {
                $item.prev().before($item);
            } else {
                $item.next().after($item);
            }
            refreshOrderButtons();
            $(this).trigger('focus');
        });

        // quitar solo lo saca de la pantalla: se elimina de la rutina al guardar
        $list.on('click', '[data-remove-exercise]', function () {
            $(this).closest('[data-exercise-item]').remove();
            refreshOrderButtons();
        });

        // si un campo oculto no es valido se abre para que el navegador pueda marcarlo
        $form.get(0).addEventListener('invalid', (event) => {
            const $field = $(event.target);
            $field.closest('[data-inline-edit]').find('[data-inline-view]').prop('hidden', true);
            $field.filter('[data-inline-input]').prop('hidden', false);
            const $panel = $field.closest('.routine-exercise-edit');
            if ($panel.length) {
                $panel.prop('hidden', false);
                $panel.closest('[data-exercise-item]').find('[data-exercise-edit-toggle]').attr('aria-expanded', 'true');
            }
        }, true);

        refreshOrderButtons();
    </script>
@endsection
