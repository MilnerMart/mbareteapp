{{--
    Publico o privado para musculos y ejercicios. Recibe $entity (null al crear) y $isAdmin.
    Lo que propone un entrenador pasa por la revision del admin antes de aparecer.
--}}
@php
    $isPublic = (bool) old('is_public', $entity['isPublic'] ?? true);
@endphp
<fieldset class="mb-3 catalog-visibility">
    <legend class="form-label">Visibilidad</legend>
    <div class="catalog-visibility-options">
        <input type="radio" class="btn-check" name="is_public" id="is_public_1" value="1" @checked($isPublic)>
        <label class="catalog-visibility-option" for="is_public_1">
            <i class="fa-solid fa-earth-americas"></i>
            <span>
                <strong>Publico</strong>
                <small>Lo ven todos los usuarios.</small>
            </span>
        </label>
        <input type="radio" class="btn-check" name="is_public" id="is_public_0" value="0" @checked(!$isPublic)>
        <label class="catalog-visibility-option" for="is_public_0">
            <i class="fa-solid fa-lock"></i>
            <span>
                <strong>Privado</strong>
                <small>Solo vos lo ves y lo usas en tus rutinas.</small>
            </span>
        </label>
    </div>
    @if (!$isAdmin && !$entity)
        <small class="catalog-hint">Un administrador lo revisara antes de que aparezca en la lista. Vas a ver el estado en Solicitudes > Enviadas.</small>
    @endif
</fieldset>
