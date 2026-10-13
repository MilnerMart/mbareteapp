{{-- creditos que pide la licencia Creative Commons de un recurso publico: discretos, se abren al tocar el icono --}}
@if (!empty($attribution))
    <details class="media-attribution">
        <summary title="Créditos y licencia" aria-label="Créditos y licencia">
            <i class="fa-brands fa-creative-commons" aria-hidden="true"></i>
        </summary>
        <p>
            <a href="{{ $attribution['sourceUrl'] }}" target="_blank" rel="noopener">{{ $attribution['title'] }}</a>
            por
            @if (!empty($attribution['authorUrl']))
                <a href="{{ $attribution['authorUrl'] }}" target="_blank" rel="noopener">{{ $attribution['author'] }}</a>,
            @else
                {{ $attribution['author'] }},
            @endif
            <a href="{{ $attribution['licenseUrl'] }}" target="_blank" rel="noopener license">{{ $attribution['license'] }}</a>
            · vía {{ $attribution['provider'] }}
        </p>
    </details>
@endif
