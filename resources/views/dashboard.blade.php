@extends('layouts.app')

@section('content')
@if ($selected)
    @include('dashboard.partials.ticket-detail')
@elseif ($view === 'new')
    @include('dashboard.partials.ticket-create')
@elseif ($view === 'users')
    @include('dashboard.partials.user-management')
@elseif ($view === 'analytics')
    @include('dashboard.partials.analytics')
@else
    @include('dashboard.partials.overview')
@endif
@endsection
