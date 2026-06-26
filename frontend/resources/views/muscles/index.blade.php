@php
    $muscles = $data['muscles'];
@endphp
@extends('layouts.layout')
@section('content')
    @if (!empty($muscles))
        <div class="row justify-content-center">
            @foreach ($muscles as $muscle)
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex justify-content-center mb-4">
                    <div class="card" style="width: 18rem;">
                        <a href="{{ route('exercise.group', $muscle['id']) }}"><img src="{{ asset($muscle['image_url']) }}" class="card-img-top"></a>
                        <div class="card-body">
                            <h5 class="card-title text-center">{{ $muscle['name'] }}</h5>
                            <p class="card-text justify-content-center">{{ $muscle['description'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection