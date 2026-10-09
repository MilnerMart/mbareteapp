{{-- Tarjeta de la grilla de gimnasios: propio (gestiona) o donde es alumno. Recibe $gym, $isAdmin y $memberRoute. --}}
@php
    $owner = $gym['refs']['owner'] ?? null;
    $ownerName = $owner ? trim(($owner['name'] ?? '') . ' ' . ($owner['last_name'] ?? '')) : null;
    $isManaged = $gym['isManaged'] ?? true;
    $membership = $gym['membership'] ?? null;
@endphp
<article @class(['gym-card', 'gym-card-member' => !$isManaged])>
    @if (!empty($gym['image_url']))
        <img src="{{ $gym['image_url'] }}" alt="" class="gym-card-img">
    @endif
    <div class="gym-card-body">
        {{-- en "Mis gimnasios" del alumno todas son membresias, la etiqueta solo distingue en "Gimnasios" --}}
        @if ($membership && $memberRoute === 'gym.index')
            <span class="gym-member-badge">Soy alumno</span>
        @endif
        <h2>
            <a href="{{ $isManaged ? route('gym.show', $gym['id']) : route($memberRoute, ['member' => $gym['id']]) }}" class="stretched-link gym-card-link">{{ $gym['name'] }}</a>
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
