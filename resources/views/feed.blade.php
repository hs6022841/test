@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            @include('flash-message')
            <div class="card">
                <div class="card-header row">
                    <div class="col-sm-6">{{  __('Feed') }}</div>
                    <div class="col-sm-6 text-right">
                        @if (Route::current()->getName() == 'feed.index')
                        <a class="text-right btn btn-primary" href="{{ route('feed.profile') }}">
                            {{  __('Profile') }}
                        </a>
                        @else
                        <a class="text-right btn btn-primary" href="{{ route('feed.index') }}">
                            {{  __('Feed') }}
                        </a>
                        @endif
                        <a class="text-right btn btn-primary" href="{{ route('feed.create') }}">
                            {{  __('Create Feed') }}
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @foreach($feeds as $feed)
                        <div class="card" style="width: 100%;">
                            <div class="card-body">
                                <p class="card-text">{{$feed->comment}}</p>
                                <small>Posted by {{$feed->user_id}} at {{$feed->created_at}}</small>

                                @if (Route::current()->getName() == 'feed.profile')
                                <a href="{{ route('feed.delete', $feed->uuid) }}" class="btn btn-danger">DELETE</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
