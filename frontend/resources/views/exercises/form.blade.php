@php
    $muscle = $data['muscle'];
    $exercise = $data['exercise'] ?? null;
    $muscles = $data['muscles'] ?? [];
    $isEdit = (bool) $exercise;
    $publicProviders = $data['publicProviders'] ?? [];
    $publicProviderNames = collect($publicProviders)->pluck('name')->join(', ', ' y ');
    $imageSource = old('image_source', 'upload');
@endphp
@extends('layouts.layout')

@section('title', ($isEdit ? 'Editar ejercicio' : 'Nuevo ejercicio').' | Mbarete App')

@section('css')
    @vite('resources/css/catalog.css')
@endsection

@section('content')
    <section class="py-3">
        <div class="catalog-form-panel">
            <a href="{{ route('exercise.group', $muscle['id']) }}" class="catalog-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ $muscle['name'] }}</span>
            </a>
            <h1>{{ $isEdit ? 'Editar '.$exercise['name'] : 'Nuevo ejercicio de '.$muscle['name'] }}</h1>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ $isEdit ? route('exercise.update', $exercise['id']) : route('exercise.store') }}" enctype="multipart/form-data" class="catalog-form">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                    <div class="mb-3">
                        <label for="muscle_id" class="form-label">Musculo o actividad</label>
                        <select class="form-select @error('muscle_id') is-invalid @enderror" id="muscle_id" name="muscle_id" required>
                            @foreach (collect($muscles)->groupBy(fn($muscleOption) => ($muscleOption['kind'] ?? 'muscle') === 'activity' ? 'Actividades' : 'Musculos')->sortKeysDesc() as $groupLabel => $optionList)
                                <optgroup label="{{ $groupLabel }}">
                                    @foreach ($optionList as $muscleOption)
                                        <option value="{{ $muscleOption['id'] }}" @selected(old('muscle_id', $exercise['muscle_id']) == $muscleOption['id'])>
                                            {{ $muscleOption['name'] }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="muscle_id" value="{{ $muscle['id'] }}">
                @endif

                <div class="mb-3">
                    <label for="name" class="form-label">Nombre</label>
                    <input
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        value="{{ old('name', $exercise['name'] ?? '') }}"
                        placeholder="Press de banca"
                        minlength="3"
                        maxlength="100"
                        required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Descripcion</label>
                    <textarea
                        class="form-control @error('description') is-invalid @enderror"
                        id="description"
                        name="description"
                        rows="3"
                        minlength="5"
                        maxlength="255"
                        required>{{ old('description', $exercise['description'] ?? '') }}</textarea>
                </div>

                <div class="mb-3">
                    <label for="recommended_rest_time" class="form-label">Descanso recomendado entre series (seg)</label>
                    <input
                        type="number"
                        class="form-control @error('recommended_rest_time') is-invalid @enderror"
                        id="recommended_rest_time"
                        name="recommended_rest_time"
                        value="{{ old('recommended_rest_time', $exercise['recommended_rest_time'] ?? 60) }}"
                        min="1"
                        max="600"
                        required>
                </div>

                <div class="mb-4">
                    <span class="form-label d-block">Imagen o GIF</span>
                    @if ($isEdit && !empty($exercise['image_url']))
                        <img src="{{ asset($exercise['image_url']) }}" alt="{{ $exercise['name'] }}" class="d-block mb-2 rounded" style="max-height: 120px;">
                    @endif

                    @if ($publicProviders)
                        <div class="catalog-source-toggle" role="radiogroup" aria-label="Origen de la imagen">
                            <label>
                                <input type="radio" name="image_source" value="upload" @checked($imageSource === 'upload')>
                                <span><i class="fa-solid fa-upload"></i> Subir imagen</span>
                            </label>
                            <label>
                                <input type="radio" name="image_source" value="public" @checked($imageSource === 'public')>
                                <span><i class="fa-solid fa-globe"></i> Recurso público ({{ $publicProviderNames }})</span>
                            </label>
                        </div>
                    @endif

                    <div data-image-source="upload">
                        <input
                            type="file"
                            class="form-control @error('image') is-invalid @enderror"
                            id="image"
                            name="image"
                            aria-label="Imagen o GIF"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            @required(!$isEdit)>
                        <small class="catalog-hint">
                            {{ $isEdit ? 'Deja vacio para mantener la imagen actual.' : 'Muestra como se hace el ejercicio.' }} Maximo 4 MB.
                        </small>
                    </div>

                    @if ($publicProviders)
                        <div data-image-source="public" class="catalog-public-picker" hidden
                             data-search-url="{{ route('public-resource.search') }}"
                             data-media-url="{{ route('public-resource.media', '__ITEM__') }}">
                            <div class="catalog-public-search">
                                <input type="search" class="form-control" data-public-query placeholder="Busca el ejercicio, ej. curl de bíceps" aria-label="Buscar en {{ $publicProviderNames }}">
                                <button type="button" class="btn btn-outline-light" data-public-search>
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <span>Buscar</span>
                                </button>
                            </div>
                            <p class="catalog-hint mb-2" data-public-status>
                                {{ $isEdit ? 'Puedes reemplazar la imagen principal o mantenerla y solo sumar imagenes.' : 'Elige la imagen principal.' }} Lo que marques con "Agregar al ejercicio" se suma a sus recursos. Puedes buscar en español o en inglés.
                            </p>
                            <div class="catalog-public-results" data-public-results></div>
                            <div class="catalog-public-media" data-public-media></div>
                        </div>
                    @endif
                </div>

                @include('layouts._partials.visibility-field', ['entity' => $exercise, 'isAdmin' => $data['isAdmin']])

                <button type="submit" class="btn btn-primary w-100">
                    {{ $isEdit ? 'Guardar cambios' : ($data['isAdmin'] ? 'Crear ejercicio' : 'Enviar a revision') }}
                </button>
            </form>
        </div>
    </section>
@endsection

@if ($publicProviders)
    @section('js')
        {{-- module: corre despues de app.js, que es quien expone jQuery --}}
        <script type="module">
            const $picker = $('.catalog-public-picker');
            const $fileInput = $('#image');
            const isEdit = @json($isEdit);
            const $status = $picker.find('[data-public-status]');
            const defaultStatus = $status.text();
            const publicProviderCount = @json(count($publicProviders));

            // solo viaja el origen elegido: el otro queda deshabilitado
            const applySource = () => {
                const source = $('input[name="image_source"]:checked').val();
                const isPublic = source === 'public';
                $('[data-image-source="upload"]').prop('hidden', isPublic);
                $picker.prop('hidden', !isPublic);
                $fileInput.prop('disabled', isPublic).prop('required', !isPublic && !isEdit);
                $picker.find('input[name]').prop('disabled', !isPublic);
                $picker.find('input[name="public_resource_key"]').first().prop('required', isPublic && !isEdit);
            };

            const setStatus = (text) => $status.text(text || defaultStatus);

            const fetchJson = async (url) => {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const body = await response.json().catch(() => ({}));
                if (!response.ok || !body.success) {
                    throw new Error(body.message || 'No pudimos conectar con el proveedor.');
                }
                return body.data;
            };

            const renderMedia = (mediaList) => {
                const $media = $picker.find('[data-public-media]').empty();
                if (!mediaList.length) {
                    setStatus('Este ejercicio no tiene imagenes ni videos disponibles.');
                    return;
                }
                // al editar se puede sumar imagenes sin tocar la principal
                if (isEdit) {
                    $('<label>', { class: 'catalog-public-media-item catalog-public-keep' })
                        .append($('<input>', { type: 'radio', name: 'public_resource_key', value: '', checked: true }), $('<span>').text('Mantener la imagen principal actual'))
                        .appendTo($media);
                }
                $.each(mediaList, (index, media) => {
                    const isImage = media.kind === 'image';
                    const $item = $('<div>', { class: 'catalog-public-media-item' });
                    const $preview = isImage
                        ? $('<img>', { src: media.url, alt: media.name, loading: 'lazy' })
                        : $('<video>', { src: media.url, muted: true, loop: true, playsinline: true, preload: 'metadata' });
                    $item.append($preview, $('<span>').text(media.name));
                    // la licencia se muestra junto a la imagen en la app; aca solo como referencia
                    if (media.attribution) {
                        $('<small>', { class: 'catalog-public-credit' })
                            .text(`${media.attribution.license} · ${media.attribution.author}`)
                            .appendTo($item);
                    }
                    // una imagen puede ser la principal; todo lo marcado se suma como recurso del ejercicio
                    if (isImage) {
                        $('<label>', { class: 'catalog-public-option' })
                            .append($('<input>', { type: 'radio', name: 'public_resource_key', value: media.key }), $('<span>').text('Principal'))
                            .appendTo($item);
                    }
                    $('<label>', { class: 'catalog-public-option' })
                        .append($('<input>', { type: 'checkbox', name: 'public_resource_media_keys[]', value: media.key, checked: true }), $('<span>').text('Agregar al ejercicio'))
                        .appendTo($item);
                    $media.append($item);
                });
                if (!isEdit) {
                    $media.find('input[name="public_resource_key"]').first().prop('checked', true);
                }
                applySource();
                setStatus();
            };

            const loadMedia = async (itemKey) => {
                setStatus('Cargando imagenes y videos...');
                try {
                    renderMedia(await fetchJson($picker.data('mediaUrl').replace('__ITEM__', encodeURIComponent(itemKey))));
                } catch (error) {
                    setStatus(error.message);
                }
            };

            const search = async () => {
                const query = $picker.find('[data-public-query]').val().trim();
                if (query.length < 2) {
                    setStatus('Escribe al menos 2 letras para buscar.');
                    return;
                }
                const $results = $picker.find('[data-public-results]').empty();
                $picker.find('[data-public-media]').empty();
                setStatus('Buscando...');
                try {
                    const resultList = await fetchJson($picker.data('searchUrl') + '?' + new URLSearchParams({ q: query }));
                    if (!resultList.length) {
                        setStatus(`No encontramos resultados para "${query}".`);
                        return;
                    }
                    $.each(resultList, (index, result) => {
                        $('<button>', { type: 'button', class: 'catalog-public-result' })
                            .text(result.name)
                            .append(publicProviderCount > 1 ? $('<small>').text(` · ${result.provider}`) : null)
                            .on('click', function () {
                                $results.find('.is-active').removeClass('is-active');
                                $(this).addClass('is-active');
                                loadMedia(result.key);
                            })
                            .appendTo($results);
                    });
                    setStatus('Elige un ejercicio para ver sus imagenes y videos.');
                } catch (error) {
                    setStatus(error.message);
                }
            };

            $('input[name="image_source"]').on('change', applySource);
            $picker.find('[data-public-search]').on('click', search);
            // Enter en el buscador busca en vez de enviar el formulario del ejercicio
            $picker.find('[data-public-query]').on('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    search();
                }
            });
            applySource();
        </script>
    @endsection
@endif
