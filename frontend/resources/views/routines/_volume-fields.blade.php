{{-- minutos y/o series-repeticiones de un ejercicio en la rutina: cada grupo se muestra con su casilla --}}
<div class="routine-volume-fields">
    <label class="routine-volume-toggle">
        <input type="checkbox" data-toggle-fields="minutes_fields_{{ $fieldId }}" @checked($fields['withMinutes'])>
        <span>Agregar minutos</span>
    </label>
    <div id="minutes_fields_{{ $fieldId }}" class="routine-volume-group" @if (!$fields['withMinutes']) hidden @endif>
        <div>
            <label for="duration_minutes_{{ $fieldId }}" class="form-label">Minutos</label>
            <input type="number" id="duration_minutes_{{ $fieldId }}" name="{{ $fields['names']['duration_minutes'] }}" class="form-control"
                value="{{ $fields['values']['duration_minutes'] }}" min="1" max="600" required @disabled(!$fields['withMinutes'])>
        </div>
    </div>

    <label class="routine-volume-toggle">
        <input type="checkbox" data-toggle-fields="sets_fields_{{ $fieldId }}" @checked($fields['withSets'])>
        <span>Agregar series y repeticiones</span>
    </label>
    <div id="sets_fields_{{ $fieldId }}" class="routine-volume-group" @if (!$fields['withSets']) hidden @endif>
        <div>
            <label for="sets_{{ $fieldId }}" class="form-label">Series</label>
            <input type="number" id="sets_{{ $fieldId }}" name="{{ $fields['names']['sets'] }}" class="form-control"
                value="{{ $fields['values']['sets'] }}" min="1" max="20" required @disabled(!$fields['withSets'])>
        </div>
        <div>
            <label for="reps_{{ $fieldId }}" class="form-label">Repeticiones</label>
            <input type="number" id="reps_{{ $fieldId }}" name="{{ $fields['names']['reps'] }}" class="form-control"
                value="{{ $fields['values']['reps'] }}" min="1" max="100" required @disabled(!$fields['withSets'])>
        </div>
    </div>

    <div>
        <label for="notes_{{ $fieldId }}" class="form-label">Indicaciones (opcional)</label>
        <input type="text" id="notes_{{ $fieldId }}" name="{{ $fields['names']['notes'] }}" class="form-control" value="{{ $fields['values']['notes'] }}"
            maxlength="255" placeholder="{{ $fields['notesPlaceholder'] }}">
    </div>
</div>
