{{-- Branded error page (go-live to-do U8) --}}
@extends('errors.xl')

@section('code', '403')
@section('title', 'Access denied')
@section('message'){{ \Illuminate\Support\Str::limit(($exception->getMessage() ?? '') ?: 'You don\'t have permission to open this page. Ask your administrator if you need access.', 200) }}@endsection
