@php
    $isActivity = ($muscle['kind'] ?? 'muscle') === 'activity';
    $kindLabel = $isActivity ? 'actividad' : 'musculo';
@endphp
<div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex justify-content-center mb-4">
    <div class="card" style="width: 18rem;">
        <a href="{{ route('exercise.group', $muscle['id']) }}"><img src="{{ asset($muscle['image_url'] ?? 'images/leoncioRest.png') }}" alt="{{ $muscle['name'] }}" class="card-img-top"></a>
        <div class="card-body">
            <h5 class="card-title text-center">{{ $muscle['name'] }}</h5>
            @if (!($muscle['isPublic'] ?? true))
                <div class="text-center mb-2">
                    <span class="catalog-private-badge"><i class="fa-solid fa-lock"></i> Privado</span>
                </div>
            @endif
            <p class="card-text justify-content-center">{{ $muscle['description'] }}</p>
            @if ($isAdmin)
                <div class="d-flex gap-2">
                    <a href="{{ route('muscle.edit', $muscle['id']) }}" class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="fa-solid fa-pen"></i>
                        <span>Editar</span>
                    </a>
                    <form method="POST" action="{{ route('muscle.destroy', $muscle['id']) }}" class="flex-fill"
                        data-confirm-delete
                        data-confirm-title="Eliminar {{ $kindLabel }}"
                        data-confirm-text="{{ $isActivity ? 'La actividad' : 'El musculo' }} dejara de mostrarse. Solo se puede eliminar si no tiene ejercicios.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fa-solid fa-trash"></i>
                            <span>Eliminar</span>
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
