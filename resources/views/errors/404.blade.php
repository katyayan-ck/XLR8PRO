{{-- Branded error page (go-live to-do U8) --}}
@extends('errors.xl')

@section('code', '404')
@section('title', 'Page not found')
@section('message', 'The page or record you asked for doesn\'t exist, or it was moved or removed.')
