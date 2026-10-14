@php
    $gym = $data['gym'];
    $students = $data['students'];
    $isAdmin = $data['isAdmin'];
    $canAssignRoutines = $data['canAssignRoutines'];
    $assignableRoutines = $data['assignableRoutines'];
    $owner = $gym['refs']['owner'] ?? null;
    $ownerName = $owner ? trim(($owner['name'] ?? '') . ' ' . ($owner['last_name'] ?? '')) : null;
@endphp
@extends('layouts.layout')

@section('title', $gym['name'] . ' | Mbarete App')

@section('css')
    @vite(['resources/css/gym.css', 'resources/css/profile.css'])
@endsection

@section('content')
    <section class="gym-page">
        <a href="{{ route('gym.index') }}" class="gym-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Gimnasios</span>
        </a>

        @if (session('gym_saved'))
            <div class="alert alert-success">{{ session('gym_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="gym-detail">
            <form method="POST" action="{{ route('gym.image', $gym['id']) }}" enctype="multipart/form-data" class="gym-cover-form">
                @csrf
                <label class="gym-cover" for="gymImageInput" title="Cambiar imagen del gimnasio">
                    @if (!empty($gym['image_url']))
                        <img src="{{ $gym['image_url'] }}" alt="Imagen de {{ $gym['name'] }}">
                    @else
                        <span class="gym-cover-empty">
                            <i class="fa-solid fa-dumbbell"></i>
                            <span>Agregar imagen</span>
                        </span>
                    @endif
                    <span class="gym-cover-overlay">
                        <i class="fa-solid fa-camera"></i>
                    </span>
                </label>
                <input
                    type="file"
                    id="gymImageInput"
                    name="gym_image"
                    class="d-none"
                    accept="image/jpeg,image/png,image/webp"
                    onchange="this.form.submit()">
            </form>

            <div class="gym-detail-info">
                <div>
                    <h1>{{ $gym['name'] }}</h1>
                    <span class="gym-slug">Codigo: {{ $gym['slug'] }}</span>
                </div>
                <dl>
                    <div>
                        <dt>Alumnos</dt>
                        <dd>{{ $gym['alumnsCount'] ?? 0 }}</dd>
                    </div>
                    @if ($isAdmin)
                        <div>
                            <dt>Dueño</dt>
                            <dd>{{ $ownerName ?: 'Sin asignar' }}</dd>
                        </div>
                    @endif
                </dl>
                <a href="{{ route('gym.edit', $gym['id']) }}" class="btn gym-edit-btn">
                    <i class="fa-solid fa-pen"></i>
                    <span>Editar</span>
                </a>
            </div>
        </div>

        <div class="gym-students">
            <h2>Alumnos</h2>

            @if (empty($students))
                <div class="gym-empty">
                    <i class="fa-solid fa-user-group"></i>
                    <p>Todavia no hay alumnos. Comparte el codigo <strong>{{ $gym['slug'] }}</strong> para que se sumen al registrarse.</p>
                </div>
            @else
                <ul class="gym-student-list">
                    @foreach ($students as $student)
                        @php
                            $studentName = trim(($student['name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
                        @endphp
                        <li class="gym-student @if ($isAdmin) is-clickable @endif" @if ($isAdmin) data-user-detail-card @endif>
                            <img
                                src="{{ $student['profile_image_url'] ?? asset('images/chavukuBiceps.png') }}"
                                alt=""
                                class="gym-student-avatar">
                            <div class="gym-student-info">
                                @if ($isAdmin)
                                    {{-- el admin abre la ficha del alumno (solo lectura) tocando la tarjeta --}}
                                    <button type="button" class="gym-student-open" data-bs-toggle="modal" data-bs-target="#userDetailModal"
                                        data-user-detail-url="{{ route('user.detail', $student['id']) }}" title="Ver ficha del alumno">
                                        {{ $studentName ?: 'Alumno' }}
                                    </button>
                                @else
                                    <strong>{{ $studentName ?: 'Alumno' }}</strong>
                                @endif
                                <span>{{ $student['email'] }}</span>
                                @if (!empty($student['routines']))
                                    <ul class="gym-student-routines" aria-label="Rutinas asignadas">
                                        @foreach ($student['routines'] as $routine)
                                            <li class="gym-routine-chip">
                                                <span>{{ $routine['name'] }}</span>
                                                @if ($canAssignRoutines)
                                                    <form method="POST" action="{{ route('routine.unassign', [$routine['id'], $student['id']]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="Quitar rutina" aria-label="Quitar la rutina {{ $routine['name'] }}">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="gym-student-actions">
                                @if ($canAssignRoutines)
                                    @if (empty($assignableRoutines))
                                        <a href="{{ route('routine.create') }}" class="btn gym-edit-btn">
                                            <i class="fa-solid fa-clipboard-list"></i>
                                            <span>Crear rutina para asignar</span>
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('routine.assign') }}" class="gym-assign-form">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $student['id'] }}">
                                            <select name="routine_id" class="form-select" aria-label="Rutina para {{ $studentName ?: 'el alumno' }}" required>
                                                @foreach ($assignableRoutines as $routine)
                                                    <option value="{{ $routine['id'] }}">{{ $routine['name'] }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn gym-edit-btn">
                                                <i class="fa-solid fa-clipboard-list"></i>
                                                <span>Asignar</span>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                                <form
                                    method="POST"
                                    action="{{ route('gym.users.remove', [$gym['id'], $student['id']]) }}"
                                    data-confirm-delete
                                    data-confirm-title="Quitar alumno"
                                    data-confirm-button="Quitar"
                                    data-confirm-text="{{ $studentName ?: 'Este alumno' }} dejara de pertenecer a {{ $gym['name'] }}. Para volver tendra que pedir unirse de nuevo.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn gym-remove-btn">
                                        <i class="fa-solid fa-user-minus"></i>
                                        <span>Quitar</span>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    @if ($isAdmin)
        <div class="modal fade" id="userDetailModal" tabindex="-1" aria-labelledby="userDetailTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content user-detail-modal">
                    <div class="modal-header">
                        <span class="user-detail-readonly"><i class="fa-solid fa-eye" aria-hidden="true"></i> Ficha del alumno (solo lectura)</span>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" data-user-detail-body></div>
                </div>
            </div>
        </div>
    @endif
@endsection

@if ($isAdmin)
    @section('js')
        {{-- module: corre despues de app.js, que es quien expone jQuery --}}
        <script type="module">
            const $modalBody = $('[data-user-detail-body]');
            const loadingHtml = '<p class="user-detail-status"><i class="fa-solid fa-spinner fa-spin"></i> Cargando ficha...</p>';

            // toda la tarjeta abre la ficha, salvo los botones y formularios propios (asignar, quitar...)
            $('[data-user-detail-card]').on('click', function (event) {
                if ($(event.target).closest('a, button, form, select, input').length) {
                    return;
                }
                this.querySelector('[data-user-detail-url]').click();
            });

            document.getElementById('userDetailModal').addEventListener('show.bs.modal', async (event) => {
                const url = event.relatedTarget?.dataset.userDetailUrl;
                $modalBody.html(loadingHtml);
                try {
                    const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
                    if (!response.ok) {
                        throw new Error(response.statusText);
                    }
                    $modalBody.html(await response.text());
                } catch (error) {
                    $modalBody.html('<p class="user-detail-status">No pudimos cargar la ficha del alumno.</p>');
                }
            });
        </script>
    @endsection
@endif
