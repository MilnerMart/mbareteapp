{{-- Tarjeta de un gimnasio al que pertenece el usuario, con su preferencia de visibilidad. Recibe $gym. --}}
@php
    $owner = $gym['refs']['owner'] ?? null;
    $ownerName = $owner ? trim(($owner['name'] ?? '') . ' ' . ($owner['last_name'] ?? '')) : null;
@endphp
<article class="gym-member">
    <div class="gym-detail">
        <div class="gym-cover gym-cover-static">
            @if (!empty($gym['image_url']))
                <img src="{{ $gym['image_url'] }}" alt="Imagen de {{ $gym['name'] }}">
            @else
                <span class="gym-cover-empty">
                    <i class="fa-solid fa-dumbbell"></i>
                </span>
            @endif
        </div>

        <div class="gym-detail-info">
            <h2 class="gym-member-name">{{ $gym['name'] }}</h2>

            @if ($owner)
                <div class="gym-owner">
                    <img src="{{ $owner['profile_image_url'] ?? asset('images/leoncioBiceps.png') }}" alt="" class="gym-student-avatar">
                    <div>
                        <span class="gym-owner-label">Entrenador</span>
                        <strong>{{ $ownerName ?: 'Entrenador' }}</strong>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('gym.member.visibility', $gym['id']) }}" class="gym-visibility-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="is_public" value="{{ $gym['isPublic'] ? 0 : 1 }}">
                <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="visibility_{{ $gym['id'] }}"
                        @checked($gym['isPublic'])
                        onchange="this.form.submit()">
                    <label class="form-check-label" for="visibility_{{ $gym['id'] }}">
                        Mostrarme en la lista de alumnos
                    </label>
                </div>
            </form>
        </div>
    </div>

    <div class="gym-students">
        <h3 class="gym-member-subtitle">Alumnos</h3>
        @if (empty($gym['publicStudents']))
            <p class="gym-member-empty">Ningun alumno eligio mostrarse todavia.</p>
        @else
            <ul class="gym-public-students">
                @foreach ($gym['publicStudents'] as $student)
                    <li>
                        <img src="{{ $student['profile_image_url'] ?? asset('images/leoncioBiceps.png') }}" alt="" class="gym-student-avatar">
                        <span>{{ trim($student['name'] . ' ' . $student['last_name']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</article>
