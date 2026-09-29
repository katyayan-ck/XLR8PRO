{{-- Branded error page (go-live to-do U8) --}}
@extends('errors.xl')

@section('code', '419')
@section('title', 'Page expired')
@section('message', 'This page was open too long and its security token expired. Reload it and try again — nothing was saved.')
@section('actions')
    <button type="button" class="xe-btn xe-btn-primary" onclick="location.reload()">Reload the page</button>
    <button type="button" class="xe-btn" onclick="history.back()">Go back</button>
@endsection
