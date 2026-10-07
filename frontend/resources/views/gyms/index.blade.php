@php
    $gyms = $data['gyms'];
    $isAdmin = $data['isAdmin'];
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
                <span class="gym-eyebrow">{{ $isAdmin ? 'Todos los gimnasios' : 'Mis gimnasios' }}</span>
                <h1>Gimnasios</h1>
            </div>
            <a href="{{ route('gym.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                <span>Nuevo gimnasio</span>
            </a>
        </div>

        @if (session('gym_saved'))
            <div class="alert alert-success">{{ session('gym_saved') }}</div>
        @endif

        @if (empty($gyms))
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
                    <article class="gym-card">
                        @if (!empty($gym['image_url']))
                            <img src="{{ $gym['image_url'] }}" alt="" class="gym-card-img">
                        @endif
                        <div class="gym-card-body">
                            <h2>
                                <a href="{{ route('gym.show', $gym['id']) }}" class="stretched-link gym-card-link">{{ $gym['name'] }}</a>
                            </h2>
                            <span class="gym-slug">{{ $gym['slug'] }}</span>
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
                        </div>
                        <a href="{{ route('gym.edit', $gym['id']) }}" class="btn gym-edit-btn">
                            <i class="fa-solid fa-pen"></i>
                            <span>Editar</span>
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
