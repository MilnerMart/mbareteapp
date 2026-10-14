@php
    $form = $data['form'];
@endphp
@extends('layouts.layout')

@section('title', 'Calendario | Mbarete App')

@section('css')
    @vite(['resources/css/calendar.css'])
@endsection

@section('content')
    <section class="calendar-page">
        <header class="calendar-header">
            <div>
                <span class="calendar-eyebrow">Tu actividad y anotaciones</span>
                <h1>Calendario</h1>
            </div>
            <nav class="calendar-nav" aria-label="Cambiar de mes">
                <a href="{{ $data['previousUrl'] }}" class="calendar-nav-btn" aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i></a>
                <span class="calendar-month">{{ $data['monthLabel'] }}</span>
                <a href="{{ $data['nextUrl'] }}" class="calendar-nav-btn" aria-label="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></a>
                <a href="{{ $data['todayUrl'] }}" class="calendar-today-btn">Hoy</a>
            </nav>
        </header>

        @if (session('calendar_saved'))
            <div class="alert alert-success">{{ session('calendar_saved') }}</div>
        @endif
        @if ($data['loadFailed'])
            <div class="alert alert-danger">No pudimos cargar tu actividad de este mes. Intenta de nuevo.</div>
        @endif

        <div class="calendar-layout">
            <div class="calendar-grid-card">
                <div class="calendar-grid">
                    <div class="calendar-row calendar-weekdays">
                        @foreach ($data['weekdayLabels'] as $weekday)
                            <span>{{ $weekday }}</span>
                        @endforeach
                    </div>
                    @foreach ($data['weekList'] as $week)
                        <div class="calendar-row">
                            @foreach ($week as $day)
                                <a href="{{ $day['url'] }}" aria-label="{{ $day['ariaLabel'] }}"
                                    @if ($day['isSelected']) aria-current="date" @endif
                                    @class([
                                        'calendar-day',
                                        'is-other-month' => !$day['isCurrentMonth'],
                                        'is-today' => $day['isToday'],
                                        'is-selected' => $day['isSelected'],
                                        'is-completed' => $day['hasCompleted'],
                                    ])>
                                    <span class="calendar-day-number">{{ $day['dayNumber'] }}</span>
                                    <span class="calendar-day-marks" aria-hidden="true">
                                        @if ($day['hasTraining'])
                                            <i @class(['fa-solid', 'fa-circle-check' => $day['hasCompleted'], 'fa-dumbbell' => !$day['hasCompleted'], 'mark-training'])></i>
                                        @endif
                                        @if ($day['actionCount'])
                                            <i class="fa-solid fa-circle mark-action"></i>
                                        @endif
                                        @if ($day['hasProgress'])
                                            <i @class(['fa-solid', 'fa-trophy' => $day['hasPersonalBest'], 'fa-chart-line' => !$day['hasPersonalBest'], 'mark-progress'])></i>
                                        @endif
                                        @if ($day['noteCount'])
                                            <i @class(['fa-solid', 'fa-bell' => $day['hasReminder'], 'fa-note-sticky' => !$day['hasReminder'], 'mark-note'])></i>
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <ul class="calendar-legend">
                    <li><i class="fa-solid fa-circle-check mark-training"></i> Rutina completa</li>
                    <li><i class="fa-solid fa-dumbbell mark-training"></i> Entrenaste</li>
                    <li><i class="fa-solid fa-circle mark-action"></i> Actividad</li>
                    <li><i class="fa-solid fa-chart-line mark-progress"></i> Progreso</li>
                    <li><i class="fa-solid fa-trophy mark-progress"></i> Record</li>
                    <li><i class="fa-solid fa-note-sticky mark-note"></i> Anotacion</li>
                    <li><i class="fa-solid fa-bell mark-note"></i> Recordatorio</li>
                </ul>
            </div>

            <div class="calendar-day-detail">
                <h2>{{ $data['selectedLabel'] }}</h2>

                @if (empty($data['noteList']) && empty($data['trainingList']) && empty($data['actionList']) && empty($data['progressList']))
                    <p class="calendar-empty">Nada registrado este dia.</p>
                @endif

                @if (!empty($data['noteList']))
                    <h3 class="calendar-section-title">Anotaciones</h3>
                    <ul class="calendar-list">
                        @foreach ($data['noteList'] as $note)
                            <li class="calendar-note">
                                <div class="calendar-note-body">
                                    <strong>{{ $note['title'] }}</strong>
                                    @if ($note['body'])
                                        <p>{{ $note['body'] }}</p>
                                    @endif
                                    @if ($note['reminderLabel'])
                                        <span class="calendar-note-reminder"><i class="fa-solid fa-bell" aria-hidden="true"></i> {{ $note['reminderLabel'] }}</span>
                                    @endif
                                </div>
                                <div class="calendar-note-actions">
                                    <a href="{{ $note['editUrl'] }}" class="calendar-icon-btn" title="Editar" aria-label="Editar {{ $note['title'] }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <form method="POST" action="{{ $note['deleteUrl'] }}" data-confirm-remove
                                        data-confirm-title="Eliminar anotacion"
                                        data-confirm-text="Se elimina «{{ $note['title'] }}» y sus recordatorios.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="calendar-icon-btn" title="Eliminar" aria-label="Eliminar {{ $note['title'] }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($data['trainingList']))
                    <h3 class="calendar-section-title">Entrenamiento</h3>
                    <ul class="calendar-list">
                        @foreach ($data['trainingList'] as $training)
                            <li @class(['calendar-item', 'is-completed' => $training['isCompleted']])>
                                <i class="{{ $training['icon'] }} calendar-item-icon" aria-hidden="true"></i>
                                <a href="{{ $training['url'] }}">{{ $training['text'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($data['progressList']))
                    <h3 class="calendar-section-title">Progreso</h3>
                    <ul class="calendar-list">
                        @foreach ($data['progressList'] as $entry)
                            <li @class(['calendar-item', 'is-highlight' => $entry['isPersonalBest']])>
                                <i class="{{ $entry['icon'] }} calendar-item-icon" aria-hidden="true"></i>
                                <span class="calendar-item-text">
                                    <a href="{{ route('progress.index') }}">{{ $entry['text'] }}</a>
                                    @if ($entry['changeText'])
                                        <small>{{ $entry['changeText'] }} desde el registro anterior</small>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($data['actionList']))
                    <h3 class="calendar-section-title">Actividad</h3>
                    <ul class="calendar-list">
                        @foreach ($data['actionList'] as $action)
                            <li @class(['calendar-item', 'is-highlight' => $action['isHighlight']])>
                                <i class="{{ $action['icon'] }} calendar-item-icon" aria-hidden="true"></i>
                                <span class="calendar-item-text">
                                    {{ $action['text'] }}
                                    @if ($action['note'])
                                        <small>{{ $action['note'] }}</small>
                                    @endif
                                </span>
                                <time>{{ $action['timeLabel'] }}</time>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ $form['action'] }}" class="calendar-note-form" id="calendar-note-form">
                    @csrf
                    @if ($form['isEdit'])
                        @method('PUT')
                    @endif
                    <h3 class="calendar-section-title">{{ $form['isEdit'] ? 'Editar anotacion' : 'Nueva anotacion' }} · {{ $form['dayLabel'] }}</h3>
                    @if ($form['noteOn'])
                        <input type="hidden" name="note_on" value="{{ $form['noteOn'] }}">
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif
                    <label for="title" class="form-label">Titulo</label>
                    <input type="text" id="title" name="title" class="form-control" value="{{ $form['values']['title'] }}"
                        minlength="2" maxlength="120" required placeholder="Ej: Medir peso, turno con el nutricionista">
                    <label for="body" class="form-label">Detalle <small>(opcional)</small></label>
                    <textarea id="body" name="body" class="form-control" rows="2" maxlength="1000">{{ $form['values']['body'] }}</textarea>
                    <fieldset class="calendar-remind-days">
                        <legend class="form-label">Recordarme <small>(opcional)</small></legend>
                        @foreach ($form['remindDayOptions'] as $days => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="remind_days[]" value="{{ $days }}" id="remind_days_{{ $days }}"
                                    @checked(in_array($days, $form['remindDays'], true))>
                                <label class="form-check-label" for="remind_days_{{ $days }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                    <p class="calendar-form-hint">Te avisamos en la campanita los dias que marques.</p>
                    <div class="calendar-form-actions">
                        @if ($form['isEdit'])
                            <a href="{{ $form['cancelUrl'] }}" class="btn calendar-cancel-btn">Cancelar</a>
                        @endif
                        <button type="submit" class="btn btn-primary">{{ $form['isEdit'] ? 'Guardar cambios' : 'Agregar' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
