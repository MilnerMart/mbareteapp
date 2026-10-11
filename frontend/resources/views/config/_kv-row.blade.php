<div class="config-kv-row">
    <input type="text" name="{{ $list }}[{{ $index }}][key]" class="form-control" value="{{ $key ?? '' }}" placeholder="{{ $keyPlaceholder }}" aria-label="Nombre">
    <input type="{{ $secret ? 'password' : 'text' }}" name="{{ $list }}[{{ $index }}][value]" class="form-control" placeholder="Valor" aria-label="Valor"
           @if ($secret) autocomplete="new-password" @endif>
    <button type="button" class="btn config-icon-btn" data-kv-remove title="Quitar fila" aria-label="Quitar fila">
        <i class="fa-solid fa-xmark"></i>
    </button>
</div>
