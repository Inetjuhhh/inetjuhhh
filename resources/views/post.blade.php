@extends('layouts.app')

@section('title', 'Page Title')

@section('styles')
    div#social-links {
        margin: 0 auto;
        max-width: 500px;
    }

    div#social-links ul li {
        display: inline-block;
    }

    div#social-links ul li a {

    }
@endsection

@section('content')
    <div class="container mt-4">
        <h2 class="mb-5 text-center">Laravel Social Share Buttons Example</h2>

        {!! $shareComponent !!}
    </div>
@endsection




