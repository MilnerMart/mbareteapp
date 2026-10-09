{{-- Deslizador para sumar o no el gimnasio base a la lista. Recibe $baseGym (null si no pertenece), $showBaseGym y $route. --}}
@if ($baseGym)
    <div class="gym-visibility-form">
        <div class="form-check form-switch">
            <input
                class="form-check-input"
                type="checkbox"
                role="switch"
                id="showBaseGym"
                @checked($showBaseGym)
                onchange="window.location.href = '{{ route($route) }}?base_gym=' + (this.checked ? 1 : 0)">
            <label class="form-check-label" for="showBaseGym">
                Ver gimnasio {{ $baseGym['name'] }}
            </label>
        </div>
    </div>
@endif
