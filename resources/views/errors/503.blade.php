{{-- Branded error page (go-live to-do U8) --}}
@extends('errors.xl')

@section('code', '503')
@section('title', 'Down for maintenance')
@section('message', 'Xceler8 is being updated and will be back in a few minutes.')
@section('actions')
    <button type="button" class="xe-btn xe-btn-primary" onclick="location.reload()">Try again</button>
@endsection
