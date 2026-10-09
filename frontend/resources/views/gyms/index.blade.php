@php
    $gyms = $data['gyms'];
    $isAdmin = $data['isAdmin'];
    $memberGymNames = $data['memberGymNames'];
    $showMemberGyms = $data['showMemberGyms'];
    $memberGym = $data['memberGym'];
@endphp
@extends('layouts.layout')

@section('title', 'Gimnasios | Mbarete App')

@section('css')
    @vite(['resources/css/gym.css'])
@endsection

@section('content')
    <section class="gym-page">
        <div class="gym-header">
            <div>
                <span class="gym-eyebrow">
                    @if ($memberGym)
                        Gimnasio al que perteneces
                    @else
                        {{ $isAdmin ? 'Todos los gimnasios' : 'Mis gimnasios' }}
                    @endif
                </span>
                <h1>Gimnasios</h1>
            </div>
            <div class="gym-header-actions">
                @if (!empty($memberGymNames) && !$memberGym)
                    <div class="gym-visibility-form">
                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="showMemberGyms"
                                @checked($showMemberGyms)
                                onchange="window.location.href = '{{ route('gym.index') }}?member_gyms=' + (this.checked ? 1 : 0)">
                            <label class="form-check-label" for="showMemberGyms">
                                Ver gimnasio {{ implode(', ', $memberGymNames) }}
                            </label>
                        </div>
                    </div>
                @endif
                @if ($memberGym)
                    <a href="{{ route('gym.index') }}" class="btn gym-edit-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Volver a gimnasios</span>
                    </a>
                @else
                    <a href="{{ route('gym.create') }}" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        <span>Nuevo gimnasio</span>
                    </a>
                @endif
            </div>
        </div>

        @if (session('gym_saved'))
            <div class="alert alert-success">{{ session('gym_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if ($memberGym)
            @include('gyms._member-card', ['gym' => $memberGym])
        @elseif (empty($gyms))
            <div class="gym-empty">
                <i class="fa-solid fa-dumbbell"></i>
                <p>Todavia no hay gimnasios cargados.</p>
            </div>
        @else
            <div class="gym-grid">
                @foreach ($gyms as $gym)
                    @php
                        $owner = $gym['refs']['owner'] ?? null;
                        $ownerName = $owner ? trim(($owner['name'] ?? '') . ' ' . ($owner['last_name'] ?? '')) : null;
                    @endphp
                    @php
                        $isManaged = $gym['isManaged'] ?? true;
                        $membership = $gym['membership'] ?? null;
                    @endphp
                    <article @class(['gym-card', 'gym-card-member' => !$isManaged])>
                        @if (!empty($gym['image_url']))
                            <img src="{{ $gym['image_url'] }}" alt="" class="gym-card-img">
                        @endif
                        <div class="gym-card-body">
                            @if ($membership)
                                <span class="gym-member-badge">Soy alumno</span>
                            @endif
                            <h2>
                                <a href="{{ $isManaged ? route('gym.show', $gym['id']) : route('gym.index', ['member' => $gym['id']]) }}" class="stretched-link gym-card-link">{{ $gym['name'] }}</a>
                            </h2>
                            @if ($isManaged)
                                <span class="gym-slug">{{ $gym['slug'] }}</span>
                            @endif
                            <dl>
                                {{-- como alumno no se ve la cantidad de alumnos del gimnasio, solo quien lo gestiona --}}
                                @if (isset($gym['alumnsCount']))
                                    <div>
                                        <dt>Alumnos</dt>
                                        <dd>{{ $gym['alumnsCount'] }}</dd>
                                    </div>
                                @endif
                                @if ($isAdmin || !$isManaged)
                                    <div>
                                        <dt>{{ $isManaged ? 'Dueño' : 'Entrenador' }}</dt>
                                        <dd>{{ $ownerName ?: 'Sin asignar' }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                        @if ($membership)
                            <form method="POST" action="{{ route('gym.member.visibility', $gym['id']) }}" class="gym-visibility-form gym-card-visibility">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="is_public" value="{{ $membership['isPublic'] ? 0 : 1 }}">
                                <div class="form-check form-switch">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="card_visibility_{{ $gym['id'] }}"
                                        @checked($membership['isPublic'])
                                        onchange="this.form.submit()">
                                    <label class="form-check-label" for="card_visibility_{{ $gym['id'] }}">
                                        Mostrarme en la lista de alumnos
                                    </label>
                                </div>
                            </form>
                        @endif
                        @if ($isManaged)
                            <a href="{{ route('gym.edit', $gym['id']) }}" class="btn gym-edit-btn">
                                <i class="fa-solid fa-pen"></i>
                                <span>Editar</span>
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
