@php
    $exerciseGroup = $data['exerciseGroup'];
@endphp
@extends('layouts.layout')
@section('css')
    @vite('resources/css/group.css')
@endsection
@section('content')
    <div class="mb-2">
        <a href="{{ route('muscle.index') }}"><i class="fa-solid fa-left-long fa-2xl" style="color: rgb(194, 19, 31);"></i></a>
    </div>
    @if (!empty($exerciseGroup))
        <div class="row justify-content-center">
            @foreach ($exerciseGroup as $exercise)
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex justify-content-center mb-4">
                    <div class="card" style="width: 25rem; border-radius: 10%;">
                        <a href="{{ route('exercise.resource', $exercise['id']) }}"><img src="{{ asset($exercise['image']) }}" class="card-img-top" style="border-radius: 10%;"></a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-exercises-wrapper">
            <img src="{{ asset('images/leoncioRest.png') }}" alt="Leoncio descansando" class="leoncio-rest-img">
        </div>
    @endif    
@endsection