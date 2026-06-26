@php
    $exerciseResources = $data['exerciseResources'];
@endphp
@extends('layouts.layout')
@section('css')
    @vite('resources/css/resource.css')
@endsection
@section('content')
    <div class="mb-2">
        <a href="{{ route('muscle.index') }}"><i class="fa-solid fa-left-long fa-2xl" style="color: rgb(194, 19, 31);"></i></a>
    </div>
    @if (!empty($exerciseResources))
        <div class="row justify-content-center">
            @foreach ($exerciseResources as $resource)
                <div class="col-12 col-md-10 col-lg-8 d-flex justify-content-center mb-4">
                    <div class="card exercise-card">
                        <div class="exercise-image-wrapper">
                            <img src="{{ asset($resource['url']) }}" class="exercise-image">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection