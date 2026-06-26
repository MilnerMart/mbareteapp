@php
    $exercises = $data['exercises'];
@endphp
@extends('layouts.layout')
@section('content')
    @if (!empty($exercises))
        <div class="row justify-content-center">
            @foreach ($exercises as $exercise)
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex justify-content-center mb-4">
                    <div class="card" style="width: 18rem;">
                        <img src="{{ asset($exercise['image']) }}" class="card-img-top">
                        <div class="card-body">
                            <h5 class="card-title text-center">{{ $exercise['name'] }}</h5>
                            <p class="card-text justify-content-center">{{ $exercise['description'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection