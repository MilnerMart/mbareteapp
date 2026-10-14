@php
    $dayList = $data['dayList'];
@endphp
@extends('layouts.layout')

@section('title', 'Actividad de '.$data['userName'].' | Mbarete App')

@section('css')
    @vite(['resources/css/activity.css'])
@endsection

@section('content')
    <section class="activity-page">
        <a href="{{ route('gym.index') }}" class="activity-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Gimnasios</span>
        </a>

        <header class="activity-header">
            <img src="{{ $data['userImageUrl'] }}" alt="" class="activity-avatar">
            <div>
                <span class="activity-eyebrow">Registro de actividad</span>
                <h1>{{ $data['userName'] }}</h1>
            </div>
        </header>

        <nav class="activity-filters" aria-label="Tipo de actividad">
            @foreach ($data['filterList'] as $filter)
                <a href="{{ $filter['url'] }}" @class(['activity-filter', 'active' => $filter['isActive']])>{{ $filter['label'] }}</a>
            @endforeach
        </nav>

        @if (empty($dayList))
            <div class="activity-empty">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <p>{{ $data['emptyText'] }}</p>
            </div>
        @else
            @foreach ($dayList as $day)
                <h2 class="activity-day">{{ $day['dateLabel'] }}</h2>
                <ol class="activity-list">
                    @foreach ($day['items'] as $item)
                        <li @class(['activity-item', 'is-delete' => $item['isDelete'], 'is-highlight' => $item['isHighlight']])>
                            <i class="{{ $item['icon'] }} activity-icon" aria-hidden="true"></i>
                            <div class="activity-body">
                                <span class="activity-text">{{ $item['text'] }}</span>
                                @if ($item['note'])
                                    <span class="activity-note">{{ $item['note'] }}</span>
                                @endif
                            </div>
                            <time class="activity-time">{{ $item['timeLabel'] }}</time>
                        </li>
                    @endforeach
                </ol>
            @endforeach
        @endif

        @if ($data['previousUrl'] || $data['nextUrl'])
            <nav class="activity-pager" aria-label="Paginas">
                @if ($data['previousUrl'])
                    <a href="{{ $data['previousUrl'] }}" class="activity-filter"><i class="fa-solid fa-chevron-left"></i> Mas recientes</a>
                @endif
                @if ($data['nextUrl'])
                    <a href="{{ $data['nextUrl'] }}" class="activity-filter">Mas antiguas <i class="fa-solid fa-chevron-right"></i></a>
                @endif
            </nav>
        @endif
    </section>
@endsection
