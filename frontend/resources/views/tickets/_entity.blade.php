{{-- Vista previa del musculo o ejercicio propuesto en una solicitud. Recibe $entity. --}}
<div class="ticket-entity">
    @if (!empty($entity['image_url']))
        <img src="{{ $entity['image_url'] }}" alt="" class="ticket-entity-img">
    @endif
    <div class="ticket-entity-info">
        <strong>{{ $entity['name'] }}</strong>
        @if (!empty($entity['muscleName']))
            <span>Musculo: {{ $entity['muscleName'] }}</span>
        @endif
        @if (!empty($entity['description']))
            <p>{{ $entity['description'] }}</p>
        @endif
        <span class="ticket-entity-visibility">
            @if ($entity['isPublic'] ?? true)
                <i class="fa-solid fa-earth-americas"></i> Publico: lo veran todos los usuarios
            @else
                <i class="fa-solid fa-lock"></i> Privado: solo lo vera quien lo propuso
            @endif
        </span>
    </div>
</div>
